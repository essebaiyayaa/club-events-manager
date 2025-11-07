<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: C:/xampp/htdocs/campusEvents/public/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];

$query = "SELECT e.*, 
          (SELECT COUNT(*) FROM Inscription WHERE id_evenement = e.id_evenement AND status = 'validée') as nb_participants
          FROM Evenement e 
          WHERE e.id_organisateur = ? 
          ORDER BY e.date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_query = "SELECT 
                COUNT(DISTINCT e.id_evenement) as total_events,
                COUNT(DISTINCT i.id_inscription) as total_participants,
                SUM(CASE WHEN e.date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_events
                FROM Evenement e
                LEFT JOIN Inscription i ON e.id_evenement = i.id_evenement AND i.status = 'validée'
                WHERE e.id_organisateur = ?";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$detailed_stats_query = "SELECT 
                        COUNT(*) as total_events,
                        SUM(CASE WHEN e.date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_events,
                        SUM(CASE WHEN e.date < CURDATE() THEN 1 ELSE 0 END) as past_events,
                        COUNT(DISTINCT i.id_inscription) as total_participants,
                        COALESCE(SUM(e.tarif), 0) as total_revenue,
                        AVG(e.tarif) as avg_tarif,
                        MAX(e.capacite_max) as max_capacity,
                        AVG(e.capacite_max) as avg_capacity
                        FROM Evenement e
                        LEFT JOIN Inscription i ON e.id_evenement = i.id_evenement AND i.status = 'validée'
                        WHERE e.id_organisateur = ?";
// Requête pour récupérer l'historique des attestations
$history_query = "SELECT 
                    a.id_attestation,
                    a.date_generation,
                    a.chemin_pdf,
                    e.titre as nom_evenement,
                    e.date as date_evenement,
                    u.nom,
                    u.prenom,
                    u.email,
                    i.date_inscription
                  FROM Attestation a
                  INNER JOIN Inscription i ON a.id_inscription = i.id_inscription
                  INNER JOIN Evenement e ON i.id_evenement = e.id_evenement
                  INNER JOIN Utilisateur u ON i.id_utilisateur = u.id_utilisateur
                  WHERE e.id_organisateur = ?
                  ORDER BY a.date_generation DESC
                  LIMIT 50";

$history_stmt = $pdo->prepare($history_query);
$history_stmt->execute([$user_id]);
$attestations_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
$detailed_stats_stmt = $pdo->prepare($detailed_stats_query);
$detailed_stats_stmt->execute([$user_id]);
$detailed_stats = $detailed_stats_stmt->fetch(PDO::FETCH_ASSOC);

$popular_events_query = "SELECT e.titre, e.date, COUNT(i.id_inscription) as participants
                        FROM Evenement e
                        LEFT JOIN Inscription i ON e.id_evenement = i.id_evenement AND i.status = 'validée'
                        WHERE e.id_organisateur = ?
                        GROUP BY e.id_evenement
                        ORDER BY participants DESC
                        LIMIT 5";
$popular_events_stmt = $pdo->prepare($popular_events_query);
$popular_events_stmt->execute([$user_id]);
$popular_events = $popular_events_stmt->fetchAll(PDO::FETCH_ASSOC);

$all_events_query = "SELECT e.*, 
                     u.nom as organisateur_nom, 
                     u.prenom as organisateur_prenom,
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
                       COUNT(DISTINCT e.id_organisateur) as total_organisateurs
                       FROM Evenement e
                       LEFT JOIN Inscription i ON e.id_evenement = i.id_evenement AND i.status = 'validée'";
$participants_query = "SELECT 
                      i.id_inscription,
                      i.status,
                      i.status_paiment,
                      i.date_inscription,
                      u.id_utilisateur,
                      u.nom,
                      u.prenom,
                      u.email,
                      u.filiere,
                      e.id_evenement,
                      e.titre as evenement_titre,
                      e.date as evenement_date
                      FROM Inscription i
                      JOIN Utilisateur u ON i.id_utilisateur = u.id_utilisateur
                      JOIN Evenement e ON i.id_evenement = e.id_evenement
                      WHERE e.id_organisateur = ?
                      ORDER BY i.date_inscription DESC";
$participants_stmt = $pdo->prepare($participants_query);
$participants_stmt->execute([$user_id]);
$participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);
$participants_stats_query = "SELECT 
                             COUNT(*) as total_inscriptions,
                             SUM(CASE WHEN i.status = 'validée' THEN 1 ELSE 0 END) as validees,
                             SUM(CASE WHEN i.status = 'en attente' THEN 1 ELSE 0 END) as en_attente,
                             SUM(CASE WHEN i.status = 'refusée' THEN 1 ELSE 0 END) as refusees,
                             SUM(CASE WHEN i.status_paiment = 'payé' THEN 1 ELSE 0 END) as payes,
                             SUM(CASE WHEN i.status_paiment = 'non payé' THEN 1 ELSE 0 END) as non_payes
                             FROM Inscription i
                             JOIN Evenement e ON i.id_evenement = e.id_evenement
                             WHERE e.id_organisateur = ?";
$events_dropdown_query = "SELECT id_evenement, titre, date 
                         FROM Evenement 
                         WHERE id_organisateur = ? 
                         ORDER BY date DESC";
$events_dropdown_stmt = $pdo->prepare($events_dropdown_query);
$events_dropdown_stmt->execute([$user_id]);
$events_dropdown = $events_dropdown_stmt->fetchAll(PDO::FETCH_ASSOC);
$participants_stats_stmt = $pdo->prepare($participants_stats_query);
$participants_stats_stmt->execute([$user_id]);
$participants_stats = $participants_stats_stmt->fetch(PDO::FETCH_ASSOC);
$global_stats_stmt = $pdo->prepare($global_stats_query);
$global_stats_stmt->execute();
$global_stats = $global_stats_stmt->fetch(PDO::FETCH_ASSOC);

$current_page = isset($_GET['page']) ? $_GET['page'] : 'events';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Organisateur - CampusEvent</title>
    
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
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .stat-icon.purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .event-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .event-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .event-body {
            padding: 1.8rem;
        }

        .event-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            line-height: 1.3;
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
            color: #667eea;
        }

        .event-actions {
            display: flex;
            gap: 0.8rem;
        }

        .btn-action {
            flex: 1;
            padding: 0.8rem;
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
        }

        .btn-modify {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-modify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        .create-event-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.8rem;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .create-event-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.6);
        }

        .stats-section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #667eea;
            display: inline-block;
        }

        .popular-events {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.25);
            border-color: #667eea;
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
            color: #667eea;
        }

        .organisateur-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            background: rgba(102, 126, 234, 0.95);
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

        .btn-details {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 25px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn-details:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
            color: white;
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

            .events-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        .status-select, .payment-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    border: none;
    border-radius: 8px;
    padding: 0.6rem 2rem 0.6rem 1rem;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    background-repeat: no-repeat;
    background-position: right 0.6rem center;
    background-size: 1em;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.status-select {
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
}

.payment-select {
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
}

.status-select:hover, .payment-select:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.status-select:focus, .payment-select:focus {
    outline: none;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.status-select option, .payment-select option {
    padding: 0.5rem;
    font-weight: 600;
    background: white;
    color: #2c3e50;
}
        .attestations-section {
            max-width: 900px;
            margin: 0 auto;
        }

        .attestation-form-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .form-header i {
            font-size: 3.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .form-header h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: #6c757d;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.8rem;
            font-size: 1rem;
        }

        .form-group label i {
            color: #667eea;
        }

        .form-select {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            color: #2c3e50;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-select:hover {
            border-color: #667eea;
        }

        .participants-preview {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .participants-preview h3 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .participants-list {
            display: grid;
            gap: 0.8rem;
            margin-bottom: 1rem;
            max-height: 300px;
            overflow-y: auto;
        }

        .participant-item {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .participant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
        }

        .participant-info {
            flex: 1;
        }

        .participant-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .participant-email {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .participants-count {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            color: #667eea;
            font-size: 1.1rem;
        }

        .btn-send-attestations {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
        }

        .btn-send-attestations:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-send-attestations:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .history-table-wrapper {
    overflow-x: auto;
    margin-top: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    font-size: 14px;
}

.history-table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.history-table th {
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
}

.history-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s;
}

.history-table tbody tr:hover {
    background-color: #f8f9ff;
}

.history-table td {
    padding: 12px;
}

.date-badge {
    display: flex;
    flex-direction: column;
    font-weight: 500;
}

.date-badge small {
    color: #666;
    font-size: 11px;
    margin-top: 2px;
}

.event-info {
    display: flex;
    flex-direction: column;
}

.event-info strong {
    color: #333;
    margin-bottom: 3px;
}

.event-info small {
    color: #888;
    font-size: 12px;
}

.participant-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.participant-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    flex-shrink: 0;
}

.email-text {
    color: #555;
    font-size: 13px;
}

.btn-download {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    background: #10b981;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 500;
    transition: background 0.2s;
}

.btn-download:hover {
    background: #059669;
}

.text-muted {
    color: #999;
    font-size: 12px;
    font-style: italic;
}

.history-stats {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9ff;
    border-radius: 8px;
    text-align: center;
}

.history-stats i {
    color: #667eea;
    margin-right: 8px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
    font-size: 16px;
}

.empty-state i {
    font-size: 48px;
    display: block;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .history-table {
        font-size: 12px;
    }
    
    .history-table th,
    .history-table td {
        padding: 8px 6px;
    }
    
    .participant-avatar-small {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }
}

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-text">
                <div>Campus</div>
                <div>Event</div>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="?page=tous_evenements" 
                   class="sidebar-link <?= $current_page === 'tous_evenements' ? 'active' : '' ?>">
                    <i class="sidebar-icon fas fa-list"></i>
                    <span>Tous les événements</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=stats" class="sidebar-link <?= $current_page === 'stats' ? 'active' : '' ?>">
                    <i class="sidebar-icon fas fa-chart-line"></i>
                    <span>Statistiques</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=events" class="sidebar-link <?= $current_page === 'events' ? 'active' : '' ?>">
                    <i class="sidebar-icon fas fa-calendar-alt"></i>
                    <span>Mes événements</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=participants" class="sidebar-link <?= $current_page === 'participants' ? 'active' : '' ?>">
                    <i class="sidebar-icon fas fa-users"></i>
                    <span>Gestion des participants</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=emails" class="sidebar-link <?= $current_page === 'emails' ? 'active' : '' ?>">
                    <i class="sidebar-icon fas fa-envelope"></i>
                    <span>Emails & Fichiers</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="?page=attestations" class="sidebar-link <?= $current_page === 'attestations' ? 'active' : '' ?>">
                    <i class="sidebar-icon fas fa-certificate"></i>
                    <span>Attestations</span>
                </a>
            </li>
                        <li class="sidebar-item">
                <a href="change_organisateur_password.php" class="sidebar-link">
                    <i class="sidebar-icon fas fa-lock"></i>
                    <span>Mot de passe</span>
                </a>
            </li>
        </ul>
        <button class="logout-btn" onclick="location.href='../auth/logout.php'">
            <i class="fas fa-sign-out-alt"></i>
            <span> Quitter</span>
        </button>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <h1 class="page-title">
                <?php
                switch($current_page) {
                    case 'tous_evenements': echo 'Tous les Événements'; break;
                    case 'stats': echo 'Statistiques Détaillées'; break;
                    case 'events': echo 'Mes événements'; break;
                    case 'create': echo 'Ajouter un événement'; break;
                    case 'participants': echo 'Gestion des participants'; break;
                    case 'emails': echo 'Emails et fichiers'; break;
                    case 'attestations': echo 'Attestations'; break;
                    default: echo 'Dashboard';
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

        <?php if($current_page === 'tous_evenements'): ?>
            <!-- Page Tous les Événements -->
            <div class="stats-grid">
                <div class="stat-card" style="border-left-color: #667eea;">
                    <div class="stat-icon purple">
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

            <div class="events-list">
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
                                         onerror="this.onerror=null; this.src='https://via.placeholder.com/280x220/667eea/ffffff?text=Image+Manquante'">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/280x220/667eea/ffffff?text=<?= urlencode($event['titre']) ?>" 
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; color: #6c757d; background: white; border-radius: 20px;">
                        <i class="far fa-calendar-times" style="font-size: 4rem; color: #667eea; margin-bottom: 20px; opacity: 0.5;"></i>
                        <p style="font-size: 1.1rem;">Aucun événement n'est disponible pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif($current_page === 'events'): ?>
            <div class="stats-grid">
                <div class="stat-card" style="border-left-color: #667eea;">
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['total_events'] ?></h3>
                        <p>Total événements</p>
                    </div>
                </div>
                <div class="stat-card" style="border-left-color: #4facfe;">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['total_participants'] ?></h3>
                        <p>Total participants</p>
                    </div>
                </div>
                <div class="stat-card" style="border-left-color: #43e97b;">
                    <div class="stat-icon green">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['upcoming_events'] ?></h3>
                        <p>Événements à venir</p>
                    </div>
                </div>
            </div>

            <div class="events-grid">
                <?php if(empty($evenements)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                        <p style="color: #6c757d;">Vous n'avez pas encore créé d'événement</p>
                    </div>
                <?php else: ?>
                    <?php foreach($evenements as $event): ?>
                        <div class="event-card">
                            <?php if($event['affiche_url']): ?>
                                <img src="../<?= htmlspecialchars($event['affiche_url']) ?>" 
                                     alt="<?= htmlspecialchars($event['titre']) ?>" 
                                     class="event-image">
                            <?php else: ?>
                                <div class="event-image"></div>
                            <?php endif; ?>
                            <div class="event-body">
                                <h3 class="event-title"><?= htmlspecialchars($event['titre']) ?></h3>
                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('d/m/Y', strtotime($event['date'])) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= htmlspecialchars($event['lieu']) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-users"></i>
                                        <span><?= $event['nb_participants'] ?> / <?= $event['capacite_max'] ?> participants</span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-euro-sign"></i>
                                        <span><?= number_format($event['tarif'], 2) ?> MAD</span>
                                    </div>
                                </div>
                                <div class="event-actions">
                                    <button class="btn-action btn-modify" onclick="modifyEvent(<?= $event['id_evenement'] ?>)">
                                        <i class="fas fa-edit"></i> Modifier
                                    </button>
                                    <button class="btn-action btn-delete" onclick="deleteEvent(<?= $event['id_evenement'] ?>, '<?= htmlspecialchars($event['titre']) ?>')">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <button class="create-event-btn" onclick="location.href='?page=create'">
                <i class="fas fa-plus"></i>
            </button>

        <?php elseif($current_page === 'stats'): ?>
        
            <div class="stats-section">
                <h2 class="section-title">Aperçu Général</h2>
                <div class="stats-grid">
                    <div class="stat-card" style="border-left-color: #667eea;">
                        <div class="stat-icon purple">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $detailed_stats['total_events'] ?></h3>
                            <p>Total Événements</p>
                        </div>
                    </div>
                    <div class="stat-card" style="border-left-color: #4facfe;">
                        <div class="stat-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $detailed_stats['total_participants'] ?></h3>
                            <p>Participants Totaux</p>
                        </div>
                    </div>
                    <div class="stat-card" style="border-left-color: #43e97b;">
                        <div class="stat-icon green">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $detailed_stats['upcoming_events'] ?></h3>
                            <p>Événements à Venir</p>
                        </div>
                    </div>
                    <div class="stat-card" style="border-left-color: #fa709a;">
                        <div class="stat-icon orange">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $detailed_stats['past_events'] ?></h3>
                            <p>Événements Passés</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <h2 class="section-title">Analyse Financière</h2>
                <div class="stats-grid">
                    <div class="stat-card" style="border-left-color: #43e97b;">
                        <div class="stat-icon green">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= number_format($detailed_stats['total_revenue'], 2) ?> MAD</h3>
                            <p>Revenu Total</p>
                        </div>
                    </div>
                    <div class="stat-card" style="border-left-color: #4facfe;">
                        <div class="stat-icon blue">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= number_format($detailed_stats['avg_tarif'], 2) ?> MAD</h3>
                            <p>Tarif Moyen</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <h2 class="section-title"> Capacité des Événements</h2>
                <div class="stats-grid">
                    <div class="stat-card" style="border-left-color: #667eea;">
                        <div class="stat-icon purple">
                            <i class="fas fa-expand"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= $detailed_stats['max_capacity'] ?></h3>
                            <p>Capacité Maximale</p>
                        </div>
                    </div>
                    <div class="stat-card" style="border-left-color: #4facfe;">
                        <div class="stat-icon blue">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= number_format($detailed_stats['avg_capacity'], 1) ?></h3>
                            <p>Capacité Moyenne</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <h2 class="section-title">Événements les Plus Populaires</h2>
                <div class="popular-events">
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
                                        <?= $event['participants'] ?> participants
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif($current_page === 'create'): ?>
            <div class="form-container" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 3rem; border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: 1px solid rgba(255, 255, 255, 0.3); max-width: 900px; margin: 0 auto;">
                <div class="form-header text-center mb-4">
                    <h1 style="font-family: 'Poppins', sans-serif; font-weight: 900; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 2.5rem; margin-bottom: 1rem;">
                        <i class="fas fa-plus-circle"></i> Créer un nouvel événement
                    </h1>
                    <p style="color: #6c757d; font-size: 1.05rem;">Remplissez les informations pour créer votre événement</p>
                </div>

                <form action="process_create_event.php" method="POST" enctype="multipart/form-data">
                    <div class="input-group-custom" style="position: relative; margin-bottom: 2rem;">
                        <label for="titre" class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-heading" style="color: #667eea;"></i> Titre de l'événement
                        </label>
                        <input type="text" class="form-control" id="titre" name="titre" required style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 15px; padding: 1rem 1rem 1rem 3rem; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); font-size: 1rem; width: 100%;">
                    </div>

                    <div class="mb-4">
                        <label class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-image" style="color: #667eea;"></i> Affiche de l'événement
                        </label>
                        
                        <div class="file-input-wrapper" style="position: relative; overflow: hidden; display: inline-block; width: 100%;">
                            <input type="file" id="affiche" name="affiche" accept="image/*" style="position: absolute; left: -9999px;">
                            <label for="affiche" class="file-input-label" style="display: flex; align-items: center; gap: 1rem; padding: 1.5rem; background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); border: 2px dashed rgba(102, 126, 234, 0.3); border-radius: 15px; cursor: pointer; transition: all 0.3s ease; text-align: center; justify-content: center; width: 100%;">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 1.5rem; color: #667eea;"></i>
                                <span>Cliquez pour sélectionner une affiche</span>
                            </label>
                        </div>
                        <small class="text-muted d-block text-center mt-2">Formats acceptés: JPG, PNG, GIF (max 5MB)</small>
                    </div>

                    <div class="input-group-custom" style="position: relative; margin-bottom: 2rem;">
                        <label for="description" class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-align-left" style="color: #667eea;"></i> Description détaillée
                        </label>
                        <textarea class="form-control" id="description" name="description" rows="6" required style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 15px; padding: 1rem 1rem 1rem 3rem; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); font-size: 1rem; width: 100%; resize: vertical; min-height: 150px;"></textarea>
                    </div>

                    <div class="row" style="margin-bottom: 1rem;">
                        <div class="col-md-6">
                            <div class="input-group-custom" style="position: relative; margin-bottom: 2rem;">
                                <label for="date" class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-calendar-alt" style="color: #667eea;"></i> Date de l'événement
                                </label>
                                <input type="date" class="form-control" id="date" name="date" required style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 15px; padding: 1rem 1rem 1rem 3rem; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); font-size: 1rem; width: 100%;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group-custom" style="position: relative; margin-bottom: 2rem;">
                                <label for="lieu" class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-map-marker-alt" style="color: #667eea;"></i> Lieu
                                </label>
                                <input type="text" class="form-control" id="lieu" name="lieu" required style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 15px; padding: 1rem 1rem 1rem 3rem; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); font-size: 1rem; width: 100%;">
                            </div>
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 1rem;">
                        <div class="col-md-6">
                            <div class="input-group-custom" style="position: relative; margin-bottom: 2rem;">
                                <label for="capacite_max" class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-users" style="color: #667eea;"></i> Capacité maximale
                                </label>
                                <input type="number" class="form-control" id="capacite_max" name="capacite_max" min="1" required style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 15px; padding: 1rem 1rem 1rem 3rem; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); font-size: 1rem; width: 100%;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group-custom" style="position: relative; margin-bottom: 2rem;">
                                <label for="tarif" class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-euro-sign" style="color: #667eea;"></i> Tarif
                                </label>
                                <input type="number" step="0.01" class="form-control" id="tarif" name="tarif" value="0" required style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 15px; padding: 1rem 1rem 1rem 3rem; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); font-size: 1rem; width: 100%;">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1.25rem 2.5rem; border-radius: 50px; font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease; width: 100%; margin-top: 1.5rem; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);">
                        <i class="fas fa-plus-circle me-2"></i>
                        Créer l'événement
                    </button>
                </form>
            </div>

            <script>
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('date').setAttribute('min', today);

                document.getElementById('affiche').addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name;
                    if (fileName) {
                        const label = document.querySelector('.file-input-label span');
                        label.textContent = '✓ ' + fileName;
                        label.style.color = '#667eea';
                        label.style.fontWeight = '600';
                    }
                });
                const inputs = document.querySelectorAll('.form-control');
                inputs.forEach(input => {
                    input.addEventListener('focus', function() {
                        this.style.borderColor = '#667eea';
                        this.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.1)';
                        this.style.background = 'white';
                    });

                    input.addEventListener('blur', function() {
                        if (this.value.trim() !== '') {
                            this.style.borderColor = '#4ade80';
                        } else {
                            this.style.borderColor = 'rgba(102, 126, 234, 0.2)';
                            this.style.boxShadow = 'none';
                        }
                    });
                });
                const submitBtnCreate = document.querySelector('.btn-submit');
                submitBtnCreate.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                    this.style.boxShadow = '0 15px 40px rgba(102, 126, 234, 0.5)';
                });

                submitBtnCreate.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 10px 30px rgba(102, 126, 234, 0.4)';
                });
            </script>
        <?php elseif($current_page === 'participants'): ?>
    
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success" style="border-radius: 15px; font-family: 'Montserrat', sans-serif; border: none; padding: 1.25rem; box-shadow: 0 5px 20px rgba(0,0,0,0.1); background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; margin-bottom: 2rem;">
            <i class="fas fa-check-circle me-2"></i>
            <?= $_SESSION['success'] ?>
            <?php unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" style="border-radius: 15px; font-family: 'Montserrat', sans-serif; border: none; padding: 1.25rem; box-shadow: 0 5px 20px rgba(0,0,0,0.1); background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; margin-bottom: 2rem;">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= $_SESSION['error'] ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    <div class="stats-grid">
        <div class="stat-card" style="border-left-color: #667eea;">
            <div class="stat-icon purple">
                <i class="fas fa-clipboard-list"></i>
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
                <p>Inscriptions Validées</p>
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
        <div class="stat-card" style="border-left-color: #4facfe;">
            <div class="stat-icon blue">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-content">
                <h3><?= $participants_stats['payes'] ?></h3>
                <p>Paiements Reçus</p>
            </div>
        </div>
    </div>
    <div class="filters-container" style="background: white; padding: 1.5rem; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 2rem;">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" style="font-weight: 600; color: #2c3e50; margin-bottom: 0.5rem;">
                    <i class="fas fa-filter me-2" style="color: #667eea;"></i>Statut Inscription
                </label>
                <select id="filterStatus" class="form-control" style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 12px; padding: 0.75rem;">
                    <option value="">Tous les statuts</option>
                    <option value="en attente">En attente</option>
                    <option value="validée">Validée</option>
                    <option value="refusée">Refusée</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-weight: 600; color: #2c3e50; margin-bottom: 0.5rem;">
                    <i class="fas fa-money-check-alt me-2" style="color: #667eea;"></i>Statut Paiement
                </label>
                <select id="filterPayment" class="form-control" style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 12px; padding: 0.75rem;">
                    <option value="">Tous les paiements</option>
                    <option value="payé">Payé</option>
                    <option value="non payé">Non payé</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-weight: 600; color: #2c3e50; margin-bottom: 0.5rem;">
                    <i class="fas fa-search me-2" style="color: #667eea;"></i>Rechercher
                </label>
                <input type="text" id="searchParticipant" class="form-control" placeholder="Nom, email, événement..." style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 12px; padding: 0.75rem;">
            </div>
        </div>
    </div>
    <div class="participants-container" style="background: white; padding: 2rem; border-radius: 20px; box-shadow: 0 8px 25px rgba(0,0,0,0.08);">
        <h3 style="font-family: 'Poppins', sans-serif; font-weight: 700; color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-users"></i> Liste des Participants
        </h3>
        <?php if(empty($participants)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                <i class="fas fa-user-times" style="font-size: 4rem; color: #667eea; margin-bottom: 20px; opacity: 0.5;"></i>
                <p style="font-size: 1.1rem;">Aucune inscription pour le moment.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" id="participantsTable" style="margin: 0;">
                    <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <tr>
                            <th style="padding: 1rem; border: none; font-family: 'Montserrat', sans-serif; font-weight: 600;">Participant</th>
                            <th style="padding: 1rem; border: none; font-family: 'Montserrat', sans-serif; font-weight: 600;">Événement</th>
                            <th style="padding: 1rem; border: none; font-family: 'Montserrat', sans-serif; font-weight: 600;">Date Inscription</th>
                            <th style="padding: 1rem; border: none; font-family: 'Montserrat', sans-serif; font-weight: 600;">Statut</th>
                            <th style="padding: 1rem; border: none; font-family: 'Montserrat', sans-serif; font-weight: 600;">Paiement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($participants as $participant): ?>
                            <tr class="participant-row" data-status="<?= $participant['status'] ?>" data-payment="<?= $participant['status_paiment'] ?>" data-search="<?= strtolower($participant['nom'] . ' ' . $participant['prenom'] . ' ' . $participant['email'] . ' ' . $participant['evenement_titre']) ?>" style="border-bottom: 1px solid #eee; transition: all 0.3s ease;">
                                <td style="padding: 1.25rem;">
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div style="width: 45px; height: 45px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 1.1rem; flex-shrink: 0;">
                                            <?= strtoupper(substr($participant['prenom'], 0, 1) . substr($participant['nom'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #2c3e50; margin-bottom: 0.2rem;">
                                                <?= htmlspecialchars($participant['prenom'] . ' ' . $participant['nom']) ?>
                                            </div>
                                            <div style="font-size: 0.85rem; color: #6c757d;">
                                                <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($participant['email']) ?>
                                            </div>
                                            <?php if($participant['filiere']): ?>
                                                <div style="font-size: 0.8rem; color: #667eea; margin-top: 0.2rem;">
                                                    <i class="fas fa-graduation-cap me-1"></i><?= htmlspecialchars($participant['filiere']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem;">
                                    <div style="font-weight: 600; color: #2c3e50; margin-bottom: 0.3rem;">
                                        <?= htmlspecialchars($participant['evenement_titre']) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #6c757d;">
                                        <i class="far fa-calendar-alt me-1"></i><?= date('d/m/Y', strtotime($participant['evenement_date'])) ?>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem; color: #6c757d; font-size: 0.9rem;">
                                    <?= date('d/m/Y H:i', strtotime($participant['date_inscription'])) ?>
                                </td>
                                <td style="padding: 1.25rem;">
                                    <select onchange="updateStatusDirect(<?= $participant['id_inscription'] ?>, this.value, '<?= htmlspecialchars($participant['email']) ?>', '<?= htmlspecialchars($participant['prenom'] . ' ' . $participant['nom']) ?>', '<?= htmlspecialchars($participant['evenement_titre']) ?>', this)" class="status-select" style="<?php 
                                    if($participant['status'] === 'en attente') {
                                        echo 'background-color: #ff9800; color: white;';
                                    } elseif($participant['status'] === 'validée') {
                                        echo 'background-color: #10b981; color: white;';
                                    } else {
                                        echo 'background-color: #ef4444; color: white;';
                                    }
                                    ?>">
                                    <option value="en attente" <?= $participant['status'] === 'en attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="validée" <?= $participant['status'] === 'validée' ? 'selected' : '' ?>>Validée</option>
                                    <option value="refusée" <?= $participant['status'] === 'refusée' ? 'selected' : '' ?>>Refusée</option>
                                </select>
                            </td>
                            <td style="padding: 1.25rem;">
                                <select onchange="updatePaymentDirect(<?= $participant['id_inscription'] ?>, this.value, this)" 
                                class="payment-select" 
                                style=" <?php 
                                if($participant['status_paiment'] === 'payé') {
                                    echo 'background-color: #10b981; color: white;';
                                 } else {
                                    echo 'background-color: #ef4444; color: white;';
                                }
                                ?>">
                                <option value="payé" <?= $participant['status_paiment'] === 'payé' ? 'selected' : '' ?>>Payé</option>
                                <option value="non payé" <?= $participant['status_paiment'] === 'non payé' ? 'selected' : '' ?>>Non payé</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <script>
document.getElementById('filterStatus').addEventListener('change', filterTable);
document.getElementById('filterPayment').addEventListener('change', filterTable);
document.getElementById('searchParticipant').addEventListener('input', filterTable);
function filterTable() {
    const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
    const paymentFilter = document.getElementById('filterPayment').value.toLowerCase();
    const searchTerm = document.getElementById('searchParticipant').value.toLowerCase();
    const rows = document.querySelectorAll('.participant-row');
    
    rows.forEach(row => {
        const status = row.getAttribute('data-status').toLowerCase();
        const payment = row.getAttribute('data-payment').toLowerCase();
        const searchData = row.getAttribute('data-search');
        
        const statusMatch = !statusFilter || status === statusFilter;
        const paymentMatch = !paymentFilter || payment === paymentFilter;
        const searchMatch = !searchTerm || searchData.includes(searchTerm);
        
        if (statusMatch && paymentMatch && searchMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
function updateStatusDirect(inscriptionId, newStatus, email, name, eventTitle, selectElement) {
    const messages = {
        'validée': `Valider l'inscription de ${name} ?\n\n Un email de confirmation sera envoyé automatiquement.`,
        'refusée': `Refuser l'inscription de ${name} ?\n\n Cette action est irréversible.`,
        'en attente': `Remettre l'inscription de ${name} en attente ?`
    };
    if(confirm(messages[newStatus])) {
        selectElement.style.opacity = '0.5';
        selectElement.disabled = true;
        const url = 'process_update_status.php?' + 
                    'id=' + inscriptionId + 
                    '&status=' + encodeURIComponent(newStatus) + 
                    '&email=' + encodeURIComponent(email) + 
                    '&name=' + encodeURIComponent(name) + 
                    '&event=' + encodeURIComponent(eventTitle);
        window.location.href = url;
    } else {
        const originalOption = selectElement.querySelector('[selected]');
        if (originalOption) {
            selectElement.value = originalOption.value;
        }
    }
}
function updatePaymentDirect(inscriptionId, newPayment, selectElement) {
    const messages = {
        'payé': ' Confirmer que le paiement a été reçu ?',
        'non payé': ' Marquer ce paiement comme non reçu ?'
    };
    
    if(confirm(messages[newPayment])) {
        selectElement.style.opacity = '0.5';
        selectElement.disabled = true;
        const url = 'process_update_payment.php?' + 
                    'id=' + inscriptionId + 
                    '&payment=' + encodeURIComponent(newPayment);
        
        window.location.href = url;
    } else {
        const originalOption = selectElement.querySelector('[selected]');
        if (originalOption) {
            selectElement.value = originalOption.value;
        }
    }
}

const allSelects = document.querySelectorAll('.status-select, .payment-select');
allSelects.forEach(select => {
    select.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.05)';
        this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.15)';
    });
    
    select.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
        this.style.boxShadow = 'none';
    });
});
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        if(this.value === 'en attente') {
            this.style.backgroundColor = '#ff9800';
            this.style.color = 'white';
        } else if(this.value === 'validée') {
            this.style.backgroundColor = '#10b981';
            this.style.color = 'white';
        } else {
            this.style.backgroundColor = '#ef4444';
            this.style.color = 'white';
        }
    });
});

