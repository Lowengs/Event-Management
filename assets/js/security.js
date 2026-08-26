/**
 * NAAP System - Client Security & Anti-Inspection Guard
 * Prevents casual users from opening Developer Tools & viewing source code
 */
(function () {
  'use strict';

  // 1. Disable Right-Click Context Menu
  document.addEventListener('contextmenu', function (e) {
    // Allow right click on text inputs/textarea for paste
    if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) {
      return;
    }
    e.preventDefault();
  }, false);

  // 2. Disable DevTools and View-Source Key Combinations
  document.addEventListener('keydown', function (e) {
    // F12
    if (e.key === 'F12' || e.keyCode === 123) {
      e.preventDefault();
      return false;
    }

    // Ctrl+Shift+I (Inspect), Ctrl+Shift+J (Console), Ctrl+Shift+C (Element Inspector)
    if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c' || e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67)) {
      e.preventDefault();
      return false;
    }

    // Ctrl+U (View Source)
    if (e.ctrlKey && (e.key === 'U' || e.key === 'u' || e.keyCode === 85)) {
      e.preventDefault();
      return false;
    }

    // Ctrl+S (Save Page)
    if (e.ctrlKey && (e.key === 'S' || e.key === 's' || e.keyCode === 83)) {
      e.preventDefault();
      return false;
    }
  }, false);

  // 3. Suppress console logs in production if needed
  if (typeof console !== 'undefined') {
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
      console.log = function () {};
      console.debug = function () {};
      console.info = function () {};
      console.warn = function () {};
    }
  }
})();
