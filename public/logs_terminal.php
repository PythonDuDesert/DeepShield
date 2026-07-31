<?php if ($role == "0"): ?>
    <!-- Graphique 2: Terminal Logs -->
    <div class="chart-card" id="terminalCard">
        <div class="chart-title">
            <span></span>
            <span>Logs Terminal</span>
        </div>
        <div class="terminal-logs" id="terminalLogs">
            <div class="terminal-header">
                <!-- Titre éditable au double-clic -->
                <span class="terminal-title" id="terminalTitle" ondblclick="startEditTitle()" title="Double-cliquez pour changer de fichier de logs">
                    <?php echo htmlspecialchars($log_filename); ?>
                </span>
                <!-- Formulaire caché pour la navigation entre fichiers -->
                <form id="logDayForm" method="GET" action="" style="display:none;">
                    <!-- Conserver les autres paramètres GET existants s'il y en a -->
                    <input type="hidden" name="log_day" id="logDayInput" value="">
                </form>
                <span class="terminal-controls">
                    <div class="log-filters">
                        <button onclick="filterLogs('all')" class="active">Tous</button>
                        <button onclick="filterLogs('info')">Info</button>
                        <button onclick="filterLogs('important_info')">Important</button>
                        <button onclick="filterLogs('warning')">Warning</button>
                        <button onclick="filterLogs('error')">Error</button>
                        <button onclick="filterLogs('BDD')">BDD</button>
                    </div>
                    <span class="control-btn maximize" onclick="toggleTerminal()" style="cursor:pointer;" title="Agrandir">□</span>
                </span>
            </div>
            <div class="terminal-content" id="terminalContent">
                <?php if (empty($logs_for_terminal)): ?>
                    <div class="terminal-line">
                        <span style="color:#99a8b8;">Aucun log disponible pour <?php echo htmlspecialchars($log_filename); ?>.</span>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs_for_terminal as $log):
                        $t = $log['time'] ?? '';
                        if (preg_match('/(\d{2}:\d{2}:\d{2})$/', $log['date'] ?? '', $m)) $t = $m[1];
                        $type  = $log['type']  ?? '';
                        $message = strtolower($log['message'] ?? '');
                        // VERT : type = info
                        if ($type == 'info') {
                            $color = '#22c55e'; $icon = '✓';
                        }
                        // JAUNE : type = important_info
                        elseif ($type == 'important_info') {
                            $color = '#eab308'; $icon = '!';
                        }
                        // ORANGE : type = warning
                        elseif ($type == 'warning') {
                            $color = '#f97316'; $icon = '⚠';
                        }
                        // ROUGE : type = error
                        elseif ($type == 'error') {
                            $color = '#ef4444'; $icon = '✖';
                        }
                        // BLEU : BDD
                        elseif ($type == 'BDD') {
                            $color = '#3b82f6'; $icon = '🗄';
                        }
                        // NEUTRE : autres (par sécurité)
                        else {
                            $color = '#64748b'; $icon = '•';
                        }
                        $msg = htmlspecialchars($log['message'] ?? '');
                    ?>
                    <div class="terminal-line" data-type="<?php echo $type; ?>">
                        <span class="log-time">[<?php echo $t; ?>]</span>
                        <span style="color:<?php echo $color; ?>; flex-shrink:0;"><?php echo $icon; ?></span>
                        <span style="color:<?php echo $color; ?>;"><?php echo $msg; ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
/* Agrandir / réduire */
function toggleTerminal() {
    const term = document.getElementById('terminalLogs');
    const overlay = document.getElementById('terminalOverlay');
    const btn = term.querySelector('.maximize');
    const isOpen = term.classList.contains('expanded');
    term.classList.toggle('expanded');
    overlay.classList.toggle('active');
    btn.textContent = isOpen ? '□' : '⊡';
    btn.title = isOpen ? 'Agrandir' : 'Réduire';
}
 
/* Filtre par type */
function filterLogs(type) {
    const logs = document.querySelectorAll('#terminalContent .terminal-line');
    const buttons = document.querySelectorAll('.log-filters button');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    logs.forEach(log => {
        log.style.display = (type === 'all' || log.dataset.type === type) ? 'flex' : 'none';
    });
}
 
/* Édition du titre (double-clic) */
function startEditTitle() {
    const titleSpan = document.getElementById('terminalTitle');
    const current = titleSpan.textContent.trim();
 
    const input = document.createElement('input');
    input.id = 'terminalTitleInput';
    input.type = 'text';
    input.value = current;
    input.spellcheck = false;
    titleSpan.replaceWith(input);
    input.focus();
    input.select();
 
    function confirmEdit() {
        const newFile = input.value.trim();
        const match = newFile.match(/^logs-(\d{2}-\d{2}-\d{4})\.json$/);
 
        if (match && newFile !== current) {
            // Soumettre le formulaire GET
            document.getElementById('logDayInput').value = match[1];
            const form = document.getElementById('logDayForm');
            form.action = window.location.pathname + '#terminalCard';
            form.submit();
        } else {
            // Annuler ou format invalide : restaurer le span
            const newSpan = document.createElement('span');
            newSpan.id = 'terminalTitle';
            newSpan.className = 'terminal-title';
            newSpan.title = titleSpan.title;
            newSpan.textContent = current;
            newSpan.ondblclick = startEditTitle;
            input.replaceWith(newSpan);
        }
    }
 
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter')  {
            e.preventDefault(); confirmEdit();
        }
        if (e.key === 'Escape') {
            const cancelSpan = document.createElement('span');
            cancelSpan.id = 'terminalTitle';
            cancelSpan.className = 'terminal-title';
            cancelSpan.title = titleSpan.title;
            cancelSpan.textContent = current;
            cancelSpan.ondblclick = startEditTitle;
            input.replaceWith(cancelSpan);
        }
    });
 
    // blur déclenché après Escape aussi — on ignore si le span a déjà été restauré
    input.addEventListener('blur', () => {
        if (document.getElementById('terminalTitleInput')) confirmEdit();
    });
}
 
// Scroll automatique vers le terminal si on vient d'un changement de fichier
<?php if ($requested_day): ?>
document.addEventListener('DOMContentLoaded', () => {
    const card = document.getElementById('terminalCard');
    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>
 
// Initialiser le filtre actif
window._activeLogFilter = 'all';
</script>