document.querySelectorAll('.payment-select').forEach(select => {
    select.addEventListener('change', function() {
        if(this.value === 'payé') {
            this.style.backgroundColor = '#10b981';
            this.style.color = 'white';
        } else {
            this.style.backgroundColor = '#ef4444';
            this.style.color = 'white';
        }
    });
});
</script>

        <?php elseif($current_page === 'emails'): ?>
            <!-- Page Emails & Fichiers -->
            <div class="form-container" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 3rem; border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: 1px solid rgba(255, 255, 255, 0.3); max-width: 900px; margin: 0 auto;">
                <div class="form-header text-center mb-4">
                    <h1 style="font-family: 'Poppins', sans-serif; font-weight: 900; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 2.5rem; margin-bottom: 1rem;">
                        <i class="fas fa-envelope"></i> Communication avec les participants
                    </h1>
                    <p style="color: #6c757d; font-size: 1.05rem;">Envoyez des emails et fichiers aux participants de vos événements</p>
                </div>

                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success" style="border-radius: 15px; font-family: 'Montserrat', sans-serif; border: none; padding: 1.25rem; box-shadow: 0 5px 20px rgba(0,0,0,0.1); background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $_SESSION['success'] ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger" style="border-radius: 15px; font-family: 'Montserrat', sans-serif; border: none; padding: 1.25rem; box-shadow: 0 5px 20px rgba(0,0,0,0.1); background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php
                $events_query = "SELECT id_evenement, titre FROM Evenement WHERE id_organisateur = ? ORDER BY date DESC";
                $events_stmt = $pdo->prepare($events_query);
                $events_stmt->execute([$user_id]);
                $events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <form action="process_send_email.php" method="POST" enctype="multipart/form-data">
                    <div class="input-group-custom" style="position: relative; margin-bottom: 2rem;">
                        <label for="id_evenement" class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-calendar-alt" style="color: #667eea;"></i> Sélectionner l'événement
                        </label>
                        <select class="form-control" id="id_evenement" name="id_evenement" required style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 15px; padding: 1rem 1rem 1rem 3rem; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); font-size: 1rem; width: 100%; appearance: none;">
                            <option value="">Choisissez un événement...</option>
                            <?php foreach($events as $event): ?>
                                <option value="<?= $event['id_evenement'] ?>"><?= htmlspecialchars($event['titre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-group-custom" style="position: relative; margin-bottom: 2rem;">
                        <label for="objet" class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-tag" style="color: #667eea;"></i> Sujet de l'email
                        </label>
                        <input type="text" class="form-control" id="objet" name="objet" required style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 15px; padding: 1rem 1rem 1rem 3rem; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); font-size: 1rem; width: 100%;" placeholder="Ex: Informations importantes pour l'événement">
                    </div>

                    <div class="input-group-custom" style="position: relative; margin-bottom: 2rem;">
                        <label for="contenu" class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-envelope-open-text" style="color: #667eea;"></i> Message
                        </label>
                        <textarea class="form-control" id="contenu" name="contenu" rows="8" required style="border: 2px solid rgba(102, 126, 234, 0.2); border-radius: 15px; padding: 1rem 1rem 1rem 3rem; font-family: 'Montserrat', sans-serif; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); font-size: 1rem; width: 100%; resize: vertical; min-height: 200px;" placeholder="Rédigez votre message ici..."></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" style="font-family: 'Poppins', sans-serif; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 1rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-paperclip" style="color: #667eea;"></i> Fichier joint (optionnel)
                        </label>
                        
                        <div class="file-input-wrapper" style="position: relative; overflow: hidden; display: inline-block; width: 100%;">
                            <input type="file" id="fichier_joint" name="fichier_joint" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt" style="position: absolute; left: -9999px;">
                            <label for="fichier_joint" class="file-input-label" style="display: flex; align-items: center; gap: 1rem; padding: 1.5rem; background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); border: 2px dashed rgba(102, 126, 234, 0.3); border-radius: 15px; cursor: pointer; transition: all 0.3s ease; text-align: center; justify-content: center; width: 100%;">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 1.5rem; color: #667eea;"></i>
                                <span>Cliquez pour ajouter un fichier joint</span>
                            </label>
                        </div>
                        <small class="text-muted d-block text-center mt-2">Formats acceptés: PDF, DOC, DOCX, JPG, JPEG, PNG, TXT (max 10MB)</small>
                    </div>

                    <div class="info-card" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); padding: 1.5rem; border-radius: 15px; margin-bottom: 2rem; border: 2px solid rgba(102, 126, 234, 0.2);">
                        <h4 style="font-family: 'Poppins', sans-serif; font-weight: 600; color: #667eea; margin-bottom: 1rem;">
                            <i class="fas fa-info-circle me-2"></i>Informations d'envoi
                        </h4>
                        <p style="color: #6c757d; margin: 0; font-size: 0.95rem;">
                            L'email sera envoyé à tous les participants <strong>validés</strong> de l'événement sélectionné.
                            Le fichier joint sera stocké dans la base de données et accessible pour les participants.
                        </p>
                    </div>

                    <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 1.25rem 2.5rem; border-radius: 50px; font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease; width: 100%; margin-top: 1.5rem; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);">
                        <i class="fas fa-paper-plane me-2"></i>
                        Envoyer aux participants
                    </button>
                </form>
            </div>

            <!-- Historique des emails envoyés -->
            <div class="form-container" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 2rem; border-radius: 30px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: 1px solid rgba(255, 255, 255, 0.3); max-width: 900px; margin: 2rem auto;">
                <h3 style="font-family: 'Poppins', sans-serif; font-weight: 700; color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-history"></i> Historique des communications
                </h3>

                <?php
                $history_query = "SELECT e.objet, e.date_envoi, ev.titre as evenement_titre, e.fichier_joint 
                                 FROM Email e 
                                 JOIN Evenement ev ON e.id_evenement = ev.id_evenement 
                                 WHERE ev.id_organisateur = ? 
                                 ORDER BY e.date_envoi DESC 
                                 LIMIT 10";
                $history_stmt = $pdo->prepare($history_query);
                $history_stmt->execute([$user_id]);
                $email_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <?php if(empty($email_history)): ?>
                    <p style="text-align: center; color: #6c757d; padding: 2rem;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                        Aucun email envoyé pour le moment.
                    </p>
                <?php else: ?>
                    <div class="email-history">
                        <?php foreach($email_history as $email): ?>
                            <div class="email-item" style="background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #667eea;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                    <h5 style="font-family: 'Poppins', sans-serif; font-weight: 600; color: #2c3e50; margin: 0; flex: 1;">
                                        <?= htmlspecialchars($email['objet']) ?>
                                    </h5>
                                    <span style="color: #6c757d; font-size: 0.85rem;">
                                        <?= date('d/m/Y H:i', strtotime($email['date_envoi'])) ?>
                                    </span>
                                </div>
                                <p style="color: #6c757d; margin: 0 0 0.5rem 0; font-size: 0.9rem;">
                                    Événement: <strong><?= htmlspecialchars($email['evenement_titre']) ?></strong>
                                </p>
                                <?php if($email['fichier_joint']): ?>
                                    <p style="color: #667eea; margin: 0; font-size: 0.85rem;">
                                        <i class="fas fa-paperclip me-1"></i>Fichier joint: <?= htmlspecialchars($email['fichier_joint']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <script>
                document.getElementById('fichier_joint').addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name;
                    if (fileName) {
                        const label = document.querySelector('#fichier_joint + label span');
                        label.textContent = '✓ ' + fileName;
                        label.style.color = '#667eea';
                        label.style.fontWeight = '600';
                    }
                });

                const inputsEmail = document.querySelectorAll('.form-control, select');
                inputsEmail.forEach(input => {
                    input.addEventListener('focus', function() {
                        this.style.borderColor = '#667eea';
                        this.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.1)';
                        this.style.background = 'white';
                    });

                    input.addEventListener('blur', function() {
                        if (this.value.trim() !== '') {
                            this.style.borderColor = '#4ade80';
                        } else {
                            this.style.borderColor = 'rgba(102, 126, 234, 0.2)';
                            this.style.boxShadow = 'none';
                        }
                    });
                });

                const submitBtnEmail = document.querySelector('.btn-submit');
                if (submitBtnEmail) {
                    submitBtnEmail.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-3px)';
                        this.style.boxShadow = '0 15px 40px rgba(102, 126, 234, 0.5)';
                    });

                    submitBtnEmail.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = '0 10px 30px rgba(102, 126, 234, 0.4)';
                    });
                }
            </script>
        <?php endif; ?>


    <?php if($current_page === 'attestations'): ?>
    <div class="attestations-section">
        <div class="attestation-form-card">
            <div class="form-header">
                <i class="fas fa-certificate"></i>
                <h2>Générer et envoyer les attestations</h2>
                <p>Sélectionnez un événement pour générer et envoyer les attestations aux participants</p>
            </div>

            <form id="attestationForm" method="POST" action="process_attestations.php">
                <div class="form-group">
                    <label for="eventSelect">
                        <i class="fas fa-calendar-check"></i>
                        Choisir l'événement
                    </label>
                    <select id="eventSelect" name="id_evenement" class="form-select" required>
                        <option value="">-- Sélectionner un événement --</option>
                        <?php foreach($events_dropdown as $evt): ?>
                            <option value="<?= $evt['id_evenement'] ?>">
                                <?= htmlspecialchars($evt['titre']) ?> - 
                                <?= date('d/m/Y', strtotime($evt['date'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="participantsPreview" class="participants-preview" style="display: none;">
                    <h3><i class="fas fa-users"></i> Participants de l'événement</h3>
                    <div id="participantsList" class="participants-list"></div>
                    <div class="participants-count">
                        <strong>Total : <span id="totalParticipants">0</span> participant(s)</strong>
                    </div>
                </div>

                <button type="submit" class="btn-send-attestations" id="sendBtn" disabled>
                    <i class="fas fa-paper-plane"></i>
                    Envoyer les attestations
                </button>
            </form>
        </div>

        <div class="attestations-history">
    <h2><i class="fas fa-history"></i> Historique des attestations envoyées</h2>
    <div id="historyList" class="history-list">
        <?php if (!empty($attestations_history)): ?>
            <div class="history-table-wrapper">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Date d'envoi</th>
                            <th><i class="fas fa-calendar-alt"></i> Événement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attestations_history as $attestation): ?>
                            <tr>
                                <td>
                                    <span class="date-badge">
                                        <?= date('d/m/Y', strtotime($attestation['date_generation'])) ?>
                                        <small><?= date('H:i', strtotime($attestation['date_generation'])) ?></small>
                                    </span>
                                </td>
                                <td>
                                    <div class="event-info">
                                        <strong><?= htmlspecialchars($attestation['nom_evenement']) ?></strong>
                                        <small><?= date('d/m/Y', strtotime($attestation['date_evenement'])) ?></small>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="history-stats">
                <p><i class="fas fa-chart-bar"></i> 
                    <strong><?= count($attestations_history) ?></strong> attestation(s) envoyée(s) au total
                </p>
            </div>
        <?php else: ?>
            <p class="empty-state">
                <i class="fas fa-inbox"></i>
                Aucune attestation envoyée pour le moment
            </p>
        <?php endif; ?>
    </div>
</div>
    </div>
    <?php endif; ?>
    </div>
    <script>
        document.getElementById('eventSelect').addEventListener('change', function() {
            const eventId = this.value;
            const sendBtn = document.getElementById('sendBtn');
            const previewDiv = document.getElementById('participantsPreview');
            
            if (!eventId) {
                previewDiv.style.display = 'none';
                sendBtn.disabled = true;
                return;
            }

            // Charger les participants via AJAX
            fetch(`get_event_participants.php?id_evenement=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.participants.length > 0) {
                        displayParticipants(data.participants);
                        previewDiv.style.display = 'block';
                        sendBtn.disabled = false;
                    } else {
                        alert('Aucun participant validé pour cet événement.');
                        previewDiv.style.display = 'none';
                        sendBtn.disabled = true;
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors du chargement des participants');
                });
        });

        function displayParticipants(participants) {
            const listDiv = document.getElementById('participantsList');
            const countSpan = document.getElementById('totalParticipants');
            
            listDiv.innerHTML = participants.map(p => `
                <div class="participant-item">
                    <div class="participant-avatar">
                        ${p.prenom.charAt(0).toUpperCase()}
                    </div>
                    <div class="participant-info">
                        <div class="participant-name">${p.prenom} ${p.nom}</div>
                        <div class="participant-email">${p.email}</div>
                    </div>
                </div>
            `).join('');
            
            countSpan.textContent = participants.length;
        }

        document.getElementById('attestationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const sendBtn = document.getElementById('sendBtn');
            const originalText = sendBtn.innerHTML;
            
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="loading-spinner"></span> Envoi en cours...';
            
            const formData = new FormData(this);
            
            fetch('process_attestations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(` ${data.message}\n${data.sent} attestation(s) envoyée(s)`);
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'envoi des attestations');
            })
            .finally(() => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalText;
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function modifyEvent(eventId) {
            window.location.href = 'modify_event.php?id=' + eventId;
        }

        function deleteEvent(eventId, eventTitle) {
            if(confirm('Êtes-vous sûr de vouloir supprimer l\'événement "' + eventTitle + '" ?\n\nUn email sera envoyé à tous les participants inscrits.')) {
                window.location.href = 'delete_event.php?id=' + eventId;
            }
        }

        if(document.getElementById('date')) {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date').setAttribute('min', today);
        }
    </script>
</body>
</html>