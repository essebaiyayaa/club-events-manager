<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';

// Vérifier que l'utilisateur est organisateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: C:/xampp/htdocs/campusEvents/public/auth/login.php');
    exit();
}

$user_name = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Récupérer le mot de passe actuel
        $query = "SELECT mot_de_passe FROM Utilisateur WHERE id_utilisateur = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Vérifier le mot de passe actuel
        if (!password_verify($current_password, $user['mot_de_passe'])) {
            $_SESSION['error'] = "Le mot de passe actuel est incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "Les nouveaux mots de passe ne correspondent pas.";
        } elseif (strlen($new_password) < 8) {
            $_SESSION['error'] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        } else {
            // Mettre à jour le mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE Utilisateur SET mot_de_passe = ? WHERE id_utilisateur = ?";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            $_SESSION['success'] = "Mot de passe modifié avec succès !";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe - Organisateur</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            padding: 2rem;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 2rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.25);
            color: white;
        }

        .form-container {
            background: white;
            padding: 3rem;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 12px;
            padding: 0.8rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .alert {
            border-radius: 15px;
            margin-bottom: 1.5rem;
            padding: 1rem;
        }

        .security-tips {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .security-tips h5 {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .security-tips ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .security-tips li {
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard_organisateur.php?page=events" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Retour au dashboard
        </a>

        <div class="form-container">
            <div class="form-header">
                <h1> Changer le mot de passe</h1>
                <p style="color: #6c757d;">Sécurisez votre compte organisateur</p>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border: none;">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $_SESSION['success'] ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; border: none;">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $_SESSION['error'] ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-lock me-2"></i>
                        Mot de passe actuel
                    </label>
                    <input type="password" class="form-control" name="current_password" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-key me-2"></i>
                        Nouveau mot de passe
                    </label>
                    <input type="password" class="form-control" name="new_password" required minlength="8">
                    <small class="text-muted">Minimum 8 caractères</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-check-double me-2"></i>
                        Confirmer le nouveau mot de passe
                    </label>
                    <input type="password" class="form-control" name="confirm_password" required minlength="8">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save me-2"></i>
                    Changer le mot de passe
                </button>
            </form>

            <div class="security-tips">
                <h5><i class="fas fa-shield-alt me-2"></i>Conseils de sécurité</h5>
                <ul>
                    <li>Utilisez au moins 8 caractères</li>
                    <li>Mélangez majuscules, minuscules, chiffres et symboles</li>
                    <li>N'utilisez pas d'informations personnelles</li>
                    <li>Ne réutilisez pas un mot de passe existant</li>
                    <li>Changez votre mot de passe régulièrement</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>