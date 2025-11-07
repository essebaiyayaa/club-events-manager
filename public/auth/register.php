<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Configuration de la base de données
require_once __DIR__ . '/../../config/config.php';

// Charger Composer autoload pour PHPMailer
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date_naissance = $_POST['date_naissance'] ?? '';
    $filiere = $_POST['filiere'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Validation
    if (empty($nom)) $errors[] = "Le nom est requis";
    if (empty($prenom)) $errors[] = "Le prénom est requis";
    if (empty($email)) $errors[] = "L'email est requis";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if (empty($date_naissance)) $errors[] = "La date de naissance est requise";
    if (empty($filiere)) $errors[] = "La filière est requise";
    if (empty($password)) $errors[] = "Le mot de passe est requis";
    if (strlen($password) < 8) $errors[] = "Le mot de passe doit contenir au moins 8 caractères";
    if ($password !== $confirm_password) $errors[] = "Les mots de passe ne correspondent pas";

    // Vérification reCAPTCHA
    if (empty($recaptcha_response)) {
        $errors[] = "Veuillez compléter le reCAPTCHA";
    } else {
        $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
        $recaptcha_data = [
            'secret' => RECAPTCHA_SECRET_KEY,
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
        $result = file_get_contents($recaptcha_url, false, $context);
        $result_json = json_decode($result);

        if (!$result_json->success) {
            $errors[] = "Vérification reCAPTCHA échouée";
        }
    }

    if (empty($errors)) {
        try {
            // Connexion à la base de données
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM Utilisateur WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Cet email est déjà utilisé";
            } else {
                // Génération du token de vérification
                $verification_token = bin2hex(random_bytes(32));
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insertion
                $stmt = $pdo->prepare("INSERT INTO Utilisateur (nom, prenom, email, mot_de_passe, date_naissance, filiere, verification_token, is_verified, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'participant', NOW())");
                $stmt->execute([$nom, $prenom, $email, $hashed_password, $date_naissance, $filiere, $verification_token]);

                // Envoi de l'email de vérification
                $verification_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                    . "://" . $_SERVER['HTTP_HOST']
                    . dirname($_SERVER['PHP_SELF']) . "/verify.php?token=" . urlencode($verification_token);

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
                    $mail->addAddress($email, $prenom . ' ' . $nom);

                    // Contenu
                    $mail->isHTML(true);
                    $mail->Subject = "Vérification de votre compte Campus Event";
                    
                    $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
                            .container { max-width: 600px; margin: 0 auto; background: white; }
                            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; text-align: center; }
                            .header h1 { margin: 0; font-size: 32px; }
                            .content { padding: 40px; }
                            .button { display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                            .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Campus Event</h1>
                            </div>
                            <div class='content'>
                                <h2>Bonjour " . htmlspecialchars($prenom) . " " . htmlspecialchars($nom) . " !</h2>
                                <p>Merci de vous être inscrit sur <strong>Campus Event</strong> !</p>
                                <p>Pour activer votre compte et profiter de toutes nos fonctionnalités, veuillez cliquer sur le bouton ci-dessous :</p>
                                <div style='text-align: center;'>
                                    <a href='" . $verification_link . "' class='button'>Vérifier mon compte</a>
                                </div>
                                <p>Ou copiez ce lien dans votre navigateur :</p>
                                <p style='word-break: break-all; background: #f5f5f5; padding: 10px; border-radius: 5px;'>" . $verification_link . "</p>
                                <p><strong>Important :</strong> Ce lien expirera dans 24 heures.</p>
                                <p>Si vous n'avez pas créé de compte, ignorez simplement cet email.</p>
                            </div>
                            <div class='footer'>
                                <p>© 2025 Campus Event. Tous droits réservés.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $mail->Body = $message;
                    $mail->AltBody = "Bonjour " . $prenom . " " . $nom . "!\n\nMerci de vous être inscrit sur Campus Event !\n\nPour activer votre compte, veuillez cliquer sur le lien suivant : " . $verification_link . "\n\nCe lien expirera dans 24 heures.\n\nSi vous n'avez pas créé de compte, ignorez simplement cet email.";

                    $mail->send();
                    $success = "Inscription réussie ! Un email de vérification a été envoyé à votre adresse.";
                    $_POST = [];
                } catch (Exception $e) {
                    $errors[] = "Erreur lors de l'envoi de l'email : " . $mail->ErrorInfo;
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - CampusEvent</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

.register-container {
            max-width: 650px;
            width: 100%;
        }

        .back-home {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .back-home:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateX(-5px);
        }

        .register-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }

        .register-logo {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .register-subtitle {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            opacity: 0.95;
        }

        .register-body {
            padding: 2.5rem;
        }

        .form-label {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
            padding-left: 45px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            z-index: 10;
            font-size: 1rem;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .strength-meter {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: -0.75rem;
            margin-bottom: 1.25rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .strength-meter.weak {
            background: linear-gradient(90deg, #dc3545 0%, #dc3545 33%, #e0e0e0 33%);
        }

        .strength-meter.medium {
            background: linear-gradient(90deg, #ffc107 0%, #ffc107 66%, #e0e0e0 66%);
        }

        .strength-meter.strong {
            background: linear-gradient(90deg, #28a745 0%, #28a745 100%);
        }

        .alert {
            border-radius: 12px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            border: none;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }

        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
            color: white;
        }

        .alert ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .login-link {
            text-align: center;
            margin: 1.5rem 0;
            font-family: 'Montserrat', sans-serif;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .login-link a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .recaptcha-wrapper {
            display: flex;
            justify-content: center;
            margin: 1.5rem 0;
        }

        .btn-register {
            width: 100%;
            padding: 0.9rem;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        @media (max-width: 768px) {
            .register-body {
                padding: 2rem 1.5rem;
            }

            .register-header {
                padding: 1.5rem;
            }

            .register-logo {
                font-size: 1.6rem;
            }

            .back-home {
                position: static;
                margin-bottom: 1rem;
                display: inline-flex;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <a href="../home.php" class="back-home">
            <i class="fas fa-arrow-left"></i>
            Retour à l'accueil
        </a>

        <div class="register-card">
            <div class="register-header">
                <div class="register-logo">
                    <div>Campus</div>
                    <div>Event</div>
                </div>
                <p class="register-subtitle">Créez votre compte pour découvrir nos événements</p>
            </div>

            <div class="register-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Erreur !</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong><?php echo htmlspecialchars($success); ?></strong>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="nom" class="form-label">
                                <i class="fas fa-user me-1"></i> Nom
                            </label>
                            <div class="input-wrapper">
                                <span class="input-icon">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="nom" name="nom" placeholder="Votre nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="prenom" class="form-label">
                                <i class="fas fa-user me-1"></i> Prénom
                            </label>
                            <div class="input-wrapper">
                                <span class="input-icon">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="prenom" name="prenom" placeholder="Votre prénom" value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope me-1"></i> Email
                            </label>
                            <div class="input-wrapper">
                                <span class="input-icon">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="votre.email@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="date_naissance" class="form-label">
                                <i class="fas fa-calendar me-1"></i> Date de naissance
                            </label>
                            <div class="input-wrapper">
                                <span class="input-icon">
                                    <i class="fas fa-calendar"></i>
                                </span>
                                <input type="date" class="form-control" id="date_naissance" name="date_naissance" value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <label for="filiere" class="form-label">
                        <i class="fas fa-graduation-cap me-1"></i> Filière
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </span>
                        <select class="form-select" id="filiere" name="filiere" required>
                            <option value="">Sélectionnez votre filière</option>
                            <option value="Génie Informatique" <?php echo (($_POST['filiere'] ?? '') === 'Génie Informatique') ? 'selected' : ''; ?>>Génie Informatique</option>
                            <option value="Génie Civil" <?php echo (($_POST['filiere'] ?? '') === 'Génie Civil') ? 'selected' : ''; ?>>Génie Civil</option>
                            <option value="Génie Mécatronique" <?php echo (($_POST['filiere'] ?? '') === 'Génie Mécatronique') ? 'selected' : ''; ?>>Génie Mécatronique</option>
                            <option value="Big Data & AI" <?php echo (($_POST['filiere'] ?? '') === 'Big Data & AI') ? 'selected' : ''; ?>>Big Data & AI</option>
                            <option value="Supply Chain Management" <?php echo (($_POST['filiere'] ?? '') === 'Supply Chain Management') ? 'selected' : ''; ?>>Supply Chain Management</option>
                            <option value="Génie Télécommunication et réseaux" <?php echo (($_POST['filiere'] ?? '') === 'Génie Télécommunication et réseaux') ? 'selected' : ''; ?>>Génie Télécommunication et réseaux</option>
                            <option value="Cybersécurité" <?php echo (($_POST['filiere'] ?? '') === 'Cybersécurité') ? 'selected' : ''; ?>>Cybersécurité</option>
                            <option value="2AP" <?php echo (($_POST['filiere'] ?? '') === '2AP') ? 'selected' : ''; ?>>2AP</option>
                            <option value="Je suis externe" <?php echo (($_POST['filiere'] ?? '') === 'Je suis externe') ? 'selected' : ''; ?>>Je suis externe</option>
                        </select>
                    </div>

                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-1"></i> Mot de passe
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                        <span class="password-toggle" onclick="togglePassword('password', 'toggleIcon1')">
                            <i class="fas fa-eye" id="toggleIcon1"></i>
                        </span>
                    </div>
                    <div class="strength-meter" id="strengthMeter"></div>

                    <label for="confirm_password" class="form-label">
                        <i class="fas fa-lock me-1"></i> Confirmer le mot de passe
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                        <span class="password-toggle" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                            <i class="fas fa-eye" id="toggleIcon2"></i>
                        </span>
                    </div>

                    <div class="login-link">
                        Vous avez déjà un compte ? 
                        <a href="login.php">Connectez-vous</a>
                    </div>

                    <div class="recaptcha-wrapper">
                        <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                    </div>

                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus me-2"></i>
                        S'inscrire
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center mt-4">
            <small style="color: rgba(255, 255, 255, 0.9);">
                © 2025 Campus Event. Tous droits réservés.
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Password strength meter
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const meter = document.getElementById('strengthMeter');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            meter.className = 'strength-meter';
            if (strength >= 1 && strength <= 2) {
                meter.classList.add('weak');
            } else if (strength === 3) {
                meter.classList.add('medium');
            } else if (strength >= 4) {
                meter.classList.add('strong');
            }
        });

        // Animation au focus des inputs
        document.querySelectorAll('.form-control, .form-select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>