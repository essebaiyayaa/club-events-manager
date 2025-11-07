<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /campusEvents/public/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Méthode non autorisée.";
    header('Location: dashboard_admin.php?page=clubs');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $id_club = intval($_POST['id_club']);
    $nom_club = trim($_POST['nom_club']);
    $description = trim($_POST['description']);
    $id_president = !empty($_POST['id_president']) ? intval($_POST['id_president']) : null;


    $check_query = "SELECT id_club FROM Club WHERE id_club = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$id_club]);
    
    if (!$check_stmt->fetch()) {
        $_SESSION['error'] = "Club introuvable.";
        header('Location: dashboard_admin.php?page=clubs');
        exit();
    }


    $check_name_query = "SELECT id_club FROM Club WHERE nom_club = ? AND id_club != ?";
    $check_name_stmt = $pdo->prepare($check_name_query);
    $check_name_stmt->execute([$nom_club, $id_club]);
    
    if ($check_name_stmt->fetch()) {
        $_SESSION['error'] = "Ce nom de club est déjà utilisé.";
        header('Location: edit_club.php?id=' . $id_club);
        exit();
    }

   
    if ($id_president) {
        $check_president_query = "SELECT id_club FROM Club WHERE id_president = ? AND id_club != ?";
        $check_president_stmt = $pdo->prepare($check_president_query);
        $check_president_stmt->execute([$id_president, $id_club]);
        
        if ($check_president_stmt->fetch()) {
            $_SESSION['error'] = "Cet organisateur préside déjà un autre club.";
            header('Location: edit_club.php?id=' . $id_club);
            exit();
        }
    }


    $update_query = "UPDATE Club SET nom_club = ?, description = ?, id_president = ? WHERE id_club = ?";
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute([$nom_club, $description, $id_president, $id_club]);

    $_SESSION['success'] = "Club modifié avec succès.";
    header('Location: dashboard_admin.php?page=clubs');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header('Location: edit_club.php?id=' . $id_club);
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
    header('Location: edit_club.php?id=' . $id_club);
    exit();
}
?>