<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email_functions.php';

$token = $_GET['token'] ?? '';

$success = false;
$error_message = '';
$event = null;

if (empty($token)) {
    $error_message = "Lien de confirmation invalide.";
} else {
    try {
        // Rechercher l'inscription en attente avec vérification d'expiration DANS SQL
        $stmt = $pdo->prepare("
            SELECT ip.*, e.titre, e.date, e.lieu, e.tarif, e.capacite_max,
                   u.nom, u.prenom, u.email,
                   (SELECT COUNT(*) FROM Inscription WHERE id_evenement = ip.id_evenement AND status != 'refusée') as inscrits,
                   TIMESTAMPDIFF(MINUTE, NOW(), ip.expires_at) as minutes_left
            FROM Inscription_Pending ip
            INNER JOIN Evenement e ON ip.id_evenement = e.id_evenement
            INNER JOIN Utilisateur u ON ip.id_utilisateur = u.id_utilisateur
            WHERE ip.confirmation_token = ?
        ");
        $stmt->execute([$token]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pending) {
            $error_message = "Ce lien de confirmation est invalide ou a déjà été utilisé.";
        } else {
            // Debug logs
            error_log("Minutes restantes: " . $pending['minutes_left']);
            error_log("Expires at: " . $pending['expires_at']);
            
            // Vérifier si le token n'a pas expiré (vérification côté serveur MySQL)
            if ($pending['minutes_left'] < 0) {
                // Token expiré - supprimer l'inscription temporaire
                $stmt = $pdo->prepare("DELETE FROM Inscription_Pending WHERE confirmation_token = ?");
                $stmt->execute([$token]);
                
                $error_message = "Le lien de confirmation a expiré (1 heure maximum). Veuillez vous réinscrire.";
            } else {
                // Vérifier s'il reste des places
                $places_restantes = $pending['capacite_max'] - $pending['inscrits'];
                if ($places_restantes <= 0) {
                    // Plus de places disponibles
                    $stmt = $pdo->prepare("DELETE FROM Inscription_Pending WHERE confirmation_token = ?");
                    $stmt->execute([$token]);
                    
                    $error_message = "Désolé, il n'y a plus de places disponibles pour cet événement.";
                } else {
                    // Vérifier que l'utilisateur n'est pas déjà inscrit (double vérification)
                    $stmt = $pdo->prepare("
                        SELECT id_inscription FROM Inscription 
                        WHERE id_utilisateur = ? AND id_evenement = ?
                    ");
                    $stmt->execute([$pending['id_utilisateur'], $pending['id_evenement']]);
                    
                    if ($stmt->fetch()) {
                        // Déjà inscrit
                        $stmt = $pdo->prepare("DELETE FROM Inscription_Pending WHERE confirmation_token = ?");
                        $stmt->execute([$token]);
                        
                        $error_message = "Vous êtes déjà inscrit à cet événement.";
                    } else {
                        // TOUT EST OK - Créer l'inscription définitive
                        $pdo->beginTransaction();
                        
                        try {
                            // Déterminer le statut de paiement
                            $status_paiement = ($pending['tarif'] == 0) ? 'payé' : 'non payé';
                            
                            // Insérer l'inscription dans la table définitive avec statut 'en attente'
                            $stmt = $pdo->prepare("
                                INSERT INTO Inscription (id_utilisateur, id_evenement, status, status_paiment, date_inscription) 
                                VALUES (?, ?, 'validée', ?, NOW())
                            ");
                            $stmt->execute([
                                $pending['id_utilisateur'], 
                                $pending['id_evenement'], 
                                $status_paiement
                            ]);
                            
                            $id_inscription = $pdo->lastInsertId();
                            
                            // Supprimer l'inscription temporaire
                            $stmt = $pdo->prepare("DELETE FROM Inscription_Pending WHERE confirmation_token = ?");
                            $stmt->execute([$token]);
                            
                            $pdo->commit();
                            
                            // Préparer les données pour l'affichage
                            $event = [
                                'titre' => $pending['titre'],
                                'date' => $pending['date'],
                                'lieu' => $pending['lieu'],
                                'tarif' => $pending['tarif']
                            ];
                            
                            // Envoyer un email de confirmation finale (optionnel)
                            $user = [
                                'nom' => $pending['nom'],
                                'prenom' => $pending['prenom'],
                                'email' => $pending['email']
                            ];
                            sendInscriptionValidatedEmail($user, $event);
                            
                            $success = true;
                            
                            // Log de succès
                            error_log("Inscription confirmée avec succès pour l'utilisateur " . $pending['id_utilisateur']);
                            
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            error_log("Erreur lors de la confirmation : " . $e->getMessage());
                            $error_message = "Une erreur s'est produite lors de la confirmation. Veuillez réessayer.";
                        }
                    }
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erreur de base de données : " . $e->getMessage());
        $error_message = "Une erreur technique s'est produite. Veuillez réessayer plus tard.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation d'inscription - CampusEvent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Montserrat', sans-serif;
            padding: 20px;
        }
        .confirmation-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }
        .success-icon { color: #10b981; animation: bounce 1s ease; }
        .error-icon { color: #ef4444; }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }
        .card-content {
            padding: 50px 40px;
            text-align: center;
        }
        .card-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .card-message {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .event-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
            border-left: 4px solid #667eea;
        }
        .event-detail-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        .event-detail-item:last-child { margin-bottom: 0; }
        .event-detail-item i {
            color: #667eea;
            font-size: 1.1rem;
            width: 30px;
        }
        .event-detail-item strong {
            color: #2c3e50;
            margin-right: 8px;
        }
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            text-decoration: none;
            display: inline-block;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-retry {
            background: #ef4444;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }
        .btn-retry:hover {
            background: #dc2626;
            transform: translateY(-2px);
            color: white;
        }
        .success-message {
            background: #d1fae5;
            border: 2px solid #10b981;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }
        .success-message p {
            margin: 0;
            color: #065f46;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="confirmation-card">
        <div class="card-content">
            <?php if ($success): ?>
                <!-- Message de succès -->
                <div class="card-icon success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="card-title">🎉 Inscription confirmée !</h1>
                <p class="card-message">
                    Félicitations ! Votre inscription a été validée avec succès. 
                    Vous êtes maintenant officiellement inscrit à cet événement.
                </p>

                <div class="success-message">
                    <p>✅ Votre place est réservée et un email de confirmation vous a été envoyé.</p>
                </div>

                <?php if ($event): ?>
                <div class="event-details">
                    <h4 style="color: #2c3e50; font-family: 'Poppins', sans-serif; font-weight: 700; margin-bottom: 20px;">
                        <i class="fas fa-calendar-check me-2"></i>Détails de l'événement
                    </h4>
                    <div class="event-detail-item">
                        <i class="fas fa-tag"></i>
                        <strong>Événement :</strong>
                        <span><?= htmlspecialchars($event['titre']) ?></span>
                    </div>
                    <div class="event-detail-item">
                        <i class="far fa-calendar"></i>
                        <strong>Date :</strong>
                        <span><?= date('d/m/Y à H:i', strtotime($event['date'])) ?></span>
                    </div>
                    <div class="event-detail-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <strong>Lieu :</strong>
                        <span><?= htmlspecialchars($event['lieu']) ?></span>
                    </div>
                    <div class="event-detail-item">
                        <i class="fas fa-money-bill-wave"></i>
                        <strong>Tarif :</strong>
                        <span><?= $event['tarif'] == 0 ? 'Gratuit' : number_format($event['tarif'], 2) . ' DH' ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <p style="color: #10b981; font-weight: 600; margin: 20px 0; font-size: 1.05rem;">
                    <i class="fas fa-heart me-2"></i>
                    Nous avons hâte de vous voir à cet événement !
                </p>

                <a href="evenements.php" class="btn-home">
                    <i class="fas fa-calendar-alt me-2"></i>Voir tous les événements
                </a>

            <?php else: ?>
                <!-- Message d'erreur -->
                <div class="card-icon error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h1 class="card-title">❌ Erreur de confirmation</h1>
                <p class="card-message">
                    <?= htmlspecialchars($error_message) ?>
                </p>

                <a href="evenements.php" class="btn-home">
                    <i class="fas fa-arrow-left me-2"></i>Retour aux événements
                </a>
                
                <a href="evenements.php" class="btn-retry">
                    <i class="fas fa-redo me-2"></i>Voir les événements disponibles
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>