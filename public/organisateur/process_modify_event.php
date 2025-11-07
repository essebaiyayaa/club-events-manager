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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['event_id'])) {
    $_SESSION['error'] = "Requête invalide.";
    header('Location: dashboard_organisateur.php?page=events');
    exit();
}

$event_id = intval($_POST['event_id']);
$user_id = $_SESSION['user_id'];

try {
    // Connexion DB
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vérifier que l'événement appartient bien à l'organisateur
    $check_query = "SELECT * FROM Evenement WHERE id_evenement = ? AND id_organisateur = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$event_id, $user_id]);
    $old_event = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$old_event) {
        $_SESSION['error'] = "Vous n'êtes pas autorisé à modifier cet événement.";
        header('Location: dashboard_organisateur.php?page=events');
        exit();
    }

    // Récupérer les données du formulaire
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $date = $_POST['date'];
    $lieu = trim($_POST['lieu']);
    $capacite_max = intval($_POST['capacite_max']);
    $tarif = floatval($_POST['tarif']);
    $notify_participants = isset($_POST['notify_participants']);

    // Gestion de l'upload de l'image
    $affiche_url = $old_event['affiche_url'];
    
    if (isset($_FILES['affiche']) && $_FILES['affiche']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/affiches/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['affiche']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            if ($_FILES['affiche']['size'] <= 5 * 1024 * 1024) { // 5MB max
                $new_filename = uniqid() . '_' . $event_id . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['affiche']['tmp_name'], $upload_path)) {
                    // Supprimer l'ancienne image si elle existe
                    if ($affiche_url && file_exists(__DIR__ . '/../../' . $affiche_url)) {
                        unlink(__DIR__ . '/../../' . $affiche_url);
                    }
                    $affiche_url = 'uploads/affiches/' . $new_filename;
                }
            }
        }
    }

    // Mettre à jour l'événement
    $update_query = "UPDATE Evenement SET titre = ?, description = ?, date = ?, lieu = ?, capacite_max = ?, tarif = ?, affiche_url = ? WHERE id_evenement = ?";
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute([$titre, $description, $date, $lieu, $capacite_max, $tarif, $affiche_url, $event_id]);

    // Envoyer un email aux participants si demandé
    if ($notify_participants) {
        // Récupérer les participants inscrits validés
        $participants_query = "SELECT u.email, u.nom, u.prenom 
                              FROM Inscription i
                              JOIN Utilisateur u ON i.id_utilisateur = u.id_utilisateur
                              WHERE i.id_evenement = ? AND i.status = 'validée'";
        $participants_stmt = $pdo->prepare($participants_query);
        $participants_stmt->execute([$event_id]);
        $participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($participants)) {
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
                    $mail->Subject = "Modification de l'événement : " . htmlspecialchars($titre);

                    $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
                            .container { max-width: 600px; margin: 0 auto; background: white; }
                            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; text-align: center; }
                            .header h1 { margin: 0; font-size: 28px; }
                            .content { padding: 30px; }
                            .event-details { background: #f9f9f9; padding: 20px; border-radius: 10px; margin: 20px 0; }
                            .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Événement modifié</h1>
                            </div>
                            <div class='content'>
                                <p>Bonjour " . htmlspecialchars($participant['prenom']) . " " . htmlspecialchars($participant['nom']) . ",</p>
                                <p>Nous vous informons que l'événement <strong>" . htmlspecialchars($titre) . "</strong> a été modifié.</p>
                                
                                <div class='event-details'>
                                    <h3>Nouveaux détails :</h3>
                                    <p><strong>Date :</strong> " . htmlspecialchars($date) . "</p>
                                    <p><strong>Lieu :</strong> " . htmlspecialchars($lieu) . "</p>
                                    <p><strong>Capacité :</strong> " . htmlspecialchars($capacite_max) . " personnes</p>
                                    <p><strong>Tarif :</strong> " . htmlspecialchars($tarif) . " €</p>
                                </div>
                                
                                <p>Nous vous remercions de votre compréhension.</p>
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
                                    "L'événement '" . $titre . "' a été modifié.\n\n".
                                    "Nouveaux détails :\n".
                                    "Date : " . $date . "\n".
                                    "Lieu : " . $lieu . "\n".
                                    "Capacité : " . $capacite_max . " personnes\n".
                                    "Tarif : " . $tarif . " €\n\n".
                                    "Nous vous remercions de votre compréhension.\n\nCampus Event";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Erreur email modification: " . $mail->ErrorInfo);
                }
            }
        }
    }

    $_SESSION['success'] = "Événement modifié avec succès" . ($notify_participants ? " et notification envoyée aux participants." : ".");
    header('Location: dashboard_organisateur.php?page=events');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header('Location: dashboard_organisateur.php?page=events');
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
    header('Location: dashboard_organisateur.php?page=events');
    exit();
}
?>