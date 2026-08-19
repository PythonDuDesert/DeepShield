<?php
declare(strict_types=1);

/**
 * ReportStore
 * ============
 * Persistance minimale des rapports d'analyse sous forme de fichiers JSON.
 * Volontairement simple (pas de base de données) puisque le périmètre du
 * projet est une démonstration (voir 3.2 : "pas de déploiement à grande
 * échelle"). Suffisant pour l'export de rapport (exigence 4.4) et
 * l'historique de démonstration.
 */
final class ReportStore
{
    public function __construct(private string $reportsDir)
    {
        if (!is_dir($this->reportsDir)) {
            mkdir($this->reportsDir, 0775, true);
        }
    }

    public function save(array $report, ?string $id = null): string
    {
        $id = $id ?? ds_uuid();
        $report['id'] = $id;
        $path = $this->pathFor($id);
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $id;
    }

    public function load(string $id): ?array
    {
        if (!preg_match('/^(?:[a-f0-9\-]{36}|\d{1,20}|video_\d{1,20}|audio_\d{1,20})$/', $id)) {
            return null;
        }
        $path = $this->pathFor($id);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode(
            (string) file_get_contents($path),
            true
        );

        return is_array($data) ? $data : null;
    }

    /** @return array<int, array{id:string, generated_at:string, verdict:string, filename:string}> */
    public function listRecent(int $limit = 10): array
    {
        $files = glob($this->reportsDir . '/*.json') ?: [];
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, $limit);

        $summaries = [];
        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $summaries[] = [
                'id' => $data['id'] ?? basename($file, '.json'),
                'generated_at' => $data['generated_at'] ?? '',
                'verdict' => $data['global']['verdict'] ?? 'INDÉTERMINÉ',
                'filename' => $data['video']['filename'] ?? ($data['audio']['filename'] ?? '—'),
                'status' => $data['status'] ?? 'error',
            ];
        }
        return $summaries;
    }

    /**
     * Statistiques globales pour les cartes du dashboard.
     * @return array{total:int, reel:int, suspect:int, deepfake:int, erreurs:int, temps_moyen:float}
     */
    public function stats(): array
    {
        $files = glob($this->reportsDir . '/*.json') ?: [];
        $stats = ['total' => 0, 'reel' => 0, 'suspect' => 0, 'deepfake' => 0, 'erreurs' => 0, 'temps_moyen' => 0.0];
        $elapsedSum = 0.0;
        $elapsedCount = 0;

        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $stats['total']++;
            if (($data['status'] ?? 'error') !== 'ok') {
                $stats['erreurs']++;
                continue;
            }
            $verdict = $data['global']['verdict'] ?? '';
            match ($verdict) {
                'RÉEL' => $stats['reel']++,
                'SUSPECT' => $stats['suspect']++,
                'DEEPFAKE' => $stats['deepfake']++,
                default => null,
            };
            if (isset($data['elapsed_seconds'])) {
                $elapsedSum += (float) $data['elapsed_seconds'];
                $elapsedCount++;
            }
        }

        $stats['temps_moyen'] = $elapsedCount > 0 ? round($elapsedSum / $elapsedCount, 2) : 0.0;
        return $stats;
    }

    private function pathFor(string $id): string
    {
        return $this->reportsDir . '/' . $id . '.json';
    }
}
