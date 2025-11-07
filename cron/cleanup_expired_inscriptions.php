<?php
/**
 * Script de nettoyage des inscriptions expirées
 * À exécuter via CRON toutes les heures : 0 * * * * /usr/bin/php /path/to/cleanup_expired_inscriptions.php
 */

require_once __DIR__ . '/../config/config.php';

try {
    // Supprimer toutes les inscriptions en attente expirées
    $stmt = $pdo->prepare("DELETE FROM Inscription_Pending WHERE expires_at < NOW()");
    $stmt->execute();
    
    $deleted_count = $stmt->rowCount();
    
    // Logger le résultat
    error_log("Cleanup: {$deleted_count} inscriptions expirées supprimées - " . date('Y-m-d H:i:s'));
    
    echo "✅ Nettoyage terminé : {$deleted_count} inscriptions expirées supprimées.\n";
    
} catch (PDOException $e) {
    error_log("Erreur de nettoyage : " . $e->getMessage());
    echo "❌ Erreur lors du nettoyage : " . $e->getMessage() . "\n";
}
?>