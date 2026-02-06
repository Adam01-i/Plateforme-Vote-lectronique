<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ./auth/login.php");
    exit();
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

/* ---------------------------------------------------------------------------
   EXPORT CSV
--------------------------------------------------------------------------- */
if(isset($_GET['export']) && $_GET['export'] === 'csv') {

    $scrutin_id = $_GET['scrutin_id'] ?? null;

    if($scrutin_id) {

        $query = "
            SELECT 
                nom_scrutin AS scrutin_nom,
                nom_candidat,
                parti_politique,
                nombre_votes,
                (SELECT COUNT(*) FROM Vote WHERE scrutin_id = :scrutin_id) AS total_votes_scrutin
            FROM vue_resultats_scrutin
            WHERE scrutin_id = :scrutin_id
            ORDER BY nombre_votes DESC
        ";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':scrutin_id', $scrutin_id);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="resultats_scrutin_'.$scrutin_id.'.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Scrutin', 'Candidat', 'Parti politique', 'Nombre de votes', 'Pourcentage']);

        foreach($rows as $row) {
            $pct = $row['total_votes_scrutin'] > 0 
                ? round(($row['nombre_votes'] / $row['total_votes_scrutin']) * 100, 2) 
                : 0;

            fputcsv($output, [
                $row['scrutin_nom'],
                $row['nom_candidat'],
                $row['parti_politique'],
                $row['nombre_votes'],
                $pct.'%'
            ]);
        }

        fclose($output);
        exit();
    }
}

/* ---------------------------------------------------------------------------
   RÉCUPÉRATION DES SCRUTINS TERMINÉS AVEC VUE
--------------------------------------------------------------------------- */
$query = "
    SELECT 
        scrutin_id AS id,
        nom_scrutin AS nom,
        type,
        date_debut,
        date_fin,
        statut,
        nb_candidats,
        (SELECT COUNT(*) FROM Vote v WHERE v.scrutin_id = vue.scrutin_id) AS total_votes
    FROM vue_scrutins_avec_candidats AS vue
    ORDER BY date_fin DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$scrutins_termines = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------------------------------------------------------------------
   SCRUTIN SÉLECTIONNÉ
--------------------------------------------------------------------------- */
$scrutin_selectionne = $_GET['scrutin_id'] ?? ($scrutins_termines[0]['id'] ?? null);

$resultats_scrutin = [];
$total_votes_scrutin = 0;
$gagnant_scrutin = null;
$info_scrutin = null;

