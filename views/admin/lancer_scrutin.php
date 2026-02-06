<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
  header("Location: ./auth/login.php");
  exit();
}

// ================================
// ⚙️ Connexion à la base de données
// ================================
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// ================================
// 🔄 Mise à jour automatique des statuts
// ================================
$now = date('Y-m-d H:i:s');

// Passe les scrutins à "en_cours"
$query = "UPDATE Scrutin SET statut = 'en_cours'
          WHERE statut = 'en_attente' AND date_debut <= :now";
$stmt = $db->prepare($query);
$stmt->bindParam(':now', $now);
$stmt->execute();

// Passe les scrutins à "termine"
$query = "UPDATE Scrutin SET statut = 'termine'
          WHERE statut = 'en_cours' AND date_fin <= :now";
$stmt = $db->prepare($query);
$stmt->bindParam(':now', $now);
$stmt->execute();

// ================================
// 🧾 Traitement du formulaire
// ================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

  if ($_POST['action'] === 'add_scrutin') {
    $nom = $_POST['nom'] ?? '';
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $date_debut = $_POST['debut'] ?? '';
    $date_fin = $_POST['fin'] ?? '';
    $candidats = $_POST['candidats'] ?? [];

    $date_debut_dt = DateTime::createFromFormat('Y-m-d', $date_debut);
    $date_fin_dt = DateTime::createFromFormat('Y-m-d', $date_fin);
    $now_dt = new DateTime();

    if ($date_debut_dt < $now_dt) {
      $_SESSION['error'] = "La date de début ne peut pas être dans le passé.";
    } elseif ($date_fin_dt <= $date_debut_dt) {
      $_SESSION['error'] = "La date de fin doit être après la date de début.";
    } elseif (empty($candidats)) {
      $_SESSION['error'] = "Veuillez sélectionner au moins un candidat.";
    } else {
      try {
        $db->beginTransaction();

        // Insertion du scrutin
        $query = "INSERT INTO Scrutin (nom, type, description, date_debut, date_fin, statut)
                          VALUES (:nom, :type, :description, :date_debut, :date_fin, 'en_attente')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->execute();

        $scrutin_id = $db->lastInsertId();

        // Lier les candidats
        $query = "INSERT INTO Scrutin_Candidat (scrutin_id, candidat_id) VALUES (:scrutin_id, :candidat_id)";
        $stmt = $db->prepare($query);

        foreach ($candidats as $candidat_id) {
          $stmt->bindParam(':scrutin_id', $scrutin_id);
          $stmt->bindParam(':candidat_id', $candidat_id);
          $stmt->execute();
        }

        $db->commit();
        $_SESSION['success'] = "Scrutin créé avec succès !";
      } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur lors de la création du scrutin : " . $e->getMessage();
      }
    }
  } elseif ($_POST['action'] === 'edit_scrutin') {
    $scrutin_id = $_POST['scrutin_id'] ?? '';
    $nom = $_POST['nom'] ?? '';
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $date_debut = $_POST['debut'] ?? '';
    $date_fin = $_POST['fin'] ?? '';
    $candidats = $_POST['candidats'] ?? [];

    $date_debut_dt = DateTime::createFromFormat('Y-m-d', $date_debut);
    $date_fin_dt = DateTime::createFromFormat('Y-m-d', $date_fin);

    if ($date_fin_dt <= $date_debut_dt) {
      $_SESSION['error'] = "La date de fin doit être après la date de début.";
    } elseif (empty($candidats)) {
      $_SESSION['error'] = "Veuillez sélectionner au moins un candidat.";
    } else {
      try {
        $db->beginTransaction();

        // Mise à jour du scrutin
        $query = "UPDATE Scrutin 
                          SET nom = :nom, type = :type, description = :description,
                              date_debut = :date_debut, date_fin = :date_fin
                          WHERE id = :id AND statut = 'en_attente'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $scrutin_id);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':date_fin', $date_fin);
        $stmt->execute();

        // Supprimer anciennes associations
        $query = "DELETE FROM Scrutin_Candidat WHERE scrutin_id = :scrutin_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':scrutin_id', $scrutin_id);
        $stmt->execute();

        // Réinsertion nouvelles associations
        $query = "INSERT INTO Scrutin_Candidat (scrutin_id, candidat_id) VALUES (:scrutin_id, :candidat_id)";
        $stmt = $db->prepare($query);

        foreach ($candidats as $candidat_id) {
          $stmt->bindParam(':scrutin_id', $scrutin_id);
          $stmt->bindParam(':candidat_id', $candidat_id);
          $stmt->execute();
        }

        $db->commit();
        $_SESSION['success'] = "Scrutin modifié avec succès !";
      } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur lors de la modification du scrutin : " . $e->getMessage();
      }
    }
  }

  header("Location: lancer_scrutin.php");
  exit();
}

