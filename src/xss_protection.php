<?php

// Échappe les données pour affichage HTML sécurisé
// Utiliser TOUJOURS cette fonction avant d'afficher des données utilisateur
function escape($data) {
    if (is_array($data)) {
        return array_map('escape', $data);
    }
    if ($data === null) {
        return '';
    }
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}


// Alias court pour escape()
function e($data) {
    return escape($data);
}


// Nettoie une chaîne de tous les tags HTML
function cleanText($text) {
    if ($text === null) return '';
    return strip_tags(trim($text));
}


// Nettoie et valide un email
function cleanEmail($email) {
    if ($email === null) return '';
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return $email;
}


// Valide un email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


// Nettoie une URL
function cleanUrl($url) {
    if ($url === null) return '';
    return filter_var($url, FILTER_SANITIZE_URL);
}


// Valide une URL
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}


// Nettoie un entier
function cleanInt($value) {
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}


// Nettoie un nombre décimal
function cleanFloat($value) {
    return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}


// Permet uniquement les caractères alphanumériques
function cleanAlphanumeric($string) {
    if ($string === null) return '';
    return preg_replace('/[^a-zA-Z0-9]/', '', $string);
}


// Nettoie une chaîne pour utilisation dans un attribut HTML
function escapeAttribute($data) {
    if ($data === null) return '';
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}


// Nettoie pour utilisation dans JavaScript
function escapeJs($data) {
    if ($data === null) return '';
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}


// Nettoie le HTML tout en gardant certaines balises autorisées
function cleanHtml($html, $allowed_tags = '<p><br><strong><em><ul><ol><li><a>') {
    if ($html === null) return '';
    
    // Supprimer tous les attributs dangereux
    $html = preg_replace('/<([a-z][a-z0-9]*)[^>]*?(on\w+\s*=)[^>]*?>/i', '<$1>', $html);
    
    // Strip tags non autorisés
    return strip_tags($html, $allowed_tags);
}


// Valide et nettoie un numéro de téléphone
function cleanPhone($phone) {
    if ($phone === null) return '';
    return preg_replace('/[^0-9+\-\s()]/', '', $phone);
}


// Protège contre les injections dans les noms de fichiers
function cleanFilename($filename) {
    if ($filename === null) return '';
    
    // Supprimer les caractères dangereux
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Empêcher les séquences dangereuses
    $filename = str_replace(['..', '/', '\\'], '', $filename);
    
    return $filename;
}


// Protège les données JSON avant affichage
function escapeJson($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}
?>