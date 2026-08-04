/**
 * Test Results Page Helper Scripts
 * Handles browser history back-button trap after test submission
 */
document.addEventListener('DOMContentLoaded', () => {
    if (window.history && window.history.pushState) {
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function () {
            window.location.href = "profile-dashboard.php";
        };
    }
});
