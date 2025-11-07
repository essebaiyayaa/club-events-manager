<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['payment'])) {
    $_SESSION['error'] = "Paramètres manquants.";
    header('Location: dashboard_organisateur.php?page=participants');
    exit();
}

$inscription_id = (int)$_GET['id'];
$new_payment = $_GET['payment'];
$verify_query = "SELECT i.* FROM Inscription i 
                 JOIN Evenement e ON i.id_evenement = e.id_evenement 
                 WHERE i.id_inscription = ? AND e.id_organisateur = ?";
$verify_stmt = $pdo->prepare($verify_query);
$verify_stmt->execute([$inscription_id, $_SESSION['user_id']]);

if (!$verify_stmt->fetch()) {
    $_SESSION['error'] = "Inscription non trouvée ou accès non autorisé.";
    header('Location: dashboard_organisateur.php?page=participants');
    exit();
}

try {
    $update_query = "UPDATE Inscription SET status_paiment = ? WHERE id_inscription = ?";
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute([$new_payment, $inscription_id]);
    
    $_SESSION['success'] = "Le statut de paiement a été mis à jour avec succès.";
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
}

header('Location: dashboard_organisateur.php?page=participants');
exit();
?>