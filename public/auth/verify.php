<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Utiliser le fichier config.php existant
require_once __DIR__ . '/../../config/config.php';

$message = '';
$success = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // CORRIGÉ : Utiliser les bons noms de table et colonnes selon votre SQL
        $stmt = $pdo->prepare("SELECT id_utilisateur, email, nom, prenom, is_verified, created_at FROM Utilisateur WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['is_verified']) {
                $message = "Ce compte a déjà été vérifié. Vous pouvez vous connecter.";
                $success = true;
            } else {
                // Vérifier l'expiration du token (24 heures)
                $created = new DateTime($user['created_at']);
                $now = new DateTime();
                $diff = $now->diff($created);
                $hours = ($diff->days * 24) + $diff->h;

                if ($hours > 24) {
                    $message = "Ce lien de vérification a expiré (valide 24h). Veuillez vous réinscrire.";
                } else {
                    // Activer le compte - CORRIGÉ : utiliser id_utilisateur
                    $stmt = $pdo->prepare("UPDATE Utilisateur SET is_verified = 1, verification_token = NULL WHERE id_utilisateur = ?");
                    $stmt->execute([$user['id_utilisateur']]);

                    $message = "Félicitations " . htmlspecialchars($user['prenom']) . " ! Votre compte a été vérifié avec succès. Vous pouvez maintenant vous connecter.";
                    $success = true;
                }
            }
        } else {
            $message = "Token de vérification invalide ou expiré.";
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données : " . $e->getMessage();
        error_log("Erreur verify.php: " . $e->getMessage());
    }
} else {
    $message = "Token de vérification manquant dans l'URL.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification du compte - CampusEvent</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Cercles décoratifs animés */
        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite ease-in-out;
        }

        .bg-circle-1 {
            width: 300px;
            height: 300px;
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }

        .bg-circle-2 {
            width: 200px;
            height: 200px;
            bottom: -50px;
            right: -50px;
            animation-delay: 5s;
        }

        .bg-circle-3 {
            width: 150px;
            height: 150px;
            top: 50%;
            left: 10%;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            50% {
                transform: translate(50px, 50px) scale(1.1);
            }
        }

        .verification-container {
            max-width: 600px;
            width: 100%;
            position: relative;
            z-index: 10;
        }

        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .verification-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }

        .verification-logo {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .verification-body {
            padding: 3rem 2.5rem;
            text-align: center;
        }

        .status-icon-wrapper {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: scaleIn 0.5s ease 0.3s both;
        }

        .status-icon-wrapper::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0) rotate(-180deg);
            }
            to {
                transform: scale(1) rotate(0deg);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.7;
            }
        }

        .success-icon-wrapper {
            background: linear-gradient(135deg, #51cf66, #37b24d);
        }

        .success-icon-wrapper::before {
            background: rgba(81, 207, 102, 0.3);
        }

        .error-icon-wrapper {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
        }

        .error-icon-wrapper::before {
            background: rgba(255, 107, 107, 0.3);
        }

        .status-icon {
            font-size: 3.5rem;
            color: white;
        }

        .status-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .decorative-line {
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 1.5rem auto;
            border-radius: 2px;
        }

        .status-message {
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            color: #6c757d;
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .button-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-custom {
            padding: 0.9rem 2rem;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-custom::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-custom:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary-custom {
            background: white;
            color: #667eea !important;
            border: 2px solid #667eea;
        }

        .btn-secondary-custom:hover {
            background: #667eea;
            color: white !important;
            border-color: #667eea;
        }

        .help-section {
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 1px solid #e0e0e0;
        }

        .help-text {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .help-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .help-text a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .footer-text {
            text-align: center;
            margin-top: 2rem;
            color: rgba(255, 255, 255, 0.95);
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .verification-body {
                padding: 2.5rem 1.5rem;
            }

            .status-icon-wrapper {
                width: 100px;
                height: 100px;
            }

            .status-icon {
                font-size: 3rem;
            }

            .status-title {
                font-size: 1.6rem;
            }

            .verification-logo {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-circle bg-circle-1"></div>
    <div class="bg-circle bg-circle-2"></div>
    <div class="bg-circle bg-circle-3"></div>

    <div class="verification-container">
        <div class="verification-card">
            <div class="verification-header">
                <div class="verification-logo">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <div>Campus</div>
                        <div>Event</div>
                    </div>
                </div>
            </div>

            <div class="verification-body">
                <div class="status-icon-wrapper <?php echo $success ? 'success-icon-wrapper' : 'error-icon-wrapper'; ?>">
                    <?php if ($success): ?>
                        <i class="fas fa-check-circle status-icon"></i>
                    <?php else: ?>
                        <i class="fas fa-times-circle status-icon"></i>
                    <?php endif; ?>
                </div>

                <h1 class="status-title">
                    <?php echo $success ? 'Vérification Réussie !' : 'Erreur de Vérification'; ?>
                </h1>

                <div class="decorative-line"></div>

                <p class="status-message">
                    <?php echo $message; ?>
                </p>

                <div class="button-group">
                    <?php if ($success): ?>
                        <a href="login.php" class="btn-custom btn-primary-custom">
                            <i class="fas fa-sign-in-alt"></i>
                            Se connecter maintenant
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="btn-custom btn-primary-custom">
                            <i class="fas fa-user-plus"></i>
                            Réessayer l'inscription
                        </a>
                        <a href="../index.php" class="btn-custom btn-secondary-custom">
                            <i class="fas fa-home"></i>
                            Retour à l'accueil
                        </a>
                    <?php endif; ?>
                </div>

                <div class="help-section">
                    <p class="help-text">
                        <i class="fas fa-question-circle"></i>
                        Besoin d'aide ? Contactez-nous à : 
                        <a href="mailto:<?php echo FROM_EMAIL; ?>"><?php echo FROM_EMAIL; ?></a>
                    </p>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>