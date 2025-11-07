<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: C:/xampp/htdocs/campusEvents/public/auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: admin_tous_evenements.php');
    exit();
}

$event_id = $_GET['id'];
$user_name = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];

$event_query = "SELECT 
    e.*, 
    u.nom as organisateur_nom, 
    u.prenom as organisateur_prenom,
    u.email as organisateur_email,
    c.nom_club,
    (SELECT COUNT(*) FROM Inscription WHERE id_evenement = e.id_evenement AND status = 'validée') as nb_participants,
    (SELECT COUNT(*) FROM Inscription WHERE id_evenement = e.id_evenement) as total_inscriptions
FROM Evenement e 
LEFT JOIN Utilisateur u ON e.id_organisateur = u.id_utilisateur
LEFT JOIN Club c ON e.id_club = c.id_club
WHERE e.id_evenement = ?";

$event_stmt = $pdo->prepare($event_query);
$event_stmt->execute([$event_id]);
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: admin_tous_evenements.php');
    exit();
}

$participants_query = "SELECT 
    i.id_inscription,
    i.status,
    i.status_paiment,
    i.date_inscription,
    u.id_utilisateur,
    u.nom,
    u.prenom,
    u.email,
    u.filiere
FROM Inscription i
JOIN Utilisateur u ON i.id_utilisateur = u.id_utilisateur
WHERE i.id_evenement = ?
ORDER BY i.date_inscription DESC";

$participants_stmt = $pdo->prepare($participants_query);
$participants_stmt->execute([$event_id]);
$participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);

$participants_stats_query = "SELECT 
    COUNT(*) as total_inscriptions,
    SUM(CASE WHEN i.status = 'validée' THEN 1 ELSE 0 END) as validees,
    SUM(CASE WHEN i.status = 'en attente' THEN 1 ELSE 0 END) as en_attente,
    SUM(CASE WHEN i.status = 'refusée' THEN 1 ELSE 0 END) as refusees,
    SUM(CASE WHEN i.status_paiment = 'payé' THEN 1 ELSE 0 END) as payes
FROM Inscription i
WHERE i.id_evenement = ?";

$participants_stats_stmt = $pdo->prepare($participants_stats_query);
$participants_stats_stmt->execute([$event_id]);
$participants_stats = $participants_stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Événement - Admin Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>

        .detail-section {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .participant-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .participant-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }
    </style>
</head>
<body>
  
    
    <div class="main-content">
        <div class="top-bar">
            <div>
                <button class="btn-action btn-view" onclick="history.back()" style="margin-bottom: 1rem;">
                    <i class="fas fa-arrow-left"></i> Retour
                </button>
                <h1 class="page-title"> Détails de l'Événement</h1>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['user_prenom'], 0, 1)) ?>
                </div>
                <span style="font-weight: 600; color: #2c3e50;"><?= htmlspecialchars($user_name) ?></span>
            </div>
        </div>

        <div class="detail-section">
            <div class="row">
                <div class="col-md-8">
                    <h2 style="font-family: 'Poppins', sans-serif; color: #2c3e50; margin-bottom: 1rem;">
                        <?= htmlspecialchars($event['titre']) ?>
                    </h2>
                    <p style="color: #6c757d; line-height: 1.6; margin-bottom: 2rem;">
                        <?= htmlspecialchars($event['description']) ?>
                    </p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h4 style="color: #2c3e50; margin-bottom: 1rem;"> Informations</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                                <div><strong>Date:</strong> <?= date('d/m/Y', strtotime($event['date'])) ?></div>
                                <div><strong>Lieu:</strong> <?= htmlspecialchars($event['lieu']) ?></div>
                                <div><strong>Capacité:</strong> <?= $event['capacite_max'] ?> personnes</div>
                                <div><strong>Tarif:</strong> <?= ($event['tarif'] == 0.00) ? 'Gratuit' : number_format($event['tarif'], 2) . ' MAD' ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4 style="color: #2c3e50; margin-bottom: 1rem;"> Organisateur</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                                <div><strong>Nom:</strong> <?= htmlspecialchars($event['organisateur_prenom'] . ' ' . $event['organisateur_nom']) ?></div>
                                <div><strong>Email:</strong> <?= htmlspecialchars($event['organisateur_email']) ?></div>
                                <?php if($event['nom_club']): ?>
                                    <div><strong>Club:</strong> <?= htmlspecialchars($event['nom_club']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <?php if($event['affiche_url']): ?>
                        <img src="../<?= htmlspecialchars($event['affiche_url']) ?>" 
                             alt="<?= htmlspecialchars($event['titre']) ?>" 
                             style="width: 100%; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <?php else: ?>
                        <div style="width: 100%; height: 200px; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); border-radius: 15px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                            Image non disponible
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h3 style="font-family: 'Poppins', sans-serif; color: #2c3e50; margin-bottom: 1.5rem;">
                 Statistiques des Participants
            </h3>
            
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card" style="border-left-color: #4facfe;">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $participants_stats['total_inscriptions'] ?></h3>
                        <p>Total Inscriptions</p>
                    </div>
                </div>
                <div class="stat-card" style="border-left-color: #43e97b;">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $participants_stats['validees'] ?></h3>
                        <p>Validées</p>
                    </div>
                </div>
                <div class="stat-card" style="border-left-color: #fa709a;">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $participants_stats['en_attente'] ?></h3>
                        <p>En Attente</p>
                    </div>
                </div>
                <div class="stat-card" style="border-left-color: #ff6b6b;">
                    <div class="stat-icon red">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $participants_stats['refusees'] ?></h3>
                        <p>Refusées</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h3 style="font-family: 'Poppins', sans-serif; color: #2c3e50; margin-bottom: 1.5rem;">
                 Liste des Participants
            </h3>
            
            <?php if(empty($participants)): ?>
                <p style="text-align: center; color: #6c757d; padding: 2rem;">
                    Aucun participant pour cet événement.
                </p>
            <?php else: ?>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach($participants as $participant): ?>
                        <div class="participant-item">
                            <div class="participant-avatar">
                                <?= strtoupper(substr($participant['prenom'], 0, 1) . substr($participant['nom'], 0, 1)) ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #2c3e50;">
                                    <?= htmlspecialchars($participant['prenom'] . ' ' . $participant['nom']) ?>
                                </div>
                                <div style="color: #6c757d; font-size: 0.9rem;">
                                    <?= htmlspecialchars($participant['email']) ?>
                                    <?php if($participant['filiere']): ?>
                                        • <?= htmlspecialchars($participant['filiere']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <span class="badge" style="background: <?= $participant['status'] === 'validée' ? '#10b981' : ($participant['status'] === 'en attente' ? '#f59e0b' : '#ef4444') ?>; color: white;">
                                    <?= $participant['status'] ?>
                                </span>
                                <span class="badge" style="background: <?= $participant['status_paiment'] === 'payé' ? '#10b981' : '#ef4444' ?>; color: white;">
                                    <?= $participant['status_paiment'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>