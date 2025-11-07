<?php
/**
 * Configuration de la base de données
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_evenements');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Configuration reCAPTCHA
 * Obtenez vos clés sur : https://www.google.com/recaptcha/admin
 */
define('RECAPTCHA_SITE_KEY', '6LcGIt8rAAAAAIRNJXJE2gL6truEsteLcJWM4YJb');
define('RECAPTCHA_SECRET_KEY', '6LcGIt8rAAAAAI8YKl6peMkTMGftBMxs-DnkhJ5d');

/**
 * Configuration SMTP pour l'envoi d'emails
 */
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '0theentirepopulationoftexas0@gmail.com');
define('SMTP_PASS', 'vzkn jdjs jtta cdnp');  // App Password Gmail
define('FROM_EMAIL', 'noreply@campusevent.com');
define('FROM_NAME', 'Campus Event');

/**
 * Configuration générale
 */
define('SITE_URL', 'http://localhost/campusEvents');
define('TIMEZONE', 'Africa/Casablanca');

// Définir le fuseau horaire
date_default_timezone_set(TIMEZONE);

/**
 * Connexion PDO (optionnelle, pour compatibilité)
 */
$host = DB_HOST;
$dbname = DB_NAME;
$username = DB_USER;
$password = DB_PASS;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>