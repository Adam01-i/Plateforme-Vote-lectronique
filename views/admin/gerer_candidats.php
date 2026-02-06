<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
  header("Location: ./auth/login.php");
  exit();
}

// Connexion à la base de données
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();
// Traitement de l'ajout d'un candidat
if($_POST && isset($_POST['action']) && $_POST['action'] == 'add') {
    $prenom = $_POST['prenom'] ?? '';
    $nom = $_POST['nom'] ?? '';
    $parti_politique = $_POST['parti_politique'] ?? '';
    
    // Chemins absolus corrigés
    $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/vote';
    
    // Gestion de l'upload de la photo
    $photo_officiel = 'default.png';
    if(isset($_FILES['photo_officiel']) && $_FILES['photo_officiel']['error'] == 0) {
        $uploadDir = $baseDir . '/assets/img/candidats/';
        
        // Vérifier et créer le dossier
        if(!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['photo_officiel']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        // Vérifier le type de fichier
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['photo_officiel']['type'];
        
        if(in_array($fileType, $allowedTypes)) {
            if(move_uploaded_file($_FILES['photo_officiel']['tmp_name'], $uploadFile)) {
                $photo_officiel = $fileName;
            }
        }
    }
    
    // Gestion de l'upload du programme PDF
    $programme = null;
    if(isset($_FILES['programme_pdf']) && $_FILES['programme_pdf']['error'] == 0) {
        $uploadDir = $baseDir . '/assets/programmes/';
        
        // Vérifier et créer le dossier
        if(!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['programme_pdf']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        // Vérifier que c'est bien un PDF
        $fileType = $_FILES['programme_pdf']['type'];
        
        if($fileType == 'application/pdf') {
            if(move_uploaded_file($_FILES['programme_pdf']['tmp_name'], $uploadFile)) {
                $programme = $fileName;
            }
        }
    }
    
    $query = "INSERT INTO Candidat (prenom, nom, parti_politique, photo_officiel, programme) 
              VALUES (:prenom, :nom, :parti_politique, :photo_officiel, :programme)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':prenom', $prenom);
    $stmt->bindParam(':nom', $nom);
    $stmt->bindParam(':parti_politique', $parti_politique);
    $stmt->bindParam(':photo_officiel', $photo_officiel);
    $stmt->bindParam(':programme', $programme);
    
    if($stmt->execute()) {
        $_SESSION['success'] = "Candidat ajouté avec succès!";
    } else {
        $_SESSION['error'] = "Erreur lors de l'ajout du candidat.";
    }
    header("Location: gerer_candidats.php");
    exit();
}

// Traitement de la modification d'un candidat
if($_POST && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $nom = $_POST['nom'] ?? '';
    $parti_politique = $_POST['parti_politique'] ?? '';
    
    // Récupérer les données actuelles du candidat
    $query = "SELECT photo_officiel, programme FROM Candidat WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $photo_officiel = $currentData['photo_officiel'];
    $programme = $currentData['programme'];
    
    $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/vote';
    
    // Gestion de l'upload de la nouvelle photo
    if(isset($_FILES['photo_officiel']) && $_FILES['photo_officiel']['error'] == 0) {
        $uploadDir = $baseDir . '/assets/img/candidats/';
        $fileName = uniqid() . '_' . basename($_FILES['photo_officiel']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['photo_officiel']['type'];
        
        if(in_array($fileType, $allowedTypes)) {
            if(move_uploaded_file($_FILES['photo_officiel']['tmp_name'], $uploadFile)) {
                // Supprimer l'ancienne photo si ce n'est pas default.png
                if($photo_officiel != 'default.png' && file_exists($uploadDir . $photo_officiel)) {
                    unlink($uploadDir . $photo_officiel);
                }
                $photo_officiel = $fileName;
            }
        }
    }
    
    // Gestion de l'upload du nouveau programme PDF
    if(isset($_FILES['programme_pdf']) && $_FILES['programme_pdf']['error'] == 0) {
        $uploadDir = $baseDir . '/assets/programmes/';
        $fileName = uniqid() . '_' . basename($_FILES['programme_pdf']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        $fileType = $_FILES['programme_pdf']['type'];
        
        if($fileType == 'application/pdf') {
            if(move_uploaded_file($_FILES['programme_pdf']['tmp_name'], $uploadFile)) {
                // Supprimer l'ancien programme PDF
                if($programme && file_exists($uploadDir . $programme)) {
                    unlink($uploadDir . $programme);
                }
                $programme = $fileName;
            }
        }
    }
    
    $query = "UPDATE Candidat SET prenom = :prenom, nom = :nom, parti_politique = :parti_politique, 
              photo_officiel = :photo_officiel, programme = :programme WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':prenom', $prenom);
    $stmt->bindParam(':nom', $nom);
    $stmt->bindParam(':parti_politique', $parti_politique);
    $stmt->bindParam(':photo_officiel', $photo_officiel);
    $stmt->bindParam(':programme', $programme);
    
    if($stmt->execute()) {
        $_SESSION['success'] = "Candidat modifié avec succès!";
    } else {
        $_SESSION['error'] = "Erreur lors de la modification du candidat.";
    }
    header("Location: gerer_candidats.php");
    exit();
}

// Traitement de la suppression d'un candidat
if($_POST && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $id = $_POST['id'] ?? '';
    
    // Récupérer les données du candidat avant suppression
    $query = "SELECT photo_officiel, programme FROM Candidat WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $candidat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($candidat) {
        $baseDir = $_SERVER['DOCUMENT_ROOT'] . '/vote';
        
        // Supprimer la photo si ce n'est pas default.png
        if($candidat['photo_officiel'] != 'default.png') {
            $photoPath = $baseDir . '/assets/img/candidats/' . $candidat['photo_officiel'];
            if(file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
        
        // Supprimer le programme PDF
        if($candidat['programme']) {
            $programmePath = $baseDir . '/assets/programmes/' . $candidat['programme'];
            if(file_exists($programmePath)) {
                unlink($programmePath);
            }
        }
    }
    
    $query = "DELETE FROM Candidat WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    
    if($stmt->execute()) {
        $_SESSION['success'] = "Candidat supprimé avec succès!";
    } else {
        $_SESSION['error'] = "Erreur lors de la suppression du candidat.";
    }
    header("Location: gerer_candidats.php");
    exit();
}

// Récupérer tous les candidats
$query = "SELECT * FROM Candidat ORDER BY date_creation DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$candidats = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gérer les candidats | Administration Vote Électronique</title>
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
    }

    .add-btn:hover {
      background: #8d7755;
    }

    /* GRID */
    .candidates-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 25px;
    }

    .candidate-card {
      background: var(--white);
      border-radius: 16px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      padding: 25px 20px;
      text-align: center;
      transition: var(--transition);
    }

    .candidate-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      border: 1px solid var(--gold);
    }

    .candidate-card img {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: 50%;
      border: 3px solid var(--gold);
      margin-bottom: 15px;
    }

    .candidate-card h3 {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--text);
    }

    .candidate-card p {
      color: #555;
      font-size: 0.95rem;
      margin: 6px 0 12px;
    }

    .status {
      font-weight: 600;
      border-radius: 8px;
      padding: 6px 10px;
      text-align: center;
      display: inline-block;
      margin-bottom: 12px;
    }

    .status.active {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .status.inactive {
      background: #fdecea;
      color: #c62828;
    }

    .actions {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    .btn {
      border: none;
      border-radius: 8px;
      padding: 6px 12px;
      font-size: 0.9rem;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn.edit {
      background: var(--gold);
      color: var(--white);
    }

    .btn.edit:hover {
      background: #8d7755;
    }

    .btn.delete {
      background: #c62828;
      color: var(--white);
    }

    .btn.delete:hover {
      background: #a91f1f;
    }

    footer {
      margin-top: auto;
      text-align: center;
      padding: 15px;
      font-size: 0.9rem;
      color: #777;
    }

    /* MODAL STYLES */
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
      margin: 5% auto;
      padding: 30px;
      border-radius: 16px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      animation: slideIn 0.3s ease;
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

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--text);
    }

    .form-control {
      width: 100%;
      padding: 12px;
      border: 2px solid var(--border);
      border-radius: 8px;
      font-size: 1rem;
      transition: var(--transition);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--gold);
    }

    textarea.form-control {
      min-height: 100px;
      resize: vertical;
    }

    .file-input {
      padding: 8px 0;
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

    /* RESPONSIVE */
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

      .candidate-card img {
        width: 90px;
        height: 90px;
      }

      .modal-content {
        margin: 10% auto;
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
      <a href="gerer_candidats.php" class="active"><i class="fa-solid fa-user-tie"></i> Gérer candidats</a>
      <a href="lancer_scrutin.php" ><i class="fa-solid fa-vote-yea"></i> Gérer scrutins</a>
      <a href="resultats.php"><i class="fa-solid fa-chart-pie"></i> Résultats</a>
      <a href="/vote/controllers/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
    </nav>
  </aside>

  <main class="main">
    <header>
      <h2>Liste des candidats</h2>
      <button class="add-btn" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Ajouter un candidat</button>
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

    <section class="candidates-grid">
      <?php if (count($candidats) > 0): ?>
        <?php foreach ($candidats as $candidat): ?>
          <div class="candidate-card">
            <img src="/vote/assets/img/candidats/<?php echo $candidat['photo_officiel'] ?: 'default.png'; ?>"
              alt="<?php echo htmlspecialchars($candidat['prenom'] . ' ' . $candidat['nom']); ?>"
              onerror="this.src='/vote/assets/img/1.png'">
            <h3><?php echo htmlspecialchars($candidat['prenom'] . ' ' . $candidat['nom']); ?></h3>
            <p><?php echo htmlspecialchars($candidat['parti_politique']); ?></p>
            <?php if ($candidat['programme']): ?>
              <p>
                <a href="/vote/assets/programmes/<?php echo $candidat['programme']; ?>" target="_blank"
                  style="color: var(--gold); text-decoration: none;">
                  <i class="fa-solid fa-file-pdf"></i> Voir le programme
                </a>
              </p>
            <?php else: ?>
              <p><small>Aucun programme disponible</small></p>
            <?php endif; ?>
            <!-- <span class="status active">Actif</span> -->
            <div class="actions">
              <button class="btn edit" onclick="openEditModal(<?php echo $candidat['id']; ?>)"><i
                  class="fa-solid fa-pen"></i> Modifier</button>
              <button class="btn delete"
                onclick="openDeleteModal(<?php echo $candidat['id']; ?>, '<?php echo htmlspecialchars($candidat['prenom'] . ' ' . $candidat['nom']); ?>')"><i
                  class="fa-solid fa-trash"></i> Supprimer</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
          <p>Aucun candidat enregistré pour le moment.</p>
        </div>
      <?php endif; ?>
    </section>

    <footer>
      © 2025 République du Sénégal — Ministère de l'Intérieur et de la Sécurité Publique
    </footer>
  </main>

  <!-- Modal Ajouter Candidat -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Ajouter un candidat</h3>
        <span class="close" onclick="closeAddModal()">&times;</span>
      </div>
      <form id="addForm" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add">
        <div class="form-group">
          <label for="add_prenom">Prénom *</label>
          <input type="text" class="form-control" id="add_prenom" name="prenom" placeholder="Prénom du candidat" required>
        </div>
        <div class="form-group">
          <label for="add_nom">Nom *</label>
          <input type="text" class="form-control" id="add_nom" name="nom" placeholder="Nom du candidat" required>
        </div>
        <div class="form-group">
          <label for="add_parti">Parti politique *</label>
          <input type="text" class="form-control" id="add_parti" name="parti_politique" placeholder="Parti politique du candidat" required>
        </div>
        <div class="form-group">
          <label for="add_photo">Photo officielle</label>
          <input type="file" class="form-control file-input" id="add_photo" name="photo_officiel" accept="image/*">
        </div>
        <div class="form-group">
          <label for="add_programme_pdf">Programme (PDF)</label>
          <input type="file" class="form-control file-input" id="add_programme_pdf" name="programme_pdf" accept=".pdf">
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeAddModal()">Annuler</button>
          <button type="submit" class="btn-submit">Ajouter</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Modifier Candidat -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Modifier le candidat</h3>
        <span class="close" onclick="closeEditModal()">&times;</span>
      </div>
      <form id="editForm" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" id="edit_id" name="id">
        <div class="form-group">
          <label for="edit_prenom">Prénom *</label>
          <input type="text" class="form-control" id="edit_prenom" name="prenom" required>
        </div>
        <div class="form-group">
          <label for="edit_nom">Nom *</label>
          <input type="text" class="form-control" id="edit_nom" name="nom" required>
        </div>
        <div class="form-group">
          <label for="edit_parti">Parti politique *</label>
          <input type="text" class="form-control" id="edit_parti" name="parti_politique" required>
        </div>
        <div class="form-group">
          <label for="edit_photo">Photo officielle</label>
          <input type="file" class="form-control file-input" id="edit_photo" name="photo_officiel" accept="image/*">
          <small>Laisser vide pour conserver l'image actuelle</small>
        </div>
        <div class="form-group">
          <label for="edit_programme_pdf">Programme (PDF)</label>
          <input type="file" class="form-control file-input" id="edit_programme_pdf" name="programme_pdf" accept=".pdf">
          <small>Laisser vide pour conserver le PDF actuel</small>
          <div id="current_programme" style="margin-top: 5px;"></div>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeEditModal()">Annuler</button>
          <button type="submit" class="btn-submit">Modifier</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Supprimer Candidat -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Supprimer le candidat</h3>
        <span class="close" onclick="closeDeleteModal()">&times;</span>
      </div>
      <form id="deleteForm" method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" id="delete_id" name="id">
        <div class="form-group">
          <p>Êtes-vous sûr de vouloir supprimer le candidat : <strong id="delete_name"></strong> ?</p>
          <p class="text-danger">Cette action est irréversible !</p>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Annuler</button>
          <button type="submit" class="btn-submit" style="background: #c62828;">Supprimer</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Données des candidats pour JavaScript
    const candidats = <?php echo json_encode($candidats); ?>;

    // Modal Ajouter
    function openAddModal() {
      document.getElementById('addModal').style.display = 'block';
      document.getElementById('addForm').reset();
    }

    function closeAddModal() {
      document.getElementById('addModal').style.display = 'none';
    }

    // Modal Modifier
    function openEditModal(id) {
      const candidat = candidats.find(c => c.id == id);
      if (candidat) {
        document.getElementById('edit_id').value = candidat.id;
        document.getElementById('edit_prenom').value = candidat.prenom;
        document.getElementById('edit_nom').value = candidat.nom;
        document.getElementById('edit_parti').value = candidat.parti_politique;

        // Afficher le programme actuel s'il existe
        const currentProgrammeDiv = document.getElementById('current_programme');
        if (candidat.programme) {
          currentProgrammeDiv.innerHTML = `<a href="/vote/assets/programmes/${candidat.programme}" target="_blank" style="color: var(--gold);">
        <i class="fa-solid fa-file-pdf"></i> Programme actuel
      </a>`;
        } else {
          currentProgrammeDiv.innerHTML = '<small>Aucun programme actuel</small>';
        }

        document.getElementById('editModal').style.display = 'block';
      }
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    // Modal Supprimer
    function openDeleteModal(id, nom) {
      document.getElementById('delete_id').value = id;
      document.getElementById('delete_name').textContent = nom;
      document.getElementById('deleteModal').style.display = 'block';
    }

    function closeDeleteModal() {
      document.getElementById('deleteModal').style.display = 'none';
    }

    // Fermer les modals en cliquant à l'extérieur
    window.onclick = function (event) {
      const modals = document.getElementsByClassName('modal');
      for (let modal of modals) {
        if (event.target == modal) {
          modal.style.display = 'none';
        }
      }
    }

    // Empêcher la fermeture du modal en cliquant à l'intérieur
    document.querySelectorAll('.modal-content').forEach(content => {
      content.addEventListener('click', (e) => {
        e.stopPropagation();
      });
    });
  </script>
</body>

</html>