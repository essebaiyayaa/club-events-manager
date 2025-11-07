<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /campusEvents/public/auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID organisateur manquant.";
    header('Location: dashboard_admin.php?page=organisateurs');
    exit();
}

$organisateur_id = intval($_GET['id']);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

 
    $check_query = "SELECT nom, prenom FROM Utilisateur WHERE id_utilisateur = ? AND role = 'organisateur'";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$organisateur_id]);
    $organisateur = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$organisateur) {
        $_SESSION['error'] = "Organisateur introuvable.";
        header('Location: dashboard_admin.php?page=organisateurs');
        exit();
    }

  
    $delete_query = "DELETE FROM Utilisateur WHERE id_utilisateur = ?";
    $delete_stmt = $pdo->prepare($delete_query);
    $delete_stmt->execute([$organisateur_id]);

    $_SESSION['success'] = "Organisateur " . htmlspecialchars($organisateur['nom'] . ' ' . $organisateur['prenom']) . " supprimé avec succès.";
    header('Location: dashboard_admin.php?page=organisateurs');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header('Location: dashboard_admin.php?page=organisateurs');
    exit();
}
?>