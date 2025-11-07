<?php
/**
 * Fichier des fonctions d'envoi d'emails
 * Gère l'envoi des notifications par email pour CampusEvent
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Si vous utilisez PHPMailer via Composer
// require_once __DIR__ . '/../vendor/autoload.php';

// Ou si vous utilisez PHPMailer en téléchargement direct
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

/**
 * Configuration email - À personnaliser selon vos paramètres
 */
define('SMTP_HOST', 'smtp.gmail.com');           // Serveur SMTP (Gmail, Outlook, etc.)
define('SMTP_PORT', 587);                         // Port SMTP (587 pour TLS, 465 pour SSL)
define('SMTP_USERNAME', 'essebaiyaya@gmail.com'); // Votre adresse email
define('SMTP_PASSWORD', 'lwuv exow molb bnnk');    // Mot de passe ou App Password
define('SMTP_FROM_EMAIL', 'essebaiyaya@gmail.com'); // Email expéditeur
define('SMTP_FROM_NAME', 'CampusEvent');          // Nom expéditeur

/**
 * Fonction principale pour envoyer un email
 * 
 * @param string $to_email Email du destinataire
 * @param string $subject Sujet de l'email
 * @param string $body Corps de l'email (HTML)
 * @param string $to_name Nom du destinataire (optionnel)
 * @return bool True si l'email est envoyé, False sinon
 */
