<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Debug (à retirer après test)
error_log("POST data: " . print_r($_POST, true));
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Vous devez être connecté.";
    header('Location: auth/login.php');
    exit();
}

// Vérifier que la requête est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mes_inscriptions.php');
    exit();
}

// Récupérer l'ID de l'inscription
$idInscription = $_POST['id_inscription'] ?? null;

if (!$idInscription) {
    $_SESSION['error_message'] = "ID d'inscription manquant.";
    header('Location: mes_inscriptions.php');
    exit();
}

try {
    // Vérifier que l'inscription appartient bien à l'utilisateur connecté
    $sql = "SELECT id_inscription, id_utilisateur, id_evenement 
            FROM Inscription 
            WHERE id_inscription = :id_inscription 
            AND id_utilisateur = :user_id 
            AND status = 'validée'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id_inscription' => $idInscription,
        'user_id' => $_SESSION['user_id']
    ]);
    
    $inscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inscription) {
        $_SESSION['error_message'] = "Inscription introuvable ou vous n'avez pas les droits pour l'annuler.";
        header('Location: mes_inscriptions.php');
        exit();
    }
    
    // Supprimer l'inscription
    $deleteSql = "DELETE FROM Inscription WHERE id_inscription = :id_inscription";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->execute(['id_inscription' => $idInscription]);
    
    $_SESSION['success_message'] = "Votre inscription a été annulée avec succès.";
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur lors de l'annulation : " . $e->getMessage();
}

header('Location: mes_inscriptions.php');
exit();
?>