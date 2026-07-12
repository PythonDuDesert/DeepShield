(function () {
  "use strict";

  var healthEl = document.getElementById("health-indicator");
  if (healthEl) {
    fetch("health.php")
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var ok = data.status === "ok";
        healthEl.textContent = "● " + (ok ? "Moteur disponible" : "Moteur dégradé") + (data.mock_mode ? " · démo" : "");
        healthEl.className = "badge " + (ok ? "badge-ok" : "badge-danger");
      })
      .catch(function () {
        healthEl.textContent = "● Moteur injoignable";
        healthEl.className = "badge badge-danger";
      });
  }

  var form = document.getElementById("analyze-form");
  if (form) {
    var errorBox = document.getElementById("client-error");
    var submitBtn = document.getElementById("submit-btn");

    form.addEventListener("submit", function (evt) {
      var video = document.getElementById("video_file");
      var audio = document.getElementById("audio_file");
      var hasVideo = video && video.files && video.files.length > 0;
      var hasAudio = audio && audio.files && audio.files.length > 0;

      if (!hasVideo && !hasAudio) {
        evt.preventDefault();
        errorBox.textContent = "Veuillez sélectionner au moins un fichier vidéo ou audio.";
        errorBox.hidden = false;
        return;
      }

      errorBox.hidden = true;
      submitBtn.setAttribute("disabled", "disabled");
      submitBtn.textContent = "Analyse en cours…";
    });
  }

  function wireDropzone(zoneId, inputId) {
    var zone = document.getElementById(zoneId);
    var input = document.getElementById(inputId);
    if (!zone || !input) return;

    ["dragenter", "dragover"].forEach(function (evtName) {
      zone.addEventListener(evtName, function (e) {
        e.preventDefault();
        zone.classList.add("is-dragover");
      });
    });
    ["dragleave", "drop"].forEach(function (evtName) {
      zone.addEventListener(evtName, function (e) {
        e.preventDefault();
        zone.classList.remove("is-dragover");
      });
    });
    zone.addEventListener("drop", function (e) {
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        input.files = e.dataTransfer.files;
      }
    });
  }
  wireDropzone("dropzone-video", "video_file");
  wireDropzone("dropzone-audio", "audio_file");
})();
