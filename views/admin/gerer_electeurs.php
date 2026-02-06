<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérifier si l'utilisateur est admin
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ./auth/login.php");
    exit();
}

// Connexion à la base de données
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Traitement de la suppression d'un électeur
if($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_electeur') {
    $electeur_id = $_POST['electeur_id'] ?? '';
    
    try {
        // Vérifier si l'électeur a déjà voté
        $query = "SELECT COUNT(*) as votes_count 
                  FROM Vote 
                  WHERE utilisateur_id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $electeur_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($result) {
            if($result['votes_count'] > 0) {
                $_SESSION['error'] = "Impossible de supprimer un électeur qui a déjà voté.";
            } else {
                // Supprimer l'électeur
                $query = "DELETE FROM Utilisateur WHERE id = :id AND role = 'electeur'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $electeur_id);
                
                if($stmt->execute()) {
                    $_SESSION['success'] = "Électeur supprimé avec succès!";
                } else {
                    $_SESSION['error'] = "Erreur lors de la suppression de l'électeur.";
                }
            }
        } else {
            $_SESSION['error'] = "Électeur non trouvé.";
        }
    } catch(Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header("Location: gerer_electeurs.php");
    exit();
}

// Récupérer les statistiques
$query = "
SELECT
    COUNT(*) AS total_electeurs,
    SUM(CASE WHEN total_votes > 0 THEN 1 ELSE 0 END) AS electeurs_votes,
    SUM(CASE WHEN total_votes = 0 THEN 1 ELSE 0 END) AS electeurs_non_votes
FROM (
    SELECT 
        u.id,
        COUNT(v.utilisateur_id) AS total_votes
    FROM Utilisateur u
    LEFT JOIN Vote v ON u.id = v.utilisateur_id
    WHERE u.role = 'electeur'
    GROUP BY u.id
) AS sub;
";


$stmt = $db->prepare($query);
$stmt->execute();
$stats_tmp = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcul des électeurs n'ayant pas voté
$stats = [];
$stats['total_electeurs'] = $stats_tmp['total_electeurs'];
$stats['electeurs_votes'] = $stats_tmp['electeurs_votes'];
$stats['electeurs_non_votes'] = $stats['total_electeurs'] - $stats['electeurs_votes'];
if ($stats['electeurs_non_votes'] < 0) $stats['electeurs_non_votes'] = 0;

// Récupérer tous les électeurs avec info "a_vote" via Vote
$query = "
SELECT 
    u.id, 
    u.nom, 
    u.prenom, 
    u.email, 
    u.cni,
    CASE WHEN COUNT(DISTINCT v.utilisateur_id) > 0 THEN 1 ELSE 0 END AS a_vote,
    u.date_creation
FROM Utilisateur u
LEFT JOIN Vote v ON u.id = v.utilisateur_id
WHERE u.role = 'electeur'
GROUP BY u.id
ORDER BY u.date_creation DESC
";


$stmt = $db->prepare($query);
$stmt->execute();
$electeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Préparer les données pour JavaScript
$electeurs_js = [];
foreach($electeurs as $electeur) {
    $electeurs_js[$electeur['id']] = [
        'id' => $electeur['id'],
        'nom' => $electeur['nom'],
        'prenom' => $electeur['prenom'],
        'email' => $electeur['email'],
        'cni' => $electeur['cni'],
        'a_vote' => $electeur['a_vote']
    ];
}
?>



<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gérer les électeurs | Administration Vote Électronique</title>
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

    .search-bar {
      display: flex;
      align-items: center;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 12px;
      width: 280px;
    }

    .search-bar input {
      border: none;
      outline: none;
      width: 100%;
      font-size: 0.95rem;
      color: var(--text);
    }

    .search-bar i {
      color: var(--gold);
      margin-right: 8px;
    }

    /* DASHBOARD */
    .dashboard {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: var(--white);
      border-radius: 12px;
      padding: 25px;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border-top: 4px solid var(--gold);
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--gold);
      margin-bottom: 8px;
    }

    .stat-label {
      font-size: 0.9rem;
      color: #666;
      font-weight: 600;
    }

    /* TABLE */
    .table-container {
      background: var(--white);
      border-radius: 16px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      padding: 25px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }

    th, td {
      padding: 14px 12px;
      border-bottom: 1px solid var(--border);
      font-size: 0.95rem;
    }

    th {
      background: var(--light);
      color: var(--gold);
      font-weight: 600;
      text-transform: uppercase;
    }

    td {
      color: var(--text);
    }

    tr:hover {
      background: #fdfaf5;
    }

    .status {
      font-weight: 600;
      border-radius: 8px;
      padding: 6px 10px;
      text-align: center;
      display: inline-block;
      font-size: 0.85rem;
    }

    .status.voted {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .status.not-voted {
      background: #fff3cd;
      color: #856404;
    }

    .status.cannot-delete {
      background: #fdecea;
      color: #c62828;
    }

    .action-btn {
      border: none;
      border-radius: 6px;
      padding: 6px 12px;
      font-size: 0.85rem;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .btn-delete {
      background: #dc3545;
      color: white;
    }

    .btn-delete:hover {
      background: #c82333;
    }

    .btn-delete:disabled {
      background: #6c757d;
      cursor: not-allowed;
    }

    /* MODAL */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      animation: fadeIn 0.3s ease;
    }

    .modal-content {
      background-color: var(--white);
      margin: 10% auto;
      padding: 30px;
      border-radius: 16px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
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
      justify-content: flex-end;
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
      background: #dc3545;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-confirm:hover {
      background: #c82333;
    }

    .alert {
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 600;
    }

    .alert-success {
      background: #e8f5e9;
      color: #2e7d32;
      border: 1px solid #c8e6c9;
    }

    .alert-error {
      background: #fdecea;
      color: #c62828;
      border: 1px solid #ffcdd2;
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
      .search-bar { width: 100%; }
      table { font-size: 0.85rem; }
      .dashboard { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 600px) {
      .dashboard { grid-template-columns: 1fr; }
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
      <a href="gerer_electeurs.php" class="active"><i class="fa-solid fa-users"></i> Gérer électeurs</a>
      <a href="gerer_candidats.php"><i class="fa-solid fa-user-tie"></i> Gérer candidats</a>
      <a href="lancer_scrutin.php"><i class="fa-solid fa-vote-yea"></i> Gérer scrutins</a>
      <a href="resultats.php"><i class="fa-solid fa-chart-pie"></i> Résultats</a>
      <a href="/vote/controllers/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
    </nav>
  </aside>

<main class="main">
    <header>
      <h2>Gestion des électeurs</h2>
      <div class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="searchInput" placeholder="Rechercher un électeur...">
      </div>
    </header>

    <!-- Messages d'alerte -->
    <?php if(isset($_SESSION['success'])): ?>
      <div class="alert alert-success">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
      <div class="alert alert-error">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <!-- Dashboard des statistiques -->
    <section class="dashboard">
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_electeurs'] ?? 0; ?></div>
        <div class="stat-label">Total électeurs</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['electeurs_votes'] ?? 0; ?></div>
        <div class="stat-label">Ont voté</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['electeurs_non_votes'] ?? 0; ?></div>
        <div class="stat-label">N'ont pas voté</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">
          <?php 
          $taux = ($stats['total_electeurs'] ?? 0) > 0 ? round(($stats['electeurs_votes'] ?? 0) / ($stats['total_electeurs'] ?? 1) * 100, 1) : 0;
          echo $taux . '%';
          ?>
        </div>
        <div class="stat-label">Taux de participation</div>
      </div>
    </section>

    <!-- Tableau des électeurs -->
    <section class="table-container">
      <table id="electeursTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nom complet</th>
            <th>Email</th>
            <th>CNI</th>
            <th>Statut vote</th>
            <th>Date inscription</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!empty($electeurs)): ?>
            <?php foreach($electeurs as $electeur): ?>
              <tr>
                <td><?php echo $electeur['id']; ?></td>
                <td><?php echo htmlspecialchars($electeur['prenom'] . ' ' . $electeur['nom']); ?></td>
                <td><?php echo htmlspecialchars($electeur['email']); ?></td>
                <td><?php echo htmlspecialchars($electeur['cni']); ?></td>
                <td>
                  <?php if($electeur['a_vote']): ?>
                    <span class="status voted">
                      <i class="fa-solid fa-check"></i> A voté
                    </span>
                  <?php else: ?>
                    <span class="status not-voted">
                      <i class="fa-solid fa-clock"></i> N'a pas voté
                    </span>
                  <?php endif; ?>
                </td>
                <td><?php echo date('d/m/Y H:i', strtotime($electeur['date_creation'])); ?></td>
                <td>
                  <button class="action-btn btn-delete" 
                          onclick="openDeleteModal(<?php echo $electeur['id']; ?>)" 
                          <?php echo $electeur['a_vote'] ? 'disabled' : ''; ?>>
                    <i class="fa-solid fa-trash"></i> Supprimer
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 30px;">
                Aucun électeur inscrit pour le moment.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <footer>
      © 2025 République du Sénégal — Ministère de l'Intérieur et de la Sécurité Publique
    </footer>
</main>


<!-- Modal de confirmation de suppression -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Confirmer la suppression</h3>
      <span class="close" onclick="closeDeleteModal()">&times;</span>
    </div>
    <form id="deleteForm" method="POST">
      <input type="hidden" name="action" value="delete_electeur">
      <input type="hidden" id="delete_electeur_id" name="electeur_id">
      <div class="form-group">
        <p>Êtes-vous sûr de vouloir supprimer l'électeur : <strong id="delete_electeur_name"></strong> ?</p>
        <p style="color: #c62828; margin-top: 10px;">
          <i class="fa-solid fa-exclamation-triangle"></i>
          <strong>Attention :</strong> Cette action est irréversible !
        </p>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Annuler</button>
        <button type="submit" class="btn-confirm">Confirmer la suppression</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Données des électeurs pour JavaScript
  const electeurs = <?php echo json_encode($electeurs_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

  // Ouvrir le modal de suppression
  function openDeleteModal(electeurId) {
    const electeur = electeurs[electeurId];
    if (!electeur) return;

    document.getElementById('delete_electeur_id').value = electeurId;
    document.getElementById('delete_electeur_name').textContent = 
      `${electeur.prenom} ${electeur.nom} (${electeur.email})`;
    document.getElementById('deleteModal').style.display = 'block';
  }

  // Fermer le modal
  function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
  }

  // Recherche en temps réel
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const rows = document.querySelectorAll('#electeursTable tbody tr');

      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
      });
    });
  }

  // Fermer le modal en cliquant à l'extérieur
  window.addEventListener('click', function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) closeDeleteModal();
  });

  // Empêcher la fermeture du modal en cliquant à l'intérieur
  const modalContent = document.querySelector('.modal-content');
  if (modalContent) {
    modalContent.addEventListener('click', (e) => e.stopPropagation());
  }

  // Tri des colonnes
  document.querySelectorAll('#electeursTable th').forEach(header => {
    header.style.cursor = 'pointer';
    header.addEventListener('click', function() {
      const table = this.closest('table');
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const columnIndex = Array.from(this.parentNode.children).indexOf(this);

      rows.sort((a, b) => {
        const aText = a.children[columnIndex].textContent.trim();
        const bText = b.children[columnIndex].textContent.trim();
        return aText.localeCompare(bText, undefined, {numeric: true, sensitivity: 'base'});
      });

      rows.forEach(row => tbody.appendChild(row));
    });
  });
</script>

</body>
</html>