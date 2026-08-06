<?php
function loadBackupHistory($historyFile) {
    if (!file_exists($historyFile)) return [];

    $content = file_get_contents($historyFile);
    if (!$content) return [];

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function saveBackupHistory($historyFile, $entries) {
    file_put_contents($historyFile, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function addHistoryEntry($historyFile, $action, $fileName, $details) {
    $entries = loadBackupHistory($historyFile);
    $entries[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action'    => $action,
        'file'      => $fileName,
        'details'   => $details,
    ];

    if (count($entries) > 500) {
        $entries = array_slice($entries, -500);
    }

    saveBackupHistory($historyFile, $entries);
}

function formatBytes($size) {
    $units = ['B','KB','MB','GB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2).' '.$units[$i];
}

function sanitizeBackupName($name) {
    return preg_replace('/[^a-zA-Z0-9._-]/', '', $name);
}

function writeDumpLine($handle, $line) {
    return fwrite($handle, $line) !== false;
}

function buildAutoBackupFileName($timestamp = null) {
    $ts = $timestamp ?? time();
    return DB_NAME . '-autobackup-' . date('Y-m-d_H-i-s', $ts) . '.sql';
}

function buildManualBackupFileName($timestamp = null) {
    $ts = $timestamp ?? time();
    return DB_NAME . '-' . date('Y-m-d_H-i-s', $ts) . '.sql';
}

function generateDatabaseDump($targetPath, &$tableCount = 0, &$rowCount = 0) {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        return 'Erreur de connexion à la base.';
    }
    $conn->set_charset('utf8mb4');
    $handle = fopen($targetPath, 'wb');
    if (!$handle) {
        $conn->close();
        return 'Impossible de créer le fichier.';
    }

    writeDumpLine($handle, "-- Dump ".DB_NAME." - ".date('Y-m-d H:i:s')."\n");
    writeDumpLine($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
    writeDumpLine($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
    writeDumpLine($handle, "START TRANSACTION;\n");
    writeDumpLine($handle, "SET time_zone = \"+01:00\";\n\n");
    
    $tablesResult = $conn->query('SHOW TABLES');
    if (!$tablesResult) {
        fclose($handle);
        $conn->close();
        return 'Impossible de lister les tables.';
    }

    while ($tableRow = $tablesResult->fetch_array()) {
        $table = $tableRow[0];
        $tableCount++;
        $createResult = $conn->query("SHOW CREATE TABLE `$table`");
        $createRow = $createResult->fetch_assoc();
        writeDumpLine($handle, "DROP TABLE IF EXISTS `$table`;\n");
        writeDumpLine($handle, $createRow['Create Table'].";\n\n");
        $rowsResult = $conn->query("SELECT * FROM `$table`");

        if ($rowsResult && $rowsResult->num_rows > 0) {
            $fields = [];
            while ($field = $rowsResult->fetch_field()) {
                $fields[] = '`'.$field->name.'`';
            }
            while ($row = $rowsResult->fetch_assoc()) {
                $rowCount++;
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null
                        ? 'NULL'
                        : "'".$conn->real_escape_string($value)."'";
                }
                writeDumpLine(
                    $handle,
                    "INSERT INTO `$table` (".implode(',', $fields).") VALUES (".implode(',', $values).");\n"
                );
            }
            writeDumpLine($handle, "\n");
        }
    }

    writeDumpLine($handle, "COMMIT;\n");
    writeDumpLine($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    $conn->close();

    return null;
}

/**
 * Fonction pour l'auto-backup (appelée par le cron)
 * Vérifie si un backup de moins d'1 heure existe, sinon en crée un
 */
function runAutoBackupCycle(array $config, string $backupDir, string $historyFile): void
{
    $pattern = $backupDir . '/deepshield_auto_*.sql';
    $files = glob($pattern) ?: [];

    // Rotation : conserver uniquement les 30 sauvegardes les plus récentes.
    if (count($files) > 30) {
        usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));

        foreach (array_slice($files, 0, count($files) - 30) as $file) {
            @unlink($file);

            addHistoryEntry(
                $historyFile,
                'auto-suppression',
                basename($file),
                'Rotation automatique (30 sauvegardes maximum)'
            );
        }

        $files = glob($pattern) ?: [];
    }

    // Recherche de la sauvegarde la plus récente.
    if ($files !== []) {
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

        $lastBackup = $files[0];
        $age = time() - filemtime($lastBackup);

        if ($age < 3600) {
            return;
        }
    }

    $fileName = 'deepshield_auto_' . date('Ymd_His') . '.sql';
    $target = $backupDir . '/' . $fileName;

    $result = run_mysqldump($config);

    if (!$result['ok']) {
        addHistoryEntry(
            $historyFile,
            'auto-erreur',
            $fileName,
            $result['error']
        );
        return;
    }

    if (file_put_contents($target, $result['sql']) === false) {
        addHistoryEntry(
            $historyFile,
            'auto-erreur',
            $fileName,
            "Impossible d'écrire le fichier."
        );
        return;
    }

    addHistoryEntry(
        $historyFile,
        'auto-création',
        $fileName,
        'Sauvegarde SQL créée (' . formatBytes(filesize($target)) . ').'
    );
}

/**
 * Liste tous les fichiers de backup disponibles
 */
function listBackupFiles($backupDir) {
    $files = glob($backupDir . '/*.sql');
    if (!$files) return [];
    $backups = [];
    foreach ($files as $file) {
        $backups[] = [
            'name' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'size_label' => formatBytes(filesize($file)),
            'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            'timestamp' => filemtime($file)
        ];
    }
    
    // Trier par date décroissante
    usort($backups, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    return $backups;
}

/**
 * Restaure une base de données à partir d'un fichier SQL
 * @param string $filePath Chemin complet vers le fichier SQL
 * @param int &$queriesExecuted Nombre de requêtes exécutées (passé par référence)
 * @return string|null Message d'erreur ou null si succès
 */
function restoreDatabaseFromSQL($filePath, &$queriesExecuted = 0) {
    if (!file_exists($filePath)) {
        return "Le fichier SQL n'existe pas.";
    }
    
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        return 'Erreur de connexion à la base de données.';
    }

    $conn->set_charset('utf8mb4');
    // Lire le contenu du fichier SQL
    $sql = file_get_contents($filePath);
    if ($sql === false) {
        $conn->close();
        return "Impossible de lire le fichier SQL.";
    }
    
    // Désactiver les vérifications de clés étrangères
    $conn->query('SET FOREIGN_KEY_CHECKS=0');
    $conn->query('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
    
    // Nettoyer le SQL et séparer les requêtes
    // Remplacer les sauts de ligne multiples par un seul
    $sql = preg_replace('/\r\n|\r/', "\n", $sql);
    // Séparer par point-virgule suivi d'un saut de ligne ou de fin de fichier
    $queries = preg_split('/;(\s*\n|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY);
    
    $errors = [];
    $successCount = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        // Ignorer les lignes vides et les commentaires
        if (empty($query)) {
            continue;
        }
        // Ignorer les commentaires -- et /* */
        $lines = explode("\n", $query);
        $cleanedQuery = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || substr($line, 0, 2) === '--') {
                continue;
            }
            $cleanedQuery .= $line . ' ';
        }
        
        $cleanedQuery = trim($cleanedQuery);
        
        // Ignorer si c'est un bloc de commentaire
        if (empty($cleanedQuery) || substr($cleanedQuery, 0, 2) === '/*') {
            continue;
        }
        
        // Exécuter la requête
        if (!$conn->query($cleanedQuery)) {
            $errorMsg = "Erreur SQL: " . $conn->error . " | Requête: " . substr($cleanedQuery, 0, 100);
            $errors[] = $errorMsg;
            error_log($errorMsg); // Logger pour debug
        } else {
            $successCount++;
        }
    }

    $queriesExecuted = $successCount;
    // Réactiver les vérifications de clés étrangères
    $conn->query('SET FOREIGN_KEY_CHECKS=1');
    $conn->close();
    if (!empty($errors)) {
        return "Restauration terminée avec " . count($errors) . " erreur(s). Première erreur: " . $errors[0];
    }
    
    return null;
}

/**
 * Lance mysqldump en sous-processus de façon défensive (même approche
 * qu'AnalysisRunner::run et que la fonction identique dans backup.php).
 *
 * @return array{ok:bool, sql?:string, error?:string}
 */
function run_mysqldump(array $config): array
{
    if (!function_exists('proc_open')) {
        return ['ok' => false, 'error' => "proc_open() est désactivé sur ce serveur."];
    }

    $mysqldump = 'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysqldump.exe';

    $cmd = [
        $mysqldump,
        '--host=' . $config['db_host'],
        '--port=' . (string) $config['db_port'],
        '--user=' . $config['db_user'],
        '--single-transaction',
        '--skip-lock-tables',
    ];

    if ($config['db_pass'] !== '') {
        $cmd[] = '--password=' . $config['db_pass'];
    }
    $cmd[] = $config['db_name'];

    $descriptorSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptorSpec, $pipes);

    if (!is_resource($process)) {
        return ['ok' => false, 'error' => "Impossible de démarrer mysqldump (vérifiez le PATH)."];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0 || trim((string) $stdout) === '') {
        $detail = $stderr !== '' ? trim($stderr) : 'Aucune sortie.';
        return ['ok' => false, 'error' => "mysqldump a échoué (code $exitCode). $detail"];
    }

    return ['ok' => true, 'sql' => $stdout];
}
?>