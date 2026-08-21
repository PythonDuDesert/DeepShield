<?php
/**
 * email_helper.php - Fonctions d'envoi d'emails pour DeepShield
 * Utilise PHPMailer, configuré via .env (voir $config['mail']).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Envoie un email HTML formaté avec la charte DeepShield
 */
function send_deepshield_email($to, $subject, $bodyHtml) {
    global $config;
    $mailConfig = $config['mail'] ?? [];
    $mail = new PHPMailer(true);

    try {
        // ── Configuration SMTP (voir .env) ───────────────────────
        $mail->isSMTP();
        $mail->Host       = $mailConfig['host'] ?? 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailConfig['username'] ?? '';
        $mail->Password   = $mailConfig['password'] ?? '';
        $mail->SMTPSecure = ($mailConfig['encryption'] ?? 'tls') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($mailConfig['port'] ?? 2525);
        $mail->CharSet    = 'UTF-8';

        // ── Expéditeur & destinataire ────────────────────────────
        $mail->setFrom($mailConfig['from_address'] ?? 'noreply@deepshield.fr', $mailConfig['from_name'] ?? 'DeepShield');
        $mail->addAddress($to);

        // ── Contenu ──────────────────────────────────────────────
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = '<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; margin:0; padding:0; }
    .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow:hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .header { background: #05070d; padding: 24px 30px; text-align: center; }
    .header h1 { color: #4fc3f7; margin:0; font-size: 22px; letter-spacing: 1px; }
    .body { padding: 30px; color: #333; line-height: 1.6; }
    .btn { display: inline-block; margin: 20px 0; padding: 12px 28px; background: #4fc3f7; color: #05070d; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 15px; }
    .footer { background: #f9f9f9; padding: 16px 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px 14px; margin: 16px 0; border-radius: 4px; font-size: 13px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header"><h1>🛡️ DeepShield</h1></div>
    <div class="body">' . $bodyHtml . '</div>
    <div class="footer">© DeepShield – Ne répondez pas à cet email automatique.</div>
  </div>
</body>
</html>';

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Erreur envoi email : " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Email de confirmation d'inscription
 */
function sendConfirmationEmail($to, $first_name, $token) {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . $_SERVER['HTTP_HOST']
              . dirname($_SERVER['SCRIPT_NAME']);
    $link = rtrim($base_url, '/') . '/verify_email.php?token=' . urlencode($token);

    $subject = '🔐 Confirmez votre inscription sur DeepShield';
    $body = "<h2>Bonjour $first_name 👋</h2>
<p>Merci de vous être inscrit sur <strong>DeepShield</strong>. Pour activer votre compte, veuillez cliquer sur le bouton ci-dessous :</p>
<a href=\"$link\" class=\"btn\">✅ Confirmer mon adresse email</a>
<div class=\"warning\">⏳ Ce lien est valable pendant <strong>24 heures</strong>.</div>
<p>Si vous n'avez pas créé de compte sur DeepShield, ignorez simplement cet email.</p>
<p>Lien de confirmation :<br><a href=\"$link\">$link</a></p>";

    return send_deepshield_email($to, $subject, $body);
}

/**
 * Email de réinitialisation de mot de passe
 */
function sendResetPasswordEmail($to, $first_name, $token) {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . $_SERVER['HTTP_HOST']
              . dirname($_SERVER['SCRIPT_NAME']);
    $link = rtrim($base_url, '/') . '/reset_password.php?token=' . urlencode($token);

    $subject = '🔑 Réinitialisation de votre mot de passe DeepShield';
    $body = "<h2>Bonjour $first_name,</h2>
<p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
<a href=\"$link\" class=\"btn\">🔑 Réinitialiser mon mot de passe</a>
<div class=\"warning\">⏳ Ce lien est valable pendant <strong>1 heure</strong> et ne peut être utilisé qu'une seule fois.</div>
<p>Si vous n'avez pas demandé de réinitialisation, votre compte est en sécurité — ignorez cet email.</p>
<p>Lien de réinitialisation :<br><a href=\"$link\">$link</a></p>";

    return send_deepshield_email($to, $subject, $body);
}

/**
 * Email de confirmation de passage au compte Premium
 */
function sendPremiumUpgradeEmail($to, $first_name) {
    $subject = '⭐ Votre compte DeepShield est maintenant Premium';
    $body = "<h2>Bonjour $first_name,</h2>
<p>Votre compte DeepShield est passé en <strong>Premium</strong>. Vous bénéficiez désormais d'un historique étendu, d'un quota d'upload plus élevé et de l'export CSV de votre historique.</p>";

    return send_deepshield_email($to, $subject, $body);
}

/**
 * Email de confirmation de suppression de compte
 */
function sendAccountDeletedEmail($to, $first_name) {
    $subject = '👋 Votre compte DeepShield a été supprimé';
    $body = "<h2>Bonjour $first_name,</h2>
<p>Votre compte DeepShield et les données associées ont bien été supprimés, à votre demande.</p>
<p>Si vous n'êtes pas à l'origine de cette suppression, contactez immédiatement le support.</p>";

    return send_deepshield_email($to, $subject, $body);
}

/**
 * Email de notification suite à un changement de mot de passe
 */
function sendPasswordChangedEmail($to, $first_name) {
    $subject = '🔑 Votre mot de passe DeepShield a été modifié';
    $body = "<h2>Bonjour $first_name,</h2>
<p>Le mot de passe de votre compte DeepShield vient d'être modifié.</p>
<div class=\"warning\">⚠️ Si vous n'êtes pas à l'origine de ce changement, contactez immédiatement le support et réinitialisez votre mot de passe.</div>";

    return send_deepshield_email($to, $subject, $body);
}
?>
