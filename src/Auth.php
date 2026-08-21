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
    private const IP_MAX_ATTEMPTS = 10;
    private const IP_WINDOW_MINUTES = 15;

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
        $passwordError = $this->passwordError($password);
        if ($passwordError !== null) {
            return ['success' => false, 'error' => $passwordError];
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
        $emailToken = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, email_token, email_token_expires, created_at, updated_at)
             VALUES (:email, :hash, :first_name, :last_name, 2, 1, :email_token, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NOW())'
        );
        $stmt->execute([
            'email' => $email,
            'hash' => $hash,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email_token' => $emailToken,
        ]);

        return [
            'success' => true,
            'error' => null,
            'user_id' => (int) $this->pdo->lastInsertId(),
            'email_token' => $emailToken,
        ];
    }

    /**
     * @return array{success:bool, error:?string}
     */
    public function login(string $email, string $password, string $ipAddress): array
    {
        $email = trim(strtolower($email));

        if ($this->isIpRateLimited($ipAddress)) {
            return ['success' => false, 'error' => "Trop de tentatives de connexion depuis cette adresse. Réessayez dans quelques minutes."];
        }

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

        if (!empty($user['email_token'])) {
            return ['success' => false, 'error' => "Merci de confirmer votre adresse email avant de vous connecter.", 'unverified' => true];
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

    /**
     * Point d'entrée unique pour passer un compte standard en premium.
     * Aujourd'hui déclenché par une confirmation simulée ; destiné à être
     * appelé plus tard par un webhook de paiement (Stripe) après
     * confirmation réelle du paiement, sans changer cette méthode.
     *
     * @return array{success:bool, error:?string, email?:string, first_name?:string}
     */
    public function upgradeToPremium(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT role, email, first_name FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false) {
            return ['success' => false, 'error' => "Compte introuvable."];
        }
        if ((int) $user['role'] !== 2) {
            return ['success' => false, 'error' => "Ce compte est déjà premium ou administrateur."];
        }

        $stmt = $this->pdo->prepare('UPDATE users SET role = 1 WHERE id = :id');
        $stmt->execute(['id' => $userId]);

        return ['success' => true, 'error' => null, 'email' => $user['email'], 'first_name' => $user['first_name']];
    }

    /** Valide un token de confirmation d'email et active le compte s'il est correct. */
    public function verifyEmail(string $token): bool
    {
        if ($token === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email_token = :token AND email_token_expires > NOW()');
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
        if ($user === false) {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE users SET email_token = NULL, email_token_expires = NULL WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        return true;
    }

    /**
     * Régénère le token de confirmation d'un compte pas encore vérifié.
     * @return array{token:string, first_name:string}|null null si le compte n'existe pas,
     *         est déjà vérifié, ou si un token a déjà été émis récemment (anti-spam).
     */
    public function resendEmailToken(string $email): ?array
    {
        $email = trim(strtolower($email));
        $stmt = $this->pdo->prepare('SELECT id, first_name, email_token, email_token_expires FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if ($user === false || empty($user['email_token'])) {
            return null;
        }
        if (!empty($user['email_token_expires'])) {
            $expiresAt = strtotime((string) $user['email_token_expires']);
            if ($expiresAt !== false && ($expiresAt - time()) > 23 * 3600 + 55 * 60) {
                return null; // token émis il y a moins de 5 min
            }
        }
        $newToken = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email_token = :token, email_token_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = :id'
        );
        $stmt->execute(['token' => $newToken, 'id' => $user['id']]);
        return ['token' => $newToken, 'first_name' => $user['first_name']];
    }

    /**
     * @return array{token:string, first_name:string, user_id:int}|null null si le compte n'existe pas ou est inactif.
     */
    public function requestPasswordReset(string $email): ?array
    {
        $email = trim(strtolower($email));
        $stmt = $this->pdo->prepare('SELECT id, first_name FROM users WHERE email = :email AND is_active = 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if ($user === false) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'UPDATE users SET reset_token = :token, reset_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = :id'
        );
        $stmt->execute(['token' => $token, 'id' => $user['id']]);
        return ['token' => $token, 'first_name' => $user['first_name'], 'user_id' => (int) $user['id']];
    }

    public function isResetTokenValid(string $token): bool
    {
        if ($token === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE reset_token = :token AND reset_token_expires > NOW()');
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() !== false;
    }

    /**
     * @return array{success:bool, error:?string, user_id?:int, email?:string, first_name?:string}
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        if ($token === '') {
            return ['success' => false, 'error' => "Lien invalide."];
        }
        $passwordError = $this->passwordError($newPassword);
        if ($passwordError !== null) {
            return ['success' => false, 'error' => $passwordError];
        }

        $stmt = $this->pdo->prepare('SELECT id, email, first_name FROM users WHERE reset_token = :token AND reset_token_expires > NOW()');
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
        if ($user === false) {
            return ['success' => false, 'error' => "Ce lien de réinitialisation est invalide ou a expiré."];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :hash, reset_token = NULL, reset_token_expires = NULL WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $user['id']]);

        return ['success' => true, 'error' => null, 'user_id' => (int) $user['id'], 'email' => $user['email'], 'first_name' => $user['first_name']];
    }

    /**
     * @return array{success:bool, error:?string}
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if ($user === false || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => "Mot de passe actuel incorrect."];
        }

        $passwordError = $this->passwordError($newPassword);
        if ($passwordError !== null) {
            return ['success' => false, 'error' => $passwordError];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $userId]);

        return ['success' => true, 'error' => null];
    }

    /**
     * Suppression volontaire de son propre compte (archivé, comme les
     * suppressions faites par un admin dans gestion_users.php).
     *
     * @return array{success:bool, error:?string, email?:string, first_name?:string, last_name?:string}
     */
    public function deleteAccount(int $userId, string $currentPassword): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user === false || !password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'error' => "Mot de passe incorrect."];
        }

        $log = $this->pdo->prepare(
            'INSERT INTO account_deletion_logs (user_id, first_name, last_name, email, role, reason, deleted_at)
             VALUES (:user_id, :first_name, :last_name, :email, :role, :reason, NOW())'
        );
        $log->execute([
            'user_id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'reason' => 'Suppression volontaire par le titulaire du compte.',
        ]);

        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);

        return [
            'success' => true,
            'error' => null,
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
        ];
    }

    private function passwordError(string $password): ?string
    {
        if (strlen($password) < 8 || strlen($password) > 72) {
            return "Le mot de passe doit contenir entre 8 et 72 caractères.";
        }
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return "Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.";
        }
        return null;
    }

    private function isIpRateLimited(string $ipAddress): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip AND attempt_time > (NOW() - INTERVAL ' . self::IP_WINDOW_MINUTES . ' MINUTE)'
        );
        $stmt->execute(['ip' => $ipAddress]);
        return (int) $stmt->fetchColumn() >= self::IP_MAX_ATTEMPTS;
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
