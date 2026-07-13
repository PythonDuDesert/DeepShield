<?php
declare(strict_types=1);

/**
 * AnalysisRunner
 * ================
 * Unique point d'intégration entre le front PHP et le moteur d'analyse.
 *
 * Aujourd'hui : lance bridge/analyze_bridge.py en sous-processus et lit
 * son JSON sur stdout. Le planning du cahier des charges prévoit une
 * future API FastAPI (phase 5, "À faire") : le jour où elle existera, il
 * suffira de remplacer le contenu de run() par un appel HTTP (curl) vers
 * POST /analyze, sans toucher au reste du front (voir README).
 *
 * Aucune sortie autre que du JSON n'est faite confiance : toute anomalie
 * (process qui plante, timeout, JSON invalide) est remontée comme une
 * erreur explicite plutôt que de faire échouer silencieusement la requête
 * (exigence 5.3).
 */
final class AnalysisRunner
{
    private array $config;
    private int $timeoutSeconds;

    public function __construct(array $config, int $timeoutSeconds = 120)
    {
        $this->config = $config;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * @param string|null $videoPath Chemin absolu du fichier vidéo, ou null
     * @param string|null $audioPath Chemin absolu du fichier audio, ou null
     * @return array Rapport décodé (toujours un tableau, jamais d'exception)
     */
    public function run(?string $videoPath, ?string $audioPath): array
    {
        $cmd = [
            $this->config['python_bin'],
            $this->config['bridge_script'],
            '--max-frames', (string) $this->config['max_frames'],
            '--threshold', (string) $this->config['threshold'],
        ];
        if ($videoPath !== null) {
            $cmd[] = '--video';
            $cmd[] = $videoPath;
        }
        if ($audioPath !== null) {
            $cmd[] = '--audio';
            $cmd[] = $audioPath;
        }

        $env = [
            'DEEPSHIELD_MOCK_MODE'       => $this->config['mock_mode'] ? '1' : '0',
            'DEEPSHIELD_MODEL_CACHE_DIR' => $this->config['model_cache_dir'],
            'DEEPSHIELD_TEMP_DIR'        => $this->config['temp_dir'],
            'PATH'                       => getenv('PATH') ?: '/usr/bin:/bin',
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        if (!function_exists('proc_open')) {
            return $this->errorReport(
                "La fonction PHP proc_open() est désactivée sur ce serveur (fréquent sur " .
                "l'hébergement mutualisé). Elle est indispensable pour lancer le moteur Python. " .
                "Contactez votre hébergeur pour l'activer, ou testez en local (XAMPP/WAMP/MAMP)."
            );
        }

        $process = proc_open($cmd, $descriptorSpec, $pipes, $this->config['root_dir'], $env);

        if (!is_resource($process)) {
            return $this->errorReport("Impossible de démarrer le processus d'analyse Python.");
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();

        while (true) {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }
            if (time() - $start > $this->timeoutSeconds) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return $this->errorReport("Délai d'analyse dépassé ({$this->timeoutSeconds}s).");
            }
            usleep(100_000);
        }

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (trim($stdout) === '') {
            return $this->errorReport(
                "Le moteur d'analyse n'a renvoyé aucune sortie (code $exitCode). " .
                ($stderr !== '' ? "Détail : " . trim($stderr) : "Vérifiez l'installation de Python.")
            );
        }

        $decoded = json_decode($stdout, true);
        if (!is_array($decoded)) {
            return $this->errorReport("Réponse du moteur d'analyse illisible (JSON invalide).");
        }

        return $decoded;
    }

    private function errorReport(string $message): array
    {
        return [
            'status' => 'error',
            'error' => $message,
            'generated_at' => gmdate('c'),
        ];
    }
}
