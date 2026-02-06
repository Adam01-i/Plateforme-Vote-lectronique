<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier si l'utilisateur est connecté et est un électeur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'electeur') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Connexion à la base de données
require_once __DIR__ . '/../config/database.php';
error_log("DEBUG _POST: " . json_encode($_POST));
error_log("DEBUG php://input: " . file_get_contents("php://input"));

try {
    $database = new Database();
    $db = $database->getConnection();

    // Récupérer les données du vote
    $input = json_decode(file_get_contents('php://input'), true);
    $candidate_id = $input['candidate_id'] ?? null;
    $scrutin_id = $input['scrutin_id'] ?? null;

    $user_id = $_SESSION['user_id'];

    // Debug pour voir ce qui arrive côté serveur
    error_log("DEBUG vote.php - POST: " . json_encode($_POST));
    error_log("DEBUG vote.php - candidate_id: $candidate_id, scrutin_id: $scrutin_id");

    // Validation des données
    if (!$candidate_id || !$scrutin_id) {
        throw new Exception('Données de vote incomplètes');
    }

    // Vérifier si l'utilisateur a déjà voté DANS CE SCRUTIN (utilisation de l'index)
    $query = "SELECT id FROM Vote WHERE utilisateur_id = :user_id AND scrutin_id = :scrutin_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':scrutin_id', $scrutin_id);
    $stmt->execute();

    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Vous avez déjà voté dans ce scrutin');
    }

    // Vérifier si le scrutin est actif (utilisation de l'index sur statut)
    $query = "SELECT * FROM Scrutin WHERE id = :scrutin_id AND statut = 'en_cours' AND date_debut <= NOW() AND date_fin >= NOW()";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':scrutin_id', $scrutin_id);
    $stmt->execute();
    $scrutin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$scrutin) {
        // Debug: Vérifier ce qui ne va pas avec le scrutin
        $query_debug = "SELECT id, nom, statut, date_debut, date_fin, NOW() as maintenant FROM Scrutin WHERE id = :scrutin_id";
        $stmt_debug = $db->prepare($query_debug);
        $stmt_debug->bindParam(':scrutin_id', $scrutin_id);
        $stmt_debug->execute();
        $scrutin_debug = $stmt_debug->fetch(PDO::FETCH_ASSOC);

        $debug_info = "Scrutin ID: {$scrutin_id} - ";
        if ($scrutin_debug) {
            $debug_info .= "Statut: {$scrutin_debug['statut']}, Début: {$scrutin_debug['date_debut']}, Fin: {$scrutin_debug['date_fin']}, Maintenant: {$scrutin_debug['maintenant']}";
        } else {
            $debug_info .= "Scrutin non trouvé";
        }

        error_log("Debug scrutin: " . $debug_info);
        throw new Exception('Scrutin non disponible pour le vote. ' . $debug_info);
    }

    // Vérifier si le candidat participe au scrutin (utilisation de l'index)
    $query = "SELECT * FROM Scrutin_Candidat WHERE scrutin_id = :scrutin_id AND candidat_id = :candidat_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':scrutin_id', $scrutin_id);
    $stmt->bindParam(':candidat_id', $candidate_id);
    $stmt->execute();
    $participation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participation) {
        throw new Exception('Candidat non participant à ce scrutin');
    }

    // Commencer la transaction
    $db->beginTransaction();

    // Enregistrer le vote AVEC le scrutin_id (utilisation de l'index)
    $query = "INSERT INTO Vote (utilisateur_id, candidat_id, scrutin_id, date_et_heure) VALUES (:user_id, :candidate_id, :scrutin_id, NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':candidate_id', $candidate_id);
    $stmt->bindParam(':scrutin_id', $scrutin_id);

    if (!$stmt->execute()) {
        throw new Exception('Erreur lors de l\'enregistrement du vote');
    }

    // Valider la transaction
    $db->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Vote enregistré avec succès']);

} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>