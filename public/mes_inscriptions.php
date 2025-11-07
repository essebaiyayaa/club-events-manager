<?php 
session_start();
require_once __DIR__ . '/../config/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php?redirect=mes_inscriptions.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Récupération des événements auxquels l'utilisateur est inscrit avec status 'validée'
$sql = "SELECT 
            e.id_evenement, 
            e.titre, 
            e.description, 
            e.date, 
            e.lieu, 
            e.tarif, 
            e.affiche_url,
            c.nom_club,
            i.id_inscription,
            i.date_inscription,
            i.status_paiment
        FROM Inscription i
        INNER JOIN Evenement e ON i.id_evenement = e.id_evenement
        LEFT JOIN Club c ON e.id_club = c.id_club
        WHERE i.id_utilisateur = :user_id 
        AND i.status = 'validée'
        ORDER BY e.date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $userId]);
$inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusEvent - Mes inscriptions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <style>
        .hero-inscriptions {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 80px 0 120px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero-inscriptions::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 100vw solid transparent;
            border-right: 0 solid transparent;
            border-bottom: 60px solid #f8f9fa;
        }
        .hero-inscriptions h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        .inscriptions-container { padding: 60px 0; background: #f8f9fa; }
        .event-card {
            background: white; border-radius: 20px; overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease; border: 2px solid transparent;
            margin-bottom: 30px; display: flex; flex-direction: row; height: 220px;
            position: relative;
        }
        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.25);
            border-color: #667eea;
        }
        .event-image-wrapper { 
            width: 280px; height: 100%; overflow: hidden; 
            flex-shrink: 0; position: relative;
        }
        .event-image-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .event-card:hover .event-image-wrapper img { transform: scale(1.08); }

        /* Badge du club sur l'image */
        .club-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 16px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            z-index: 2;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            color: #667eea;
        }

        .event-content {
            flex: 1;
            padding: 25px 30px;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        .event-info { flex: 1; }
        .event-title {
            font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.35rem;
            color: #2c3e50; margin-bottom: 12px; line-height: 1.3;
        }
        .event-description {
            font-family: 'Montserrat', sans-serif; color: #6c757d;
            font-size: 0.95rem; line-height: 1.6; margin-bottom: 15px;
            overflow: hidden; text-overflow: ellipsis;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }
        .event-meta {
            display: flex; align-items: center; gap: 20px;
            font-family: 'Montserrat', sans-serif; font-size: 0.9rem; color: #6c757d; flex-wrap: wrap;
        }
        .event-meta-item { display: flex; align-items: center; gap: 6px; }
        .event-meta-item i { color: #667eea; font-size: 1rem; }

        .badge-payment {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 14px;
            border-radius: 20px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.8rem;
            z-index: 10;
        }
        .badge-paid {
            background: #10b981;
            color: white;
        }
        .badge-unpaid {
            background: #f59e0b;
            color: white;
        }

        .event-actions { display: flex; gap: 12px; align-items: center; flex-shrink: 0; }
        .btn-details, .btn-cancel {
            border: none; padding: 10px 24px; border-radius: 25px;
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: 0.9rem; text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center; transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-details {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-details:hover {
            transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
        }
        .btn-cancel {
            background: #ef4444; color: white;
        }
        .btn-cancel:hover {
            background: #dc2626; transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.35);
        }

        .no-inscriptions { 
            text-align: center; padding: 60px 20px; color: #6c757d; 
            font-family: 'Montserrat', sans-serif; font-size: 1.1rem; 
        }
        .no-inscriptions i { 
            font-size: 4rem; color: #667eea; margin-bottom: 20px; opacity: 0.5; 
        }

        /* Alert messages */
        .alert-custom {
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 30px;
            font-family: 'Montserrat', sans-serif;
            border: none;
        }

        @media (max-width: 991px) {
            .event-card { flex-direction: column; height: auto; }
            .event-image-wrapper { width: 100%; height: 200px; }
            .event-content { flex-direction: column; align-items: flex-start; }
            .event-actions { width: 100%; justify-content: space-between; }
            .btn-details, .btn-cancel { width: 48%; }
            .badge-payment { top: 10px; right: 10px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <section class="hero-inscriptions">
        <div class="container">
            <h1>Mes inscriptions validées</h1>
        </div>
    </section>

    <section class="inscriptions-container">
        <div class="container">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (count($inscriptions) > 0): ?>
                <?php foreach ($inscriptions as $event): ?>
                    <div class="event-card">
                        <?php if ($event['tarif'] > 0): ?>
                            <div class="badge-payment <?= $event['status_paiment'] === 'payé' ? 'badge-paid' : 'badge-unpaid' ?>">
                                <i class="fas <?= $event['status_paiment'] === 'payé' ? 'fa-check' : 'fa-clock' ?>"></i>
                                <?= $event['status_paiment'] === 'payé' ? 'Payé' : 'Non payé' ?>
                            </div>
                        <?php endif; ?>

                        <div class="event-image-wrapper">
                            <!-- Badge du club -->
                            <?php if (!empty($event['nom_club'])): ?>
                                <div class="club-badge">
                                    <?= htmlspecialchars($event['nom_club']) ?>
                                </div>
                            <?php endif; ?>

                            <img src="<?= htmlspecialchars($event['affiche_url']) ?>" 
                                 alt="<?= htmlspecialchars($event['titre']) ?>"
                                 onerror="this.onerror=null; this.src='https://via.placeholder.com/280x220/667eea/ffffff?text=Image+Manquante'">
                        </div>
                        
                        <div class="event-content">
                            <div class="event-info">
                                <h2 class="event-title"><?= htmlspecialchars($event['titre']) ?></h2>
                                <p class="event-description"><?= htmlspecialchars($event['description']) ?></p>
                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <span><?= date('d F Y', strtotime($event['date'])) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= htmlspecialchars($event['lieu']) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-tag"></i>
                                        <span>Tarif : <?= ($event['tarif'] == 0.00) ? 'Gratuit' : $event['tarif'].' DH' ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-user-check"></i>
                                        <span>Inscrit le <?= date('d/m/Y', strtotime($event['date_inscription'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="event-actions">
                                <a href="details.php?id=<?= $event['id_evenement'] ?>" class="btn-details">
                                    Voir les détails
                                </a>
                                <button type="button" class="btn-cancel" 
                                        onclick="confirmCancel(<?= $event['id_inscription'] ?>, '<?= htmlspecialchars(addslashes($event['titre'])) ?>')">
                                    Annuler
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-inscriptions">
                    <i class="far fa-calendar-times"></i>
                    <p>Vous n'avez aucune inscription validée pour le moment.</p>
                    <a href="evenements.php" class="btn-details mt-3" style="display: inline-flex;">
                        Découvrir les événements
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>

    <!-- Modal de confirmation d'annulation -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none;">
                <div class="modal-header" style="border-bottom: none; padding: 30px 30px 0;">
                    <h5 class="modal-title" style="font-family: 'Poppins', sans-serif; font-weight: 700; color: #2c3e50;">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Confirmer l'annulation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 20px 30px; font-family: 'Montserrat', sans-serif; color: #6c757d;">
                    Êtes-vous sûr de vouloir annuler votre inscription à l'événement "<span id="eventTitle"></span>" ?
                    <br><br>
                    <strong style="color: #ef4444;">Cette action est irréversible.</strong>
                </div>
                <div class="modal-footer" style="border-top: none; padding: 0 30px 30px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
                            style="border-radius: 25px; padding: 10px 24px; font-family: 'Montserrat', sans-serif; font-weight: 600;">
                        Non, garder
                    </button>
                    <form method="POST" action="annuler_inscription.php" id="cancelForm" style="display: inline;">
                        <input type="hidden" name="id_inscription" id="inscriptionId">
                        <button type="submit" class="btn btn-danger" 
                                style="border-radius: 25px; padding: 10px 24px; font-family: 'Montserrat', sans-serif; font-weight: 600; background: #ef4444; border: none;">
                            Oui, annuler
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmCancel(inscriptionId, eventTitle) {
            document.getElementById('inscriptionId').value = inscriptionId;
            document.getElementById('eventTitle').textContent = eventTitle;
            const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
            modal.show();
        }
    </script>
</body>
</html>