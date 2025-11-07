<?php 
session_start();
require_once __DIR__ . '/../config/config.php';

// Récupération des événements depuis la BD avec le nom du club
$sql = "SELECT 
            e.id_evenement, 
            e.titre, 
            e.description, 
            e.date, 
            e.lieu, 
            e.tarif, 
            e.affiche_url,
            c.nom_club
        FROM Evenement e
        LEFT JOIN Club c ON e.id_club = c.id_club
        ORDER BY e.date ASC";
$stmt = $pdo->query($sql);
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vérifie si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusEvent - Événements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <style>
        .hero-events {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 80px 0 120px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero-events::after {
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
        .hero-events h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        .events-container { padding: 60px 0; background: #f8f9fa; }
        .event-card {
            background: white; border-radius: 20px; overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease; border: 2px solid transparent;
            margin-bottom: 30px; display: flex; flex-direction: row; height: 220px;
        }
        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.25);
            border-color: #667eea;
        }
        .event-image-wrapper { width: 280px; height: 100%; overflow: hidden; flex-shrink: 0; position: relative; }
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

        /* Alignement horizontal */
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

        .event-actions { display: flex; gap: 12px; align-items: center; flex-shrink: 0; }
        .btn-details, .btn-register {
            border: none; padding: 10px 24px; border-radius: 25px;
            font-family: 'Montserrat', sans-serif; font-weight: 600;
            font-size: 0.9rem; text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center; transition: all 0.3s ease;
        }
        .btn-details {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-details:hover {
            transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
        }
        .btn-register {
            background: #10b981; color: white;
        }
        .btn-register:hover {
            background: #059669; transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
        }

        .no-events { text-align: center; padding: 60px 20px; color: #6c757d; font-family: 'Montserrat', sans-serif; font-size: 1.1rem; }
        .no-events i { font-size: 4rem; color: #667eea; margin-bottom: 20px; opacity: 0.5; }

        @media (max-width: 991px) {
            .event-card { flex-direction: column; height: auto; }
            .event-image-wrapper { width: 100%; height: 200px; }
            .event-content { flex-direction: column; align-items: flex-start; }
            .event-actions { width: 100%; justify-content: space-between; }
            .btn-details, .btn-register { width: 48%; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <section class="hero-events">
        <div class="container">
            <h1>Découvrez les événements du campus !</h1>
        </div>
    </section>

    <section class="events-container">
        <div class="container">
            <?php if (count($evenements) > 0): ?>
                <?php foreach ($evenements as $event): ?>
                    <div class="event-card">
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
                                    <div class="event-meta-item"><i class="far fa-calendar-alt"></i><span><?= date('d F Y', strtotime($event['date'])) ?></span></div>
                                    <div class="event-meta-item"><i class="fas fa-map-marker-alt"></i><span><?= htmlspecialchars($event['lieu']) ?></span></div>
                                    <div class="event-meta-item"><i class="fas fa-tag"></i><span>Tarif : <?= ($event['tarif'] == 0.00) ? 'Gratuit' : $event['tarif'].' DH' ?></span></div>
                                </div>
                            </div>
                            
                            <div class="event-actions">
                                <a href="details.php?id=<?= $event['id_evenement'] ?>" class="btn-details">
                                    Voir les détails
                                </a>
                                <?php if ($isLoggedIn): ?>
                                    <a href="inscription_evenement.php?id=<?= htmlspecialchars($event['id_evenement']) ?>" class="btn-register">
                                        Inscrire
                                    </a>
                                <?php else: ?>
                                    <?php 
                                        $redirectUrl = 'inscription_evenement.php?id=' . $event['id_evenement'];
                                    ?>
                                    <a href="auth/login.php?redirect=<?= urlencode($redirectUrl) ?>" class="btn-register">
                                        Inscrire
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-events">
                    <i class="far fa-calendar-times"></i>
                    <p>Aucun événement n'est disponible pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>