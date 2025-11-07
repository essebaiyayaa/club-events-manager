<?php 
session_start();
require_once __DIR__ . '/../config/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Récupération des informations de l'utilisateur
$sql = "SELECT * FROM Utilisateur WHERE id_utilisateur = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: auth/login.php');
    exit();
}

// Récupération des inscriptions de l'utilisateur avec détails des événements
$sqlInscriptions = "SELECT i.*, e.titre, e.date, e.lieu, e.affiche_url, e.tarif
                    FROM Inscription i
                    JOIN Evenement e ON i.id_evenement = e.id_evenement
                    WHERE i.id_utilisateur = :user_id
                    ORDER BY e.date DESC";
$stmtInscriptions = $pdo->prepare($sqlInscriptions);
$stmtInscriptions->execute(['user_id' => $userId]);
$inscriptions = $stmtInscriptions->fetchAll(PDO::FETCH_ASSOC);

// Gestion de la mise à jour du profil
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $date_naissance = $_POST['date_naissance'];
    $filiere = trim($_POST['filiere']);
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email)) {
        $errorMessage = "Nom, prénom et email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Format d'email invalide.";
    } else {
        // Vérifier si l'email existe déjà pour un autre utilisateur
        $sqlCheckEmail = "SELECT id_utilisateur FROM Utilisateur WHERE email = :email AND id_utilisateur != :id";
        $stmtCheck = $pdo->prepare($sqlCheckEmail);
        $stmtCheck->execute(['email' => $email, 'id' => $userId]);
        
        if ($stmtCheck->fetch()) {
            $errorMessage = "Cet email est déjà utilisé par un autre utilisateur.";
        } else {
            // Mise à jour du profil
            $sqlUpdate = "UPDATE Utilisateur 
                         SET nom = :nom, prenom = :prenom, email = :email, 
                             date_naissance = :date_naissance, filiere = :filiere
                         WHERE id_utilisateur = :id";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            
            if ($stmtUpdate->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'date_naissance' => $date_naissance ?: null,
                'filiere' => $filiere ?: null,
                'id' => $userId
            ])) {
                $successMessage = "Profil mis à jour avec succès !";
                // Recharger les données
                $stmt->execute(['id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errorMessage = "Erreur lors de la mise à jour du profil.";
            }
        }
    }
}

// Gestion du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMessage = "Tous les champs du mot de passe sont obligatoires.";
    } elseif (!password_verify($currentPassword, $user['mot_de_passe'])) {
        $errorMessage = "Le mot de passe actuel est incorrect.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = "Les nouveaux mots de passe ne correspondent pas.";
    } elseif (strlen($newPassword) < 6) {
        $errorMessage = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sqlUpdatePassword = "UPDATE Utilisateur SET mot_de_passe = :password WHERE id_utilisateur = :id";
        $stmtPassword = $pdo->prepare($sqlUpdatePassword);
        
        if ($stmtPassword->execute(['password' => $hashedPassword, 'id' => $userId])) {
            $successMessage = "Mot de passe modifié avec succès !";
        } else {
            $errorMessage = "Erreur lors du changement de mot de passe.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - CampusEvent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <style>
        body { background: #f8f9fa; }
        
        .hero-profile {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px 0 80px;
            color: white;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        .hero-profile::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 100vw solid transparent;
            border-right: 0 solid transparent;
            border-bottom: 40px solid #f8f9fa;
        }
        .hero-profile h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2rem;
            margin: 0;
        }

        .profile-container {
            padding: 40px 0 80px;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3rem;
            color: white;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
        }

        .profile-name {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .profile-role {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.9rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .section-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #667eea;
        }

        .form-label {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 15px;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .btn-update {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.35);
            color: white;
        }

        .inscription-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
            transition: all 0.3s ease;
        }

        .inscription-card:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.15);
        }

        .inscription-image {
            width: 120px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .inscription-info {
            flex: 1;
        }

        .inscription-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .inscription-meta {
            display: flex;
            gap: 15px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.85rem;
            color: #6c757d;
            flex-wrap: wrap;
        }

        .inscription-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .inscription-meta i {
            color: #667eea;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-attente {
            background: #fef3c7;
            color: #92400e;
        }

        .status-validee {
            background: #d1fae5;
            color: #065f46;
        }

        .status-refusee {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert-custom {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        .alert-success-custom {
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
        }

        .alert-error-custom {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
        }

        .no-inscriptions {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-family: 'Montserrat', sans-serif;
        }

        .no-inscriptions i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .profile-card {
                padding: 25px;
            }
            .inscription-card {
                flex-direction: column;
                text-align: center;
            }
            .inscription-image {
                width: 100%;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <section class="hero-profile">
        <div class="container">
            <h1>Mon Profil</h1>
        </div>
    </section>

    <section class="profile-container">
        <div class="container">
            <?php if ($successMessage): ?>
                <div class="alert-custom alert-success-custom">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert-custom alert-error-custom">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>

            <!-- En-tête du profil -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1)) ?>
                    </div>
                    <h2 class="profile-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h2>
                    <span class="profile-role">
                        <i class="fas fa-user-tag"></i> <?= ucfirst(htmlspecialchars($user['role'])) ?>
                    </span>
                </div>

                <!-- Informations du profil -->
                <h3 class="section-title">
                    <i class="fas fa-user-edit"></i>
                    Informations personnelles
                </h3>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($user['nom']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date de naissance</label>
                            <input type="date" name="date_naissance" class="form-control" value="<?= htmlspecialchars($user['date_naissance'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Filière</label>
                        <input type="text" name="filiere" class="form-control" value="<?= htmlspecialchars($user['filiere'] ?? '') ?>" placeholder="Ex: Informatique, Marketing...">
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="update_profile" class="btn-update">
                            <i class="fas fa-save"></i> Mettre à jour le profil
                        </button>
                    </div>
                </form>
            </div>

            <!-- Changement de mot de passe -->
            <div class="profile-card">
                <h3 class="section-title">
                    <i class="fas fa-lock"></i>
                    Changer le mot de passe
                </h3>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirmer le mot de passe</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="change_password" class="btn-update">
                            <i class="fas fa-key"></i> Changer le mot de passe
                        </button>
                    </div>
                </form>
            </div>
    </section>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>