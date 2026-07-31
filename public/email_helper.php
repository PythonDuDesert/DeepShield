<?php
/**
 * email_helper.php - Fonctions d'envoi d'emails pour SecureMail
 * Utilise PHPMailer avec Mailtrap (tests en local)
 * 
 * AVANT D'UTILISER : installer PHPMailer via Composer :
 *    composer require phpmailer/phpmailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';

/**
 * Envoie un email HTML formaté avec la charte SecureMail
 */
function sendSecureMailEmail($to, $subject, $bodyHtml) {
    $mail = new PHPMailer(true);

    try {
        // ── Configuration SMTP Mailtrap ──────────────────────────
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'c6084dcc5d0340';   // username Mailtrap
        $mail->Password   = '1565dd277060f0'; // mot de passe Mailtrap
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 2525;
        $mail->CharSet    = 'UTF-8';

        // ── Expéditeur & destinataire ────────────────────────────
        $mail->setFrom('noreply@securemail.fr', 'SecureMail');
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
    .header { background: #1a1a2e; padding: 24px 30px; text-align: center; }
    .header h1 { color: #4fc3f7; margin:0; font-size: 22px; letter-spacing: 1px; }
    .body { padding: 30px; color: #333; line-height: 1.6; }
    .btn { display: inline-block; margin: 20px 0; padding: 12px 28px; background: #4fc3f7; color: #1a1a2e; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 15px; }
    .footer { background: #f9f9f9; padding: 16px 30px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
    .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px 14px; margin: 16px 0; border-radius: 4px; font-size: 13px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header"><h1>🛡️ SecureMail</h1></div>
    <div class="body">' . $bodyHtml . '</div>
    <div class="footer">© SecureMail – Ne répondez pas à cet email automatique.</div>
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

    $subject = '🔐 Confirmez votre inscription sur SecureMail';
    $body = "<h2>Bonjour $first_name 👋</h2>
<p>Merci de vous être inscrit sur <strong>SecureMail</strong>. Pour activer votre compte, veuillez cliquer sur le bouton ci-dessous :</p>
<a href=\"$link\" class=\"btn\">✅ Confirmer mon adresse email</a>
<div class=\"warning\">⏳ Ce lien est valable pendant <strong>24 heures</strong>. Après ce délai, vous devrez vous réinscrire.</div>
<p>Si vous n'avez pas créé de compte sur SecureMail, ignorez simplement cet email.</p>
<p>Lien de confirmation :<br><a href=\"$link\">$link</a></p>";

    return sendSecureMailEmail($to, $subject, $body);
}

/**
 * Email de réinitialisation de mot de passe
 */
function sendResetPasswordEmail($to, $first_name, $token) {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . $_SERVER['HTTP_HOST']
              . dirname($_SERVER['SCRIPT_NAME']);
    $link = rtrim($base_url, '/') . '/reset_password.php?token=' . urlencode($token);

    $subject = '🔑 Réinitialisation de votre mot de passe SecureMail';
    $body = "<h2>Bonjour $first_name,</h2>
<p>Vous avez demandé la réinitialisation de votre mot de passe. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
<a href=\"$link\" class=\"btn\">🔑 Réinitialiser mon mot de passe</a>
<div class=\"warning\">⏳ Ce lien est valable pendant <strong>1 heure</strong> et ne peut être utilisé qu'une seule fois.</div>
<p>Si vous n'avez pas demandé de réinitialisation, votre compte est en sécurité — ignorez cet email.</p>
<p>Lien de réinitialisation :<br><a href=\"$link\">$link</a></p>";

    return sendSecureMailEmail($to, $subject, $body);
}
?>