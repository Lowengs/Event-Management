/**
 * liveness.js — Challenge-response anti-spoofing for NAAP attendance.
 *
 * Requires face-api.js (assets/js/lib/face-api.min.js) and the bundled models in
 * assets/models: tiny_face_detector, face_landmark_68, face_recognition.
 *
 * How it defeats spoofing:
 *  1. Randomized active challenges (blink / turn left / turn right). A printed photo
 *     cannot blink or turn, and a pre-recorded video cannot predict the random order.
 *  2. Each challenge has a time limit; failing restarts with a NEW random sequence.
 *  3. Exactly one face must stay in frame; a sudden jump of the face box (swapping
 *     a phone/photo in front of the camera) resets the session.
 *  4. Optional identity check: the live face descriptor is compared (Euclidean
 *     distance, face-api's native metric) with the student's registered descriptor.
 *
 * NOTE: this runs in the browser, so it raises the bar against casual spoofing
 * (photos, phone screens, replayed clips) but is not tamper-proof against a user
 * who edits the page's JavaScript. Server-side checks remain authoritative.
 */
(function (global) {
  'use strict';

  const MODEL_PATHS = [
    '../../assets/models',
    '../assets/models',
    '/Project/assets/models',
    'assets/models',
    'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/'
  ];

  const CFG = {
    EAR_CLOSED: 0.20,        // eye aspect ratio below this = eyes closed
    EAR_OPEN: 0.25,          // above this = eyes open
    YAW_TURN: 0.16,          // normalized nose offset required for a head turn
    YAW_CENTER: 0.07,        // must return near center between turn challenges
    CHALLENGE_TIMEOUT_MS: 8000,
    MAX_BOX_JUMP: 0.45,      // fraction of face width the box may move between frames
    MATCH_THRESHOLD: 0.5,    // Euclidean distance; lower = stricter (face-api default 0.6)
    MIN_FACE_WIDTH: 90       // px — face must be close enough for reliable landmarks
  };

  const CHALLENGES = {
    blink:      { label: '👁️ Please BLINK your eyes' },
    turn_left:  { label: '⬅️ Slowly turn your head to YOUR LEFT' },
    turn_right: { label: '➡️ Slowly turn your head to YOUR RIGHT' }
  };

  let modelsPromise = null;

  /** Load detector + landmarks (+ recognition when needed). Resolves true/false. */
  function loadModels(faceapi, withRecognition = true) {
    if (modelsPromise) return modelsPromise;
    modelsPromise = (async () => {
      for (const p of MODEL_PATHS) {
        try {
          const loads = [
            faceapi.nets.tinyFaceDetector.loadFromUri(p),
            faceapi.nets.faceLandmark68Net.loadFromUri(p)
          ];
          if (withRecognition) loads.push(faceapi.nets.faceRecognitionNet.loadFromUri(p));
          await Promise.all(loads);
          return true;
        } catch (e) {
          console.warn('[liveness] model path failed:', p, e);
        }
      }
      modelsPromise = null; // allow retry
      return false;
    })();
    return modelsPromise;
  }

  function dist(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
  }

  /** Eye aspect ratio (Soukupová & Čech, 2016) from 6 eye landmarks. */
  function eyeAspectRatio(eye) {
    const v1 = dist(eye[1], eye[5]);
    const v2 = dist(eye[2], eye[4]);
    const h = dist(eye[0], eye[3]);
    return h > 0 ? (v1 + v2) / (2 * h) : 0;
  }

  /**
   * Normalized yaw from raw (un-mirrored) camera landmarks.
   * Positive = nose toward image-right = subject turned to THEIR left.
   */
  function yawRatio(points) {
    const leftEyeOuter = points[36];
    const rightEyeOuter = points[45];
    const nose = points[30];
    const midX = (leftEyeOuter.x + rightEyeOuter.x) / 2;
    const eyeSpan = Math.abs(rightEyeOuter.x - leftEyeOuter.x) || 1;
    return (nose.x - midX) / eyeSpan;
  }

  function shuffle(arr) {
    const a = arr.slice();
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  function buildSequence(count) {
    // Always include one blink (defeats photos) + random turn(s), random order.
    const turns = shuffle(['turn_left', 'turn_right']);
    const seq = ['blink', ...turns.slice(0, Math.max(1, count - 1))];
    return shuffle(seq);
  }

  /**
   * Create a liveness session.
   * @param {object} opts
   *   faceapi          face-api global
   *   video            <video> element with the camera stream
   *   challenges       number of challenges (default 2)
   *   referenceDescriptor  optional Float32Array|number[] of the registered face
   *   onStatus(text, type)  UI callback; type: pending | success | error
   *   onPass({distance, identityChecked})
   *   onFail(reason)   called on a hard failure (identity mismatch)
   */
  function createSession(opts) {
    const faceapi = opts.faceapi || global.faceapi;
    const video = opts.video;
    const onStatus = opts.onStatus || function () {};
    const challengeCount = Math.min(3, Math.max(1, opts.challenges || 2));
    const reference = opts.referenceDescriptor
      ? new Float32Array(opts.referenceDescriptor)
      : null;

    let seq = buildSequence(challengeCount);
    let idx = 0;
    let challengeStart = 0;
    let lastBox = null;
    let eyesWereClosed = false;
    let needRecenter = false;
    let done = false;
    let busy = false;
    let timer = null;
    let attempts = 0;

    function reset(reason) {
      attempts++;
      seq = buildSequence(challengeCount);
      idx = 0;
      challengeStart = 0;
      eyesWereClosed = false;
      needRecenter = false;
      lastBox = null;
      if (reason) onStatus('⚠️ ' + reason + ' — starting a new challenge…', 'error');
    }

    function currentPrompt() {
      return `Step ${idx + 1}/${seq.length}: ${CHALLENGES[seq[idx]].label}`;
    }

    async function finish(lastDetection) {
      done = true;
      stop();
      let distance = null;
      if (reference && faceapi.nets.faceRecognitionNet.isLoaded) {
        onStatus('Liveness confirmed — verifying identity…', 'pending');
        let desc = lastDetection && lastDetection.descriptor;
        if (!desc) {
          const d = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.4 }))
            .withFaceLandmarks()
            .withFaceDescriptor();
          desc = d && d.descriptor;
        }
        if (!desc) {
          done = false;
          reset('Face lost during identity check');
          start();
          return;
        }
        distance = faceapi.euclideanDistance(desc, reference);
        if (distance > CFG.MATCH_THRESHOLD) {
          onStatus('❌ Face does not match the registered student.', 'error');
          if (opts.onFail) opts.onFail({ reason: 'identity_mismatch', distance });
          return;
        }
      }
      onStatus('✓ Live face verified' + (reference ? ' & identity matched' : ''), 'success');
      if (opts.onPass) opts.onPass({ distance, identityChecked: !!reference, attempts });
    }

    async function tick() {
      if (done || busy || !video || video.readyState < 2) return;
      busy = true;
      try {
        const dets = await faceapi
          .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 }))
          .withFaceLandmarks();

        if (!dets || dets.length === 0) {
          lastBox = null;
          onStatus('Center your face inside the frame…', 'pending');
          return;
        }
        if (dets.length > 1) {
          reset('Multiple faces detected — only one person allowed');
          return;
        }

        const det = dets[0];
        const box = det.detection.box;
        if (box.width < CFG.MIN_FACE_WIDTH) {
          onStatus('Move a little closer to the camera…', 'pending');
          return;
        }

        // Anti-swap: the face must not teleport between frames.
        if (lastBox) {
          const jump = Math.hypot(box.x - lastBox.x, box.y - lastBox.y) / box.width;
          if (jump > CFG.MAX_BOX_JUMP) {
            reset('Sudden face change detected');
            return;
          }
        }
        lastBox = box;

        if (!challengeStart) challengeStart = Date.now();
        if (Date.now() - challengeStart > CFG.CHALLENGE_TIMEOUT_MS) {
          reset('Challenge timed out');
          return;
        }

        const pts = det.landmarks.positions;
        const ear = (eyeAspectRatio(pts.slice(36, 42)) + eyeAspectRatio(pts.slice(42, 48))) / 2;
        const yaw = yawRatio(pts);

        if (needRecenter) {
          if (Math.abs(yaw) < CFG.YAW_CENTER) {
            needRecenter = false;
            challengeStart = Date.now();
          } else {
            onStatus('Good — now look straight at the camera', 'pending');
            return;
          }
        }

        const ch = seq[idx];
        let passed = false;
        if (ch === 'blink') {
          if (ear < CFG.EAR_CLOSED) eyesWereClosed = true;
          else if (eyesWereClosed && ear > CFG.EAR_OPEN) passed = true;
        } else if (ch === 'turn_left') {
          passed = yaw > CFG.YAW_TURN;
        } else if (ch === 'turn_right') {
          passed = yaw < -CFG.YAW_TURN;
        }

        if (passed) {
          idx++;
          eyesWereClosed = false;
          challengeStart = Date.now();
          if (ch !== 'blink') needRecenter = true;
          if (idx >= seq.length) {
            await finish(null);
            return;
          }
        }
        onStatus(currentPrompt(), 'pending');
      } catch (e) {
        console.warn('[liveness] frame error:', e);
      } finally {
        busy = false;
      }
    }

    function start() {
      done = false;
      if (!timer) timer = setInterval(tick, 120);
      onStatus(currentPrompt(), 'pending');
    }

    function stop() {
      if (timer) clearInterval(timer);
      timer = null;
    }

    return { start, stop, reset: () => { reset(); start(); } };
  }

  /** Fetch the logged-in student's own registered descriptor (or null). */
  async function fetchOwnDescriptor(endpoint) {
    try {
      const res = await fetch(endpoint || '../../config/API/endpoints/index.php?action=get_face_descriptors', {
        credentials: 'same-origin'
      });
      const data = await res.json();
      const face = data && data.success && Array.isArray(data.faces) ? data.faces[0] : null;
      return face && Array.isArray(face.descriptor) && face.descriptor.length === 128 ? face.descriptor : null;
    } catch (e) {
      console.warn('[liveness] could not load reference descriptor:', e);
      return null;
    }
  }

  global.NaapLiveness = { loadModels, createSession, fetchOwnDescriptor, CFG };
})(window);
