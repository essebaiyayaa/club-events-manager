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

    $nom_club = trim($_POST['nom_club']);
    $description = trim($_POST['description']);
    $id_president = !empty($_POST['id_president']) ? intval($_POST['id_president']) : null;


    $check_club_query = "SELECT id_club FROM Club WHERE nom_club = ?";
    $check_club_stmt = $pdo->prepare($check_club_query);
    $check_club_stmt->execute([$nom_club]);
    
    if ($check_club_stmt->fetch()) {
        $_SESSION['error'] = "Ce nom de club existe déjà.";
        header('Location: dashboard_admin.php?page=add_club');
        exit();
    }


    if ($id_president) {
        $check_president_query = "SELECT id_club FROM Club WHERE id_president = ?";
        $check_president_stmt = $pdo->prepare($check_president_query);
        $check_president_stmt->execute([$id_president]);
        
        if ($check_president_stmt->fetch()) {
            $_SESSION['error'] = "Cet organisateur préside déjà un autre club.";
            header('Location: dashboard_admin.php?page=add_club');
            exit();
        }
    }

   
    $insert_query = "INSERT INTO Club (nom_club, description, id_president) VALUES (?, ?, ?)";
    $insert_stmt = $pdo->prepare($insert_query);
    $insert_stmt->execute([$nom_club, $description, $id_president]);

    $_SESSION['success'] = "Club créé avec succès !";
    
 
    if (isset($_GET['from']) && $_GET['from'] === 'organisateurs') {
        header('Location: dashboard_admin.php?page=organisateurs');
    } else {
        header('Location: dashboard_admin.php?page=clubs');
    }
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header('Location: dashboard_admin.php?page=add_club');
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
    header('Location: dashboard_admin.php?page=add_club');
    exit();
}
?>