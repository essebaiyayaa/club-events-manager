<?php 
session_start();
require_once __DIR__ . '/../config/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'participant') {
    header('Location: auth/login.php?redirect=' . urlencode('inscription_evenement.php?id=' . ($_GET['id'] ?? '')));
    exit();
}

// Récupérer l'ID de l'événement
$id_evenement = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_evenement <= 0) {
    header('Location: evenements.php');
    exit();
}

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT nom, prenom, email, filiere, date_naissance FROM Utilisateur WHERE id_utilisateur = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: evenements.php');
    exit();
}

// Récupérer les informations de l'événement
$stmt = $pdo->prepare("
    SELECT e.*, c.nom_club 
    FROM Evenement e 
    LEFT JOIN Club c ON e.id_club = c.id_club 
    WHERE e.id_evenement = ?
");
$stmt->execute([$id_evenement]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: evenements.php');
    exit();
}

// Vérifier si l'utilisateur est déjà inscrit
$stmt = $pdo->prepare("SELECT id_inscription FROM Inscription WHERE id_utilisateur = ? AND id_evenement = ?");
$stmt->execute([$_SESSION['user_id'], $id_evenement]);
$already_registered = $stmt->fetch();

// Vérifier le nombre d'inscrits
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Inscription WHERE id_evenement = ? AND status != 'refusée'");
$stmt->execute([$id_evenement]);
$inscrits = $stmt->fetch()['total'];

$places_restantes = $event['capacite_max'] - $inscrits;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= htmlspecialchars($event['titre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <!-- Fichier Style -->
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <style>
        body { background: #f8f9fa; font-family: 'Montserrat', sans-serif; }
        .inscription-container { padding: 60px 0; min-height: 100vh; }
        .inscription-card {
            background: white; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            overflow: hidden; max-width: 900px; margin: 0 auto;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px; color: white; text-align: center;
        }
        .card-header h1 {
            font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 2rem; margin-bottom: 10px;
        }
        .card-header p { font-size: 1rem; opacity: 0.95; margin: 0; }
        .card-body { padding: 40px; }
        .section-title {
            font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.3rem;
            color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px;
            border-bottom: 3px solid #667eea; display: inline-block;
        }
        .info-box {
            background: #f8f9fa; border-radius: 12px; padding: 20px; margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        .info-item { display: flex; align-items: center; margin-bottom: 12px; }
        .info-item:last-child { margin-bottom: 0; }
        .info-item i { color: #667eea; font-size: 1.1rem; width: 30px; }
        .info-item strong { color: #2c3e50; margin-right: 8px; }
        .form-label {
            font-weight: 600; color: #2c3e50; font-size: 0.95rem; margin-bottom: 8px;
        }
        .form-control {
            border: 2px solid #e0e0e0; border-radius: 10px; padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        .form-control:disabled {
            background-color: #f8f9fa; color: #6c757d;
        }
        .btn-confirm {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white; border: none; padding: 15px 40px; border-radius: 12px;
            font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.1rem;
            transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-confirm:hover {
            transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        .btn-back {
            background: #6c757d; color: white; border: none; padding: 15px 40px;
            border-radius: 12px; font-family: 'Poppins', sans-serif; font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-back:hover { background: #5a6268; transform: translateY(-2px); }
        .alert-custom {
            border-radius: 12px; padding: 15px 20px; margin-bottom: 20px;
            font-family: 'Montserrat', sans-serif;
        }
        .captcha-container { margin: 25px 0; }
        .tarif-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 8px 20px; border-radius: 20px; font-weight: 700;
            display: inline-block; font-size: 1.1rem;
        }
        .places-badge {
            background: #10b981; color: white; padding: 5px 15px; border-radius: 15px;
            font-weight: 600; font-size: 0.9rem; display: inline-block;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="inscription-container">
        <div class="container">
            <div class="inscription-card">
                <div class="card-header">
                    <h1><i class="fas fa-clipboard-check me-2"></i>Inscription à l'événement</h1>
                    <p>Veuillez vérifier vos informations avant de confirmer</p>
                </div>

                <div class="card-body">
                    <?php if ($already_registered): ?>
                    <div class="alert alert-custom alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Attention :</strong> Vous êtes déjà inscrit à cet événement.
                    </div>
                    <a href="evenements.php" class="btn btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Retour aux événements
                    </a>
                    <?php elseif ($places_restantes <= 0): ?>
                    <div class="alert alert-custom alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Désolé :</strong> Cet événement est complet. Il n'y a plus de places disponibles.
                    </div>
                    <a href="evenements.php" class="btn btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Retour aux événements
                    </a>
                    <?php else: ?>

                    <!-- Informations de l'événement -->
                    <h3 class="section-title"><i class="fas fa-calendar-alt me-2"></i>Détails de l'événement</h3>
                    <div class="info-box">
                        <div class="info-item">
                            <i class="fas fa-tag"></i>
                            <strong>Événement :</strong> 
                            <span><?= htmlspecialchars($event['titre']) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="far fa-calendar"></i>
                            <strong>Date :</strong> 
                            <span><?= date('d/m/Y', strtotime($event['date'])) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <strong>Lieu :</strong> 
                            <span><?= htmlspecialchars($event['lieu']) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-users"></i>
                            <strong>Places restantes :</strong> 
                            <span class="places-badge"><?= $places_restantes ?> / <?= $event['capacite_max'] ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <strong>Tarif :</strong> 
                            <span class="tarif-badge">
                                <?= $event['tarif'] == 0 ? 'Gratuit' : number_format($event['tarif'], 2) . ' DH' ?>
                            </span>
                        </div>
                        <?php if ($event['nom_club']): ?>
                        <div class="info-item">
                            <i class="fas fa-users-cog"></i>
                            <strong>Organisé par :</strong> 
                            <span><?= htmlspecialchars($event['nom_club']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Formulaire d'inscription -->
                    <h3 class="section-title"><i class="fas fa-user me-2"></i>Vos informations</h3>
                    
                    <form action="process_inscription.php" method="POST" id="inscriptionForm">
                        <input type="hidden" name="id_evenement" value="<?= $id_evenement ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nom" class="form-label">
                                    <i class="fas fa-user me-1"></i>Nom
                                </label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?= htmlspecialchars($user['nom']) ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prenom" class="form-label">
                                    <i class="fas fa-user me-1"></i>Prénom
                                </label>
                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                       value="<?= htmlspecialchars($user['prenom']) ?>" disabled>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-1"></i>Email
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="filiere" class="form-label">
                                    <i class="fas fa-graduation-cap me-1"></i>Filière
                                </label>
                                <input type="text" class="form-control" id="filiere" name="filiere" 
                                       value="<?= htmlspecialchars($user['filiere'] ?? 'Non spécifiée') ?>" disabled>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="date_naissance" class="form-label">
                                <i class="far fa-calendar-alt me-1"></i>Date de naissance
                            </label>
                            <input type="text" class="form-control" id="date_naissance" name="date_naissance" 
                                   value="<?= $user['date_naissance'] ? date('d/m/Y', strtotime($user['date_naissance'])) : 'Non spécifiée' ?>" 
                                   disabled>
                        </div>

                        <!-- CAPTCHA -->
                        <div class="captcha-container text-center">
                            <div class="g-recaptcha" data-sitekey="6LcDCeMrAAAAAKaW6QNlkXBMh6AvKkbARXAg_sFY
"></div>
                        </div>

                        <!-- Boutons -->
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="evenements.php" class="btn btn-back">
                                <i class="fas fa-arrow-left me-2"></i>Retour
                            </a>
                            <button type="submit" class="btn btn-confirm" id="btnSubmit">
                                <i class="fas fa-check-circle me-2"></i>Confirmer l'inscription
                            </button>
                        </div>
                    </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('inscriptionForm')?.addEventListener('submit', function(e) {
            const recaptchaResponse = grecaptcha.getResponse();
            if (!recaptchaResponse) {
                e.preventDefault();
                alert('Veuillez compléter le CAPTCHA avant de continuer.');
                return false;
            }
        });
    </script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>