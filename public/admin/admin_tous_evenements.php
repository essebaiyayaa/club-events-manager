<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: C:/xampp/htdocs/campusEvents/public/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];

$all_events_query = "SELECT 
    e.*, 
    u.nom as organisateur_nom, 
    u.prenom as organisateur_prenom,
    u.email as organisateur_email,
    c.nom_club,
    (SELECT COUNT(*) FROM Inscription WHERE id_evenement = e.id_evenement AND status = 'validée') as nb_participants
FROM Evenement e 
LEFT JOIN Utilisateur u ON e.id_organisateur = u.id_utilisateur
LEFT JOIN Club c ON e.id_club = c.id_club
ORDER BY e.date DESC";

$all_events_stmt = $pdo->prepare($all_events_query);
$all_events_stmt->execute();
$tous_evenements = $all_events_stmt->fetchAll(PDO::FETCH_ASSOC);

$global_stats_query = "SELECT 
    COUNT(DISTINCT e.id_evenement) as total_events,
    COUNT(DISTINCT i.id_inscription) as total_participants,
    SUM(CASE WHEN e.date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_events,
    COUNT(DISTINCT e.id_organisateur) as total_organisateurs,
    AVG(e.tarif) as avg_tarif,
    SUM(e.tarif) as total_revenue
FROM Evenement e
LEFT JOIN Inscription i ON e.id_evenement = i.id_evenement AND i.status = 'validée'";

$global_stats_stmt = $pdo->prepare($global_stats_query);
$global_stats_stmt->execute();
$global_stats = $global_stats_stmt->fetch(PDO::FETCH_ASSOC);

$popular_events_query = "SELECT 
    e.titre, 
    e.date, 
    COUNT(i.id_inscription) as participants,
    u.nom as organisateur_nom,
    u.prenom as organisateur_prenom
FROM Evenement e
LEFT JOIN Inscription i ON e.id_evenement = i.id_evenement AND i.status = 'validée'
LEFT JOIN Utilisateur u ON e.id_organisateur = u.id_utilisateur
GROUP BY e.id_evenement
ORDER BY participants DESC
LIMIT 5";

$popular_events_stmt = $pdo->prepare($popular_events_query);
$popular_events_stmt->execute();
$popular_events = $popular_events_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tous les Événements - Admin Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #f8f9fa;
            overflow-x: hidden;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #ff6b6b 0%, #ee5a6f 100%);
            padding: 2rem 0;
            z-index: 1000;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }

        .sidebar-logo {
            text-align: center;
            margin-bottom: 2rem;
            padding: 0 1.5rem;
        }

        .logo-text {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.5rem;
            color: white;
            line-height: 1.2;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-item {
            margin: 0.3rem 0;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .sidebar-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .sidebar-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            font-weight: 600;
        }

        .sidebar-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
        }

        .sidebar-icon {
            width: 24px;
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .logout-btn {
            position: absolute;
            bottom: 2rem;
            left: 1.5rem;
            right: 1.5rem;
            padding: 0.8rem;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            color: #2c3e50;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s ease;
            border-left: 5px solid;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 50%);
            border-radius: 0 20px 0 0;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-icon.red {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-content h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2.2rem;
            color: #2c3e50;
            margin: 0;
            line-height: 1;
        }

        .stat-content p {
            color: #6c757d;
            font-size: 0.95rem;
            margin: 0.3rem 0 0 0;
            font-weight: 500;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .event-card-horizontal {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: flex;
            flex-direction: row;
            height: 280px;
        }

        .event-card-horizontal:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(255, 107, 107, 0.25);
            border-color: #ff6b6b;
        }

        .event-image-wrapper {
            width: 280px;
            height: 100%;
            overflow: hidden;
            flex-shrink: 0;
            position: relative;
        }

        .event-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .event-card-horizontal:hover .event-image-wrapper img {
            transform: scale(1.08);
        }

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
            color: #ff6b6b;
        }

        .organisateur-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(255, 107, 107, 0.95);
            padding: 6px 14px;
            border-radius: 20px;
            z-index: 2;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
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

        .event-info {
            flex: 1;
        }

        .event-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .event-description {
            font-family: 'Montserrat', sans-serif;
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 15px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
            color: #6c757d;
            font-size: 0.95rem;
        }

        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .event-meta-item i {
            width: 20px;
            color: #ff6b6b;
        }

        .event-actions {
            display: flex;
            gap: 0.8rem;
        }

        .btn-action {
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-view {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        .popular-events {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .event-rank {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .event-rank:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .event-rank:last-child {
            border-bottom: none;
        }

        .rank-number {
            width: 35px;
            height: 35px;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .rank-1 { background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0 0%, #A9A9A9 100%); }
        .rank-3 { background: linear-gradient(135deg, #CD7F32 0%, #A0522D 100%); }
        .rank-4, .rank-5 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

        .event-rank-info {
            flex: 1;
        }

        .event-rank-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }

        .event-rank-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #ff6b6b;
            display: inline-block;
        }

        @media (max-width: 991px) {
            .event-card-horizontal {
                flex-direction: column;
                height: auto;
            }

            .event-image-wrapper {
                width: 100%;
                height: 200px;
            }

            .event-content {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .sidebar-link span {
                display: none;
            }

            .sidebar-logo {
                font-size: 1rem;
            }

            .logout-btn span {
                display: none;
            }

            .main-content {
                margin-left: 70px;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-text">
                <div>Campus</div>
                <div>Event</div>
                <div style="font-size: 0.7rem; font-weight: 600; opacity: 0.9;">ADMIN</div>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="dashboard_admin.php?page=stats" class="sidebar-link">
                    <i class="sidebar-icon fas fa-chart-line"></i>
                    <span>Statistiques</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard_admin.php?page=organisateurs" class="sidebar-link">
                    <i class="sidebar-icon fas fa-users-cog"></i>
                    <span>Organisateurs</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="dashboard_admin.php?page=clubs" class="sidebar-link">
                    <i class="sidebar-icon fas fa-users"></i>
                    <span>Clubs</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="admin_tous_evenements.php" class="sidebar-link active">
                    <i class="sidebar-icon fas fa-calendar-alt"></i>
                    <span>Tous les Événements</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="change_admin_password.php" class="sidebar-link">
                    <i class="sidebar-icon fas fa-lock"></i>
                    <span>Mot de passe</span>
                </a>
            </li>
        </ul>

        <button class="logout-btn" onclick="location.href='../auth/logout.php'">
            <i class="fas fa-sign-out-alt"></i>
            <span> Déconnexion</span>
        </button>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title"> Tous les Événements</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['user_prenom'], 0, 1)) ?>
                </div>
                <span style="font-weight: 600; color: #2c3e50;"><?= htmlspecialchars($user_name) ?></span>
            </div>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success" style="border-radius: 15px; margin-bottom: 2rem; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border: none; padding: 1.25rem; box-shadow: 0 5px 20px rgba(67, 233, 123, 0.3);">
                <i class="fas fa-check-circle me-2"></i>
                <?= $_SESSION['success'] ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="border-radius: 15px; margin-bottom: 2rem; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; border: none; padding: 1.25rem; box-shadow: 0 5px 20px rgba(255, 107, 107, 0.3);">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card" style="border-left-color: #ff6b6b;">
                <div class="stat-icon red">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $global_stats['total_events'] ?></h3>
                    <p>Total Événements</p>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: #4facfe;">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $global_stats['total_participants'] ?></h3>
                    <p>Total Participants</p>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: #43e97b;">
                <div class="stat-icon green">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $global_stats['upcoming_events'] ?></h3>
                    <p>Événements à Venir</p>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: #fa709a;">
                <div class="stat-icon orange">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $global_stats['total_organisateurs'] ?></h3>
                    <p>Organisateurs</p>
                </div>
            </div>
        </div>

        <div class="popular-events">
            <h3 class="section-title">🏆 Événements les Plus Populaires</h3>
            <?php if(empty($popular_events)): ?>
                <p style="text-align: center; color: #6c757d; padding: 2rem;">
                    Aucun événement avec des participants pour le moment.
                </p>
            <?php else: ?>
                <?php foreach($popular_events as $index => $event): ?>
                    <div class="event-rank">
                        <div class="rank-number <?= 'rank-' . ($index + 1) ?>">
                            <?= $index + 1 ?>
                        </div>
                        <div class="event-rank-info">
                            <div class="event-rank-title"><?= htmlspecialchars($event['titre']) ?></div>
                            <div class="event-rank-meta">
                                <?= date('d/m/Y', strtotime($event['date'])) ?> • 
                                <?= $event['participants'] ?> participants •
                                Organisé par <?= htmlspecialchars($event['organisateur_prenom'] . ' ' . $event['organisateur_nom']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="events-list">
            <h3 class="section-title"> Liste Complète des Événements</h3>
            <?php if (count($tous_evenements) > 0): ?>
                <?php foreach ($tous_evenements as $event): ?>
                    <div class="event-card-horizontal">
                        <div class="event-image-wrapper">
                            <?php if (!empty($event['nom_club'])): ?>
                                <div class="club-badge">
                                    <i class="fas fa-users-cog me-1"></i>
                                    <?= htmlspecialchars($event['nom_club']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="organisateur-badge">
                                <i class="fas fa-user me-1"></i>
                                <?= htmlspecialchars($event['organisateur_prenom'] . ' ' . $event['organisateur_nom']) ?>
                            </div>

                            <?php if ($event['affiche_url']): ?>
                                <img src="../<?= htmlspecialchars($event['affiche_url']) ?>" 
                                     alt="<?= htmlspecialchars($event['titre']) ?>"
                                     onerror="this.onerror=null; this.src='https://via.placeholder.com/280x220/ff6b6b/ffffff?text=Image+Manquante'">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/280x220/ff6b6b/ffffff?text=<?= urlencode($event['titre']) ?>" 
                                     alt="<?= htmlspecialchars($event['titre']) ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="event-content">
                            <div class="event-info">
                                <h2 class="event-title"><?= htmlspecialchars($event['titre']) ?></h2>
                                <p class="event-description"><?= htmlspecialchars($event['description']) ?></p>
                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <span><?= date('d/m/Y', strtotime($event['date'])) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= htmlspecialchars($event['lieu']) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-users"></i>
                                        <span><?= $event['nb_participants'] ?> participants</span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-tag"></i>
                                        <span><?= ($event['tarif'] == 0.00) ? 'Gratuit' : number_format($event['tarif'], 2) . ' MAD' ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?= htmlspecialchars($event['organisateur_email']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="event-actions">
                                <a href="admin_event_details.php?id=<?= $event['id_evenement'] ?>" class="btn-action btn-view">
                                    <i class="fas fa-eye"></i> Voir Détails
                                </a>
                                <button class="btn-action btn-delete" onclick="deleteEvent(<?= $event['id_evenement'] ?>, '<?= htmlspecialchars($event['titre']) ?>')">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #6c757d; background: white; border-radius: 20px;">
                    <i class="far fa-calendar-times" style="font-size: 4rem; color: #ff6b6b; margin-bottom: 20px; opacity: 0.5;"></i>
                    <p style="font-size: 1.1rem;">Aucun événement n'est disponible pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function deleteEvent(eventId, eventTitle) {
            if(confirm('Êtes-vous sûr de vouloir supprimer l\'événement "' + eventTitle + '" ?\n\nCette action est irréversible et supprimera également toutes les inscriptions associées.')) {
                window.location.href = 'admin_delete_event.php?id=' + eventId;
            }
        }

       
        function filterEvents() {
            const searchTerm = document.getElementById('searchEvents').value.toLowerCase();
            const eventCards = document.querySelectorAll('.event-card-horizontal');
            
            eventCards.forEach(card => {
                const title = card.querySelector('.event-title').textContent.toLowerCase();
                const description = card.querySelector('.event-description').textContent.toLowerCase();
                const organisateur = card.querySelector('.organisateur-badge').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || description.includes(searchTerm) || organisateur.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const topBar = document.querySelector('.top-bar');
            const searchHtml = `
                <div style="flex: 1; max-width: 400px; margin: 0 2rem;">
                    <div style="position: relative;">
                        <input type="text" id="searchEvents" placeholder="Rechercher un événement..." 
                               style="width: 100%; padding: 0.8rem 1rem 0.8rem 3rem; border: 2px solid #e0e0e0; border-radius: 25px; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease;"
                               oninput="filterEvents()">
                        <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #6c757d;"></i>
                    </div>
                </div>
            `;
            
            topBar.querySelector('.page-title').insertAdjacentHTML('afterend', searchHtml);
        });
    </script>
</body>
</html>