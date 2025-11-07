<?php
session_start();
require_once 'C:/xampp/htdocs/campusEvents/config/config.php';

// Vérifier que l'utilisateur est connecté et est organisateur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'organisateur') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $date = $_POST['date'];
    $lieu = trim($_POST['lieu']);
    $capacite_max = intval($_POST['capacite_max']);
    $tarif = floatval($_POST['tarif']);
    $id_organisateur = $_SESSION['user_id'];
    
    // Récupérer l'ID du club dont l'organisateur est président
    $club_query = "SELECT id_club FROM Club WHERE id_president = ?";
    $club_stmt = $pdo->prepare($club_query);
    $club_stmt->execute([$id_organisateur]);
    $club = $club_stmt->fetch(PDO::FETCH_ASSOC);
    $id_club = $club ? $club['id_club'] : null;
    
    // Gestion de l'upload de l'affiche
    $affiche_url = null;
    if (isset($_FILES['affiche']) && $_FILES['affiche']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/affiches/'; // Remonte d'un niveau depuis organisateur/ vers uploads/
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['affiche']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            // Vérifier la taille du fichier (max 5MB)
            if ($_FILES['affiche']['size'] <= 5 * 1024 * 1024) {
                $new_filename = uniqid('affiche_') . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['affiche']['tmp_name'], $destination)) {
                    // Stocker le chemin relatif depuis la racine du site
                    $affiche_url = 'uploads/affiches/' . $new_filename;
                }
            }
        }
    }
    
    try {
        // Insérer l'événement dans la base de données avec l'ID du club
        $query = "INSERT INTO Evenement (titre, description, affiche_url, date, lieu, capacite_max, tarif, id_organisateur, id_club) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$titre, $description, $affiche_url, $date, $lieu, $capacite_max, $tarif, $id_organisateur, $id_club]);
        
        $_SESSION['success'] = "Événement créé avec succès !";
        header('Location: dashboard_organisateur.php?page=events');
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la création de l'événement : " . $e->getMessage();
        header('Location: dashboard_organisateur.php?page=create');
        exit();
    }
}
?>