if($scrutin_selectionne) {

    /* ------------------ Résultats ---------------------- */
    $query = "
        SELECT 
            scrutin_id,
            nom_scrutin,
            candidat_id AS id,
            nom_candidat,
            parti_politique,
            nombre_votes
        FROM vue_resultats_scrutin
        WHERE scrutin_id = :scrutin_id
        ORDER BY nombre_votes DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':scrutin_id', $scrutin_selectionne);
    $stmt->execute();
    $resultats_scrutin = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ------------------ Total des votes ---------------------- */
    $query = "SELECT COUNT(*) AS total FROM Vote WHERE scrutin_id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $scrutin_selectionne);
    $stmt->execute();
    $total_votes_scrutin = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    /* ------------------ Calculs + photos ---------------------- */
    foreach($resultats_scrutin as &$r) {

        $r['pourcentage'] =
            $total_votes_scrutin > 0
                ? round(($r['nombre_votes'] / $total_votes_scrutin) * 100, 2)
                : 0;

        // Photo
        $getPhoto = $db->prepare("SELECT photo_officiel FROM Candidat WHERE id = :id");
        $getPhoto->bindParam(':id', $r['id']);
        $getPhoto->execute();
        $photo = $getPhoto->fetch(PDO::FETCH_ASSOC);
        $r['photo_officiel'] = $photo['photo_officiel'] ?? null;

        // Split nom complet
        $parts = explode(" ", $r['nom_candidat'], 2);
        $r['prenom'] = $parts[0] ?? "";
        $r['nom'] = $parts[1] ?? $parts[0];
    }
    unset($r);

    /* ------------------ Gagnant ---------------------- */
    $gagnant_scrutin = $resultats_scrutin[0] ?? null;

    /* ------------------ Infos scrutin ---------------------- */
    $stmt = $db->prepare("SELECT * FROM Scrutin WHERE id = :id");
    $stmt->bindParam(':id', $scrutin_selectionne);
    $stmt->execute();
    $info_scrutin = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ------------------ Calcul taux de participation ---------------------- */
    $stmt = $db->prepare("SELECT COUNT(*) AS total_electeurs FROM Utilisateur WHERE role='electeur'");
    $stmt->execute();
    $total_electeurs = $stmt->fetch(PDO::FETCH_ASSOC)['total_electeurs'] ?? 0;

    $taux_participation_scrutin = $total_electeurs > 0
        ? round(($total_votes_scrutin / $total_electeurs) * 100, 2)
        : 0;

    /* ------------------ Statistiques globales ---------------------- */
    $stmt = $db->prepare("SELECT COUNT(*) AS total_votes_globaux FROM Vote");
    $stmt->execute();
    $total_votes_globaux = $stmt->fetch(PDO::FETCH_ASSOC)['total_votes_globaux'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) AS total_candidats FROM Candidat");
    $stmt->execute();
    $total_candidats = $stmt->fetch(PDO::FETCH_ASSOC)['total_candidats'] ?? 0;

    $taux_participation_global = $total_electeurs > 0
        ? round(($total_votes_globaux / $total_electeurs) * 100, 2)
        : 0;
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Résultats des scrutins | Administration Vote Électronique</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Merriweather:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      --gold: #aa9166;
      --white: #ffffff;
      --light: #f8f8f8;
      --text: #0b0b0b;
      --border: #e5e5e5;
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "Poppins", sans-serif;
      background: var(--light);
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
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    header h2 {
      font-family: "Merriweather", serif;
      font-size: 1.6rem;
    }

    .export-btn {
      background: var(--gold);
      color: var(--white);
      border: none;
      border-radius: 8px;
      padding: 10px 18px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .export-btn:hover {
      background: #8d7755;
    }

    /* SELECTION SCRUTIN */
    .scrutin-selector {
      background: var(--white);
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
      margin-bottom: 20px;
    }

    .scrutin-selector label {
      font-weight: 600;
      margin-bottom: 10px;
      display: block;
    }

    .scrutin-select {
      width: 100%;
      padding: 12px;
      border: 2px solid var(--border);
      border-radius: 8px;
      font-size: 1rem;
      background: var(--white);
    }

    .scrutin-select:focus {
      border-color: var(--gold);
      outline: none;
    }

    /* WINNER CARD */
    .winner-card {
      background: linear-gradient(135deg, var(--gold), #d4b78c);
      color: var(--white);
      border-radius: 20px;
      padding: 30px;
      text-align: center;
      box-shadow: 0 10px 30px rgba(170,145,102,0.3);
      margin-bottom: 30px;
    }

    .winner-card h3 {
      font-family: "Merriweather", serif;
      font-size: 1.4rem;
      margin-bottom: 15px;
    }

    .winner-info {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 20px;
      margin-bottom: 15px;
    }

    .winner-info img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      border: 3px solid var(--white);
      object-fit: cover;
    }

    .winner-details h4 {
      font-size: 1.3rem;
      margin-bottom: 5px;
    }

    .winner-details p {
      font-size: 1rem;
      opacity: 0.9;
    }

    .winner-stats {
      font-size: 1.1rem;
      font-weight: 600;
    }

    /* STATS GRID */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: var(--white);
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }

    .stat-card i {
      font-size: 2rem;
      color: var(--gold);
      margin-bottom: 10px;
    }

    .stat-card h4 {
      font-size: 0.9rem;
      color: #666;
      margin-bottom: 5px;
    }

    .stat-card p {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text);
    }

    /* RESULTS TABLE */
    .results-container {
      background: var(--white);
      border-radius: 16px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      padding: 25px;
    }

    .results-container h3 {
      font-family: "Merriweather", serif;
      margin-bottom: 20px;
      color: var(--text);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
    }

    th, td {
      padding: 15px 12px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }

    th {
      background: var(--gold);
      color: var(--white);
      font-weight: 600;
    }

    tr:hover {
      background: var(--light);
    }

    .position {
      font-weight: 700;
      text-align: center;
      width: 60px;
    }

    .position-1 { color: #d4af37; }
    .position-2 { color: #c0c0c0; }
    .position-3 { color: #cd7f32; }

    .progress-bar {
      width: 150px;
      height: 8px;
      background: var(--border);
      border-radius: 4px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: var(--gold);
      border-radius: 4px;
      transition: width 0.8s ease;
    }

    .no-results {
      text-align: center;
      padding: 40px;
      color: #666;
    }

    footer {
      margin-top: auto;
      text-align: center;
      padding: 15px;
      font-size: 0.9rem;
      color: #777;
    }

    /* RESPONSIVE */
    @media (max-width: 900px) {
      body { flex-direction: column; }
      .sidebar {
        width: 100%;
        flex-direction: row;
        justify-content: space-around;
        border-right: none;
        border-bottom: 3px solid var(--gold);
      }
      .main { padding: 20px; }
      .winner-info { flex-direction: column; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .export-btn { font-size: 0.9rem; padding: 8px 12px; }
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
      <a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> Tableau de bord</a>
      <a href="gerer_electeurs.php"><i class="fa-solid fa-users"></i> Gérer électeurs</a>
      <a href="gerer_candidats.php"><i class="fa-solid fa-user-tie"></i> Gérer candidats</a>
      <a href="lancer_scrutin.php" ><i class="fa-solid fa-vote-yea"></i> Gérer scrutins</a>
      <a href="resultats.php" class="active"><i class="fa-solid fa-chart-pie"></i> Résultats</a>
      <a href="/vote/controllers/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
    </nav>
  </aside>

<main class="main">
  <header>
    <h2>Résultats des scrutins</h2>
    <?php if($scrutin_selectionne && $total_votes_scrutin > 0): ?>
      <a href="?export=csv&scrutin_id=<?php echo $scrutin_selectionne; ?>" class="export-btn">
        <i class="fa-solid fa-download"></i> Exporter les résultats
      </a>
    <?php endif; ?>
  </header>

  <!-- Sélection du scrutin -->
  <section class="scrutin-selector">
    <label for="scrutin_select">Sélectionner un scrutin :</label>
    <select id="scrutin_select" class="scrutin-select" onchange="window.location.href = '?scrutin_id=' + this.value">
      <option value="">-- Choisir un scrutin --</option>
      <?php foreach($scrutins_termines as $scrutin): ?>
        <option value="<?php echo $scrutin['id']; ?>" 
                <?php echo $scrutin_selectionne == $scrutin['id'] ? 'selected' : ''; ?>> 
          <?php echo htmlspecialchars($scrutin['nom']); ?> 
          (<?php echo date('d/m/Y', strtotime($scrutin['date_fin'])); ?>)
          - <?php echo $scrutin['total_votes']; ?> votes
        </option>
      <?php endforeach; ?>
    </select>
  </section>

  <?php if($scrutin_selectionne && $info_scrutin): ?>
    
    <?php if($gagnant_scrutin && $total_votes_scrutin > 0): ?>
    <!-- CARTE DU GAGNANT -->
    <section class="winner-card">
      <h3><i class="fa-solid fa-trophy"></i> CANDIDAT ÉLU - <?php echo htmlspecialchars($info_scrutin['nom']); ?></h3>
      <div class="winner-info">
        <img src="/vote/assets/img/candidats/<?php echo $gagnant_scrutin['photo_officiel'] ?: 'default.jpg'; ?>" 
             alt="<?php echo htmlspecialchars($gagnant_scrutin['prenom'] . ' ' . $gagnant_scrutin['nom']); ?>"
             onerror="this.src='/vote/assets/img/1.png'">
        <div class="winner-details">
          <h4><?php echo htmlspecialchars($gagnant_scrutin['prenom'] . ' ' . $gagnant_scrutin['nom']); ?></h4>
          <p><?php echo htmlspecialchars($gagnant_scrutin['parti_politique']); ?></p>
        </div>
      </div>
      <div class="winner-stats">
        <?php echo $gagnant_scrutin['nombre_votes']; ?> votes • <?php echo $gagnant_scrutin['pourcentage']; ?>%
      </div>
    </section>
    <?php endif; ?>

    <!-- STATISTIQUES DU SCRUTIN -->
    <section class="stats-grid">
      <div class="stat-card">
        <i class="fa-solid fa-calendar"></i>
        <h4>Date du scrutin</h4>
        <p><?php echo date('d/m/Y', strtotime($info_scrutin['date_fin'])); ?></p>
      </div>
      <div class="stat-card">
        <i class="fa-solid fa-user-tie"></i>
        <h4>Candidats</h4>
        <p><?php echo count($resultats_scrutin); ?></p>
      </div>
      <div class="stat-card">
        <i class="fa-solid fa-vote-yea"></i>
        <h4>Votes exprimés</h4>
        <p><?php echo $total_votes_scrutin; ?></p>
      </div>
      <div class="stat-card">
        <i class="fa-solid fa-percentage"></i>
        <h4>Taux participation</h4>
        <p><?php echo $taux_participation_scrutin; ?>%</p>
      </div>
    </section>

    <!-- TABLEAU DES RÉSULTATS DÉTAILLÉS -->
    <section class="results-container">
      <h3>Classement détaillé - <?php echo htmlspecialchars($info_scrutin['nom']); ?></h3>
      <?php if(count($resultats_scrutin) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Position</th>
              <th>Candidat</th>
              <th>Parti politique</th>
              <th>Nombre de votes</th>
              <th>Pourcentage</th>
              <th>Progression</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($resultats_scrutin as $index => $candidat): ?>
              <tr>
                <td class="position position-<?php echo $index + 1; ?>">
                  <?php echo $index + 1; ?>
                  <?php if($index < 3): ?>
                    <i class="fa-solid fa-medal"></i>
                  <?php endif; ?>
                </td>
                <td>
                  <strong><?php echo htmlspecialchars($candidat['nom_candidat']); ?></strong>
                </td>
                <td><?php echo htmlspecialchars($candidat['parti_politique']); ?></td>
                <td><?php echo $candidat['nombre_votes']; ?></td>
                <td><strong><?php echo $candidat['pourcentage']; ?>%</strong></td>
                <td>
                  <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $candidat['pourcentage']; ?>%"></div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="no-results">
          <p>Aucun vote n'a été enregistré pour ce scrutin.</p>
        </div>
      <?php endif; ?>
    </section>

  <?php else: ?>
    <!-- AUCUN SCRUTIN SELECTIONNE -->
    <section class="results-container">
      <div class="no-results">
        <i class="fa-solid fa-chart-bar" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
        <h3>Aucun scrutin terminé</h3>
        <p>Sélectionnez un scrutin terminé pour voir les résultats détaillés.</p>
      </div>
    </section>
  <?php endif; ?>

  <!-- STATISTIQUES GLOBALES -->
  <section class="results-container">
    <h3>Statistiques globales de la plateforme</h3>
    <div class="stats-grid">
      <div class="stat-card">
        <i class="fa-solid fa-users"></i>
        <h4>Total électeurs</h4>
        <p><?php echo $total_electeurs; ?></p>
      </div>
      <div class="stat-card">
        <i class="fa-solid fa-vote-yea"></i>
        <h4>Votes globaux</h4>
        <p><?php echo $total_votes_globaux; ?></p>
      </div>
      <div class="stat-card">
        <i class="fa-solid fa-percentage"></i>
        <h4>Taux participation global</h4>
        <p><?php echo $taux_participation_global; ?>%</p>
      </div>
      <div class="stat-card">
        <i class="fa-solid fa-user-tie"></i>
        <h4>Total candidats</h4>
        <p><?php echo $total_candidats; ?></p>
      </div>
    </div>
  </section>

  <footer>
    © 2025 République du Sénégal — Ministère de l'Intérieur et de la Sécurité Publique
  </footer>
</main>

<script>
  // Animation des barres de progression
  document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
      const width = bar.style.width;
      bar.style.width = '0';
      setTimeout(() => {
        bar.style.width = width;
      }, 100);
    });
  });

  // Mise à jour automatique des résultats toutes les 30 secondes
  setInterval(() => {
    if(window.location.search.includes('scrutin_id')) {
      window.location.reload();
    }
  }, 30000);
</script>



</body>
</html>