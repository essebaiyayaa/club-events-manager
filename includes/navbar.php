<?php
// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);

$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
$user_role = $is_logged_in ? $_SESSION['user_role'] : null;
$user_name = $is_logged_in ? ($_SESSION['user_prenom'] ?? $_SESSION['user_nom'] ?? 'Utilisateur') : null;
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container position-relative">
        <!-- Logo stylisé -->
        <div class="logo">
            <div class="logo-container">
                <div class="logo-text">
                    <span class="campus">Campus</span>
                    <br>
                    <span class="event">Event</span>
                </div>
            </div>
        </div>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav nav-center">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'home.php') ? 'active' : ''; ?>" href="../public/home.php">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'evenements.php') ? 'active' : ''; ?>" href="../public/evenements.php">Événements</a>
                </li>
                
                <?php if ($is_logged_in && $user_role === 'participant'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'mes_inscriptions.php') ? 'active' : ''; ?>" href="../public/mes_inscriptions.php">Mes inscriptions</a>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="d-flex ms-auto align-items-center">
                <?php if ($is_logged_in && $user_role === 'participant'): ?>
                    <!-- Menu déroulant pour utilisateur connecté -->
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($user_name); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../public/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                            <li><a class="dropdown-item" href="../public/mes_inscriptions.php"><i class="bi bi-calendar-check me-2"></i>Mes inscriptions</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../public/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Se déconnecter</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <!-- Boutons de connexion/inscription pour utilisateurs non connectés -->
                    <a href="../public/auth/login.php" class="btn btn-outline-primary me-2">Se connecter</a>
                    <a href="../public/auth/register.php" class="btn btn-outline-primary">S'inscrire</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<style>
/* Styles pour le dropdown utilisateur */
.dropdown-menu {
    min-width: 200px;
}

.dropdown-item {
    padding: 0.5rem 1rem;
    transition: background-color 0.2s;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item i {
    width: 20px;
}
</style>