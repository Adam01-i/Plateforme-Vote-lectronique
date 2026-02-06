<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier si l'utilisateur est connecté et est un électeur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'electeur') {
  header("Location: /vote/views/auth/login.php");
  exit();
}

// Connexion à la base de données
require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Citoyen';

// Vérifier dans quels scrutins l'utilisateur a déjà voté
$query = "SELECT scrutin_id FROM Vote WHERE utilisateur_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$scrutins_votes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les scrutins actifs avec la vue optimisée
$query = "
    SELECT 
        scrutin_id as id,
        nom_scrutin as nom,
        type,
        description,
        date_debut,
        date_fin,
        statut,
        nb_candidats
    FROM vue_scrutins_avec_candidats 
    WHERE statut = 'en_cours' 
    AND date_debut <= NOW() 
    AND date_fin >= NOW()
    ORDER BY date_debut DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$scrutins_actifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les candidats pour chaque scrutin actif
$scrutins_avec_candidats = [];
foreach ($scrutins_actifs as $scrutin) {
  $query = "
        SELECT 
            c.id, 
            c.prenom, 
            c.nom, 
            c.parti_politique,
            c.photo_officiel,
            c.programme
        FROM Candidat c 
        INNER JOIN Scrutin_Candidat sc ON c.id = sc.candidat_id 
        WHERE sc.scrutin_id = :scrutin_id
        ORDER BY c.nom, c.prenom
    ";
  $stmt = $db->prepare($query);
  $stmt->bindParam(':scrutin_id', $scrutin['id']);
  $stmt->execute();
  $candidats = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $scrutins_avec_candidats[] = [
    'scrutin' => $scrutin,
    'candidats' => $candidats,
    'a_vote' => in_array($scrutin['id'], $scrutins_votes)
  ];
}

