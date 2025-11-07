<?php 
session_start();
require_once __DIR__ . '/../config/config.php';

// Récupération de l'ID de l'événement
$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($eventId === 0) {
    header('Location: evenements.php');
    exit();
}

// Récupération des détails de l'événement avec informations du club et de l'organisateur
$sql = "SELECT e.*, 
               c.nom_club, 
               u.nom as organisateur_nom, 
               u.prenom as organisateur_prenom,
               (SELECT COUNT(*) FROM Inscription WHERE id_evenement = e.id_evenement AND status = 'validée') as inscriptions_count
        FROM Evenement e
        LEFT JOIN Club c ON e.id_club = c.id_club
        LEFT JOIN Utilisateur u ON e.id_organisateur = u.id_utilisateur
        WHERE e.id_evenement = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: evenements.php');
    exit();
}

// Récupération des fichiers associés
$sqlFiles = "SELECT * FROM Fichier WHERE id_evenement = :id ORDER BY date_upload DESC";
$stmtFiles = $pdo->prepare($sqlFiles);
$stmtFiles->execute(['id' => $eventId]);
$fichiers = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

// Calcul des places restantes
$placesRestantes = $event['capacite_max'] - $event['inscriptions_count'];

// Vérifie si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']);

// Vérifier si l'utilisateur est déjà inscrit
$isRegistered = false;
if ($isLoggedIn) {
    $sqlCheck = "SELECT COUNT(*) FROM Inscription WHERE id_utilisateur = :user_id AND id_evenement = :event_id";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute(['user_id' => $_SESSION['user_id'], 'event_id' => $eventId]);
    $isRegistered = $stmtCheck->fetchColumn() > 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['titre']) ?> - CampusEvent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <style>
        body { background: #f8f9fa; }
        
        .hero-detail {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px 0 80px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero-detail::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 100vw solid transparent;
            border-right: 0 solid transparent;
            border-bottom: 40px solid #f8f9fa;
        }

        .detail-container {
            padding: 40px 0 80px;
        }

        .event-detail-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .event-image-container {
            position: relative;
            width: 100%;
            height: 400px;
            background: #f8f9fa;
        }

        .event-image-hero {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Badge du club sur l'image */
        .club-badge-detail {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 20px;
            border-radius: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 2;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            color: #667eea;
        }

        .event-detail-content {
            padding: 40px;
            text-align: center;
        }

        .event-detail-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2.2rem;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .event-badges {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .badge-custom {
            padding: 8px 16px;
            border-radius: 20px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-date {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .badge-location {
            background: #10b981;
            color: white;
        }

        .badge-price {
            background: #f59e0b;
            color: white;
        }

        .badge-places {
            background: #3b82f6;
            color: white;
        }

        .event-detail-description {
            font-family: 'Montserrat', sans-serif;
            color: #4b5563;
            font-size: 1.05rem;
            line-height: 1.8;
            margin-bottom: 30px;
            text-align: center;
        }

        .info-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }

        .info-section-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            font-family: 'Montserrat', sans-serif;
            color: #4b5563;
            justify-content: center;
        }

        .info-item i {
            color: #667eea;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .fichiers-section {
            margin-top: 30px;
            text-align: center;
        }

        .fichier-item {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .fichier-item:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .fichier-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .fichier-icon {
            font-size: 1.5rem;
            color: #667eea;
        }

        .fichier-name {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: #2c3e50;
        }

        .btn-download {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-register-detail {
            background: #10b981;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 30px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-register-detail:hover {
            background: #059669;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.35);
            color: white;
        }

        .btn-register-detail:disabled,
        .btn-register-detail.disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .btn-back {
            background: #6b7280;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 30px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #4b5563;
            transform: translateY(-3px);
            color: white;
        }

        .alert-registered {
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
            padding: 15px 20px;
            border-radius: 12px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .alert-full {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
            padding: 15px 20px;
            border-radius: 12px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .event-detail-title {
                font-size: 1.8rem;
            }
            .event-image-container {
                height: 250px;
            }
            .event-detail-content {
                padding: 25px;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn-register-detail, .btn-back {
                width: 100%;
                justify-content: center;
            }
            .club-badge-detail {
                top: 15px;
                left: 15px;
                font-size: 0.85rem;
                padding: 8px 16px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <section class="hero-detail">
        <div class="container" style="text-align: center;">
            <h1 style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 2rem; margin: 0;">
                Détails de l'événement
            </h1>
        </div>
    </section>

    <section class="detail-container">
        <div class="container">
            <div class="event-detail-card">
                <div class="event-image-container">
                    <!-- Badge du club -->
                    <?php if (!empty($event['nom_club'])): ?>
                        <div class="club-badge-detail">
                            <?= htmlspecialchars($event['nom_club']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <img src="<?= htmlspecialchars($event['affiche_url']) ?>" 
                         alt="<?= htmlspecialchars($event['titre']) ?>"
                         class="event-image-hero"
                         onerror="this.onerror=null; this.src='https://via.placeholder.com/1200x400/667eea/ffffff?text=Image+Manquante'">
                </div>
                
                <div class="event-detail-content">
                    <h1 class="event-detail-title"><?= htmlspecialchars($event['titre']) ?></h1>
                    
                    <div class="event-badges">
                        <span class="badge-custom badge-date">
                            <i class="far fa-calendar-alt"></i>
                            <?= date('d F Y', strtotime($event['date'])) ?>
                        </span>
                        <span class="badge-custom badge-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($event['lieu']) ?>
                        </span>
                        <span class="badge-custom badge-price">
                            <i class="fas fa-tag"></i>
                            <?= ($event['tarif'] == 0.00) ? 'Gratuit' : $event['tarif'].' DH' ?>
                        </span>
                        <span class="badge-custom badge-places">
                            <i class="fas fa-users"></i>
                            <?= $placesRestantes ?> / <?= $event['capacite_max'] ?> places disponibles
                        </span>
                    </div>

                    <div class="info-section">
                        <h3 class="info-section-title">
                            <i class="fas fa-info-circle"></i>
                            Description
                        </h3>
                        <p class="event-detail-description">
                            <?= nl2br(htmlspecialchars($event['description'])) ?>
                        </p>
                    </div>

                    <div class="info-section">
                        <h3 class="info-section-title">
                            <i class="fas fa-building"></i>
                            Informations organisateur
                        </h3>
                        <?php if ($event['nom_club']): ?>
                            <div class="info-item">
                                <i class="fas fa-star"></i>
                                <span><strong>Club organisateur :</strong> <?= htmlspecialchars($event['nom_club']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($event['organisateur_nom']): ?>
                            <div class="info-item">
                                <i class="fas fa-user-tie"></i>
                                <span><strong>Responsable :</strong> <?= htmlspecialchars($event['organisateur_prenom'] . ' ' . $event['organisateur_nom']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($fichiers) > 0): ?>
                        <div class="fichiers-section">
                            <h3 class="info-section-title">
                                <i class="fas fa-paperclip"></i>
                                Documents et fichiers
                            </h3>
                            <?php foreach ($fichiers as $fichier): ?>
                                <div class="fichier-item">
                                    <div class="fichier-info">
                                        <i class="fas fa-file fichier-icon"></i>
                                        <span class="fichier-name"><?= htmlspecialchars($fichier['nom_fichier']) ?></span>
                                    </div>
                                    <a href="<?= htmlspecialchars($fichier['chemin_fichier']) ?>" 
                                       class="btn-download" 
                                       download>
                                        <i class="fas fa-download"></i> Télécharger
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isRegistered): ?>
                        <div class="alert-registered">
                            <i class="fas fa-check-circle" style="font-size: 1.3rem;"></i>
                            Vous êtes déjà inscrit(e) à cet événement !
                        </div>
                    <?php elseif ($placesRestantes <= 0): ?>
                        <div class="alert-full">
                            <i class="fas fa-exclamation-circle" style="font-size: 1.3rem;"></i>
                            Désolé, cet événement est complet !
                        </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <?php if (!$isRegistered && $placesRestantes > 0): ?>
                            <?php if ($isLoggedIn): ?>
                                <a href="inscription_evenement.php?id=<?= $event['id_evenement'] ?>" class="btn-register-detail">
                                    <i class="fas fa-user-plus"></i>
                                    S'inscrire à cet événement
                                </a>
                            <?php else: ?>
                                <?php 
                                    $redirectUrl = 'inscription_evenement.php?id=' . $event['id_evenement'];
                                ?>
                                <a href="auth/login.php?redirect=<?= urlencode($redirectUrl) ?>" class="btn-register-detail">
                                    <i class="fas fa-user-plus"></i>
                                    S'inscrire à cet événement
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <a href="evenements.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i>
                            Retour aux événements
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>