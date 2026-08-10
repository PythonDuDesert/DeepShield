/**
 * DeepShield — Anti-AFK (garde d'inactivité)
 * ============================================
 * Avertit l'utilisateur avant déconnexion automatique, puis le déconnecte
 * réellement si aucune activité n'est détectée. Le vrai verrou reste
 * côté serveur (bootstrap.php compare l'horodatage de session) : ce script
 * n'est qu'un confort UX, jamais la seule barrière de sécurité.
 */
(function () {
    'use strict';

    var TIMEOUT_MINUTES = window.DS_SESSION_TIMEOUT_MINUTES || 15;
    var WARNING_BEFORE_SECONDS = 60;
    var TIMEOUT_MS = TIMEOUT_MINUTES * 60 * 1000;
    var WARN_MS = Math.max(TIMEOUT_MS - WARNING_BEFORE_SECONDS * 1000, 5000);

    var warnTimer = null;
    var logoutTimer = null;
    var countdownInterval = null;
    var modal = null;

    function buildModal() {
        if (modal) {
            return modal;
        }
        modal = document.createElement('div');
        modal.id = 'ds-idle-modal';
        modal.style.cssText =
            'position:fixed;inset:0;background:rgba(5,7,13,0.75);' +
            'display:flex;align-items:center;justify-content:center;z-index:9999;';
        modal.innerHTML =
            '<div style="background:#0d1117;border:1px solid rgba(255,255,255,0.12);border-radius:14px;' +
            'padding:28px 32px;max-width:360px;text-align:center;color:#e6edf3;font-family:inherit;">' +
            '  <h2 style="margin:0 0 10px;font-size:1.1em;">Toujours là ?</h2>' +
            '  <p style="margin:0 0 18px;color:#9aa4b2;font-size:0.9em;">' +
            '    Pour votre sécurité, vous allez être déconnecté dans ' +
            '    <span id="ds-idle-countdown">' + WARNING_BEFORE_SECONDS + '</span>s d\'inactivité.' +
            '  </p>' +
            '  <button id="ds-idle-stay" style="background:#2f81f7;color:#fff;border:none;border-radius:8px;' +
            '    padding:10px 18px;font-size:0.9em;cursor:pointer;">Rester connecté</button>' +
            '</div>';
        document.body.appendChild(modal);
        document.getElementById('ds-idle-stay').addEventListener('click', function () {
            window.location.reload();
        });
        return modal;
    }

    function showWarning() {
        var el = buildModal();
        el.style.display = 'flex';
        var secondsLeft = WARNING_BEFORE_SECONDS;
        var countdownEl = document.getElementById('ds-idle-countdown');
        countdownEl.textContent = String(secondsLeft);
        clearInterval(countdownInterval);
        countdownInterval = setInterval(function () {
            secondsLeft -= 1;
            countdownEl.textContent = String(Math.max(secondsLeft, 0));
            if (secondsLeft <= 0) {
                clearInterval(countdownInterval);
            }
        }, 1000);
    }

    function doLogout() {
        window.location.href = 'logout.php';
    }

    function resetTimers() {
        clearTimeout(warnTimer);
        clearTimeout(logoutTimer);
        clearInterval(countdownInterval);
        if (modal) {
            modal.style.display = 'none';
        }
        warnTimer = setTimeout(showWarning, WARN_MS);
        logoutTimer = setTimeout(doLogout, TIMEOUT_MS);
    }

    ['mousemove', 'keydown', 'mousedown', 'scroll', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, resetTimers, { passive: true });
    });

    resetTimers();
})();
