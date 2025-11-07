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

    $id_utilisateur = intval($_POST['id_utilisateur']);
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $date_naissance = $_POST['date_naissance'];
    $filiere = trim($_POST['filiere']);
    $new_password = trim($_POST['new_password']);

    $check_organisateur_query = "SELECT * FROM Utilisateur WHERE id_utilisateur = ? AND role = 'organisateur'";
    $check_organisateur_stmt = $pdo->prepare($check_organisateur_query);
    $check_organisateur_stmt->execute([$id_utilisateur]);
    $ancien_organisateur = $check_organisateur_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ancien_organisateur) {
        $_SESSION['error'] = "Organisateur introuvable.";
        header('Location: dashboard_admin.php?page=organisateurs');
        exit();
    }

    $check_email_query = "SELECT id_utilisateur FROM Utilisateur WHERE email = ? AND id_utilisateur != ?";
    $check_email_stmt = $pdo->prepare($check_email_query);
    $check_email_stmt->execute([$email, $id_utilisateur]);
    
    if ($check_email_stmt->fetch()) {
        $_SESSION['error'] = "Cet email est déjà utilisé par un autre utilisateur.";
        header("Location: edit_organisateur.php?id=" . $id_utilisateur);
        exit();
    }

    $changements = [];
    $nouveau_mot_de_passe = null;

    if ($ancien_organisateur['nom'] !== $nom) {
        $changements[] = "Nom : " . $ancien_organisateur['nom'] . " → " . $nom;
    }
    if ($ancien_organisateur['prenom'] !== $prenom) {
        $changements[] = "Prénom : " . $ancien_organisateur['prenom'] . " → " . $prenom;
    }
    if ($ancien_organisateur['email'] !== $email) {
        $changements[] = "Email : " . $ancien_organisateur['email'] . " → " . $email;
    }
    if ($ancien_organisateur['date_naissance'] !== $date_naissance) {
        $changements[] = "Date de naissance : " . $ancien_organisateur['date_naissance'] . " → " . $date_naissance;
    }
    if ($ancien_organisateur['filiere'] !== $filiere) {
        $ancienne_filiere = $ancien_organisateur['filiere'] ?: 'Non renseignée';
        $nouvelle_filiere = $filiere ?: 'Non renseignée';
        $changements[] = "Filière : " . $ancienne_filiere . " → " . $nouvelle_filiere;
    }

    if (!empty($new_password)) {
        $nouveau_mot_de_passe = $new_password;
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $changements[] = "Mot de passe : Modifié";
    } else {
        $hashed_password = $ancien_organisateur['mot_de_passe'];
    }

    if (!empty($new_password)) {
        $update_query = "UPDATE Utilisateur SET nom = ?, prenom = ?, email = ?, date_naissance = ?, filiere = ?, mot_de_passe = ? WHERE id_utilisateur = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$nom, $prenom, $email, $date_naissance, $filiere, $hashed_password, $id_utilisateur]);
    } else {
        $update_query = "UPDATE Utilisateur SET nom = ?, prenom = ?, email = ?, date_naissance = ?, filiere = ? WHERE id_utilisateur = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$nom, $prenom, $email, $date_naissance, $filiere, $id_utilisateur]);
    }

    $mail = new PHPMailer(true);

    try {

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
        $mail->Subject = "Mise à jour de votre compte Organisateur - Campus Event";

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
                .changes { background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #ff6b6b; }
                .changes ul { margin: 0; padding-left: 20px; }
                .changes li { margin: 8px 0; }
                .credentials { background: #fff3cd; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #ffc107; }
                .button { display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; text-decoration: none; border-radius: 25px; margin: 20px 0; font-weight: bold; }
                .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; }
                .info-box { background: #e7f3ff; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1> Mise à jour de votre compte</h1>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>" . htmlspecialchars($prenom . ' ' . $nom) . "</strong>,</p>
                    
                    <p>Votre compte organisateur sur Campus Event a été mis à jour par l'administrateur.</p>";
        
        if (!empty($changements)) {
            $message .= "
                    <div class='changes'>
                        <h3>Modifications apportées :</h3>
                        <ul>";
            foreach ($changements as $changement) {
                $message .= "<li>" . htmlspecialchars($changement) . "</li>";
            }
            $message .= "
                        </ul>
                    </div>";
        } else {
            $message .= "
                    <div class='info-box'>
                        <strong>Information :</strong> Aucune modification n'a été apportée à vos informations personnelles.
                    </div>";
        }

        if ($nouveau_mot_de_passe) {
            $message .= "
                    <div class='credentials'>
                        <h3>Nouveau mot de passe :</h3>
                        <p><strong>" . htmlspecialchars($nouveau_mot_de_passe) . "</strong></p>
                        <p><em>Pour des raisons de sécurité, nous vous recommandons de changer ce mot de passe après votre prochaine connexion.</em></p>
                    </div>";
        }

        $message .= "
                    <div class='info-box'>
                        <strong>Vos identifiants de connexion :</strong><br>
                        Email : <strong>" . htmlspecialchars($email) . "</strong><br>
                        " . ($nouveau_mot_de_passe ? "Mot de passe : <strong>" . htmlspecialchars($nouveau_mot_de_passe) . "</strong>" : "Votre mot de passe reste inchangé") . "
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='" . $login_url . "' class='button'>Se connecter maintenant</a>
                    </p>
                    
                    <p>Si vous n'êtes pas à l'origine de ces modifications ou si vous avez des questions, veuillez contacter l'administrateur du site.</p>
                    
                    <p>Cordialement,<br>L'équipe Campus Event</p>
                </div>
                <div class='footer'>
                    <p>© 2025 Campus Event. Tous droits réservés.</p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>";

        $altBody = "Bonjour " . $prenom . " " . $nom . ",\n\n" .
                  "Votre compte organisateur sur Campus Event a été mis à jour par l'administrateur.\n\n";
        
        if (!empty($changements)) {
            $altBody .= "Modifications apportées :\n";
            foreach ($changements as $changement) {
                $altBody .= "• " . $changement . "\n";
            }
            $altBody .= "\n";
        }

        if ($nouveau_mot_de_passe) {
            $altBody .= "Nouveau mot de passe : " . $nouveau_mot_de_passe . "\n\n";
        }

        $altBody .= "Email de connexion : " . $email . "\n" .
                   ($nouveau_mot_de_passe ? "Mot de passe : " . $nouveau_mot_de_passe . "\n\n" : "\n") .
                   "Connectez-vous sur : " . $login_url . "\n\n" .
                   "Cordialement,\nL'équipe Campus Event";

        $mail->Body = $message;
        $mail->AltBody = $altBody;

        $mail->send();

    
        $_SESSION['success'] = "Organisateur modifié avec succès ! Un email de notification a été envoyé à " . $email;
        
    } catch (Exception $e) {
     
        $_SESSION['warning'] = "Organisateur modifié avec succès, mais l'email de notification n'a pas pu être envoyé.";
        if ($nouveau_mot_de_passe) {
            $_SESSION['warning'] .= " Nouveau mot de passe : " . $nouveau_mot_de_passe;
        }
    }

    header('Location: dashboard_admin.php?page=organisateurs');
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    header("Location: edit_organisateur.php?id=" . $id_utilisateur);
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
    header("Location: edit_organisateur.php?id=" . $id_organisateur);
    exit();
}
?>