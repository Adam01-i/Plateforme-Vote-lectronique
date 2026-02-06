<?php
session_start();

// Rediriger vers la page appropriée selon le rôle
if(isset($_SESSION['user_id'])) {
    if($_SESSION['user_role'] === 'admin') {
        header("Location: /vote/views/admin/dashboard_admin.php");
    } else if($_SESSION['user_role'] === 'electeur') {
        header("Location: /vote/views/electeur/accueil_electeur.php");
    } else {
        header("Location: /vote/views/home/accueil.html");
    }
} else {
    header("Location: /vote/views/auth/login.php");
}
exit();
?>