// Chemin de base pour les images et programmes
$image_base_path = '/vote/assets/img/candidats/';
$programme_base_path = '/vote/assets/programmes/';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vote Électronique — Plateforme de Vote</title>

  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Merriweather:wght@400;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      --gold: #aa9166;
      --black: #0b0b0b;
      --white: #fff;
      --grey: #f7f7f7;
      --light-grey: #f8f8f8;
      --success: #28a745;
      --warning: #ffc107;
      --danger: #dc3545;
      --shadow: 0 6px 20px rgba(0, 0, 0, 0.07);
      --transition: all 0.35s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "Poppins", sans-serif;
      background: var(--grey);
      color: var(--black);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* --- HEADER --- */
    header {
      background: var(--white);
      border-bottom: 3px solid var(--gold);
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
      padding: 18px 50px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 1px;
    }

    .logo h1 {
      font-family: "Georgia", serif;
      font-style: italic;
      font-weight: 900;
      font-size: 2.1rem;
      color: var(--gold);
      letter-spacing: 0.5px;
    }

    .logo img {
      width: 35px;
      height: auto;
      object-fit: contain;
      filter: brightness(0) saturate(100%) invert(56%) sepia(12%) saturate(1000%) hue-rotate(5deg) brightness(92%) contrast(88%);
    }

    nav ul {
      list-style: none;
      display: flex;
      gap: 28px;
      align-items: center;
    }

    nav ul li a {
      text-decoration: none;
      font-weight: 600;
      color: var(--black);
      position: relative;
      transition: var(--transition);
    }

    nav ul li a::after {
      content: "";
      position: absolute;
      bottom: -5px;
      left: 0;
      width: 0%;
      height: 2px;
      background: var(--gold);
      transition: width 0.3s ease;
    }

    nav ul li a:hover::after {
      width: 100%;
    }

    .logout-btn {
      background: var(--gold);
      color: var(--white);
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
    }

    .logout-btn:hover {
      background: var(--black);
      color: var(--gold);
      transform: scale(1.05);
    }

    /* --- SECTION PRINCIPALE --- */
    main {
      flex: 1;
      padding: 70px 80px 50px;
      animation: fadeIn 1s ease forwards;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(15px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .citizen-box {
      background: var(--white);
      border-left: 6px solid var(--gold);
      border-radius: 16px;
      padding: 40px;
      margin-bottom: 55px;
      box-shadow: var(--shadow);
      text-align: center;
    }

    .citizen-box h2 {
      font-family: "Merriweather", serif;
      font-size: 2.1rem;
      margin-bottom: 12px;
      color: var(--black);
    }

    .citizen-box p {
      font-size: 1.05rem;
      color: #555;
      line-height: 1.6;
    }

    .status-badge {
      display: inline-block;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
      margin-top: 10px;
    }

    .status-voted {
      background: #e8f5e9;
      color: var(--success);
    }

    .status-not-voted {
      background: #fff3cd;
      color: var(--warning);
    }

    h3.section-title {
      font-family: "Merriweather", serif;
      font-size: 1.7rem;
      margin-bottom: 25px;
      border-left: 5px solid var(--gold);
      padding-left: 12px;
      color: var(--black);
    }

    /* --- SCRUTIN CARDS --- */
    .scrutin-card {
      background: var(--white);
      border-radius: 16px;
      padding: 30px;
      margin-bottom: 40px;
      box-shadow: var(--shadow);
      border: 2px solid transparent;
      transition: var(--transition);
    }

    .scrutin-card:hover {
      border-color: var(--gold);
    }

    .scrutin-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 2px solid var(--light-grey);
    }

    .scrutin-title {
      font-family: "Merriweather", serif;
      font-size: 1.4rem;
      color: var(--black);
    }

    .scrutin-info {
      display: flex;
      gap: 20px;
      font-size: 0.9rem;
      color: #666;
    }

    .scrutin-dates {
      background: var(--light-grey);
      padding: 10px 15px;
      border-radius: 8px;
    }

    .scrutin-status {
      background: var(--gold);
      color: var(--white);
      padding: 8px 15px;
      border-radius: 8px;
      font-weight: 600;
    }

    /* --- GRID CANDIDATS --- */
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 28px;
    }

    .candidate-card {
      background: var(--white);
      border-radius: 18px;
      padding: 25px;
      box-shadow: var(--shadow);
      text-align: center;
      transition: var(--transition);
      border: 2px solid transparent;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .candidate-card:hover {
      transform: translateY(-8px);
      border-color: var(--gold);
      box-shadow: 0 10px 22px rgba(0, 0, 0, 0.1);
    }

    .candidate-card img {
      width: 110px;
      height: 110px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--gold);
      margin-bottom: 12px;
      transition: var(--transition);
    }

    .candidate-card:hover img {
      transform: scale(1.08);
    }

    .candidate-card h4 {
      font-size: 1.1rem;
      margin-bottom: 6px;
      color: var(--black);
      font-weight: 600;
    }

    .candidate-card p {
      font-size: 0.9rem;
      color: #555;
      margin-bottom: 14px;
    }

    .candidate-actions {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .vote-btn {
      background: var(--black);
      color: var(--white);
      border: none;
      padding: 9px 18px;
      border-radius: 8px;
      font-weight: 600;
      transition: var(--transition);
      cursor: pointer;
    }

    .vote-btn:hover {
      background: var(--gold);
      color: var(--black);
      transform: scale(1.03);
    }

    .vote-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    .programme-btn {
      background: var(--gold);
      color: var(--white);
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 0.85rem;
      transition: var(--transition);
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }

    .programme-btn:hover {
      background: var(--black);
      color: var(--gold);
    }

    .programme-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    /* --- MODAL --- */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      animation: fadeIn 0.3s ease;
    }

    .modal-content {
      background-color: var(--white);
      margin: 10% auto;
      padding: 30px;
      border-radius: 16px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      animation: slideIn 0.3s ease;
      text-align: center;
    }

    @keyframes slideIn {
      from {
        transform: translateY(-50px);
        opacity: 0;
      }

      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 2px solid var(--gold);
    }

    .modal-header h3 {
      font-family: "Merriweather", serif;
      color: var(--gold);
    }

    .close {
      color: #aaa;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      transition: var(--transition);
    }

    .close:hover {
      color: var(--text);
    }

    .modal-actions {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 25px;
    }

    .btn-cancel {
      background: #6c757d;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-cancel:hover {
      background: #5a6268;
    }

    .btn-confirm {
      background: var(--gold);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-confirm:hover {
      background: #8d7755;
    }

    .no-scrutins {
      text-align: center;
      padding: 40px;
      background: var(--white);
      border-radius: 16px;
      color: #666;
    }

    footer {
      background: var(--white);
      border-top: 2px solid var(--gold);
      text-align: center;
      padding: 25px;
      font-size: 0.9rem;
      color: #555;
    }

    @media (max-width: 900px) {
      main {
        padding: 40px 25px;
      }

      header {
        flex-direction: column;
        gap: 10px;
        text-align: center;
      }

      nav ul {
        flex-wrap: wrap;
        justify-content: center;
      }

      .scrutin-header {
        flex-direction: column;
        gap: 15px;
      }

      .scrutin-info {
        flex-direction: column;
        gap: 10px;
      }
    }
  </style>
</head>

<body>
  <header>
    <div class="logo">
      <h1>Vote</h1>
      <img src="/vote/assets/img/1.png" alt="Logo Vote">
    </div>

    <nav>
      <ul>
        <li><a href="accueil_electeur.php">Accueil</a></li>
        <li><a href="resultats.php">Résultats</a></li>
        <li><a href="/vote/controllers/logout.php" class="logout-btn">Déconnexion</a></li>
      </ul>
    </nav>
  </header>

  <main>
    <div class="citizen-box">
      <h2>Bienvenue, <?php echo htmlspecialchars($user_name); ?></h2>
      <p>
        Plateforme de vote électronique sécurisée de la République du Sénégal.<br>
        Votre vote est anonyme, sécurisé et garanti par l'État.
      </p>
      <?php if (count($scrutins_votes) > 0): ?>
        <div class="status-badge status-voted">
          <i class="fa-solid fa-check-circle"></i>
          Vous avez voté dans <?php echo count($scrutins_votes); ?> scrutin(s)
        </div>
      <?php else: ?>
        <div class="status-badge status-not-voted">
          <i class="fa-solid fa-clock"></i>
          En attente de vote
        </div>
      <?php endif; ?>
    </div>

    <?php if (count($scrutins_avec_candidats) > 0): ?>
      <?php foreach ($scrutins_avec_candidats as $scrutin_data):
        $scrutin = $scrutin_data['scrutin'];
        $candidats = $scrutin_data['candidats'];
        $a_vote = $scrutin_data['a_vote'];
        ?>
        <section class="scrutin-card">
          <div class="scrutin-header">
            <div>
              <h3 class="scrutin-title"><?php echo htmlspecialchars($scrutin['nom']); ?></h3>
              <p><?php echo htmlspecialchars($scrutin['description']); ?></p>
            </div>
            <div class="scrutin-info">
              <div class="scrutin-dates">
                <strong>Période de vote :</strong><br>
                <?php echo date('d/m/Y H:i', strtotime($scrutin['date_debut'])); ?> -
                <?php echo date('d/m/Y H:i', strtotime($scrutin['date_fin'])); ?>
              </div>
              <div class="scrutin-dates">
                <strong>Candidats :</strong> <?php echo $scrutin['nb_candidats']; ?>
              </div>
              <?php if ($a_vote): ?>
                <div class="scrutin-status">
                  <i class="fa-solid fa-check"></i> Vous avez voté
                </div>
              <?php endif; ?>
            </div>
          </div>

          <h3 class="section-title">Liste des Candidats</h3>

          <div class="grid">
            <?php foreach ($candidats as $candidate):
              $full_name = $candidate['prenom'] . ' ' . $candidate['nom'];
              $party = $candidate['parti_politique'];
              $photo = $candidate['photo_officiel'];
              $programme = $candidate['programme'];

              $photo_path = $image_base_path . $photo;
              $programme_path = $programme ? $programme_base_path . $programme : '#';
              ?>
              <div class="candidate-card">
                <div>
                  <img src="<?php echo $photo_path; ?>" alt="<?php echo htmlspecialchars($full_name); ?>"
                    onerror="this.src='/vote/assets/img/1.png'">
                  <h4><?php echo htmlspecialchars($full_name); ?></h4>
                  <p><?php echo htmlspecialchars($party); ?></p>
                </div>
                <div class="candidate-actions">
                  <?php if ($programme): ?>
                    <a href="<?php echo $programme_path; ?>" target="_blank" class="programme-btn">
                      <i class="fa-solid fa-file-pdf"></i> Voir le programme
                    </a>
                  <?php else: ?>
                    <button class="programme-btn" disabled>
                      <i class="fa-solid fa-file-pdf"></i> Programme indisponible
                    </button>
                  <?php endif; ?>

                  <button class="vote-btn" <?php echo $a_vote ? 'disabled' : ''; ?>
                    onclick='openVoteModal(
                        <?php echo (int) $candidate['id']; ?>, 
                        <?php echo json_encode($full_name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo (int) $scrutin['id']; ?>)'>
                    <i class="fa-solid fa-vote-yea"></i>
                    <?php echo $a_vote ? 'Déjà voté' : 'Voter'; ?>
                  </button>

                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-scrutins">
        <i class="fa-solid fa-calendar-times" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
        <h3>Aucun scrutin actif</h3>
        <p>Il n'y a actuellement aucun scrutin en cours. Veuillez revenir ultérieurement.</p>
      </div>
    <?php endif; ?>
  </main>

  <!-- Modal de confirmation de vote -->
  <div id="voteModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Confirmer votre vote</h3>
        <span class="close" onclick="closeVoteModal()">&times;</span>
      </div>
      <div id="voteModalContent">
        <!-- Contenu chargé dynamiquement -->
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeVoteModal()">Annuler</button>
        <button type="button" class="btn-confirm" onclick="confirmVote()">Confirmer le vote</button>
      </div>
    </div>
  </div>

  <footer>
    © 2025 République du Sénégal — Système de Vote Électronique
  </footer>

  <script>
    let selectedVote = {
      candidateId: null,
      scrutinId: null
    };

    /* -------------------------------
       OUVERTURE DU MODAL DE CONFIRMATION
    -------------------------------- */
    function openVoteModal(candidateId, candidateName, scrutinId) {

      console.log("DEBUG openVoteModal:", {
        candidateId,
        candidateName,
        scrutinId
      });

      selectedVote.candidateId = candidateId;
      selectedVote.scrutinId = scrutinId;

      // Injecter dans la modale
      document.getElementById("voteModalContent").innerHTML = `
        <p>Êtes-vous sûr de vouloir voter pour :</p>
        <h4 style="color: var(--gold); margin: 15px 0;">${candidateName}</h4>
        <p style="color: #666; font-size: 0.9rem;">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <strong>Attention :</strong> Cette action est irréversible.
        </p>
    `;

      document.getElementById("voteModal").style.display = "block";
    }

    /* -------------------------------
       FERMETURE DE LA MODALE
    -------------------------------- */
    function closeVoteModal() {
      document.getElementById("voteModal").style.display = "none";
    }

    /* -------------------------------
       CONFIRMATION DU VOTE
    -------------------------------- */
    function confirmVote() {

      console.log("DEBUG confirmVote - Envoi:", {
        candidate_id: selectedVote.candidateId,
        scrutin_id: selectedVote.scrutinId
      });

      // Désactiver les boutons de vote
      const buttons = document.querySelectorAll(".vote-btn");
      buttons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Vote en cours...';
      });

      closeVoteModal();

      fetch("/vote/controllers/vote.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          candidate_id: selectedVote.candidateId,  // <-- clé en snake_case
          scrutin_id: selectedVote.scrutinId       // <-- clé en snake_case
        })
      })
        .then(res => res.json())
        .then(data => {
          console.log("DEBUG vote.php réponse:", data);

          if (data.success) {
            openSuccessModal(); // <-- affichage du nouveau modal
          } else {
            alert("❌ Erreur : " + data.message);

            // Réactiver les boutons en cas d'échec
            buttons.forEach(btn => {
              btn.disabled = false;
              btn.innerHTML = '<i class="fa-solid fa-vote-yea"></i> Voter';
            });
          }

        })
        .catch(err => {
          console.error("Erreur fetch:", err);
          alert("❌ Erreur de connexion au serveur");

          // Réactivation
          buttons.forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-vote-yea"></i> Voter';
          });
        });
    }

    function openSuccessModal() {
      document.getElementById("successModal").style.display = "block";
    }

    function closeSuccessModal() {
      document.getElementById("successModal").style.display = "none";
    }

    function reloadPage() {
      window.location.reload();
    }


    // Fermer le modal en cliquant à l'extérieur
    window.onclick = function (event) {
      const modal = document.getElementById('voteModal');
      if (event.target == modal) {
        closeVoteModal();
      }
    }

    // Empêcher la fermeture du modal en cliquant à l'intérieur
    document.querySelector('.modal-content').addEventListener('click', (e) => {
      e.stopPropagation();
    });

    // Mise à jour automatique de la page toutes les 30 secondes
    setInterval(() => {
      window.location.reload();
    }, 30000);
  </script>
  <!-- Modal de succès après vote -->
  <div id="successModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Vote Enregistré</h3>
        <span class="close" onclick="closeSuccessModal()">&times;</span>
      </div>
      <div style="text-align:center; padding: 20px 0;">
        <i class="fa-solid fa-check-circle" style="font-size: 3rem; color: var(--success);"></i>
        <p style="margin-top: 15px; font-size: 1.1rem; color: #333;">
          ✔️ Votre vote a été enregistré avec succès !
        </p>
      </div>
      <div class="modal-actions">
        <button class="btn-confirm" onclick="reloadPage()">OK</button>
      </div>
    </div>
  </div>



</body>




</html>