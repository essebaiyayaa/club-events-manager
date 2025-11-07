
CREATE DATABASE IF NOT EXISTS gestion_evenements;
USE gestion_evenements;

CREATE TABLE Utilisateur (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL AFTER email;
    date_naissance DATE,
    filiere VARCHAR(100),
    role ENUM('admin', 'organisateur', 'participant') DEFAULT 'participant'
    verification_token VARCHAR(64), 
    is_verified TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE Club (
    id_club INT AUTO_INCREMENT PRIMARY KEY,
    nom_club VARCHAR(100) NOT NULL,
    description TEXT,
    id_president INT,
    FOREIGN KEY (id_president) REFERENCES Utilisateur(id_utilisateur)
        ON DELETE SET NULL
);


CREATE TABLE Evenement (
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
    FOREIGN KEY (id_club) REFERENCES Club(id_club)
        ON DELETE CASCADE,
    FOREIGN KEY (id_organisateur) REFERENCES Utilisateur(id_utilisateur)
        ON DELETE SET NULL
);


CREATE TABLE Inscription (
    id_inscription INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT NOT NULL,
    id_evenement INT NOT NULL,
    status ENUM('en attente', 'validée', 'refusée') DEFAULT 'en attente',
    status_paiment ENUM('payé', 'non payé') DEFAULT 'non payé',
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur)
        ON DELETE CASCADE,
    FOREIGN KEY (id_evenement) REFERENCES Evenement(id_evenement)
        ON DELETE CASCADE
);


CREATE TABLE Attestation (
    id_attestation INT AUTO_INCREMENT PRIMARY KEY,
    date_generation DATETIME DEFAULT CURRENT_TIMESTAMP,
    chemin_pdf VARCHAR(255),
    id_inscription INT UNIQUE,
    FOREIGN KEY (id_inscription) REFERENCES Inscription(id_inscription)
        ON DELETE CASCADE
);

CREATE TABLE Email (
    id_email INT AUTO_INCREMENT PRIMARY KEY,
    objet VARCHAR(255) NOT NULL,
    contenu TEXT NOT NULL,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_evenement INT,
    FOREIGN KEY (id_evenement) REFERENCES Evenement(id_evenement)
        ON DELETE CASCADE
);


CREATE TABLE Fichier (
    id_fichier INT AUTO_INCREMENT PRIMARY KEY,
    nom_fichier VARCHAR(255),
    type_fichier VARCHAR(100),
    chemin_fichier VARCHAR(255),
    date_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_evenement INT,
    FOREIGN KEY (id_evenement) REFERENCES Evenement(id_evenement)
        ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    INDEX `idx_token` (`token`),
    INDEX `idx_email` (`email`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Inscription_Pending (
    id_pending INT PRIMARY KEY AUTO_INCREMENT,
    id_utilisateur INT NOT NULL,
    id_evenement INT NOT NULL,
    confirmation_token VARCHAR(64) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (id_utilisateur) REFERENCES Utilisateur(id_utilisateur),
    FOREIGN KEY (id_evenement) REFERENCES Evenement(id_evenement)
);
