<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: C:/xampp/htdocs/campusEvents/public/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];


$stats_query = "SELECT 
    (SELECT COUNT(*) FROM Utilisateur WHERE role = 'participant') as total_participants,
    (SELECT COUNT(*) FROM Utilisateur WHERE role = 'organisateur') as total_organisateurs,
    (SELECT COUNT(*) FROM Club) as total_clubs,
    (SELECT COUNT(*) FROM Evenement) as total_events,
    (SELECT COUNT(*) FROM Evenement WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())) as events_this_month,
    (SELECT COUNT(*) FROM Utilisateur WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as new_users_this_month";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);


$most_active_club_query = "SELECT c.nom_club, COUNT(e.id_evenement) as nb_events 
    FROM Club c 
    LEFT JOIN Evenement e ON c.id_club = e.id_club 
    GROUP BY c.id_club 
    ORDER BY nb_events DESC 
    LIMIT 1";
$most_active_club_stmt = $pdo->prepare($most_active_club_query);
$most_active_club_stmt->execute();
$most_active_club = $most_active_club_stmt->fetch(PDO::FETCH_ASSOC);


$least_active_club_query = "SELECT c.nom_club, COUNT(e.id_evenement) as nb_events 
    FROM Club c 
    LEFT JOIN Evenement e ON c.id_club = e.id_club 
    GROUP BY c.id_club 
    ORDER BY nb_events ASC 
    LIMIT 1";
$least_active_club_stmt = $pdo->prepare($least_active_club_query);
$least_active_club_stmt->execute();
$least_active_club = $least_active_club_stmt->fetch(PDO::FETCH_ASSOC);


$avg_participants_query = "SELECT AVG(nb_participants) as avg_participants 
    FROM (SELECT COUNT(i.id_inscription) as nb_participants 
          FROM Evenement e 
          LEFT JOIN Inscription i ON e.id_evenement = i.id_evenement AND i.status = 'validée' 
          GROUP BY e.id_evenement) as subquery";
$avg_participants_stmt = $pdo->prepare($avg_participants_query);
$avg_participants_stmt->execute();
$avg_participants = $avg_participants_stmt->fetch(PDO::FETCH_ASSOC);


$clubs_events_query = "SELECT c.nom_club, COUNT(e.id_evenement) as nb_events 
    FROM Club c 
    LEFT JOIN Evenement e ON c.id_club = e.id_club 
    GROUP BY c.id_club 
    ORDER BY nb_events DESC 
    LIMIT 10";
$clubs_events_stmt = $pdo->prepare($clubs_events_query);
$clubs_events_stmt->execute();
$clubs_events = $clubs_events_stmt->fetchAll(PDO::FETCH_ASSOC);


$events_per_month_query = "SELECT 
    DATE_FORMAT(date, '%Y-%m') as mois, 
    COUNT(*) as nb_events 
    FROM Evenement 
    WHERE date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH) 
    GROUP BY mois 
    ORDER BY mois";
$events_per_month_stmt = $pdo->prepare($events_per_month_query);
$events_per_month_stmt->execute();
$events_per_month = $events_per_month_stmt->fetchAll(PDO::FETCH_ASSOC);


$users_per_month_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as mois, 
    COUNT(*) as nb_users 
    FROM Utilisateur 
    WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH) 
    GROUP BY mois 
    ORDER BY mois";
$users_per_month_stmt = $pdo->prepare($users_per_month_query);
$users_per_month_stmt->execute();
$users_per_month = $users_per_month_stmt->fetchAll(PDO::FETCH_ASSOC);


$organisateurs_query = "SELECT u.*, c.nom_club 
    FROM Utilisateur u 
    LEFT JOIN Club c ON u.id_utilisateur = c.id_president 
    WHERE u.role = 'organisateur' 
    ORDER BY u.created_at DESC";
$organisateurs_stmt = $pdo->prepare($organisateurs_query);
$organisateurs_stmt->execute();
$organisateurs = $organisateurs_stmt->fetchAll(PDO::FETCH_ASSOC);


$clubs_query = "SELECT c.*, u.nom as president_nom, u.prenom as president_prenom, 
    COUNT(e.id_evenement) as nb_events 
    FROM Club c 
    LEFT JOIN Utilisateur u ON c.id_president = u.id_utilisateur 
    LEFT JOIN Evenement e ON c.id_club = e.id_club 
    GROUP BY c.id_club 
    ORDER BY c.nom_club";
