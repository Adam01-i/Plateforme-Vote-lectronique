<?php
// controllers/auth.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once '../config/database.php';
require_once '../models/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

if(isset($_POST['register'])) {
    $user->nom = $_POST['nom'];
    $user->prenom = $_POST['prenom'];
    $user->cni = $_POST['cni'];
    $user->email = $_POST['email'];
    $user->mdp = $_POST['password'];
    $user->role = $_POST['role'];

    if($user->register()) {
        header("Location: /vote/views/auth/login.php?success=1");
    } else {
        header("Location: /vote/views/auth/signup.php?error=1");
    }
    exit();
}

if(isset($_POST['login'])) {
    $user->email = $_POST['email'];
    $user->mdp = $_POST['password'];

    if($user->login()) {
        // REDIRECTION EN FONCTION DU RÔLE
        if($_SESSION['user_role'] === 'admin') {
            header("Location: /vote/views/admin/dashboard_admin.php");
        } else {
            header("Location: /vote/views/electeur/accueil_electeur.php");
        }
    } else {
        header("Location: /vote/views/auth/login.php?error=1");
    }
    exit();
}
?>