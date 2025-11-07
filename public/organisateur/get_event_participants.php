<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$id_evenement = $_GET['id_evenement'] ?? null;

if (!$id_evenement) {
    echo json_encode(['success' => false, 'message' => 'ID événement manquant']);
    exit();
}

try {
    $check_query = "SELECT id_evenement FROM Evenement 
                    WHERE id_evenement = ? AND id_organisateur = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$id_evenement, $_SESSION['user_id']]);
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Événement non trouvé']);
        exit();
    }
    $query = "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, i.id_inscription
              FROM Inscription i
              JOIN Utilisateur u ON i.id_utilisateur = u.id_utilisateur
              WHERE i.id_evenement = ? AND i.status = 'validée'
              ORDER BY u.nom, u.prenom";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id_evenement]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'participants' => $participants
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>