// ===============================================
// 📋 Données pour le tableau de bord via les vues
// ===============================================

// Vue participation
$query = "SELECT * FROM vue_participation_scrutin";
$stmt = $db->prepare($query);
$stmt->execute();
$participations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vue résultats
$query = "SELECT * FROM vue_resultats_scrutin";
$stmt = $db->prepare($query);
$stmt->execute();
$resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vue scrutins
$query = "SELECT * FROM vue_scrutins_avec_candidats ORDER BY date_debut DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$scrutins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Liste des candidats
$query = "SELECT id, prenom, nom, parti_politique FROM Candidat ORDER BY nom";
$stmt = $db->prepare($query);
$stmt->execute();
$candidats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Préparer les données pour JavaScript
$scrutins_js = [];
foreach ($scrutins as $scrutin) {
  // Récupération des candidats pour ce scrutin
  $query = "SELECT candidat_id FROM Scrutin_Candidat WHERE scrutin_id = :scrutin_id";
  $stmt = $db->prepare($query);
  $stmt->bindParam(':scrutin_id', $scrutin['scrutin_id']);
  $stmt->execute();
  $candidats_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

  $scrutins_js[$scrutin['scrutin_id']] = [
    'scrutin_id' => $scrutin['scrutin_id'],
    'nom' => $scrutin['nom_scrutin'],
    'type' => $scrutin['type'],
    'description' => $scrutin['description'],
    'date_debut' => $scrutin['date_debut'],
    'date_fin' => $scrutin['date_fin'],
    'statut' => $scrutin['statut'],
    'candidats_ids' => array_map('intval', $candidats_ids)
  ];
}




$stats = [
  'total_scrutins' => count($scrutins),
  'scrutins_attente' => count(array_filter($scrutins, fn($s) => $s['statut'] === 'en_attente')),
  'scrutins_cours' => count(array_filter($scrutins, fn($s) => $s['statut'] === 'en_cours')),
  'scrutins_termines' => count(array_filter($scrutins, fn($s) => $s['statut'] === 'termine')),
];

