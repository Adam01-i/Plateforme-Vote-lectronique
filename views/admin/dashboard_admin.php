<?php
session_start();


// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
  header("Location: ./auth/login.php");
  exit();
}

// Connexion à la base de données
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Récupérer les statistiques complètes
$stats = [];

/* --------------------------------------------------------
   1. TOTAL ELECTEURS
---------------------------------------------------------*/
$query = "SELECT COUNT(*) AS total_electeurs 
          FROM Utilisateur 
          WHERE role = 'electeur'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = array_merge($stats, $stmt->fetch(PDO::FETCH_ASSOC));

/* --------------------------------------------------------
   2. TOTAL CANDIDATS
---------------------------------------------------------*/
$query = "SELECT COUNT(*) AS total_candidats FROM Candidat";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_candidats'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_candidats'];

/* --------------------------------------------------------
   3. TOTAL VOTES (nombre de bulletins enregistrés)
---------------------------------------------------------*/
$query = "SELECT COUNT(*) AS votes_enregistres FROM Vote";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['votes_enregistres'] = $stmt->fetch(PDO::FETCH_ASSOC)['votes_enregistres'];

/* --------------------------------------------------------
   4. NOMBRE D'ÉLECTEURS AYANT VOTÉ (DISTINCT)
---------------------------------------------------------*/
$query = "SELECT COUNT(DISTINCT utilisateur_id) AS electeurs_votes 
          FROM Vote";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['electeurs_votes'] = $stmt->fetch(PDO::FETCH_ASSOC)['electeurs_votes'];

/* --------------------------------------------------------
   5. TOTAL SCRUTINS + ETAT PAR STATUT
---------------------------------------------------------*/
$query = "SELECT 
            COUNT(*) AS total_scrutins,
            SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS scrutins_attente,
            SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) AS scrutins_actifs,
            SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS scrutins_termines
          FROM Scrutin";
$stmt = $db->prepare($query);
$stmt->execute();
$stats = array_merge($stats, $stmt->fetch(PDO::FETCH_ASSOC));

/* --------------------------------------------------------
    6.1 Nombre d'électeurs n'ayant pas voté
---------------------------------------------------------*/

$stats['electeurs_non_votes'] = $stats['total_electeurs'] - $stats['electeurs_votes'];
if ($stats['electeurs_non_votes'] < 0) {
    $stats['electeurs_non_votes'] = 0; // sécurité
}

/* --------------------------------------------------------
   6.2 CALCUL TAUX DE PARTICIPATION
---------------------------------------------------------*/
$stats['taux_participation'] = $stats['total_electeurs'] > 0 ? 
    round(($stats['electeurs_votes'] / $stats['total_electeurs']) * 100, 1) : 0;

/* --------------------------------------------------------
   7. ÉLECTEURS RÉCENTS (AUCUNE COLONNE a_vote)
---------------------------------------------------------*/
$query = "SELECT id, nom, prenom, cni, email, date_creation 
          FROM Utilisateur
          WHERE role = 'electeur'
          ORDER BY date_creation DESC
          LIMIT 8";
$stmt = $db->prepare($query);
$stmt->execute();
$electeurs_recents = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* --------------------------------------------------------
   8. SCRUTINS RÉCENTS
---------------------------------------------------------*/
$query = "SELECT id, nom, type, date_debut, date_fin, statut 
          FROM Scrutin
          ORDER BY date_creation DESC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$scrutins_recents = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* --------------------------------------------------------
   9. ACTIVITÉS RÉCENTES (Votes)
---------------------------------------------------------*/
$query = "SELECT 
            u.prenom, u.nom,
            c.prenom AS candidat_prenom, c.nom AS candidat_nom,
            v.date_et_heure
          FROM Vote v
          JOIN Utilisateur u ON v.utilisateur_id = u.id
          JOIN Candidat c ON v.candidat_id = c.id
          ORDER BY v.date_et_heure DESC
          LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$activites_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>




