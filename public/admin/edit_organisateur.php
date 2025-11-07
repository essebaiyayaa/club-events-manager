<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: C:/xampp/htdocs/campusEvents/public/auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: dashboard_admin.php?page=organisateurs');
    exit();
}

$organisateur_id = intval($_GET['id']);


$query = "SELECT * FROM Utilisateur WHERE id_utilisateur = ? AND role = 'organisateur'";
$stmt = $pdo->prepare($query);
$stmt->execute([$organisateur_id]);
$organisateur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$organisateur) {
    $_SESSION['error'] = "Organisateur introuvable.";
    header('Location: dashboard_admin.php?page=organisateurs');
    exit();
}

$user_name = $_SESSION['user_nom'] . ' ' . $_SESSION['user_prenom'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'Organisateur - CampusEvent</title>
    
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
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 900px;
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
            text-decoration: none;
        }

        .form-container {
            background: white;
            padding: 3rem;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .form-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .form-header h1 {
            font-family: 'Poppins', sans-serif;
            font-weight: 900;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 2px solid rgba(255, 107, 107, 0.2);
            border-radius: 12px;
            padding: 0.8rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #ff6b6b;
            box-shadow: 0 0 0 4px rgba(255, 107, 107, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.4);
        }

        .alert {
            border-radius: 15px;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard_admin.php?page=organisateurs" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Retour aux organisateurs
        </a>

        <div class="form-container">
            <div class="form-header">
                <h1>✏️ Modifier l'Organisateur</h1>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $_SESSION['error'] ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="process_edit_organisateur.php" method="POST">
                <input type="hidden" name="id_utilisateur" value="<?= $organisateur['id_utilisateur'] ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom</label>
                        <input type="text" class="form-control" name="nom" value="<?= htmlspecialchars($organisateur['nom']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Prénom</label>
                        <input type="text" class="form-control" name="prenom" value="<?= htmlspecialchars($organisateur['prenom']) ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($organisateur['email']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Date de Naissance</label>
                    <input type="date" class="form-control" name="date_naissance" value="<?= $organisateur['date_naissance'] ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Filière</label>
                    <input type="text" class="form-control" name="filiere" value="<?= htmlspecialchars($organisateur['filiere']) ?>">
                </div>

                <div class="alert" style="background: rgba(255, 107, 107, 0.1); border: 2px solid rgba(255, 107, 107, 0.2); color: #ff6b6b;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> Pour changer le mot de passe, laissez le champ vide. Sinon, entrez un nouveau mot de passe.
                </div>

                <div class="mb-3">
                    <label class="form-label">Nouveau Mot de Passe (optionnel)</label>
                    <input type="password" class="form-control" name="new_password" placeholder="Laissez vide pour conserver le mot de passe actuel">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save me-2"></i>
                    Enregistrer les modifications
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>