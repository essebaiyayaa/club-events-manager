<?php
session_start();

require_once __DIR__ . '/../../config/config.php';

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Récupération et nettoyage des données
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validation des champs obligatoires
    if (empty($email) || empty($password)) {
        // Préserver le paramètre redirect si présent
        $redirectParam = isset($_POST['redirect']) ? '&redirect=' . urlencode($_POST['redirect']) : '';
        header('Location: login.php?error=empty' . $redirectParam);
        exit();
    }
    
    // Validation de l'email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $redirectParam = isset($_POST['redirect']) ? '&redirect=' . urlencode($_POST['redirect']) : '';
        header('Location: login.php?error=invalid_email' . $redirectParam);
        exit();
    }
    
    try {
        // Recherche de l'utilisateur dans la base de données
        $stmt = $pdo->prepare("
            SELECT 
                id_utilisateur,
                nom,
                prenom,
                email,
                mot_de_passe,
                role,
                filiere,
                date_naissance
            FROM Utilisateur 
            WHERE email = :email
            LIMIT 1
        ");
        
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        // Vérification si l'utilisateur existe
        if (!$user) {
            // L'utilisateur n'existe pas
            sleep(1); // Protection contre brute force
            $redirectParam = isset($_POST['redirect']) ? '&redirect=' . urlencode($_POST['redirect']) : '';
            header('Location: login.php?error=invalid' . $redirectParam);
            exit();
        }
        
        // Vérification du mot de passe avec password_verify
        if (!password_verify($password, $user['mot_de_passe'])) {
            // Mot de passe incorrect
            sleep(1); // Protection contre brute force
            $redirectParam = isset($_POST['redirect']) ? '&redirect=' . urlencode($_POST['redirect']) : '';
            header('Location: login.php?error=invalid' . $redirectParam);
            exit();
        }
        
        
        // ✅ Connexion réussie - Créer la session
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id_utilisateur'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_prenom'] = $user['prenom'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_filiere'] = $user['filiere'] ?? '';
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        // 🔒 Vérifier si une redirection personnalisée a été demandée
        if (isset($_POST['redirect']) && !empty($_POST['redirect'])) {
            $redirect = $_POST['redirect'];
            
            // Protection : éviter une URL externe (redirection malveillante)
            // On autorise uniquement les chemins relatifs
            if (!preg_match('#^https?://#i', $redirect)) {
                // Construire le chemin complet depuis auth/
                header('Location: ../' . ltrim($redirect, '/'));
                exit();
            }
        }
        
        // Redirection par défaut selon le rôle de l'utilisateur
        switch ($user['role']) {
            case 'admin':
                header('Location: ../admin/dashboard_admin.php');
                break;
            case 'organisateur':
                header('Location: ../organisateur/dashboard_organisateur.php');
                break;
            case 'participant':
            default:
                header('Location: ../home.php');
                break;
        }
        exit();
        
    } catch (PDOException $e) {
        // Erreur de base de données
        error_log("Erreur de connexion : " . $e->getMessage());
        $redirectParam = isset($_POST['redirect']) ? '&redirect=' . urlencode($_POST['redirect']) : '';
        header('Location: login.php?error=system' . $redirectParam);
        exit();
    }
    
} else {
    // Accès direct au fichier sans POST
    header('Location: login.php');
    exit();
}
?>