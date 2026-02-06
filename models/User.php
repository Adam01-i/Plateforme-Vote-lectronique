<?php
require_once '../config/database.php';

class User {
    private $conn;
    private $table_name = "Utilisateur";

    public $id;
    public $nom;
    public $prenom;
    public $cni;
    public $email;
    public $mdp;
    public $role;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ================================
    //           REGISTER
    // ================================
    public function register() {
        // Vérifier si email ou CNI existe déjà
        $query = "SELECT id FROM " . $this->table_name . " 
                  WHERE email = :email OR cni = :cni";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":cni", $this->cni);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return false; // existe déjà
        }

        // Hash du mot de passe
        $hashed_password = password_hash($this->mdp, PASSWORD_DEFAULT);

        // Insertion
        $query = "INSERT INTO " . $this->table_name . " 
                  SET nom=:nom, prenom=:prenom, cni=:cni, 
                      email=:email, mdp=:mdp, role=:role";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":nom", $this->nom);
        $stmt->bindParam(":prenom", $this->prenom);
        $stmt->bindParam(":cni", $this->cni);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":mdp", $hashed_password);
        $stmt->bindParam(":role", $this->role);

        return $stmt->execute();
    }

    // ================================
    //             LOGIN
    // ================================
    public function login() {
        $query = "SELECT id, nom, prenom, cni, email, mdp, role
                  FROM " . $this->table_name . " 
                  WHERE email = :email LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérification du mot de passe
            if (password_verify($this->mdp, $row['mdp'])) {

                // Sauvegarde session
                $_SESSION['user_id']    = $row['id'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['user_role']  = $row['role'];
                $_SESSION['user_name']  = $row['prenom'] . ' ' . $row['nom'];

                return true;
            }
        }

        return false;
    }
}
?>
