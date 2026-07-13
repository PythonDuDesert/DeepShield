<?php
declare(strict_types=1);

/**
 * Connexion PDO unique vers la base `deepshield`.
 * Configuration exclusivement via .env (DB_HOST, DB_PORT, DB_NAME, DB_USER,
 * DB_PASS), conformément à l'exigence 5.3 (aucun identifiant codé en dur).
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function connect(array $config): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_port'],
            $config['db_name']
        );

        try {
            self::$instance = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Jamais d'échec silencieux (exigence 5.3) : on relance une
            // exception explicite que les pages appelantes savent afficher.
            throw new RuntimeException(
                "Connexion à la base de données impossible. Vérifiez DB_HOST/DB_NAME/DB_USER/DB_PASS dans .env. " .
                "Détail : " . $e->getMessage()
            );
        }

        return self::$instance;
    }
}
