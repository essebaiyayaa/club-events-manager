<?php
session_start();
require_once '../../config/config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['error'] = "Paramètres manquants.";
    header('Location: dashboard_organisateur.php?page=participants');
    exit();
}

$inscription_id = (int)$_GET['id'];
$new_status = $_GET['status'];
$participant_email = $_GET['email'] ?? '';
$participant_name = $_GET['name'] ?? '';
$event_title = $_GET['event'] ?? '';

$verify_query = "SELECT i.*, e.titre, e.date, e.lieu 
                 FROM Inscription i 
                 JOIN Evenement e ON i.id_evenement = e.id_evenement 
                 WHERE i.id_inscription = ? AND e.id_organisateur = ?";
$verify_stmt = $pdo->prepare($verify_query);
$verify_stmt->execute([$inscription_id, $_SESSION['user_id']]);
$inscription = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$inscription) {
    $_SESSION['error'] = "Inscription non trouvée ou accès non autorisé.";
    header('Location: dashboard_organisateur.php?page=participants');
    exit();
}

try {
    $update_query = "UPDATE Inscription SET status = ? WHERE id_inscription = ?";
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute([$new_status, $inscription_id]);
    if ($new_status === 'validée' && !empty($participant_email)) {
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'essebaiyaya@gmail.com';  
            $mail->Password   = 'lwuv exow molb bnnk'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom('essebaiyaya@gmail.com', 'CampusEvent'); 
            $mail->addAddress($participant_email, $participant_name);
            $mail->isHTML(true);
            $mail->Subject = "Validation de votre inscription - " . $inscription['titre'];
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                    .info-box { background: #f0f7ff; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; }
                    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.9em; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1> Inscription Validée !</h1>
                    </div>
                    <div class='content'>
                        <p>Bonjour <strong>" . htmlspecialchars($participant_name) . "</strong>,</p>
                        
                        <p>Nous avons le plaisir de vous confirmer que votre inscription à l'événement a été <strong>validée avec succès</strong> !</p>
                        
                        <div class='info-box'>
                            <h3 style='margin-top: 0; color: #667eea;'>📋 Détails de l'événement :</h3>
                            <p style='margin: 5px 0;'><strong>Événement :</strong> " . htmlspecialchars($inscription['titre']) . "</p>
                            <p style='margin: 5px 0;'><strong>Date :</strong> " . date('d/m/Y', strtotime($inscription['date'])) . "</p>
                            <p style='margin: 5px 0;'><strong>Lieu :</strong> " . htmlspecialchars($inscription['lieu']) . "</p>
                        </div>
                        
                        <p>Votre place est désormais confirmée. Nous avons hâte de vous accueillir !</p>
                        
                        <p style='margin-top: 30px;'>À très bientôt,<br><strong>L'équipe CampusEvent</strong></p>
                    </div>
                    <div class='footer'>
                        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->send();
            $_SESSION['success'] = "Le statut de l'inscription a été mis à jour avec succès. Un email de confirmation a été envoyé au participant.";
            
        } catch (Exception $e) {
            $_SESSION['success'] = "Le statut a été mis à jour, mais l'email n'a pas pu être envoyé. Erreur : {$mail->ErrorInfo}";
        }
    }
    else if($new_status === 'refusée' && !empty($participant_email)){
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'essebaiyaya@gmail.com';  
            $mail->Password   = 'lwuv exow molb bnnk'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom('essebaiyaya@gmail.com', 'CampusEvent'); 
            $mail->addAddress($participant_email, $participant_name);
            $mail->isHTML(true);
            $mail->Subject = "Refus de votre inscription - " . $inscription['titre'];
            $mail->Body = "
<html>
<head>
    <style>
        body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
        .header { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
        .info-box { background: #fff5f5; padding: 15px; border-left: 4px solid #e74c3c; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Inscription Non Retenue</h1>
        </div>
        <div class='content'>
            <p>Bonjour <strong>" . htmlspecialchars($participant_name) . "</strong>,</p>
            
            <p>Nous regrettons de vous informer que votre inscription à l'événement n'a pas pu être <strong>acceptée</strong>.</p>
            
            <div class='info-box'>
                <h3 style='margin-top: 0; color: #e74c3c;'>📋 Événement concerné :</h3>
                <p style='margin: 5px 0;'><strong>Événement :</strong> " . htmlspecialchars($inscription['titre']) . "</p>
                <p style='margin: 5px 0;'><strong>Date :</strong> " . date('d/m/Y', strtotime($inscription['date'])) . "</p>
                <p style='margin: 5px 0;'><strong>Lieu :</strong> " . htmlspecialchars($inscription['lieu']) . "</p>
            </div>
            
            <p>Cette décision peut être due à différentes raisons (capacité d'accueil limitée, critères de sélection, etc.).</p>
            
            <p>Nous vous remercions de l'intérêt que vous avez porté à cet événement et espérons avoir le plaisir de vous accueillir lors d'une prochaine occasion.</p>
            
            <p style='margin-top: 30px;'>Cordialement,<br><strong>L'équipe CampusEvent</strong></p>
        </div>
        <div class='footer'>
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>
";
            
            $mail->send();
            $_SESSION['success'] = "Le statut de l'inscription a été mis à jour avec succès. Un email de confirmation a été envoyé au participant.";
            
        } catch (Exception $e) {
            $_SESSION['success'] = "Le statut a été mis à jour, mais l'email n'a pas pu être envoyé. Erreur : {$mail->ErrorInfo}";
        }
    }
    else {
        $_SESSION['success'] = "Le statut de l'inscription a été mis à jour avec succès.";
    }

    
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
}

header('Location: dashboard_organisateur.php?page=participants');
exit();
?>