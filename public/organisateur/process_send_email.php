<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Vérifier que l'utilisateur est connecté et est organisateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: /campusEvents/public/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Méthode non autorisée.";
    header('Location: dashboard_organisateur.php?page=emails');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les données du formulaire
    $id_evenement = intval($_POST['id_evenement']);
    $objet = trim($_POST['objet']);
    $contenu = trim($_POST['contenu']);
    $user_id = $_SESSION['user_id'];

    // Vérifier que l'événement appartient à l'organisateur
    $check_event_query = "SELECT titre FROM Evenement WHERE id_evenement = ? AND id_organisateur = ?";
    $check_event_stmt = $pdo->prepare($check_event_query);
    $check_event_stmt->execute([$id_evenement, $user_id]);
    $event = $check_event_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $_SESSION['error'] = "Événement non trouvé ou vous n'avez pas les droits.";
        header('Location: dashboard_organisateur.php?page=emails');
        exit();
    }

    // Gestion du fichier uploadé
    $fichier_joint = null;
    $type_fichier = null;

    if (isset($_FILES['fichier_joint']) && $_FILES['fichier_joint']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/emails/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['fichier_joint']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            if ($_FILES['fichier_joint']['size'] <= 10 * 1024 * 1024) { // 10MB max
                $new_filename = uniqid() . '_' . $id_evenement . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['fichier_joint']['tmp_name'], $upload_path)) {
                    $fichier_joint = $new_filename;
                    $type_fichier = $_FILES['fichier_joint']['type'];
                }
            }
        }
    }

    // Récupérer les participants validés de l'événement
    $participants_query = "SELECT u.email, u.nom, u.prenom 
                          FROM Inscription i
                          JOIN Utilisateur u ON i.id_utilisateur = u.id_utilisateur
                          WHERE i.id_evenement = ? AND i.status = 'validée'";
    $participants_stmt = $pdo->prepare($participants_query);
    $participants_stmt->execute([$id_evenement]);
    $participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($participants)) {
        $_SESSION['error'] = "Aucun participant validé pour cet événement.";
        header('Location: dashboard_organisateur.php?page=emails');
        exit();
    }

    // Enregistrer l'email dans la base de données
    $insert_email_query = "INSERT INTO Email (objet, contenu, id_evenement, fichier_joint, type_fichier) 
                          VALUES (?, ?, ?, ?, ?)";
    $insert_email_stmt = $pdo->prepare($insert_email_query);
    $insert_email_stmt->execute([$objet, $contenu, $id_evenement, $fichier_joint, $type_fichier]);
    $email_id = $pdo->lastInsertId();

    // Envoyer les emails aux participants
    $sent_count = 0;
    foreach ($participants as $participant) {
        $mail = new PHPMailer(true);

        try {
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';

            // Destinataires
            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->addAddress($participant['email'], $participant['prenom'] . ' ' . $participant['nom']);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $objet;

            $message_html = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
                    .container { max-width: 600px; margin: 0 auto; background: white; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; text-align: center; }
                    .header h1 { margin: 0; font-size: 24px; }
                    .content { padding: 30px; }
                    .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; }
                    .event-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Campus Event</h1>
                    </div>
                    <div class='content'>
                        <p>Bonjour " . htmlspecialchars($participant['prenom']) . " " . htmlspecialchars($participant['nom']) . ",</p>
                        
                        <div class='event-info'>
                            <strong>Événement:</strong> " . htmlspecialchars($event['titre']) . "<br>
                        </div>
                        
                        " . nl2br(htmlspecialchars($contenu)) . "
                        
                        <p>Cordialement,<br>L'équipe Campus Event</p>
                    </div>
                    <div class='footer'>
                        <p>© 2025 Campus Event. Tous droits réservés.</p>
                        <p>Cet email a été envoyé aux participants de l'événement " . htmlspecialchars($event['titre']) . ".</p>
                    </div>
                </div>
            </body>
            </html>";

            $mail->Body = $message_html;
            $mail->AltBody = "Bonjour " . $participant['prenom'] . " " . $participant['nom'] . ",\n\n" .
                            "Événement: " . $event['titre'] . "\n\n" .
                            $contenu . "\n\n" .
                            "Cordialement,\nL'équipe Campus Event";

            // Ajouter le fichier joint si présent
            if ($fichier_joint) {
                $file_path = $upload_dir . $fichier_joint;
                if (file_exists($file_path)) {
                    $mail->addAttachment($file_path, $fichier_joint);
                }
            }

            $mail->send();
            $sent_count++;

        } catch (Exception $e) {
            error_log("Erreur envoi email à " . $participant['email'] . ": " . $mail->ErrorInfo);
            continue;
        }
    }

    $_SESSION['success'] = "Email envoyé avec succès à " . $sent_count . " participant(s).";
    header('Location: dashboard_organisateur.php?page=emails');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header('Location: dashboard_organisateur.php?page=emails');
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
    header('Location: dashboard_organisateur.php?page=emails');
    exit();
}
?>