<?php
declare(strict_types=1);

/**
 * Auth
 * =====
 * Authentification réelle branchée sur les tables `users` et
 * `login_attempts` du dump fourni (deepshield_bdd.sql). Aucune table n'est
 * modifiée : les colonnes existantes (failed_login_attempts, is_active)
 * suffisent à implémenter un verrouillage anti-bruteforce simple.
 */
final class Auth
{
    private const MAX_FAILED_ATTEMPTS = 5;

    public function __construct(private PDO $pdo)
    {
    }

    public function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'] ?? '',
            'first_name' => $_SESSION['first_name'] ?? '',
            'last_name' => $_SESSION['last_name'] ?? '',
            'role' => $_SESSION['role'] ?? 2,
        ];
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * @return array{success:bool, error:?string}
     */
    public function register(string $email, string $password, string $firstName, string $lastName): array
    {
        $email = trim(strtolower($email));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Adresse e-mail invalide."];
        }
        if (strlen($email) > 50) {
            return ['success' => false, 'error' => "Adresse e-mail trop longue (50 caractères maximum)."];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => "Le mot de passe doit contenir au moins 8 caractères."];
        }
        if ($firstName === '' || $lastName === '') {
            return ['success' => false, 'error' => "Merci d'indiquer votre nom et votre prénom."];
        }

        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch() !== false) {
            return ['success' => false, 'error' => "Un compte existe déjà avec cette adresse e-mail."];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, created_at, updated_at)
             VALUES (:email, :hash, :first_name, :last_name, 3, 1, NOW(), NOW())'
        );
        $stmt->execute([
            'email' => $email,
            'hash' => $hash,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);

        return ['success' => true, 'error' => null, 'user_id' => (int) $this->pdo->lastInsertId()];
    }

    /**
     * @return array{success:bool, error:?string}
     */
    public function login(string $email, string $password, string $ipAddress): array
    {
        $email = trim(strtolower($email));

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user === false) {
            $this->logAttempt($ipAddress, $email);
            return ['success' => false, 'error' => "Adresse e-mail ou mot de passe incorrect."];
        }

        if ((int) $user['is_active'] === 2) {
            return ['success' => false, 'error' => "Ce compte est temporairement bloqué après plusieurs tentatives de connexion échouées. Réessayez plus tard ou contactez le support."];
        }
        if ((int) $user['is_active'] === 0) {
            return ['success' => false, 'error' => "Ce compte est désactivé."];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->logAttempt($ipAddress, $email);
            $this->registerFailedAttempt((int) $user['id'], (int) $user['failed_login_attempts']);
            return ['success' => false, 'error' => "Adresse e-mail ou mot de passe incorrect."];
        }

        // Connexion réussie : on réinitialise le compteur d'échecs.
        $stmt = $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = 0, last_login = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['role'] = (int) $user['role'];

        return ['success' => true, 'error' => null];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    private function logAttempt(string $ipAddress, ?string $email): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (ip_address, email, attempt_time) VALUES (:ip, :email, NOW())'
        );
        $stmt->execute(['ip' => $ipAddress, 'email' => $email]);
    }

    private function registerFailedAttempt(int $userId, int $currentAttempts): void
    {
        $newCount = $currentAttempts + 1;
        $shouldLock = $newCount >= self::MAX_FAILED_ATTEMPTS;

        $stmt = $this->pdo->prepare(
            'UPDATE users SET failed_login_attempts = :count, last_try_login = NOW()' .
            ($shouldLock ? ', is_active = 2' : '') .
            ' WHERE id = :id'
        );
        $stmt->execute(['count' => $newCount, 'id' => $userId]);
    }
}