$clubs_stmt = $pdo->prepare($clubs_query);
$clubs_stmt->execute();
$clubs = $clubs_stmt->fetchAll(PDO::FETCH_ASSOC);

$current_page = isset($_GET['page']) ? $_GET['page'] : 'stats';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - CampusEvent</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        .stat-icon.pink {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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

        .chart-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .chart-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }

        .table-container {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .data-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
        }

        .data-table td {
            padding: 1rem;
            font-size: 0.9rem;
            color: #2c3e50;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0 0.2rem;
        }

        .btn-edit {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .btn-add {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .btn-action:hover, .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        .badge-warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .badge-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .sidebar-link span {
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
                <a href="?page=stats" class="sidebar-link <?= $current_page === 'stats' ? 'active' : '' ?>">
                    <i class="sidebar-icon fas fa-chart-line"></i>
                    <span>Statistiques</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=organisateurs" class="sidebar-link <?= $current_page === 'organisateurs' ? 'active' : '' ?>">
                    <i class="sidebar-icon fas fa-users-cog"></i>
                    <span>Organisateurs</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=clubs" class="sidebar-link <?= $current_page === 'clubs' ? 'active' : '' ?>">
                    <i class="sidebar-icon fas fa-users"></i>
                    <span>Clubs</span>
                </a>
            </li>
            <li class="sidebar-item">
    <a href="admin_tous_evenements.php" class="sidebar-link <?= $current_page === 'evenements' ? 'active' : '' ?>">
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
            <h1 class="page-title">
                <?php
                switch($current_page) {
                    case 'stats': echo ' Statistiques du Site'; break;
                    case 'organisateurs': echo ' Gestion des Organisateurs'; break;
                    case 'clubs': echo ' Gestion des Clubs'; break;
                    default: echo ' Dashboard Admin';
                }
                ?>
            </h1>
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

        <?php if($current_page === 'stats'): ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['total_organisateurs'] ?></h3>
                        <p>Organisateurs</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['total_participants'] ?></h3>
                        <p>Participants</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['total_clubs'] ?></h3>
                        <p>Clubs</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['total_events'] ?></h3>
                        <p>Total Événements</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['events_this_month'] ?></h3>
                        <p>Événements ce mois</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon pink">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['new_users_this_month'] ?></h3>
                        <p>Nouveaux users ce mois</p>
                    </div>
                </div>
            </div>

            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $most_active_club['nb_events'] ?? 0 ?></h3>
                        <p>Club le plus actif</p>
                        <small style="color: #667eea; font-weight: 600;"><?= htmlspecialchars($most_active_club['nom_club'] ?? 'N/A') ?></small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($avg_participants['avg_participants'] ?? 0, 1) ?></h3>
                        <p>Participants moyens/événement</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $least_active_club['nb_events'] ?? 0 ?></h3>
                        <p>Club le moins actif</p>
                        <small style="color: #ff6b6b; font-weight: 600;"><?= htmlspecialchars($least_active_club['nom_club'] ?? 'N/A') ?></small>
                    </div>
                </div>
            </div>

          
            <div class="chart-card">
                <h3 class="chart-title"> Événements par Club (Top 10)</h3>
                <canvas id="clubsChart" height="80"></canvas>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 2rem;">
                <div class="chart-card">
                    <h3 class="chart-title"> Événements par Mois</h3>
                    <canvas id="eventsMonthChart"></canvas>
                </div>

                <div class="chart-card">
                    <h3 class="chart-title"> Nouveaux Utilisateurs par Mois</h3>
                    <canvas id="usersMonthChart"></canvas>
                </div>
            </div>

        <?php elseif($current_page === 'organisateurs'): ?>
          
            <button class="btn-add" onclick="location.href='?page=add_organisateur'">
                <i class="fas fa-plus"></i>
                Ajouter un Organisateur
            </button>

            <div class="table-container">
                <h3 class="section-title">Liste des Organisateurs</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom Complet</th>
                            <th>Email</th>
                            <th>Club Présidé</th>
                            <th>Date d'inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($organisateurs as $org): ?>
                        <tr>
                            <td><?= htmlspecialchars($org['nom'] . ' ' . $org['prenom']) ?></td>
                            <td><?= htmlspecialchars($org['email']) ?></td>
                            <td>
                                <?php if($org['nom_club']): ?>
                                    <span class="badge badge-success"><?= htmlspecialchars($org['nom_club']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Aucun club</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($org['created_at'])) ?></td>
                            <td>
                                <button class="btn-action btn-edit" onclick="editOrganisateur(<?= $org['id_utilisateur'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action btn-delete" onclick="deleteOrganisateur(<?= $org['id_utilisateur'] ?>, '<?= htmlspecialchars($org['nom']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif($current_page === 'clubs'): ?>
           
            <button class="btn-add" onclick="location.href='?page=add_club'">
                <i class="fas fa-plus"></i>
                Ajouter un Club
            </button>

            <div class="table-container">
                <h3 class="section-title">Liste des Clubs</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom du Club</th>
                            <th>Président</th>
                            <th>Nombre d'Événements</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($clubs as $club): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($club['nom_club']) ?></strong></td>
                            <td>
                                <?php if($club['president_nom']): ?>
                                    <?= htmlspecialchars($club['president_nom'] . ' ' . $club['president_prenom']) ?>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pas de président</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-success"><?= $club['nb_events'] ?> événement(s)</span>
                            </td>
                            <td>
                                <button class="btn-action btn-edit" onclick="editClub(<?= $club['id_club'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action btn-delete" onclick="deleteClub(<?= $club['id_club'] ?>, '<?= htmlspecialchars($club['nom_club']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif($current_page === 'add_organisateur'): ?>
            
            <div class="form-container" style="background: white; padding: 3rem; border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto;">
                <div class="form-header text-center mb-4">
                    <h1 style="font-family: 'Poppins', sans-serif; font-weight: 900; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 2.5rem; margin-bottom: 1rem;">
                        <i class="fas fa-user-plus"></i> Ajouter un Organisateur
                    </h1>
                </div>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success" style="border-radius: 15px; margin-bottom: 2rem;">
                        <?= $_SESSION['success'] ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger" style="border-radius: 15px; margin-bottom: 2rem;">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form action="process_add_organisateur.php" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; color: #2c3e50;">Nom</label>
                            <input type="text" class="form-control" name="nom" required style="border-radius: 12px; padding: 0.8rem;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 600; color: #2c3e50;">Prénom</label>
                            <input type="text" class="form-control" name="prenom" required style="border-radius: 12px; padding: 0.8rem;">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Email</label>
                        <input type="email" class="form-control" name="email" required style="border-radius: 12px; padding: 0.8rem;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Date de Naissance</label>
                        <input type="date" class="form-control" name="date_naissance" required style="border-radius: 12px; padding: 0.8rem;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Filière</label>
                        <input type="text" class="form-control" name="filiere" style="border-radius: 12px; padding: 0.8rem;">
                    </div>

                    <div class="alert" style="background: linear-gradient(135deg, rgba(255, 107, 107, 0.1) 0%, rgba(238, 90, 111, 0.1) 100%); border: 2px solid rgba(255, 107, 107, 0.2); border-radius: 15px; padding: 1.5rem; margin-bottom: 2rem;">
                        <i class="fas fa-info-circle" style="color: #ff6b6b;"></i>
                        <strong style="color: #ff6b6b;">Information:</strong> Un mot de passe sera généré automatiquement et envoyé par email à l'organisateur.
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn-add" style="flex: 1; justify-content: center;">
                            <i class="fas fa-check"></i>
                            Créer l'Organisateur
                        </button>
                        <button type="button" class="btn-action btn-delete" onclick="location.href='?page=organisateurs'" style="padding: 1rem 2rem; font-size: 1rem;">
                            <i class="fas fa-times"></i>
                            Annuler
                        </button>
                    </div>
                </form>
            </div>

        <?php elseif($current_page === 'add_club'): ?>
            
            <div class="form-container" style="background: white; padding: 3rem; border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto;">
                <div class="form-header text-center mb-4">
                    <h1 style="font-family: 'Poppins', sans-serif; font-weight: 900; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 2.5rem; margin-bottom: 1rem;">
                        <i class="fas fa-users"></i> Ajouter un Club
                    </h1>
                </div>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success" style="border-radius: 15px; margin-bottom: 2rem;">
                        <?= $_SESSION['success'] ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger" style="border-radius: 15px; margin-bottom: 2rem;">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php
             
                $org_list_query = "SELECT id_utilisateur, nom, prenom FROM Utilisateur WHERE role = 'organisateur' ORDER BY nom";
                $org_list_stmt = $pdo->prepare($org_list_query);
                $org_list_stmt->execute();
                $org_list = $org_list_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <form action="process_add_club.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Nom du Club</label>
                        <input type="text" class="form-control" name="nom_club" required style="border-radius: 12px; padding: 0.8rem;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Description</label>
                        <textarea class="form-control" name="description" rows="4" style="border-radius: 12px; padding: 0.8rem;"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #2c3e50;">Président du Club</label>
                        <select class="form-control" name="id_president" style="border-radius: 12px; padding: 0.8rem;">
                            <option value="">Sélectionner un organisateur...</option>
                            <?php foreach($org_list as $org): ?>
                                <option value="<?= $org['id_utilisateur'] ?>">
                                    <?= htmlspecialchars($org['nom'] . ' ' . $org['prenom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn-add" style="flex: 1; justify-content: center;">
                            <i class="fas fa-check"></i>
                            Créer le Club
                        </button>
                        <button type="button" class="btn-action btn-delete" onclick="location.href='?page=clubs'" style="padding: 1rem 2rem; font-size: 1rem;">
                            <i class="fas fa-times"></i>
                            Annuler
                        </button>
                    </div>
                </form>
            </div>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if($current_page === 'stats'): ?>
    <script>
        
        const clubsData = <?= json_encode(array_column($clubs_events, 'nom_club')) ?>;
        const clubsEvents = <?= json_encode(array_column($clubs_events, 'nb_events')) ?>;

        const ctxClubs = document.getElementById('clubsChart').getContext('2d');
        new Chart(ctxClubs, {
            type: 'bar',
            data: {
                labels: clubsData,
                datasets: [{
                    label: 'Nombre d\'événements',
                    data: clubsEvents,
                    backgroundColor: 'rgba(255, 107, 107, 0.8)',
                    borderColor: 'rgba(255, 107, 107, 1)',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

     
        const eventsMonthLabels = <?= json_encode(array_column($events_per_month, 'mois')) ?>;
        const eventsMonthData = <?= json_encode(array_column($events_per_month, 'nb_events')) ?>;

        const ctxEventsMonth = document.getElementById('eventsMonthChart').getContext('2d');
        new Chart(ctxEventsMonth, {
            type: 'line',
            data: {
                labels: eventsMonthLabels,
                datasets: [{
                    label: 'Événements',
                    data: eventsMonthData,
                    borderColor: 'rgba(67, 233, 123, 1)',
                    backgroundColor: 'rgba(67, 233, 123, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

      
        const usersMonthLabels = <?= json_encode(array_column($users_per_month, 'mois')) ?>;
        const usersMonthData = <?= json_encode(array_column($users_per_month, 'nb_users')) ?>;

        const ctxUsersMonth = document.getElementById('usersMonthChart').getContext('2d');
        new Chart(ctxUsersMonth, {
            type: 'line',
            data: {
                labels: usersMonthLabels,
                datasets: [{
                    label: 'Nouveaux Utilisateurs',
                    data: usersMonthData,
                    borderColor: 'rgba(79, 172, 254, 1)',
                    backgroundColor: 'rgba(79, 172, 254, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>

    <script>
        function editOrganisateur(id) {
            window.location.href = 'edit_organisateur.php?id=' + id;
        }

        function deleteOrganisateur(id, nom) {
            if(confirm('Êtes-vous sûr de vouloir supprimer l\'organisateur "' + nom + '" ?\n\nTous les événements associés seront également supprimés.')) {
                window.location.href = 'delete_organisateur.php?id=' + id;
            }
        }

        function editClub(id) {
            window.location.href = 'edit_club.php?id=' + id;
        }

        function deleteClub(id, nom) {
            if(confirm('Êtes-vous sûr de vouloir supprimer le club "' + nom + '" ?\n\nTous les événements associés seront également supprimés.')) {
                window.location.href = 'delete_club.php?id=' + id;
            }
        }
    </script>
</body>
</html>