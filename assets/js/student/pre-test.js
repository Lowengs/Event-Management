/* pre-test.js — interactive test helper */
window.alert = function(msg) {
    console.warn("Alert suppressed:", msg);
};