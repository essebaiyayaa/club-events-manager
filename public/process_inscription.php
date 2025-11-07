<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/email_functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'participant') {
    header('Location: auth/login.php');
    exit();
}

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: evenements.php');
    exit();
}

// Récupérer l'ID de l'événement
$id_evenement = isset($_POST['id_evenement']) ? (int)$_POST['id_evenement'] : 0;
$id_utilisateur = $_SESSION['user_id'];

if ($id_evenement <= 0) {
    header('Location: evenements.php?error=invalid_event');
    exit();
}

// Vérifier le CAPTCHA
$recaptcha_secret = '6LcDCeMrAAAAAC8Yv7TQI4VKfCTEfTGm5UwPuI9n';
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

$recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
$recaptcha_data = [
    'secret' => $recaptcha_secret,
    'response' => $recaptcha_response,
    'remoteip' => $_SERVER['REMOTE_ADDR']
];

$options = [
    'http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($recaptcha_data)
    ]
];

$context = stream_context_create($options);
$verify = file_get_contents($recaptcha_url, false, $context);
$captcha_success = json_decode($verify);

if (!$captcha_success->success) {
    header('Location: inscription_evenement.php?id=' . $id_evenement . '&error=captcha');
    exit();
}

try {
    // Vérifier si l'événement existe et a des places disponibles
    $stmt = $pdo->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM Inscription WHERE id_evenement = e.id_evenement AND status != 'refusée') as inscrits
        FROM Evenement e 
        WHERE e.id_evenement = ?
    ");
    $stmt->execute([$id_evenement]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        header('Location: evenements.php?error=event_not_found');
        exit();
    }

    // Vérifier les places disponibles
    $places_restantes = $event['capacite_max'] - $event['inscrits'];
    if ($places_restantes <= 0) {
        header('Location: inscription_evenement.php?id=' . $id_evenement . '&error=full');
        exit();
    }

    // Vérifier si l'utilisateur est déjà inscrit (dans Inscription ET Inscription_Pending)
    $stmt = $pdo->prepare("
        SELECT id_inscription FROM Inscription 
        WHERE id_utilisateur = ? AND id_evenement = ?
    ");
    $stmt->execute([$id_utilisateur, $id_evenement]);
    if ($stmt->fetch()) {
        header('Location: inscription_evenement.php?id=' . $id_evenement . '&error=already_registered');
        exit();
    }

    // Vérifier s'il y a déjà une inscription en attente
    $stmt = $pdo->prepare("
        SELECT id_pending FROM Inscription_Pending 
        WHERE id_utilisateur = ? AND id_evenement = ? AND expires_at > NOW()
    ");
    $stmt->execute([$id_utilisateur, $id_evenement]);
    if ($stmt->fetch()) {
        header('Location: inscription_evenement.php?id=' . $id_evenement . '&error=pending_exists');
        exit();
    }

    // Récupérer les infos utilisateur
    $stmt = $pdo->prepare("SELECT nom, prenom, email FROM Utilisateur WHERE id_utilisateur = ?");
    $stmt->execute([$id_utilisateur]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Générer un token de confirmation unique
    $confirmation_token = bin2hex(random_bytes(32));

    // Créer une inscription TEMPORAIRE (expire dans 1 heure)
    $stmt = $pdo->prepare("
        INSERT INTO Inscription_Pending (id_utilisateur, id_evenement, confirmation_token, created_at, expires_at) 
        VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))
    ");
    $stmt->execute([$id_utilisateur, $id_evenement, $confirmation_token]);
    
    $id_pending = $pdo->lastInsertId();

    // Créer le lien de confirmation
    // Méthode 1 : URL complète avec port si nécessaire
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST']; // Inclut le port (ex: localhost:8000)
    
    // Si confirm_inscription.php est dans le même dossier que process_inscription.php
    $confirmation_link = $protocol . '://' . $host . '/public/confirm_inscription.php?token=' . $confirmation_token;
    
    // OU si la structure est différente, ajustez le chemin :
    // $confirmation_link = $protocol . '://' . $host . '/confirm_inscription.php?token=' . $confirmation_token;
    
    // Envoyer l'email de confirmation
    if (sendInscriptionConfirmationEmail($user, $event, $confirmation_link)) {
        // Rediriger vers la page de succès
        header('Location: inscription_success.php?id=' . $id_evenement);
        exit();
    } else {
        // Supprimer l'inscription temporaire si l'email n'a pas pu être envoyé
        $stmt = $pdo->prepare("DELETE FROM Inscription_Pending WHERE id_pending = ?");
        $stmt->execute([$id_pending]);
        
        header('Location: inscription_evenement.php?id=' . $id_evenement . '&error=email_failed');
        exit();
    }

} catch (PDOException $e) {
    error_log("Erreur d'inscription : " . $e->getMessage());
    header('Location: inscription_evenement.php?id=' . $id_evenement . '&error=database');
    exit();
}
?>