?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des scrutins | Administration Vote Électronique</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Merriweather:wght@700&display=swap"
    rel="stylesheet">
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
    }

    .add-btn {
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
    }

    .add-btn:hover {
      background: #8d7755;
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
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
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

    /* LISTE DES SCRUTINS */
    .scrutins-section {
      background: var(--white);
      border-radius: 18px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
      padding: 30px;
    }

    .section-header {
      display: flex;
      justify-content: between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 2px solid var(--border);
    }

    .section-header h3 {
      font-family: "Merriweather", serif;
      font-size: 1.4rem;
      color: var(--text);
    }

    .scrutins-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
      gap: 20px;
    }

    .scrutin-card {
      background: var(--light);
      border-radius: 12px;
      padding: 20px;
      border-left: 4px solid var(--gold);
      transition: var(--transition);
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
    }

    .scrutin-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    }

    .scrutin-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 15px;
    }

    .scrutin-header h4 {
      font-size: 1.1rem;
      color: var(--text);
      margin: 0;
      flex: 1;
      margin-right: 15px;
    }

    .statut {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .statut.en_attente {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeaa7;
    }

    .statut.en_cours {
      background: #d1ecf1;
      color: #0c5460;
      border: 1px solid #bee5eb;
    }

    .statut.termine {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .scrutin-info {
      margin-bottom: 15px;
    }

    .scrutin-info p {
      margin: 5px 0;
      font-size: 0.9rem;
      color: #555;
    }

    .scrutin-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn {
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .btn-view {
      background: var(--gold);
      color: white;
    }

    .btn-view:hover {
      background: #8d7755;
    }

    .btn-edit {
      background: #17a2b8;
      color: white;
    }

    .btn-edit:hover {
      background: #138496;
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
      background-color: rgba(0, 0, 0, 0.5);
      animation: fadeIn 0.3s ease;
    }

    .modal-content {
      background-color: var(--white);
      margin: 2% auto;
      padding: 30px;
      border-radius: 16px;
      width: 90%;
      max-width: 800px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      animation: slideIn 0.3s ease;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-content.view-modal {
      max-width: 700px;
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

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--text);
    }

    input,
    select,
    textarea {
      width: 100%;
      padding: 12px;
      border: 2px solid var(--border);
      border-radius: 8px;
      font-size: 1rem;
      transition: var(--transition);
    }

    input:focus,
    select:focus,
    textarea:focus {
      border-color: var(--gold);
      outline: none;
    }

    .date-group {
      display: flex;
      gap: 15px;
    }

    .date-group .form-group {
      flex: 1;
    }

    .candidats-list {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      max-height: 200px;
      overflow-y: auto;
      padding: 10px;
      border: 1px solid var(--border);
      border-radius: 8px;
    }

    .candidat-option {
      background: var(--light);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 12px;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      transition: var(--transition);
      flex: 1 1 calc(50% - 10px);
      min-width: 200px;
    }

    .candidat-option:hover {
      border-color: var(--gold);
      background: #f9f4ed;
    }

    .candidat-option input {
      accent-color: var(--gold);
      width: auto;
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

    .btn-submit {
      background: var(--gold);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-submit:hover {
      background: #8d7755;
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

    .no-scrutins {
      text-align: center;
      padding: 40px;
      color: #666;
      grid-column: 1 / -1;
    }

    .candidats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 15px;
      margin-top: 15px;
    }

    .candidat-item {
      background: var(--light);
      padding: 15px;
      border-radius: 8px;
      border-left: 3px solid var(--gold);
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

      .main {
        padding: 20px;
      }

      .scrutins-grid {
        grid-template-columns: 1fr;
      }

      .candidat-option {
        flex: 1 1 100%;
      }

      .date-group {
        flex-direction: column;
      }

      .modal-content {
        margin: 5% auto;
        width: 95%;
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
      <a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> Tableau de bord</a>
      <a href="gerer_electeurs.php"><i class="fa-solid fa-users"></i> Gérer électeurs</a>
      <a href="gerer_candidats.php"><i class="fa-solid fa-user-tie"></i> Gérer candidats</a>
      <a href="lancer_scrutin.php" class="active"><i class="fa-solid fa-vote-yea"></i> Gérer scrutins</a>
      <a href="resultats.php"><i class="fa-solid fa-chart-pie"></i> Résultats</a>
      <a href="/vote/controllers/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
    </nav>
  </aside>

  <main class="main">
    <header>
      <h2>Gestion des scrutins</h2>
      <button class="add-btn" onclick="openAddModal()">
        <i class="fa-solid fa-plus"></i> Nouveau scrutin
      </button>
    </header>

    <!-- Messages d'alerte -->
    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success">
        <?php echo $_SESSION['success'];
        unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error">
        <?php echo $_SESSION['error'];
        unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <!-- Dashboard -->
    <section class="dashboard">
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['total_scrutins'] ?? 0; ?></div>
        <div class="stat-label">Total scrutins</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['scrutins_attente'] ?? 0; ?></div>
        <div class="stat-label">En attente</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['scrutins_cours'] ?? 0; ?></div>
        <div class="stat-label">En cours</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?php echo $stats['scrutins_termines'] ?? 0; ?></div>
        <div class="stat-label">Terminés</div>
      </div>
    </section>

    <!-- Liste des scrutins -->
    <section class="scrutins-section">
      <div class="section-header">
        <h3>Liste des scrutins</h3>
      </div>

      <?php if (count($scrutins) > 0): ?>
        <div class="scrutins-grid">
          <?php foreach ($scrutins as $scrutin): ?>
            <div class="scrutin-card">
              <div class="scrutin-header">
                <h4><?php echo htmlspecialchars($scrutin['nom_scrutin']); ?></h4>
                <span class="statut <?php echo htmlspecialchars($scrutin['statut']); ?>">
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
                      echo htmlspecialchars($scrutin['statut']);
                  }
                  ?>
                </span>
              </div>

              <div class="scrutin-info">
                <p><strong>Type:</strong> <?php echo htmlspecialchars($scrutin['type']); ?></p>
                <p><strong>Début:</strong> <?php echo date('d/m/Y H:i', strtotime($scrutin['date_debut'])); ?></p>
                <p><strong>Fin:</strong> <?php echo date('d/m/Y H:i', strtotime($scrutin['date_fin'])); ?></p>
                <p><strong>Candidats:</strong> <?php echo $scrutin['nb_candidats']; ?></p>
              </div>

              <div class="scrutin-actions">
                <button class="btn btn-view" onclick="openViewModal(<?php echo $scrutin['scrutin_id']; ?>)">
                  <i class="fa-solid fa-eye"></i> Voir
                </button>
                <?php if ($scrutin['statut'] === 'en_attente'): ?>
                  <button class="btn btn-edit" onclick="openEditModal(<?php echo $scrutin['scrutin_id']; ?>)">
                    <i class="fa-solid fa-pen"></i> Modifier
                  </button>
                <?php endif; ?>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="no-scrutins">
          <p>Aucun scrutin créé pour le moment.</p>
        </div>
      <?php endif; ?>
    </section>

    <footer>
      © 2025 République du Sénégal — Ministère de l'Intérieur et de la Sécurité Publique
    </footer>
  </main>


  <!-- Modal Ajouter Scrutin -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Nouveau scrutin</h3>
        <span class="close" onclick="closeModal('addModal')">&times;</span>
      </div>
      <form id="addForm" method="POST">
        <input type="hidden" name="action" value="add_scrutin">
        <div class="form-group">
          <label for="add_nom">Nom du scrutin *</label>
          <input type="text" class="form-control" id="add_nom" name="nom"
            placeholder="Ex : Élection présidentielle 2025" required>
        </div>

        <div class="form-group">
          <label for="add_type">Type d'élection *</label>
          <select id="add_type" name="type" required>
            <option value="">-- Sélectionner --</option>
            <option value="presidentielle">Présidentielle</option>
            <option value="legislative">Législative</option>
            <option value="locale">Locale</option>
            <option value="municipale">Municipale</option>
            <option value="professionnelle">Professionnelle</option>
          </select>
        </div>

        <div class="date-group">
          <div class="form-group">
            <label for="add_debut">Date d'ouverture *</label>
            <input type="date" id="add_debut" name="debut" min="<?php echo date('Y-m-d'); ?>" required>
          </div>

          <div class="form-group">
            <label for="add_fin">Date de clôture *</label>
            <input type="date" id="add_fin" name="fin" min="<?php echo date('Y-m-d'); ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label>Candidats participants *</label>
          <?php if (count($candidats) > 0): ?>
            <div class="candidats-list">
              <?php foreach ($candidats as $candidat): ?>
                <label class="candidat-option">
                  <input type="checkbox" name="candidats[]" value="<?php echo $candidat['id']; ?>">
                  <?php echo htmlspecialchars($candidat['prenom'] . ' ' . $candidat['nom'] . ' - ' . $candidat['parti_politique']); ?>
                </label>
              <?php endforeach; ?>
            </div>
            <small style="color: #666;">Sélectionnez au moins un candidat</small>
          <?php else: ?>
            <p style="color: #c62828; padding: 10px; background: #fdecea; border-radius: 8px;">
              <i class="fa-solid fa-exclamation-triangle"></i>
              Aucun candidat disponible. Veuillez d'abord ajouter des candidats.
            </p>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="add_description">Description du scrutin *</label>
          <textarea id="add_description" name="description" rows="4" placeholder="Brève description du scrutin..."
            required></textarea>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Annuler</button>
          <button type="submit" class="btn-submit" <?php echo (count($candidats) == 0) ? 'disabled' : ''; ?>>
            Créer le scrutin
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Voir Scrutin -->
  <div id="viewModal" class="modal">
    <div class="modal-content view-modal">
      <div class="modal-header">
        <h3>Détails du scrutin</h3>
        <span class="close" onclick="closeModal('viewModal')">&times;</span>
      </div>
      <div id="viewContent">
        <!-- Contenu chargé dynamiquement -->
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal('viewModal')">Fermer</button>
      </div>
    </div>
  </div>

  <!-- Modal Modifier Scrutin -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Modifier le scrutin</h3>
        <span class="close" onclick="closeModal('editModal')">&times;</span>
      </div>
      <form id="editForm" method="POST">
        <input type="hidden" name="action" value="edit_scrutin">
        <input type="hidden" id="edit_scrutin_id" name="scrutin_id">

        <div class="form-group">
          <label for="edit_nom">Nom du scrutin *</label>
          <input type="text" class="form-control" id="edit_nom" name="nom" required>
        </div>

        <div class="form-group">
          <label for="edit_type">Type d'élection *</label>
          <select id="edit_type" name="type" required>
            <option value="">-- Sélectionner --</option>
            <option value="presidentielle">Présidentielle</option>
            <option value="legislative">Législative</option>
            <option value="locale">Locale</option>
            <option value="municipale">Municipale</option>
            <option value="professionnelle">Professionnelle</option>
          </select>
        </div>

        <div class="date-group">
          <div class="form-group">
            <label for="edit_debut">Date d'ouverture *</label>
            <input type="date" id="edit_debut" name="debut" required>
          </div>

          <div class="form-group">
            <label for="edit_fin">Date de clôture *</label>
            <input type="date" id="edit_fin" name="fin" required>
          </div>
        </div>

        <div class="form-group">
          <label>Candidats participants *</label>
          <?php if (count($candidats) > 0): ?>
            <div class="candidats-list" id="edit_candidats_list">
              <?php foreach ($candidats as $candidat): ?>
                <label class="candidat-option">
                  <input type="checkbox" name="candidats[]" value="<?php echo $candidat['id']; ?>">
                  <?php echo htmlspecialchars($candidat['prenom'] . ' ' . $candidat['nom'] . ' - ' . $candidat['parti_politique']); ?>
                </label>
              <?php endforeach; ?>
            </div>
            <small style="color: #666;">Sélectionnez au moins un candidat</small>
          <?php else: ?>
            <p style="color: #c62828; padding: 10px; background: #fdecea; border-radius: 8px;">
              <i class="fa-solid fa-exclamation-triangle"></i> Aucun candidat disponible.
            </p>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="edit_description">Description du scrutin *</label>
          <textarea id="edit_description" name="description" rows="4" required></textarea>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Annuler</button>
          <button type="submit" class="btn-submit">Modifier</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const tousLesCandidats = <?php echo json_encode($candidats); ?>;

    function openAddModal() {
      document.getElementById('addModal').style.display = 'block';
      document.getElementById('addForm').reset();
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('add_debut').min = today;
      document.getElementById('add_fin').min = today;
    }
    const scrutins = <?php echo json_encode($scrutins_js); ?>;

    function openViewModal(scrutinId) {
      const scrutin = scrutins[scrutinId];
      if (!scrutin) return;

      const viewContent = document.getElementById('viewContent');
      const candidatsScrutin = tousLesCandidats.filter(c => scrutin.candidats_ids.includes(c.id));

      viewContent.innerHTML = `
        <div class="form-group"><label>Nom du scrutin</label><p>${scrutin.nom}</p></div>
        <div class="form-group"><label>Type d'élection</label><p>${scrutin.type}</p></div>
        <div class="date-group">
            <div class="form-group"><label>Date d'ouverture</label><p>${new Date(scrutin.date_debut).toLocaleDateString('fr-FR')}</p></div>
            <div class="form-group"><label>Date de clôture</label><p>${new Date(scrutin.date_fin).toLocaleDateString('fr-FR')}</p></div>
        </div>
        <div class="form-group"><label>Candidats</label>
            <ul>
                ${candidatsScrutin.map(c => `<li>${c.prenom} ${c.nom} - ${c.parti_politique}</li>`).join('')}
            </ul>
        </div>
        <div class="form-group"><label>Description</label><p>${scrutin.description}</p></div>
    `;
      document.getElementById('viewModal').style.display = 'block';
    }


    function openEditModal(scrutinId) {
      const scrutin = scrutins[scrutinId];
      if (!scrutin) return;

      document.getElementById('edit_scrutin_id').value = scrutin.scrutin_id;
      document.getElementById('edit_nom').value = scrutin.nom;
      document.getElementById('edit_type').value = scrutin.type;
      document.getElementById('edit_debut').value = scrutin.date_debut.split(' ')[0];
      document.getElementById('edit_fin').value = scrutin.date_fin.split(' ')[0];
      document.getElementById('edit_description').value = scrutin.description;

      // Cocher les candidats associés
      const checkboxes = document.querySelectorAll('#edit_candidats_list input[type="checkbox"]');
      checkboxes.forEach(cb => {
        cb.checked = scrutin.candidats_ids.includes(parseInt(cb.value));
      });

      document.getElementById('editModal').style.display = 'block';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }


    // Validation des dates
    document.getElementById('add_debut').addEventListener('change', function () {
      const fin = document.getElementById('add_fin');
      fin.min = this.value;
      if (fin.value && fin.value < this.value) fin.value = this.value;
    });

    document.getElementById('edit_debut').addEventListener('change', function () {
      document.getElementById('edit_fin').min = this.value;
    });

    // Validation des formulaires
    document.getElementById('addForm').addEventListener('submit', function (e) {
      if (document.querySelectorAll('#addForm input[name="candidats[]"]:checked').length === 0) {
        e.preventDefault(); alert('Veuillez sélectionner au moins un candidat.');
      }
    });
    document.getElementById('editForm').addEventListener('submit', function (e) {
      if (document.querySelectorAll('#editForm input[name="candidats[]"]:checked').length === 0) {
        e.preventDefault(); alert('Veuillez sélectionner au moins un candidat.');
      }
    });

    // Fermer modals en cliquant à l'extérieur
    window.onclick = function (event) {
      Array.from(document.getElementsByClassName('modal')).forEach(modal => {
        if (event.target == modal) modal.style.display = 'none';
      });
    };

    // Empêcher fermeture en cliquant à l'intérieur
    document.querySelectorAll('.modal-content').forEach(content => {
      content.addEventListener('click', e => e.stopPropagation());
    });
  </script>

</body>

</html>