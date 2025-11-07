<?php 
session_start();
require_once __DIR__ . '/../config/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

$id_evenement = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Récupérer les infos de l'événement
$event = null;
if ($id_evenement > 0) {
    $stmt = $pdo->prepare("SELECT titre, date, lieu, tarif FROM Evenement WHERE id_evenement = ?");
    $stmt->execute([$id_evenement]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
}

$user_email = $_SESSION['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription en attente - CampusEvent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Montserrat', sans-serif;
            padding: 20px;
        }
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 650px;
            width: 100%;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-header-custom {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            padding: 40px;
            text-align: center;
            color: white;
        }
        .card-icon {
            font-size: 5rem;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .card-content {
            padding: 40px;
        }
        .card-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .card-message {
            font-size: 1.05rem;
            color: #6c757d;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .email-box {
            background: #fff7ed;
            border: 2px solid #fed7aa;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }
        .email-box i {
            color: #f59e0b;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .email-box strong {
            color: #2c3e50;
            font-size: 1.1rem;
        }
        .email-address {
            color: #667eea;
            font-weight: 700;
            font-size: 1.05rem;
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            border-left: 4px solid #667eea;
        }
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        .info-item:last-child { margin-bottom: 0; }
        .info-item i {
            color: #667eea;
            font-size: 1.1rem;
            width: 30px;
        }
        .info-item strong {
            color: #2c3e50;
            margin-right: 8px;
        }
        .warning-box {
            background: #fef3c7;
            border: 2px solid #fbbf24;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
        }
        .warning-box i {
            color: #f59e0b;
            font-size: 1.5rem;
            margin-right: 10px;
        }
        .warning-box p {
            margin: 0;
            color: #78350f;
            font-weight: 600;
        }
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .steps {
            margin: 30px 0;
        }
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .step-number {
            background: #667eea;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
            margin-right: 15px;
        }
        .step-content {
            flex: 1;
            padding-top: 5px;
        }
        .step-content strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
        }
        .step-content p {
            margin: 0;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="card-header-custom">
            <div class="card-icon">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <h1 style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 2rem; margin: 0;">
                Vérifiez votre email !
            </h1>
        </div>

        <div class="card-content">
            <h2 class="card-title">📧 Email de confirmation envoyé</h2>
            <p class="card-message">
                Votre demande d'inscription a bien été prise en compte. Pour finaliser votre inscription, 
                vous devez confirmer votre adresse email.
            </p>

            <div class="email-box">
                <i class="fas fa-paper-plane"></i>
                <p><strong>Un email a été envoyé à :</strong></p>
                <p class="email-address"><?= htmlspecialchars($user_email) ?></p>
            </div>

            <?php if ($event): ?>
            <div class="info-box">
                <h4 style="color: #2c3e50; font-family: 'Poppins', sans-serif; font-weight: 700; margin-bottom: 20px;">
                    <i class="fas fa-info-circle me-2"></i>Récapitulatif
                </h4>
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
                    <i class="fas fa-money-bill-wave"></i>
                    <strong>Tarif :</strong>
                    <span><?= $event['tarif'] == 0 ? 'Gratuit' : number_format($event['tarif'], 2) . ' DH' ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="steps">
                <h4 style="color: #2c3e50; font-family: 'Poppins', sans-serif; font-weight: 700; margin-bottom: 20px;">
                    Étapes suivantes :
                </h4>
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <strong>Vérifiez votre boîte de réception</strong>
                        <p>Ouvrez l'email que nous venons de vous envoyer</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <strong>Cliquez sur le lien de confirmation</strong>
                        <p>Validez votre inscription en cliquant sur le bouton dans l'email</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <strong>C'est terminé !</strong>
                        <p>Votre inscription sera confirmée et vous recevrez tous les détails</p>
                    </div>
                </div>
            </div>

            <div class="warning-box">
                <i class="fas fa-clock"></i>
                <p>⏰ Le lien de confirmation expire dans <strong>1 heure</strong>. 
                Si vous ne confirmez pas dans ce délai, vous devrez vous réinscrire.</p>
            </div>

            <p style="color: #6c757d; font-size: 0.95rem; margin-top: 20px;">
                <i class="fas fa-question-circle me-1"></i>
                Vous n'avez pas reçu l'email ? Vérifiez votre dossier spam ou courrier indésirable.
            </p>

            <div class="text-center">
                <a href="evenements.php" class="btn-home">
                    <i class="fas fa-calendar-alt me-2"></i>Retour aux événements
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>