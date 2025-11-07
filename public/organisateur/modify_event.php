<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';

// Vérifier que l'utilisateur est connecté et est organisateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: C:/xampp/htdocs/campusEvents/public/auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: dashboard_organisateur.php?page=events');
    exit();
}

$event_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Récupérer l'événement
$query = "SELECT * FROM Evenement WHERE id_evenement = ? AND id_organisateur = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$event_id, $user_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    $_SESSION['error'] = "Événement introuvable ou vous n'avez pas les droits.";
    header('Location: dashboard_organisateur.php?page=events');
    exit();
}

$user_name = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'événement - CampusEvent</title>
    
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(118, 75, 162, 0.2) 0%, transparent 50%);
            pointer-events: none;
            z-index: 1;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 3rem;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .form-header {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .form-header h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .form-header p {
            color: #6c757d;
            font-size: 1.05rem;
        }

        .current-image-container {
            position: relative;
            width: 100%;
            max-width: 350px;
            margin: 0 auto 1.5rem;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }

        .current-image {
            width: 100%;
            height: auto;
            display: block;
        }

        .image-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .form-label {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: #667eea;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 2rem;
        }

        .form-control, .form-select {
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 15px;
            padding: 1rem 1rem 1rem 3rem;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: white;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1.2rem;
            z-index: 10;
            pointer-events: none;
        }

        textarea.form-control {
            padding-left: 3rem;
            resize: vertical;
            min-height: 150px;
        }

        .form-check {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 1.25rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 2px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }

        .form-check:hover {
            border-color: #667eea;
        }

        .form-check-input {
            width: 1.5rem;
            height: 1.5rem;
            cursor: pointer;
            border: 2px solid #667eea;
        }

        .form-check-input:checked {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }

        .form-check-label {
            font-weight: 600;
            color: #2c3e50;
            cursor: pointer;
            margin-left: 0.5rem;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1.25rem 2.5rem;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1.5rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-submit:hover {
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
        }

        .alert {
            border-radius: 15px;
            font-family: 'Montserrat', sans-serif;
            border: none;
            padding: 1.25rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .row {
            margin-bottom: 1rem;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 2px dashed rgba(102, 126, 234, 0.3);
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            justify-content: center;
        }

        .file-input-label:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
        }

        .file-input-label i {
            font-size: 1.5rem;
            color: #667eea;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .form-container {
                padding: 2rem 1.5rem;
                border-radius: 20px;
            }

            .form-header h1 {
                font-size: 2rem;
            }

            .form-control, .form-select {
                padding: 0.9rem 0.9rem 0.9rem 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard_organisateur.php?page=events" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Retour à mes événements
        </a>

        <div class="form-container">
            <div class="form-header">
                <h1> Modifier l'événement</h1>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $_SESSION['error'] ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="process_modify_event.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="event_id" value="<?= $event['id_evenement'] ?>">
                
                <div class="input-group-custom">
                    <label for="titre" class="form-label">
                        Titre de l'événement
                    </label>
                    <input type="text" class="form-control" id="titre" name="titre" value="<?= htmlspecialchars($event['titre']) ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-image"></i> Affiche de l'événement
                    </label>
                    <?php if($event['affiche_url']): ?>
                        <div class="current-image-container">
                            <div class="image-overlay">Affiche actuelle</div>
                            <img src="../<?= htmlspecialchars($event['affiche_url']) ?>" alt="Affiche actuelle" class="current-image">
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center mb-3">Aucune affiche actuellement</p>
                    <?php endif; ?>
                    
                    <div class="file-input-wrapper">
                        <input type="file" id="affiche" name="affiche" accept="image/*">
                        <label for="affiche" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Cliquez pour changer l'affiche</span>
                        </label>
                    </div>
                    <small class="text-muted d-block text-center mt-2">Formats acceptés: JPG, PNG, GIF (max 5MB)</small>
                </div>

                <div class="input-group-custom">
                    <label for="description" class="form-label">
                        <i class="fas fa-align-left"></i> Description détaillée
                    </label>
                    
                    <textarea class="form-control" id="description" name="description" required><?= htmlspecialchars($event['description']) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group-custom">
                            <label for="date" class="form-label">
                                <i class="fas fa-calendar-alt"></i> Date de l'événement
                            </label>
                           
                            <input type="date" class="form-control" id="date" name="date" value="<?= $event['date'] ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group-custom">
                            <label for="lieu" class="form-label">
                                <i class="fas fa-map-marker-alt"></i> Lieu
                            </label>
                            
                            <input type="text" class="form-control" id="lieu" name="lieu" value="<?= htmlspecialchars($event['lieu']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group-custom">
                            <label for="capacite_max" class="form-label">
                                <i class="fas fa-users"></i> Capacité maximale
                            </label>
                            
                            <input type="number" class="form-control" id="capacite_max" name="capacite_max" value="<?= $event['capacite_max'] ?>" min="1" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group-custom">
                            <label for="tarif" class="form-label">
                                <i class="fas fa-euro-sign"></i> Tarif
                            </label>
                            
                            <input type="number" step="0.01" class="form-control" id="tarif" name="tarif" value="<?= $event['tarif'] ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="notify_participants" name="notify_participants" checked>
                    <label class="form-check-label" for="notify_participants">
                             Notifier les participants inscrits par email
                    </label>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-rocket me-2"></i>
                    Enregistrer les modifications
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
   
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date').setAttribute('min', today);

        document.getElementById('affiche').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                const label = document.querySelector('.file-input-label span');
                label.textContent = '✓ ' + fileName;
                label.style.color = '#667eea';
            }
        });
    </script>
</body>
</html>