
<?php
$servername = "localhost";
$username = "root"; // adapte selon ton cas
$password = "";     // adapte si besoin
$dbname = "gestion_evenements";

try {
    // Connexion uniquement au serveur (sans base de données)
    $conn = new PDO("mysql:host=$servername", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Créer la base si elle n’existe pas
    $conn->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Sélectionner la base
    $conn->exec("USE $dbname");

    // Table Utilisateur
    $conn->exec("
        CREATE TABLE IF NOT EXISTS Utilisateur (
            id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            mot_de_passe VARCHAR(255) NOT NULL,
            date_naissance DATE,
            filiere VARCHAR(100),
            role ENUM('admin', 'organisateur', 'participant') DEFAULT 'participant',
            verification_token VARCHAR(64), 
            is_verified TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");

    // Table Club
    $conn->exec("
        CREATE TABLE IF NOT EXISTS Club (
            id_club INT AUTO_INCREMENT PRIMARY KEY,
            nom_club VARCHAR(100) NOT NULL,
            description TEXT,
            id_president INT,
            FOREIGN KEY (id_president) REFERENCES Utilisateur(id_utilisateur) ON DELETE SET NULL
        ) ENGINE=InnoDB;
    ");

    // Table Evenement
    $conn->exec("
        CREATE TABLE IF NOT EXISTS Evenement (
            id_evenement INT AUTO_INCREMENT PRIMARY KEY,
            titre VARCHAR(150) NOT NULL,
            description TEXT,
            affiche_url VARCHAR(255),
            date DATE NOT NULL,
            lieu VARCHAR(150),
            capacite_max INT,
            tarif DECIMAL(10,2) DEFAULT 0.00,
            id_club INT,
            id_organisateur INT,
            FOREIGN KEY (id_club) REFERENCES Club(id_club) ON DELETE CASCADE,
            FOREIGN KEY (id_organisateur) REFERENCES Utilisateur(id_utilisateur) ON DELETE SET NULL
        ) ENGINE=InnoDB;
    ");

    // Table Inscription
    $conn->exec("
        CREATE TABLE IF NOT EXISTS Inscription (
            id_inscription INT AUTO_INCREMENT PRIMARY KEY,
            id_utilisateur INT NOT NULL,
            id_evenement INT NOT NULL,
            status ENUM('en attente', 'validée', 'refusée') DEFAULT 'en attente',
            status_paiment ENUM('payé', 'non payé') DEFAULT 'non payé',
            date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE,
            FOREIGN KEY (id_evenement) REFERENCES Evenement(id_evenement) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // Table Attestation
    $conn->exec("
        CREATE TABLE IF NOT EXISTS Attestation (
            id_attestation INT AUTO_INCREMENT PRIMARY KEY,
            date_generation DATETIME DEFAULT CURRENT_TIMESTAMP,
            chemin_pdf VARCHAR(255),
            id_inscription INT UNIQUE,
            FOREIGN KEY (id_inscription) REFERENCES Inscription(id_inscription) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // Table Email
    $conn->exec("
        CREATE TABLE IF NOT EXISTS Email (
            id_email INT AUTO_INCREMENT PRIMARY KEY,
            objet VARCHAR(255) NOT NULL,
            contenu TEXT NOT NULL,
            date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
            id_evenement INT,
            FOREIGN KEY (id_evenement) REFERENCES Evenement(id_evenement) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // Table Fichier
    $conn->exec("
        CREATE TABLE IF NOT EXISTS Fichier (
            id_fichier INT AUTO_INCREMENT PRIMARY KEY,
            nom_fichier VARCHAR(255),
            type_fichier VARCHAR(100),
            chemin_fichier VARCHAR(255),
            date_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
            id_evenement INT,
            FOREIGN KEY (id_evenement) REFERENCES Evenement(id_evenement) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // Table password_resets
    $conn->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX idx_token (token),
            INDEX idx_email (email),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Table Inscription_Pending
    $conn->exec("
        CREATE TABLE IF NOT EXISTS Inscription_Pending (
            id_pending INT PRIMARY KEY AUTO_INCREMENT,
            id_utilisateur INT NOT NULL,
            id_evenement INT NOT NULL,
            confirmation_token VARCHAR(64) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur),
            FOREIGN KEY (id_evenement) REFERENCES Evenement(id_evenement)
        ) ENGINE=InnoDB;
    ");

} catch(PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusEvent - Accueil</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Fichier Style -->
    <link rel="stylesheet" href="../public/assets/css/style.css">
</head>
<body>

    <!-- Navbar -->
    <?php include '../includes/navbar.php'; ?>

    <!-- HERO SECTION -->
    <section class="hero-fullscreen d-flex align-items-center">
        <div class="container text-center">
            <h1 class="hero-title mb-3">
                <span class="d-block">CampusEvent – L’application web de la</span>
                <span class="hero-highlight">GESTION DES PARTICIPANTS AUX ÉVÉNEMENTS</span>
                <span class="d-block">QUI SIMPLIFIE L’ORGANISATION</span>
            </h1>

            <p class="hero-lead mx-auto mb-4">
                Quel organisateur n’a pas déjà perdu du temps avec les inscriptions ?
                Grâce à cette interface intuitive, CampusEvent vous garantit une gestion
                simple, rapide et efficace des événements des clubs.
            </p>

            <a href="../public/evenements.php" class="btn btn-hero btn-lg">
                Voir les <em>événements</em>
            </a>
        </div>
    </section>

    <!-- Section Comment ça marche -->
    <section class="py-5 bg-white">
        <div class="container">
            <h2 class="text-center mb-5 section-title">Comment ça marche</h2>
            <div class="row g-4">
                <div class="col-md-3 text-center">
                    <div class="step-card p-4 bg-white rounded shadow-sm">
                        <div class="step-number mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <span class="h4 mb-0">1</span>
                        </div>
                        <div class="step-icon mb-3">
                            <i class="fas fa-user-plus fa-2x"></i>
                        </div>
                        <h5 class="step-title">Créer un compte</h5>
                        <p class="text-muted">Inscrivez-vous gratuitement en quelques clics pour accéder à tous les événements</p>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-card p-4 bg-white rounded shadow-sm">
                        <div class="step-number mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <span class="h4 mb-0">2</span>
                        </div>
                        <div class="step-icon mb-3">
                            <i class="fas fa-calendar-alt fa-2x"></i>
                        </div>
                        <h5 class="step-title">Consulter les événements</h5>
                        <p class="text-muted">Découvrez tous les événements organisés par les clubs</p>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-card p-4 bg-white rounded shadow-sm">
                        <div class="step-number mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <span class="h4 mb-0">3</span>
                        </div>
                        <div class="step-icon mb-3">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <h5 class="step-title">S'inscrire à un événement</h5>
                        <p class="text-muted">Inscrivez-vous facilement aux événements qui vous intéressent</p>
                    </div>
                </div>
                <div class="col-md-3 text-center">
                    <div class="step-card p-4 bg-white rounded shadow-sm">
                        <div class="step-number mx-auto mb-3 d-flex align-items-center justify-content-center">
                            <span class="h4 mb-0">4</span>
                        </div>
                        <div class="step-icon mb-3">
                            <i class="fas fa-certificate fa-2x"></i>
                        </div>
                        <h5 class="step-title">Recevoir l'attestation</h5>
                        <p class="text-muted">Obtenez automatiquement votre attestation de participation par email</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Pourquoi CampusEvent -->
    <section class="why-section py-5">
        <div class="container">
            <h2 class="section-title text-center mb-5">Pourquoi choisir CampusEvent ?</h2>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="fas fa-shield-alt feature-icon"></i>
                        </div>
                        <div class="feature-content">
                            <h5 class="feature-title">Inscriptions sécurisées</h5>
                            <p class="feature-text">Système CAPTCHA et validation par email pour des inscriptions fiables</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="fas fa-envelope feature-icon"></i>
                        </div>
                        <div class="feature-content">
                            <h5 class="feature-title">Communication centralisée</h5>
                            <p class="feature-text">Envoyez emails et fichiers à tous les participants en un clic</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="fas fa-certificate feature-icon"></i>
                        </div>
                        <div class="feature-content">
                            <h5 class="feature-title">Attestations automatiques</h5>
                            <p class="feature-text">Génération et envoi automatiques des certificats de participation</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon-wrapper">
                            <i class="fa-solid fa-chart-simple feature-icon"></i>
                        </div>
                        <div class="feature-content">
                            <h5 class="feature-title">Tableaux de bord complets</h5>
                            <p class="feature-text">Suivez vos événements avec des statistiques détaillées</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
