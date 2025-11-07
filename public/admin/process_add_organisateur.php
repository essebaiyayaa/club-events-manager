<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /campusEvents/public/auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Méthode non autorisée.";
    header('Location: dashboard_admin.php?page=organisateurs');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $date_naissance = $_POST['date_naissance'];
    $filiere = trim($_POST['filiere']);
    $club_id = isset($_POST['club_id']) && !empty($_POST['club_id']) ? intval($_POST['club_id']) : null;


    $check_email_query = "SELECT id_utilisateur FROM Utilisateur WHERE email = ?";
    $check_email_stmt = $pdo->prepare($check_email_query);
    $check_email_stmt->execute([$email]);
    
    if ($check_email_stmt->fetch()) {
        $_SESSION['error'] = "Cet email est déjà utilisé.";
        header('Location: dashboard_admin.php?page=add_organisateur');
        exit();
    }

    //mot de passe ela zhar
    $password = bin2hex(random_bytes(8)); 
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

  
    $insert_query = "INSERT INTO Utilisateur (nom, prenom, email, mot_de_passe, date_naissance, filiere, role, is_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, 'organisateur', 1)";
    $insert_stmt = $pdo->prepare($insert_query);
    $insert_stmt->execute([$nom, $prenom, $email, $hashed_password, $date_naissance, $filiere]);
    
    $new_user_id = $pdo->lastInsertId();


    if ($club_id) {
        $update_club_query = "UPDATE Club SET id_president = ? WHERE id_club = ?";
        $update_club_stmt = $pdo->prepare($update_club_query);
        $update_club_stmt->execute([$new_user_id, $club_id]);
    }

    $mail = new PHPMailer(true);

    try {
        //  SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

      
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($email, $prenom . ' ' . $nom);

   
        $mail->isHTML(true);
        $mail->Subject = "Bienvenue sur Campus Event - Vos identifiants Organisateur";

        $login_url = "http://" . $_SERVER['HTTP_HOST'] . "/campusEvents/public/auth/login.php";

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; padding: 40px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; }
                .credentials { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #ff6b6b; }
                .credentials p { margin: 10px 0; font-size: 16px; }
                .credentials strong { color: #ff6b6b; }
                .button { display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; text-decoration: none; border-radius: 25px; margin: 20px 0; font-weight: bold; }
                .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; }
                .warning { background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Bienvenue sur Campus Event</h1>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>" . htmlspecialchars($prenom . ' ' . $nom) . "</strong>,</p>
                    
                    <p>Félicitations ! Vous avez été désigné comme <strong>Organisateur</strong> sur la plateforme Campus Event.</p>
                    
                    <p>Voici vos identifiants de connexion :</p>
                    
                    <div class='credentials'>
                        <p><strong> Email :</strong> " . htmlspecialchars($email) . "</p>
                        <p><strong> Mot de passe :</strong> " . htmlspecialchars($password) . "</p>
                    </div>
                    
                    <div class='warning'>
                        <strong> Important :</strong> Pour des raisons de sécurité, veuillez changer votre mot de passe lors de votre première connexion.
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='" . $login_url . "' class='button'>Se connecter maintenant</a>
                    </p>
                    
                    <p>En tant qu'organisateur, vous pourrez :</p>
                    <ul>
                        <li> Créer et gérer vos événements</li>
                        <li> Gérer les inscriptions des participants</li>
                        <li> Envoyer des emails et fichiers aux participants</li>
                        <li> Générer des attestations de participation</li>
                        <li> Consulter les statistiques de vos événements</li>
                    </ul>
                    
                    <p>Nous vous souhaitons beaucoup de succès dans l'organisation de vos événements !</p>
                    
                    <p>Cordialement,<br>L'équipe Campus Event</p>
                </div>
                <div class='footer'>
                    <p>© 2025 Campus Event. Tous droits réservés.</p>
                    <p>Si vous n'avez pas demandé ce compte, veuillez ignorer cet email.</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $message;
        $mail->AltBody = "Bonjour " . $prenom . " " . $nom . ",\n\n" .
                        "Vous avez été désigné comme Organisateur sur Campus Event.\n\n" .
                        "Email: " . $email . "\n" .
                        "Mot de passe: " . $password . "\n\n" .
                        "Connectez-vous sur: " . $login_url . "\n\n" .
                        "Cordialement,\nL'équipe Campus Event";

        $mail->send();

        $_SESSION['success'] = "Organisateur créé avec succès ! Un email avec les identifiants a été envoyé à " . $email;
        
        if ($club_id) {
            header('Location: dashboard_admin.php?page=organisateurs&club_id=' . $club_id);
        } else {
            header('Location: dashboard_admin.php?page=organisateurs');
        }
        exit();

    } catch (Exception $e) {
      
        $_SESSION['error'] = "Organisateur créé mais l'email n'a pas pu être envoyé. Mot de passe: " . $password;
        
        if ($club_id) {
            header('Location: dashboard_admin.php?page=organisateurs&club_id=' . $club_id);
        } else {
            header('Location: dashboard_admin.php?page=organisateurs');
        }
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header('Location: dashboard_admin.php?page=add_organisateur');
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
    header('Location: dashboard_admin.php?page=add_organisateur');
    exit();
}
?> n'a pas pu être envoyé. Mot de passe: " . $password;
        header('Location: dashboard_admin.php?page=organisateurs');
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header('Location: dashboard_admin.php?page=add_organisateur');
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
    header('Location: dashboard_admin.php?page=add_organisateur');
    exit();
}
?>