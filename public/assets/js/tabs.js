/* ── Onglets de page génériques (data-tabgroup) ── */
function switchPageTab(tabName, btn, groupId) {
  var group = groupId ? document.getElementById(groupId) : document;

  group.querySelectorAll(".page-tab-panel").forEach(function (panel) {
    panel.classList.remove("active");
  });
  group.querySelectorAll(".page-tab").forEach(function (tab) {
    tab.classList.remove("active");
  });

  var panel = document.getElementById("tab-" + tabName);
  if (panel) panel.classList.add("active");
  if (btn) btn.classList.add("active");

  if (window.history && window.history.replaceState) {
    window.history.replaceState(null, "", "#" + tabName);
  } else {
    window.location.hash = tabName;
  }
}

/* Restaure l'onglet actif au chargement selon le fragment d'URL (#assistance...) */
(function () {
  window.addEventListener("load", function () {
    var fragment = window.location.hash.replace("#", "");
    if (!fragment) return;
    var btn = document.querySelector('.page-tab[data-tab="' + fragment + '"]');
    if (btn) switchPageTab(fragment, btn);
  });
})();

/* ── Formulaire de résolution d'une demande d'assistance (admin) ── */
function openResolveForm(id) {
  var form = document.getElementById("resolve-form-" + id);
  if (form) form.style.display = "block";
}
function closeResolveForm(id) {
  var form = document.getElementById("resolve-form-" + id);
  if (form) form.style.display = "none";
}
