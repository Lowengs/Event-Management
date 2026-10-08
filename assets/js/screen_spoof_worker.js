/**
 * screen_spoof_worker.js — runs COCO-SSD (TensorFlow.js) in its own thread.
 *
 * Kept in a Web Worker on purpose: loading tf.js 4 on the page itself breaks
 * face-api.js (which bundles its own older tf core), so the two must never
 * share a global scope.
 *
 * Messages in:  { type: 'load', tfUrl, cocoUrl, modelUrl }
 *               { type: 'detect', id, width, height, data (Uint8ClampedArray RGBA) }
 * Messages out: { type: 'loaded', ok, backend?, error? }
 *               { type: 'result', id, preds: [{ class, score, bbox:[x,y,w,h] }] , error? }
 */
let model = null;

self.onmessage = async (ev) => {
  const msg = ev.data || {};
  if (msg.type === 'load') {
    try {
      importScripts(msg.tfUrl, msg.cocoUrl);
      try { await tf.setBackend('webgl'); } catch (e) { /* OffscreenCanvas WebGL unavailable */ }
      await tf.ready();
      if (tf.getBackend() !== 'webgl') { try { await tf.setBackend('cpu'); await tf.ready(); } catch (e) {} }
      model = await cocoSsd.load({ base: 'lite_mobilenet_v2', modelUrl: msg.modelUrl });
      self.postMessage({ type: 'loaded', ok: true, backend: tf.getBackend() });
    } catch (e) {
      self.postMessage({ type: 'loaded', ok: false, error: String(e && e.message || e) });
    }
    return;
  }
  if (msg.type === 'detect') {
    if (!model) { self.postMessage({ type: 'result', id: msg.id, preds: [], error: 'model not loaded' }); return; }
    let input = null;
    try {
      input = tf.browser.fromPixels({ data: new Uint8Array(msg.data.buffer), width: msg.width, height: msg.height });
      const preds = await model.detect(input, 10, 0.25);
      self.postMessage({ type: 'result', id: msg.id, preds: preds.map(p => ({ class: p.class, score: p.score, bbox: p.bbox })) });
    } catch (e) {
      self.postMessage({ type: 'result', id: msg.id, preds: [], error: String(e && e.message || e) });
    } finally {
      if (input) input.dispose();
    }
  }
};
