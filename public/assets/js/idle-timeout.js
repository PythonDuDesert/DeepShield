(function () {
    var timeoutSeconds = parseInt(window.DS_IDLE_TIMEOUT_SECONDS, 10) || 900;
    var timer = null;

    function resetTimer() {
        if (timer) {
            clearTimeout(timer);
        }
        timer = setTimeout(function () {
            window.location.href = 'logout.php';
        }, timeoutSeconds * 1000);
    }

    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, resetTimer, { passive: true });
    });

    resetTimer();
})();
