<?php
session_start();

require_once __DIR__ . '/../../config/config.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Récupération et nettoyage des données
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? null;
    $filiere = trim($_POST['filiere'] ?? '');
    $terms = isset($_POST['terms']);
    
    // Validation des champs obligatoires
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        header('Location: register.php?error=empty_fields');
        exit();
    }
    
    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: register.php?error=invalid_email');
        exit();
    }
    
    // Validation des mots de passe
    if ($password !== $confirm_password) {
        header('Location: register.php?error=passwords_mismatch');
        exit();
    }
    
    // Validation de la force du mot de passe
    if (strlen($password) < 8) {
        header('Location: register.php?error=weak_password');
        exit();
    }
    
    // Validation des conditions d'utilisation
    if (!$terms) {
        header('Location: register.php?error=terms_not_accepted');
        exit();
    }
    
    try {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id_utilisateur FROM Utilisateur WHERE email = :email");
        $stmt->execute(['email' => $email]);
        
        if ($stmt->fetch()) {
            header('Location: register.php?error=email_exists');
            exit();
        }
        
        // Hasher le mot de passe
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Conversion de la date de naissance (peut être null)
        $date_naissance_sql = !empty($date_naissance) ? $date_naissance : null;
        
        // Insertion du nouvel utilisateur
        $stmt = $pdo->prepare("
            INSERT INTO Utilisateur (
                nom, 
                prenom, 
                email, 
                mot_de_passe, 
                date_naissance, 
                filiere, 
                role
            ) VALUES (
                :nom, 
                :prenom, 
                :email, 
                :mot_de_passe, 
                :date_naissance, 
                :filiere, 
                'participant'
            )
        ");
        
        $result = $stmt->execute([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'mot_de_passe' => $password_hash,
            'date_naissance' => $date_naissance_sql,
            'filiere' => !empty($filiere) ? $filiere : null
        ]);
        
        if ($result) {
            // Récupérer l'ID du nouvel utilisateur
            $user_id = $pdo->lastInsertId();
            
            // Envoyer un email de bienvenue (optionnel)
            // sendWelcomeEmail($email, $prenom);
            
            // Connexion automatique après inscription
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_nom'] = $nom;
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_role'] = 'participant';
            $_SESSION['user_filiere'] = $filiere;
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Redirection vers le tableau de bord
            header('Location: ../home.php?welcome=1');
            exit();
            
        } else {
            header('Location: register.php?error=registration_failed');
            exit();
        }
        
    } catch (PDOException $e) {
        // Gestion des erreurs de base de données
        error_log("Erreur d'inscription : " . $e->getMessage());
        header('Location: register.php?error=system');
        exit();
    }
    
} else {
    // Accès direct au fichier sans POST
    header('Location: register.php');
    exit();
}

// Fonction optionnelle pour envoyer un email de bienvenue
function sendWelcomeEmail($email, $prenom) {
    $subject = "Bienvenue sur CampusEvent !";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>CampusEvent</h1>
            </div>
            <div class='content'>
                <h2>Bienvenue " . htmlspecialchars($prenom) . " !</h2>
                <p>Nous sommes ravis de vous accueillir sur CampusEvent.</p>
                <p>Vous pouvez maintenant découvrir et vous inscrire aux événements de votre campus.</p>
                <a href='https://votresite.com' class='button'>Découvrir les événements</a>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: CampusEvent <noreply@campusevent.com>' . "\r\n";
    
    mail($email, $subject, $message, $headers);
}
?>