<?php
declare(strict_types=1);

/**
 * VideoRepository
 * =================
 * Isole tous les accès à la table `videos` du dump fourni. Le score
 * (colonne tinyint 0-100) stocke le pourcentage de confiance "réel", et le
 * champ `explinations` (tel quel dans le schéma fourni) stocke un résumé
 * lisible commençant par le verdict (RÉEL/SUSPECT/DEEPFAKE), ce qui permet
 * de filtrer l'historique par verdict avec un simple LIKE sans modifier le
 * schéma.
 */
final class VideoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function insert(int $userId, string $videoName, int $fileSize, ?string $senderEmail, int $score, string $explinations): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO videos (user_id, sender_email, video_name, file_size, uploaded_at, score, explinations)
             VALUES (:user_id, :sender_email, :video_name, :file_size, NOW(), :score, :explinations)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'sender_email' => $senderEmail,
            'video_name' => $videoName,
            'file_size' => $fileSize,
            'score' => max(0, min(100, $score)),
            'explinations' => substr($explinations, 0, 255),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM videos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function listByUser(int $userId, int $limit = 20, string $verdictFilter = ''): array
    {
        $sql = 'SELECT * FROM videos WHERE user_id = :user_id';
        $params = ['user_id' => $userId];
        if ($verdictFilter !== '') {
            $sql .= ' AND explinations LIKE :verdict';
            $params['verdict'] = $verdictFilter . '%';
        }
        $sql .= ' ORDER BY uploaded_at DESC LIMIT ' . max(1, min(500, $limit));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array{total:int, reel:int, suspect:int, deepfake:int, temps_moyen:float} */
    public function statsByUser(int $userId): array
    {
        $stats = ['total' => 0, 'reel' => 0, 'suspect' => 0, 'deepfake' => 0, 'score_moyen' => 0.0];

        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total, AVG(score) AS avg_score FROM videos WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['score_moyen'] = $row['avg_score'] !== null ? round((float) $row['avg_score'], 1) : 0.0;

        foreach (['RÉEL' => 'reel', 'SUSPECT' => 'suspect', 'DEEPFAKE' => 'deepfake'] as $label => $key) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) AS n FROM videos WHERE user_id = :user_id AND explinations LIKE :verdict');
            $stmt->execute(['user_id' => $userId, 'verdict' => $label . '%']);
            $stats[$key] = (int) $stmt->fetch()['n'];
        }

        return $stats;
    }

    public static function verdictFromExplinations(?string $explinations): string
    {
        foreach (['RÉEL', 'SUSPECT', 'DEEPFAKE'] as $verdict) {
            if (str_starts_with((string) $explinations, $verdict)) {
                return $verdict;
            }
        }
        return 'INDÉTERMINÉ';
    }

    /**
     * Statistiques toutes organisations confondues (page "Résultats globaux").
     * @return array{total:int, reel:int, suspect:int, deepfake:int, score_moyen:float, contributeurs:int}
     */
    public function statsGlobal(): array
    {
        $stats = ['total' => 0, 'reel' => 0, 'suspect' => 0, 'deepfake' => 0, 'score_moyen' => 0.0, 'contributeurs' => 0];

        $stmt = $this->pdo->query('SELECT COUNT(*) AS total, AVG(score) AS avg_score, COUNT(DISTINCT user_id) AS users FROM videos');
        $row = $stmt->fetch();
        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['score_moyen'] = $row['avg_score'] !== null ? round((float) $row['avg_score'], 1) : 0.0;
        $stats['contributeurs'] = (int) ($row['users'] ?? 0);

        foreach (['RÉEL' => 'reel', 'SUSPECT' => 'suspect', 'DEEPFAKE' => 'deepfake'] as $label => $key) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) AS n FROM videos WHERE explinations LIKE :verdict');
            $stmt->execute(['verdict' => $label . '%']);
            $stats[$key] = (int) $stmt->fetch()['n'];
        }

        return $stats;
    }

    /** @return array<int, array<string, mixed>> Analyses de tous les comptes, avec identité de l'auteur. */
    public function listAllWithUser(int $limit = 100, string $verdictFilter = ''): array
    {
        $sql = 'SELECT v.*, u.first_name AS u_first_name, u.last_name AS u_last_name, u.email AS u_email
                FROM videos v
                INNER JOIN users u ON u.id = v.user_id';
        $params = [];
        if ($verdictFilter !== '') {
            $sql .= ' WHERE v.explinations LIKE :verdict';
            $params['verdict'] = $verdictFilter . '%';
        }
        $sql .= ' ORDER BY v.uploaded_at DESC LIMIT ' . max(1, min(500, $limit));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> Classement des comptes par nombre d'analyses. */
    public function topContributors(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, COUNT(v.id) AS total, AVG(v.score) AS avg_score
             FROM videos v
             INNER JOIN users u ON u.id = v.user_id
             GROUP BY u.id, u.first_name, u.last_name, u.email
             ORDER BY total DESC
             LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
