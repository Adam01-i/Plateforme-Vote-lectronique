<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscription | Plateforme de Vote Électronique</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: "Poppins", sans-serif;
    }

    body {
      height: 100vh;
      display: flex;
      background-color: #000;
      color: #fff;
    }

    .left-side {
      width: 45%;
      background-color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      border-top-right-radius: 40px;
      border-bottom-right-radius: 40px;
      box-shadow: 10px 0 25px rgba(255, 255, 255, 0.05);
      animation: slideInLeft 1.1s ease-in-out;
    }

    .form-box {
      width: 80%;
      max-width: 380px;
      text-align: center;
      color: #000;
    }

    .form-box h2 {
      color: #000;
      margin-bottom: 8px;
      font-size: 1.7rem;
    }

    .form-box p {
      color: #555;
      font-size: 0.9rem;
      margin-bottom: 28px;
    }

    .form-box input,
    .form-box select {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border-radius: 10px;
      border: 1px solid #ccc;
      font-size: 0.95rem;
      outline: none;
      transition: all 0.3s ease;
    }

    .form-box input:focus,
    .form-box select:focus {
      border-color: #000;
    }

    .form-box button {
      width: 100%;
      padding: 12px;
      background-color: #000;
      color: #fff;
      font-weight: 600;
      border: none;
      border-radius: 10px;
      font-size: 1rem;
      cursor: pointer;
      transition: 0.3s ease;
    }

    .form-box button:hover {
      background-color: #222;
    }

    .form-box .login {
      margin-top: 15px;
      font-size: 0.9rem;
    }

    .form-box .login a {
      color: #000;
      text-decoration: none;
      font-weight: bold;
    }

    .form-box .login a:hover {
      text-decoration: underline;
    }

    .alert {
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }

    .alert-error {
      background: #ffe6e6;
      color: #d63031;
      border: 1px solid #ff7675;
    }

    .right-side {
      width: 55%;
      background-color: #000;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 60px;
      animation: fadeInRight 1.3s ease-in-out;
    }

    .right-side img {
      width: 260px;
      height: auto;
      margin-bottom: 35px;
      filter: brightness(0) invert(1);
    }

    .right-side h1 {
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 12px;
      letter-spacing: 1px;
    }

    .right-side p {
      font-size: 0.95rem;
      color: #ccc;
      max-width: 350px;
      line-height: 1.5;
    }

    @keyframes fadeInRight {
      from { opacity: 0; transform: translateX(-100px); }
      to { opacity: 1; transform: translateX(0); }
    }

    @keyframes slideInLeft {
      from { transform: translateX(-100px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }

    @media (max-width: 900px) {
      body {
        flex-direction: column-reverse;
      }

      .left-side, .right-side {
        width: 100%;
        border-radius: 0;
      }

      .right-side {
        padding: 40px 20px;
      }

      .right-side img {
        width: 180px;
      }

      .left-side {
        padding: 50px 0;
        box-shadow: none;
      }
    }
  </style>
</head>
<body>
  <div class="left-side">
    <div class="form-box">
      <h2>Créer un compte</h2>
      <p>Inscrivez-vous pour participer au vote électronique</p>
      
      <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-error">
          ❌ Erreur lors de l'inscription. Email ou CNI déjà utilisé.
        </div>
      <?php endif; ?>

      <form action="/vote/controllers/auth.php" method="post">
        <input type="text" name="prenom" placeholder="Prénom" required>
        <input type="text" name="nom" placeholder="Nom" required>
        <input type="text" name="cni" placeholder="Numéro de CNI" required>
        <input type="email" name="email" placeholder="Adresse e-mail" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <select name="role" required>
          <option value="" disabled selected>Choisissez votre rôle</option>
          <option value="electeur">Électeur</option>
          <option value="admin">Administrateur</option>
        </select>
        <button type="submit" name="register">S'inscrire</button>
      </form>
      <div class="login">
        Déjà un compte ? <a href="login.php">Se connecter</a>
      </div>
            <!-- retour a l'accueil -->
      <div class="login">
        <a href="/vote/views/home/accueil.html">Retour à l'accueil</a>
      </div>
    </div>
  </div>

  <div class="right-side">
    <img src="/vote/assets/img/1.png" alt="Logo de la plateforme de vote">
    <h1>Plateforme de Vote Électronique</h1>
    <p>Votez en toute sécurité, confidentialité et transparence.</p>
  </div>
</body>
</html>