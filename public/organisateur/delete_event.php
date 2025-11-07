<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration de la base de données
require_once __DIR__ . '/../../config/config.php';

// Charger Composer autoload pour PHPMailer
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Vérifier que l'utilisateur est connecté et est organisateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: /campusEvents/public/auth/login.php');
    exit();
}

// Vérifier que l'ID est passé
if (!isset($_GET['id'])) {
    header('Location: dashboard_organisateur.php?page=events');
    exit();
}

$event_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

try {
    // Connexion DB
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vérifier que l'événement appartient bien à l'organisateur
    $check_query = "SELECT * FROM Evenement WHERE id_evenement = ? AND id_organisateur = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$event_id, $user_id]);
    $event = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à supprimer cet événement.";
        header('Location: dashboard_organisateur.php?page=events');
        exit();
    }

    // Récupérer les participants inscrits validés
    $participants_query = "SELECT u.email, u.nom, u.prenom 
                          FROM Inscription i
                          JOIN Utilisateur u ON i.id_utilisateur = u.id_utilisateur
                          WHERE i.id_evenement = ? AND i.status = 'validée'";
    $participants_stmt = $pdo->prepare($participants_query);
    $participants_stmt->execute([$event_id]);
    $participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Supprimer l'événement
    $delete_query = "DELETE FROM Evenement WHERE id_evenement = ?";
    $delete_stmt = $pdo->prepare($delete_query);
    $delete_stmt->execute([$event_id]);

    // Envoyer un email à tous les participants
    if (!empty($participants)) {
        foreach ($participants as $participant) {
            $mail = new PHPMailer(true);

            try {
                // Configuration SMTP (même que register.php)
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
                $mail->Subject = "Annulation de l'événement : " . htmlspecialchars($event['titre']);

                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
                        .container { max-width: 600px; margin: 0 auto; background: white; }
                        .header { background: linear-gradient(135deg, #e53935 0%, #e35d5b 100%); color: white; padding: 40px; text-align: center; }
                        .header h1 { margin: 0; font-size: 28px; }
                        .content { padding: 30px; }
                        .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Événement annulé</h1>
                        </div>
                        <div class='content'>
                            <p>Bonjour " . htmlspecialchars($participant['prenom']) . " " . htmlspecialchars($participant['nom']) . ",</p>
                            <p>Nous vous informons que l'événement <strong>" . htmlspecialchars($event['titre']) . "</strong> prévu le <strong>" . htmlspecialchars($event['date_evenement']) . "</strong> a été <strong>annulé</strong>.</p>
                            <p>Nous nous excusons pour la gêne occasionnée.</p>
                            <p>L'équipe Campus Event</p>
                        </div>
                        <div class='footer'>
                            <p>© 2025 Campus Event. Tous droits réservés.</p>
                        </div>
                    </div>
                </body>
                </html>";

                $mail->Body = $message;
                $mail->AltBody = "Bonjour " . $participant['prenom'] . " " . $participant['nom'] . ",\n\n".
                                "L'événement '" . $event['titre'] . "' prévu le " . $event['date_evenement'] . " a été annulé.\n\n".
                                "Nous nous excusons pour la gêne occasionnée.\n\nCampus Event";

                $mail->send();
            } catch (Exception $e) {
                error_log("Erreur email suppression: " . $mail->ErrorInfo);
            }
        }
    }

    $_SESSION['success'] = "Événement supprimé et notification envoyée aux participants.";
    header('Location: dashboard_organisateur.php?page=events');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header('Location: dashboard_organisateur.php?page=events');
    exit();
}