function sendEmail($to_email, $subject, $body, $to_name = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Désactiver la vérification SSL en développement (À RETIRER EN PRODUCTION)
        // $mail->SMTPOptions = array(
        //     'ssl' => array(
        //         'verify_peer' => false,
        //         'verify_peer_name' => false,
        //         'allow_self_signed' => true
        //     )
        // );
        
        // Expéditeur
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Destinataire
        if (!empty($to_name)) {
            $mail->addAddress($to_email, $to_name);
        } else {
            $mail->addAddress($to_email);
        }
        
        // Contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body); // Version texte brut
        
        // Envoyer l'email
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Log l'erreur
        error_log("Erreur d'envoi d'email à {$to_email}: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Envoyer un email de confirmation d'inscription
 * 
 * @param array $user Informations de l'utilisateur (nom, prenom, email)
 * @param array $event Informations de l'événement (titre, date, lieu, tarif)
 * @param string $confirmation_link Lien de confirmation
 * @return bool
 */
function sendInscriptionConfirmationEmail($user, $event, $confirmation_link) {
    $subject = "Confirmez votre inscription - " . $event['titre'];
    
    $body = "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0;
                background-color: #f4f4f4;
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background-color: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 40px 30px; 
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
            }
            .header p {
                margin: 10px 0 0 0;
                font-size: 16px;
                opacity: 0.95;
            }
            .content { 
                background: #ffffff; 
                padding: 40px 30px;
            }
            .content p {
                margin: 0 0 15px 0;
                font-size: 15px;
                color: #555;
            }
            .button-container {
                text-align: center;
                margin: 35px 0;
            }
            .button { 
                display: inline-block; 
                background: #10b981; 
                color: white !important; 
                padding: 16px 40px; 
                text-decoration: none; 
                border-radius: 8px; 
                font-weight: 700;
                font-size: 16px;
                box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
                transition: all 0.3s ease;
            }
            .button:hover {
                background: #059669;
                box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            }
            .info-box { 
                background: #f8f9fa; 
                padding: 25px; 
                border-radius: 8px; 
                border-left: 4px solid #667eea; 
                margin: 25px 0;
            }
            .info-box h3 {
                margin: 0 0 15px 0;
                color: #2c3e50;
                font-size: 18px;
                font-weight: 700;
            }
            .info-box p {
                margin: 8px 0;
                font-size: 14px;
            }
            .info-box strong {
                color: #2c3e50;
                font-weight: 600;
            }
            .warning {
                background: #fef3c7;
                border: 2px solid #fbbf24;
                border-radius: 8px;
                padding: 20px;
                margin: 25px 0;
            }
            .warning p {
                margin: 0;
                color: #78350f;
                font-size: 14px;
            }
            .footer { 
                text-align: center; 
                color: #999; 
                padding: 30px; 
                font-size: 12px;
                background-color: #f8f9fa;
                border-top: 1px solid #e0e0e0;
            }
            .footer p {
                margin: 5px 0;
            }
            .divider {
                height: 1px;
                background: #e0e0e0;
                margin: 30px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎉 Confirmation d'inscription</h1>
                <p>Bienvenue sur CampusEvent</p>
            </div>
            
            <div class='content'>
                <p>Bonjour <strong>{$user['prenom']} {$user['nom']}</strong>,</p>
                
                <p>Merci de votre intérêt pour notre événement ! Pour finaliser votre inscription, veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous :</p>
                
                <div class='button-container'>
                    <a href='{$confirmation_link}' class='button'>✓ Confirmer mon inscription</a>
                </div>
                
                <p style='text-align: center; font-size: 13px; color: #999;'>
                    Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                    <a href='{$confirmation_link}' style='color: #667eea; word-break: break-all;'>{$confirmation_link}</a>
                </p>
                
                <div class='divider'></div>
                
                <div class='info-box'>
                    <h3>📋 Détails de l'événement</h3>
                    <p><strong>Événement :</strong> {$event['titre']}</p>
                    <p><strong>📅 Date :</strong> " . date('d/m/Y à H:i', strtotime($event['date'])) . "</p>
                    <p><strong>📍 Lieu :</strong> {$event['lieu']}</p>
                    <p><strong>💰 Tarif :</strong> " . ($event['tarif'] == 0 ? 'Gratuit' : number_format($event['tarif'], 2) . ' DH') . "</p>
                </div>
                
                <div class='warning'>
                    <p><strong>⚠️ Important :</strong> Ce lien de confirmation expire dans <strong>1 heure</strong>. 
                    Si vous ne confirmez pas dans ce délai, votre inscription sera automatiquement annulée et vous devrez vous réinscrire.</p>
                </div>
                
                <p style='margin-top: 30px; font-size: 14px; color: #666;'>
                    Si vous n'avez pas demandé cette inscription, vous pouvez ignorer cet email en toute sécurité.
                </p>
                
                <p style='margin-top: 25px; font-weight: 600; color: #2c3e50;'>
                    Cordialement,<br>
                    L'équipe CampusEvent 🎓
                </p>
            </div>
            
            <div class='footer'>
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                <p>&copy; " . date('Y') . " CampusEvent - Tous droits réservés</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body, $user['prenom'] . ' ' . $user['nom']);
}

/**
 * Envoyer un email de confirmation après validation
 * 
 * @param array $user Informations de l'utilisateur
 * @param array $event Informations de l'événement
 * @return bool
 */
function sendInscriptionValidatedEmail($user, $event) {
    $subject = "Inscription confirmée - " . $event['titre'];
    
    $body = "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 40px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .content { padding: 40px 30px; }
            .info-box { background: #f8f9fa; padding: 25px; border-radius: 8px; border-left: 4px solid #10b981; margin: 25px 0; }
            .footer { text-align: center; color: #999; padding: 30px; font-size: 12px; background: #f8f9fa; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Inscription confirmée !</h1>
            </div>
            <div class='content'>
                <p>Bonjour <strong>{$user['prenom']} {$user['nom']}</strong>,</p>
                <p>Votre inscription à l'événement a été confirmée avec succès !</p>
                
                <div class='info-box'>
                    <h3>Détails de votre inscription</h3>
                    <p><strong>Événement :</strong> {$event['titre']}</p>
                    <p><strong>Date :</strong> " . date('d/m/Y à H:i', strtotime($event['date'])) . "</p>
                    <p><strong>Lieu :</strong> {$event['lieu']}</p>
                    <p><strong>Tarif :</strong> " . ($event['tarif'] == 0 ? 'Gratuit' : number_format($event['tarif'], 2) . ' DH') . "</p>
                </div>
                
                <p>Nous avons hâte de vous voir à cet événement !</p>
                <p>Cordialement,<br>L'équipe CampusEvent</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " CampusEvent - Tous droits réservés</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body, $user['prenom'] . ' ' . $user['nom']);
}

/**
 * Envoyer un email de rappel d'événement
 * 
 * @param array $user Informations de l'utilisateur
 * @param array $event Informations de l'événement
 * @return bool
 */
function sendEventReminderEmail($user, $event) {
    $subject = "Rappel : " . $event['titre'] . " - Demain !";
    
    $body = "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; text-align: center; }
            .content { padding: 40px 30px; }
            .info-box { background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 25px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>⏰ Rappel d'événement</h1>
            </div>
            <div class='content'>
                <p>Bonjour <strong>{$user['prenom']}</strong>,</p>
                <p>Nous vous rappelons que l'événement <strong>{$event['titre']}</strong> aura lieu demain !</p>
                
                <div class='info-box'>
                    <p><strong>Date :</strong> " . date('d/m/Y à H:i', strtotime($event['date'])) . "</p>
                    <p><strong>Lieu :</strong> {$event['lieu']}</p>
                </div>
                
                <p>À très bientôt !</p>
                <p>L'équipe CampusEvent</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($user['email'], $subject, $body, $user['prenom'] . ' ' . $user['nom']);
}

/**
 * Fonction de test d'envoi d'email
 * Utilisez cette fonction pour tester votre configuration email
 */
function testEmailConfiguration($test_email) {
    $subject = "Test de configuration email - CampusEvent";
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <h2>Test de configuration email</h2>
        <p>Si vous recevez cet email, votre configuration email fonctionne correctement !</p>
        <p>Date du test : " . date('d/m/Y H:i:s') . "</p>
    </body>
    </html>
    ";
    
    return sendEmail($test_email, $subject, $body);
}