<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$navActive = 'gestion_users';

ds_require_admin($auth, $dbError);

$roleLabels = [0 => 'Admin', 1 => 'Utilisateur premium', 2 => 'Utilisateur'];

if ($dbError === null) {
    $currentUser = $auth->currentUser();
    $currentUserId = (int) $currentUser['id'];
    $currentRole = (int) $currentUser['role'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $targetId = (int) ($_POST['user_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $targetId]);
        $target = $stmt->fetch();

        if ($target === false) {
            ds_flash_set('error', "Utilisateur introuvable.");
        } elseif ($targetId === $currentUserId && in_array($action, ['deactivate', 'delete_user'], true)) {
            ds_flash_set('error', "Vous ne pouvez pas désactiver ou supprimer votre propre compte.");
        } elseif ((int) $target['role'] <= 1 && $currentRole !== 0 && $action !== 'unlock') {
            ds_flash_set('error', "Seul un super admin peut modifier un compte admin.");
        } else {
            switch ($action) {
                case 'update_role':
                    $newRole = (int) ($_POST['role'] ?? 2);
                    if ($newRole < 0 || $newRole > 2) {
                        ds_flash_set('error', "Rôle invalide.");
                        break;
                    }
                    if ($newRole <= 1 && $currentRole !== 0) {
                        ds_flash_set('error', "Seul un admin peut promouvoir un compte administrateur.");
                        break;
                    }
                    if ($targetId === $currentUserId && $newRole > $currentRole) {
                        ds_flash_set('error', "Vous ne pouvez pas vous rétrograder vous-même.");
                        break;
                    }
                    $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
                    $stmt->execute(['role' => $newRole, 'id' => $targetId]);
                    ds_flash_set('success', "Rôle mis à jour pour " . $target['first_name'] . ' ' . $target['last_name'] . '.');
                    break;

                case 'unlock':
                    $stmt = $pdo->prepare('UPDATE users SET is_active = 1, failed_login_attempts = 0 WHERE id = :id');
                    $stmt->execute(['id' => $targetId]);
                    ds_flash_set('success', "Compte débloqué.");
                    break;

                case 'deactivate':
                    $stmt = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
                    $stmt->execute(['id' => $targetId]);
                    ds_flash_set('success', "Compte désactivé.");
                    break;

                case 'activate':
                    $stmt = $pdo->prepare('UPDATE users SET is_active = 1, failed_login_attempts = 0 WHERE id = :id');
                    $stmt->execute(['id' => $targetId]);
                    ds_flash_set('success', "Compte réactivé.");
                    break;

                case 'delete_user':
                    $log = $pdo->prepare(
                        'INSERT INTO account_deletion_logs (user_id, first_name, last_name, email, role, reason, deleted_at)
                         VALUES (:user_id, :first_name, :last_name, :email, :role, :reason, NOW())'
                    );
                    $log->execute([
                        'user_id' => $target['id'],
                        'first_name' => $target['first_name'],
                        'last_name' => $target['last_name'],
                        'email' => $target['email'],
                        'role' => $target['role'],
                        'reason' => 'Suppression manuelle via la gestion des utilisateurs.',
                    ]);
                    $del = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $del->execute(['id' => $targetId]);
                    ds_flash_set('success', "Compte supprimé (archivé dans le journal de suppression).");
                    break;

                default:
                    ds_flash_set('error', "Action inconnue.");
            }
        }

        header('Location: gestion_users.php');
        exit;
    }
}

$flash = ds_flash_get();
$users = [];
if ($dbError === null) {
    $stmt = $pdo->query('SELECT id, email, first_name, last_name, role, is_active, failed_login_attempts, created_at, last_login FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion des utilisateurs — DeepShield</title>
<link href="assets/css/site.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
<link rel="shortcut icon" href="assets/images/bouclier.ico" type="image/x-icon">
</head>
<body>

<canvas id="bg-canvas"></canvas>

<div class="app-layout">
  <?php include __DIR__ . '/../src/partials/app_sidebar.php'; ?>

  <main class="app-main">
    <div class="app-page-head">
      <h1>Gestion des utilisateurs</h1>
      <p>Rôles, blocages et suppression des comptes de la plateforme.</p>
    </div>

    <?php if ($dbError !== null): ?>
      <div class="notice"><img src="assets/images/supprimer.png" alt="supprimer.png" width="30px"> Base de données injoignable : <?= e($dbError) ?></div>
    <?php else: ?>

    <?php if ($flash): ?>
      <div class="notice" style="margin-bottom:18px;<?= $flash['type'] === 'error' ? 'border-color:var(--danger);background:rgba(248,113,113,0.08);color:var(--danger);' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <section class="panel">
      <?php if (empty($users)): ?>
        <div class="empty-state"><p>Aucun compte enregistré.</p></div>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Compte</th><th>Rôle</th><th>Statut</th><th>Échecs</th><th>Dernière connexion</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): $isSelf = (int) $u['id'] === $currentUserId; ?>
              <tr>
                <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?><br><span class="muted"><?= e($u['email']) ?></span></td>
                <td>
                  <form method="post" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <select name="role" onchange="this.form.submit()" <?= ((int) $u['role'] <= 1 && $currentRole !== 0) ? 'disabled' : '' ?>
                      style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.09);color:var(--text-main);border-radius:8px;padding:5px 8px;font-size:0.85em;">
                      <?php foreach ($roleLabels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= (int) $u['role'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
                <td>
                  <?php if ((int) $u['is_active'] === 1): ?>
                    <span class="badge badge-ok">ACTIF</span>
                  <?php elseif ((int) $u['is_active'] === 2): ?>
                    <span class="badge badge-warn">BLOQUÉ</span>
                  <?php else: ?>
                    <span class="badge badge-neutral">DÉSACTIVÉ</span>
                  <?php endif; ?>
                </td>
                <td><?= (int) $u['failed_login_attempts'] ?></td>
                <td><?= $u['last_login'] ? e(substr((string) $u['last_login'], 0, 16)) : '—' ?></td>
                <td>
                  <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php if ((int) $u['is_active'] === 2): ?>
                      <form method="post"><input type="hidden" name="action" value="unlock"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button type="submit" class="btn-ghost">Débloquer</button></form>
                    <?php elseif ((int) $u['is_active'] === 0): ?>
                      <form method="post"><input type="hidden" name="action" value="activate"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button type="submit" class="btn-ghost">Réactiver</button></form>
                    <?php else: ?>
                      <form method="post" onsubmit="return !<?= $isSelf ? 'true' : 'false' ?>;"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button type="submit" class="btn-ghost" <?= $isSelf ? 'disabled title="Vous ne pouvez pas désactiver votre propre compte"' : '' ?>>Désactiver</button></form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Supprimer définitivement ce compte ?');"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><button type="submit" class="btn-ghost" style="color:var(--danger);" <?= $isSelf ? 'disabled title="Vous ne pouvez pas supprimer votre propre compte"' : '' ?>>Supprimer</button></form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <p class="disclaimer">Seul un super admin peut modifier ou supprimer un compte admin. Chaque suppression est archivée dans le journal de suppression (<code>account_deletion_logs</code>).</p>

    <?php endif; ?>
  </main>
</div>

<script src="assets/js/site.js"></script>
</body>
</html>