<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de bord administrateur — Système de Vote Électronique</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Merriweather:wght@700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      --gold: #aa9166;
      --white: #ffffff;
      --light-grey: #f8f8f8;
      --text: #0b0b0b;
      --border: #e5e5e5;
      --transition: all 0.3s ease;
      --success: #28a745;
      --warning: #ffc107;
      --danger: #dc3545;
      --info: #17a2b8;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "Poppins", sans-serif;
      background: var(--light-grey);
      color: var(--text);
      display: flex;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* SIDEBAR */
    .sidebar {
      width: 260px;
      background: var(--white);
      border-right: 3px solid var(--gold);
      display: flex;
      flex-direction: column;
      padding: 25px 20px;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 1px;
      margin-bottom: 40px;
    }

    .logo h1 {
      font-family: "Merriweather", serif;
      font-style: italic;
      font-size: 1.8rem;
      color: var(--gold);
    }

    .logo img {
      width: 35px;
      height: auto;
      filter: brightness(0) saturate(100%) invert(56%) sepia(12%) saturate(1000%) hue-rotate(5deg) brightness(92%) contrast(88%);
    }

    .menu {
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .menu a {
      text-decoration: none;
      color: var(--text);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 14px;
      border-radius: 12px;
      transition: var(--transition);
    }

    .menu a:hover,
    .menu a.active {
      background: var(--gold);
      color: var(--white);
    }

    /* MAIN */
    .main {
      flex: 1;
      padding: 40px 50px;
      display: flex;
      flex-direction: column;
      gap: 40px;
      animation: fadeIn 0.6s ease forwards;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    header h2 {
      font-family: "Merriweather", serif;
      font-size: 1.6rem;
      color: var(--text);
    }

    header .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 600;
      color: var(--gold);
      background: var(--white);
      padding: 10px 15px;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    header .user-info i {
      font-size: 1.3rem;
    }

    /* STATS CARDS */
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 25px;
    }

    .card {
      background: var(--white);
      border-radius: 16px;
      padding: 25px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      text-align: left;
      border: 1px solid var(--border);
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: var(--gold);
    }

    .card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .card i {
      font-size: 2.2rem;
      color: var(--gold);
      margin-bottom: 15px;
    }

    .card h3 {
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 8px;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .card .number {
      font-size: 2.2rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 5px;
    }

    .card .subtext {
      font-size: 0.85rem;
      color: #888;
      font-weight: 500;
    }

    .card .trend {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 0.8rem;
      margin-top: 8px;
    }

    .trend.up {
      color: var(--success);
    }

    .trend.down {
      color: var(--danger);
    }

    /* GRID LAYOUT */
    .dashboard-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
    }

    .main-content {
      display: flex;
      flex-direction: column;
      gap: 30px;
    }

    .sidebar-content {
      display: flex;
      flex-direction: column;
      gap: 30px;
    }

    /* SECTIONS */
    .section {
      background: var(--white);
      border-radius: 16px;
      padding: 25px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 2px solid var(--light-grey);
    }

    .section-header h3 {
      font-family: "Merriweather", serif;
      font-size: 1.3rem;
      color: var(--text);
    }

    .section-header a {
      color: var(--gold);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    /* TABLES */
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    th,
    td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }

    th {
      background: var(--light-grey);
      color: var(--gold);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.8rem;
      letter-spacing: 0.5px;
    }

    tr:hover {
      background: var(--light-grey);
    }

    .status {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status.voted {
      background: #e8f5e9;
      color: var(--success);
    }

    .status.not-voted {
      background: #fff3cd;
      color: var(--warning);
    }

    .status.en_attente {
      background: #fff3cd;
      color: #856404;
    }

    .status.en_cours {
      background: #d1ecf1;
      color: var(--info);
    }

    .status.termine {
      background: #e8f5e9;
      color: var(--success);
    }

    /* ACTIVITY LIST */
    .activity-list {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    .activity-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      background: var(--light-grey);
      border-radius: 10px;
      transition: var(--transition);
    }

    .activity-item:hover {
      background: #f0f0f0;
    }

    .activity-icon {
      width: 36px;
      height: 36px;
      background: var(--gold);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 0.9rem;
    }

    .activity-content {
      flex: 1;
    }

    .activity-content p {
      font-size: 0.9rem;
      margin-bottom: 2px;
    }

    .activity-time {
      font-size: 0.75rem;
      color: #888;
    }

    /* PROGRESS BARS */
    .progress-section {
      margin-top: 20px;
    }

    .progress-item {
      margin-bottom: 15px;
    }

    .progress-label {
      display: flex;
      justify-content: space-between;
      margin-bottom: 5px;
      font-size: 0.85rem;
    }

    .progress-bar {
      height: 8px;
      background: var(--border);
      border-radius: 4px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: var(--gold);
      border-radius: 4px;
      transition: width 1s ease;
    }

    footer {
      margin-top: auto;
      text-align: center;
      padding: 15px;
      font-size: 0.9rem;
      color: #777;
    }

    /* RESPONSIVE */
    @media (max-width: 1200px) {
      .dashboard-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 900px) {
      body {
        flex-direction: column;
      }

      .sidebar {
        width: 100%;
        flex-direction: row;
        justify-content: space-around;
        border-right: none;
        border-bottom: 3px solid var(--gold);
      }

      .menu {
        flex-direction: row;
        gap: 12px;
      }

      .main {
        padding: 20px;
      }

      .stats {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 600px) {
      .stats {
        grid-template-columns: 1fr;
      }

      .menu {
        flex-wrap: wrap;
      }
    }
  </style>
</head>

<body>
  <aside class="sidebar">
    <div class="logo">
      <h1>Vote</h1>
      <img src="/vote/assets/img/1.png" alt="Logo Vote">
    </div>

    <nav class="menu">
      <a href="dashboard_admin.php" class="active"><i class="fa-solid fa-house"></i> Tableau de bord</a>
      <a href="gerer_electeurs.php"><i class="fa-solid fa-users"></i> Gérer électeurs</a>
      <a href="gerer_candidats.php"><i class="fa-solid fa-user-tie"></i> Gérer candidats</a>
      <a href="lancer_scrutin.php"><i class="fa-solid fa-vote-yea"></i> Gérer scrutins</a>
      <a href="resultats.php"><i class="fa-solid fa-chart-pie"></i> Résultats</a>
      <a href="/vote/controllers/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
    </nav>
  </aside>

  <main class="main">
    <header>
      <h2>Tableau de bord administrateur</h2>
      <div class="user-info">
        <i class="fa-solid fa-user-shield"></i> <?php echo $_SESSION['user_name']; ?>
      </div>
    </header>

    <!-- Statistiques principales -->
    <section class="stats">
      <div class="card">
        <i class="fa-solid fa-users"></i>
        <h3>Électeurs</h3>
        <div class="number"><?php echo $stats['total_electeurs']; ?></div>
        <div class="subtext"><?php echo $stats['electeurs_votes']; ?> ont voté</div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: <?php echo $stats['taux_participation']; ?>%"></div>
        </div>
      </div>

      <div class="card">
        <i class="fa-solid fa-user-tie"></i>
        <h3>Candidats</h3>
        <div class="number"><?php echo $stats['total_candidats']; ?></div>
        <div class="subtext">En compétition</div>
      </div>

      <div class="card">
        <i class="fa-solid fa-vote-yea"></i>
        <h3>Scrutins</h3>
        <div class="number"><?php echo $stats['total_scrutins']; ?></div>
        <div class="subtext"><?php echo $stats['scrutins_actifs']; ?> actifs</div>
      </div>

      <div class="card">
        <i class="fa-solid fa-chart-bar"></i>
        <h3>Votes</h3>
        <div class="number"><?php echo $stats['votes_enregistres']; ?></div>
        <div class="subtext">Total enregistrés</div>
      </div>
    </section>

    <div class="dashboard-grid">
      <div class="main-content">
        <!-- Électeurs récents -->
        <section class="section">
          <div class="section-header">
            <h3>Électeurs récemment inscrits</h3>
            <a href="gerer_electeurs.php">
              Voir tout <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>
          <table>
            <thead>
              <tr>
                <th>Nom complet</th>
                <th>CNI</th>
                <th>Email</th>
                <th>Statut</th>
                <th>Inscription</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($electeurs_recents) > 0): ?>
                <?php foreach ($electeurs_recents as $electeur): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($electeur['prenom'] . ' ' . $electeur['nom']); ?></td>
                    <td><?php echo htmlspecialchars($electeur['cni']); ?></td>
                    <td><?php echo htmlspecialchars($electeur['email']); ?></td>
                    <td>
                      <?php
                      $a_vote = $electeur['a_vote'] ?? false;
                      echo $a_vote
                        ? '<span class="status voted">A voté</span>'
                        : '<span class="status not-voted">En attente</span>';
                      ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($electeur['date_creation'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align: center; padding: 20px;">
                    Aucun électeur inscrit
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </section>

        <!-- Scrutins récents -->
        <section class="section">
          <div class="section-header">
            <h3>Scrutins récents</h3>
            <a href="lancer_scrutin.php">
              Voir tout <i class="fa-solid fa-arrow-right"></i>
            </a>
          </div>
          <table>
            <thead>
              <tr>
                <th>Nom</th>
                <th>Type</th>
                <th>Date début</th>
                <th>Date fin</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($scrutins_recents) > 0): ?>
                <?php foreach ($scrutins_recents as $scrutin): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($scrutin['nom']); ?></td>
                    <td><?php echo htmlspecialchars($scrutin['type']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($scrutin['date_debut'])); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($scrutin['date_fin'])); ?></td>
                    <td>
                      <span class="status <?php echo $scrutin['statut']; ?>">
                        <?php
                        switch ($scrutin['statut']) {
                          case 'en_attente':
                            echo 'En attente';
                            break;
                          case 'en_cours':
                            echo 'En cours';
                            break;
                          case 'termine':
                            echo 'Terminé';
                            break;
                          default:
                            echo $scrutin['statut'];
                        }
                        ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align: center; padding: 20px;">
                    Aucun scrutin créé
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </section>
      </div>

      <div class="sidebar-content">
        <!-- Activités récentes -->
        <section class="section">
          <div class="section-header">
            <h3>Activités récentes</h3>
          </div>
          <div class="activity-list">
            <?php if (count($activites_recentes) > 0): ?>
              <?php foreach ($activites_recentes as $activite): ?>
                <div class="activity-item">
                  <div class="activity-icon">
                    <i class="fa-solid fa-vote-yea"></i>
                  </div>
                  <div class="activity-content">
                    <p>
                      <strong><?php echo htmlspecialchars($activite['prenom'] . ' ' . $activite['nom']); ?></strong>
                      a voté pour
                      <strong><?php echo htmlspecialchars($activite['candidat_prenom'] . ' ' . $activite['candidat_nom']); ?></strong>
                    </p>
                    <div class="activity-time">
                      <?php echo date('d/m/Y H:i', strtotime($activite['date_et_heure'])); ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="text-align: center; padding: 20px; color: #888;">
                <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 10px;"></i>
                <p>Aucune activité récente</p>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <!-- Statistiques de participation -->
        <section class="section">
          <div class="section-header">
            <h3>Participation</h3>
          </div>
          <div class="progress-section">
            <div class="progress-item">
              <div class="progress-label">
                <span>Taux de participation</span>
                <span><?php echo $stats['taux_participation']; ?>%</span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $stats['taux_participation']; ?>%"></div>
              </div>
            </div>
            <div class="progress-item">
              <div class="progress-label">
                <span>Électeurs ayant voté</span>
                <span><?php echo $stats['electeurs_votes']; ?></span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill"
                  style="width: <?php echo ($stats['electeurs_votes'] / max($stats['total_electeurs'], 1)) * 100; ?>%">
                </div>
              </div>
            </div>
            <div class="progress-item">
              <div class="progress-label">
                <span>Électeurs n'ayant pas voté</span>
                <span><?php echo $stats['electeurs_non_votes']; ?></span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill"
                  style="width: <?php echo ($stats['electeurs_non_votes'] / max($stats['total_electeurs'], 1)) * 100; ?>%">
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <footer>
      © 2025 République du Sénégal — Ministère de l'Intérieur et de la Sécurité Publique
    </footer>
  </main>


  <script>
    // Animation des barres de progression
    document.addEventListener('DOMContentLoaded', function () {
      const progressBars = document.querySelectorAll('.progress-fill');
      progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
          bar.style.width = width;
        }, 500);
      });
    });

    // Mise à jour automatique toutes les 30 secondes
    setInterval(() => {
      window.location.reload();
    }, 30000);
  </script>
</body>

</html>