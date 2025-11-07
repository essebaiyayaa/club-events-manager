<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /campusEvents/public/auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID du club non spécifié.";
    header('Location: dashboard_admin.php?page=clubs');
    exit();
}

$club_id = intval($_GET['id']);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();
    $club_info_query = "SELECT nom_club, id_president FROM Club WHERE id_club = ?";
    $club_info_stmt = $pdo->prepare($club_info_query);
    $club_info_stmt->execute([$club_id]);
    $club_info = $club_info_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$club_info) {
        $_SESSION['error'] = "Club introuvable.";
        header('Location: dashboard_admin.php?page=clubs');
        exit();
    }

    $club_name = $club_info['nom_club'];
    $president_id = $club_info['id_president'];
    $events_query = "SELECT id_evenement, titre FROM Evenement WHERE id_club = ?";
    $events_stmt = $pdo->prepare($events_query);
    $events_stmt->execute([$club_id]);
    $events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);

    $participants = [];
    if (!empty($events)) {
        $event_ids = array_column($events, 'id_evenement');
        $placeholders = str_repeat('?,', count($event_ids) - 1) . '?';
        
        $participants_query = "SELECT DISTINCT i.id_utilisateur, u.email, u.nom, u.prenom 
                              FROM Inscription i 
                              JOIN Utilisateur u ON i.id_utilisateur = u.id_utilisateur 
                              WHERE i.id_evenement IN ($placeholders)";
        $participants_stmt = $pdo->prepare($participants_query);
        $participants_stmt->execute($event_ids);
        $participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!empty($events)) {
        $delete_inscriptions_query = "DELETE FROM Inscription WHERE id_evenement IN ($placeholders)";
        $delete_inscriptions_stmt = $pdo->prepare($delete_inscriptions_query);
        $delete_inscriptions_stmt->execute($event_ids);
    }

    $delete_events_query = "DELETE FROM Evenement WHERE id_club = ?";
    $delete_events_stmt = $pdo->prepare($delete_events_query);
    $delete_events_stmt->execute([$club_id]);

    if ($president_id) {
        $update_president_query = "UPDATE Club SET id_president = NULL WHERE id_club = ?";
        $update_president_stmt = $pdo->prepare($update_president_query);
        $update_president_stmt->execute([$club_id]);
    }


    $delete_club_query = "DELETE FROM Club WHERE id_club = ?";
    $delete_club_stmt = $pdo->prepare($delete_club_query);
    $delete_club_stmt->execute([$club_id]);

   
    $pdo->commit();


    if (!empty($participants)) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
   
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(FROM_EMAIL, FROM_NAME);


            foreach ($participants as $participant) {
                try {
                    $mail->clearAddresses();
                    $mail->addAddress($participant['email'], $participant['prenom'] . ' ' . $participant['nom']);
                    
                    $mail->isHTML(true);
                    $mail->Subject = "Annulation d'événements - Club " . $club_name;
                    
                    $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px; }
                            .header { background: #ff6b6b; color: white; padding: 20px; text-align: center; }
                            .content { background: white; padding: 20px; }
                            .footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>Événements Annulés</h2>
                            </div>
                            <div class='content'>
                                <p>Bonjour " . htmlspecialchars($participant['prenom']) . ",</p>
                                <p>Nous vous informons que le club <strong>" . htmlspecialchars($club_name) . "</strong> a été supprimé de la plateforme Campus Event.</p>
                                <p>Par conséquent, tous les événements organisés par ce club ont été annulés.</p>
                                <p>Nous nous excusons pour la gêne occasionnée.</p>
                                <p>Cordialement,<br>L'équipe Campus Event</p>
                            </div>
                            <div class='footer'>
                                <p>© 2025 Campus Event. Tous droits réservés.</p>
                            </div>
                        </div>
                    </body>
                    </html>";
                    
                    $mail->Body = $message;
                    $mail->AltBody = "Bonjour " . $participant['prenom'] . ",\n\n" .
                                    "Le club " . $club_name . " a été supprimé. Tous ses événements sont annulés.\n\n" .
                                    "Cordialement,\nL'équipe Campus Event";
                    
                    $mail->send();
                } catch (Exception $e) {
                 
                    error_log("Erreur envoi email participant: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
      
            error_log("Erreur configuration email: " . $e->getMessage());
        }
    }

  
    error_log("Admin " . $_SESSION['user_id'] . " a supprimé le club: " . $club_name . " (ID: " . $club_id . ")");

    $_SESSION['success'] = "Le club '" . htmlspecialchars($club_name) . "' a été supprimé avec succès. " . 
                          count($events) . " événement(s) et " . count($participants) . " inscription(s) ont été supprimés.";
    
    header('Location: dashboard_admin.php?page=clubs');
    exit();

} catch (PDOException $e) {
   
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = "Erreur lors de la suppression du club : " . $e->getMessage();
    error_log("Erreur suppression club: " . $e->getMessage());
    header('Location: dashboard_admin.php?page=clubs');
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
    error_log("Erreur générale suppression club: " . $e->getMessage());
    header('Location: dashboard_admin.php?page=clubs');
    exit();
}