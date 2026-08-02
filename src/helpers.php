<?php
declare(strict_types=1);

/** Échappement HTML raccourci */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function ds_format_bytes(int $bytes): string
{
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $i = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return sprintf('%.1f %s', $value, $units[$i]);
}

function ds_verdict_class(string $verdict): string
{
    return match ($verdict) {
        'RÉEL' => 'badge-ok',
        'SUSPECT' => 'badge-warn',
        'DEEPFAKE' => 'badge-danger',
        default => 'badge-neutral',
    };
}

function ds_verdict_label(string $verdict): string
{
    return match ($verdict) {
        'RÉEL' => 'RÉEL',
        'SUSPECT' => 'SUSPECT',
        'DEEPFAKE' => 'DEEPFAKE',
        default => 'INDÉTERMINÉ',
    };
}

function ds_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** Vérifie que l'extension d'un nom de fichier fait partie d'une liste autorisée */
function ds_has_allowed_extension(string $filename, array $allowed): bool
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed, true);
}

function ds_flash_get(): ?array
{
    if (!empty($_SESSION['ds_flash'])) {
        $flash = $_SESSION['ds_flash'];
        unset($_SESSION['ds_flash']);
        return $flash;
    }
    return null;
}

function ds_flash_set(string $type, string $message): void
{
    $_SESSION['ds_flash'] = ['type' => $type, 'message' => $message];
}

/** Génère (une fois par session) et retourne le jeton CSRF courant. */
function ds_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Vérifie qu'un jeton soumis correspond au jeton CSRF de la session. */
function ds_csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}
