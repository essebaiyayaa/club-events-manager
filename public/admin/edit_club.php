<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: C:/xampp/htdocs/campusEvents/public/auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: dashboard_admin.php?page=clubs');
    exit();
}

$club_id = intval($_GET['id']);


$query = "SELECT c.*, u.nom as president_nom, u.prenom as president_prenom 
          FROM Club c 
          LEFT JOIN Utilisateur u ON c.id_president = u.id_utilisateur 
          WHERE c.id_club = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$club_id]);
$club = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$club) {
    $_SESSION['error'] = "Club introuvable.";
    header('Location: dashboard_admin.php?page=clubs');
    exit();
}

$organisateurs_query = "SELECT id_utilisateur, nom, prenom, email 
                       FROM Utilisateur 
                       WHERE role = 'organisateur' 
                       ORDER BY nom, prenom";
$organisateurs_stmt = $pdo->prepare($organisateurs_query);
$organisateurs_stmt->execute();
$organisateurs = $organisateurs_stmt->fetchAll(PDO::FETCH_ASSOC);

$user_name = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Club - CampusEvent</title>
    
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
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 2rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            text-decoration: none;
        }

        .form-container {
            background: white;
            padding: 3rem;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .form-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .form-header h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .form-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            border: 2px solid rgba(255, 107, 107, 0.2);
            border-radius: 12px;
            padding: 0.8rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #ff6b6b;
            box-shadow: 0 0 0 4px rgba(255, 107, 107, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.4);
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .alert {
            border-radius: 15px;
            margin-bottom: 2rem;
            border: none;
        }

        .club-info {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.1) 0%, rgba(238, 90, 111, 0.1) 100%);
            border: 2px solid rgba(255, 107, 107, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .club-info h4 {
            color: #ff6b6b;
            margin-bottom: 1rem;
            font-family: 'Poppins', sans-serif;
        }

        .club-info p {
            margin: 0.5rem 0;
            color: #2c3e50;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .form-actions .btn-submit {
            margin-top: 0;
            flex: 2;
        }

        .form-actions .btn-cancel {
            margin-top: 0;
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard_admin.php?page=clubs" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Retour aux clubs
        </a>

        <div class="form-container">
            <div class="form-header">
                <h1>✏️ Modifier le Club</h1>
                <p>Mettez à jour les informations du club</p>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $_SESSION['error'] ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $_SESSION['success'] ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <div class="club-info">
                <h4><i class="fas fa-info-circle"></i> Informations actuelles</h4>
                <p><strong>Nom :</strong> <?= htmlspecialchars($club['nom_club']) ?></p>
                <p><strong>Président actuel :</strong> 
                    <?= $club['president_nom'] ? htmlspecialchars($club['president_prenom'] . ' ' . $club['president_nom']) : 'Aucun président' ?>
                </p>
                <p><strong>Description :</strong> <?= htmlspecialchars($club['description'] ?: 'Aucune description') ?></p>
            </div>

            <form action="process_edit_club.php" method="POST">
                <input type="hidden" name="id_club" value="<?= $club['id_club'] ?>">
                
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-users"></i>
                        Nom du Club
                    </label>
                    <input type="text" class="form-control" name="nom_club" 
                           value="<?= htmlspecialchars($club['nom_club']) ?>" 
                           required
                           placeholder="Entrez le nom du club">
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i>
                        Description
                    </label>
                    <textarea class="form-control" name="description" rows="4" 
                              placeholder="Décrivez le club, ses objectifs, ses activités..."><?= htmlspecialchars($club['description']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-user-tie"></i>
                        Président du Club
                    </label>
                    <select class="form-control" name="id_president">
                        <option value="">Sélectionner un organisateur...</option>
                        <?php foreach($organisateurs as $org): ?>
                            <option value="<?= $org['id_utilisateur'] ?>" 
                                <?= $org['id_utilisateur'] == $club['id_president'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($org['prenom'] . ' ' . $org['nom']) ?> 
                                (<?= htmlspecialchars($org['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Laisser vide si le club n'a pas de président
                    </small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save me-2"></i>
                        Enregistrer les modifications
                    </button>
                    <button type="button" class="btn-cancel" onclick="location.href='dashboard_admin.php?page=clubs'">
                        <i class="fas fa-times me-2"></i>
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            const formControls = document.querySelectorAll('.form-control');
            
            formControls.forEach(control => {
                control.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 5px 15px rgba(255, 107, 107, 0.1)';
                });
                
                control.addEventListener('blur', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });

            const form = document.querySelector('form');
            const nomClub = document.querySelector('input[name="nom_club"]');
            
            nomClub.addEventListener('input', function() {
                if (this.value.trim().length < 2) {
                    this.style.borderColor = '#ff6b6b';
                } else {
                    this.style.borderColor = '#28a745';
                }
            });
        });
    </script>
</body>
</html>