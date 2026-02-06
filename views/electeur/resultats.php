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

// Chemin de base pour les images et programmes
$image_base_path = '/vote/assets/img/candidats/';

// Récupérer les scrutins terminés avec candidats et votes
$query = "
    SELECT 
        s.scrutin_id as id,
        s.nom_scrutin as nom,
        s.type,
        s.description,
        s.date_debut,
        s.date_fin,
        s.statut,
        s.nb_candidats
    FROM vue_scrutins_avec_candidats s
    WHERE s.statut = 'termine'
    ORDER BY s.date_fin DESC
";
$stmt = $db->prepare($query);
$stmt->execute();
$scrutins_termine = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pour chaque scrutin, récupérer les candidats et leurs résultats
$scrutins_resultats = [];
foreach ($scrutins_termine as $scrutin) {
    $query = "
        SELECT 
            c.id,
            c.prenom,
            c.nom,
            c.parti_politique,
            c.photo_officiel,
            COUNT(v.id) as votes_obtenus
        FROM Candidat c
        LEFT JOIN Vote v ON v.candidat_id = c.id AND v.scrutin_id = :scrutin_id
        INNER JOIN Scrutin_Candidat sc ON sc.candidat_id = c.id
        WHERE sc.scrutin_id = :scrutin_id
        GROUP BY c.id
        ORDER BY votes_obtenus DESC, c.nom ASC
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':scrutin_id', $scrutin['id']);
    $stmt->execute();
    $candidats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculer le total de votes pour le scrutin
    $total_votes = array_sum(array_column($candidats, 'votes_obtenus'));

    $scrutins_resultats[] = [
        'scrutin' => $scrutin,
        'candidats' => $candidats,
        'total_votes' => $total_votes
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats — Plateforme de Vote</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --gold: #aa9166;
            --black: #0b0b0b;
            --white: #fff;
            --grey: #f7f7f7;
            --light-grey: #f8f8f8;
            --success: #28a745;
            --shadow: 0 6px 20px rgba(0, 0, 0, 0.07);
            --transition: all 0.35s ease;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body { font-family: "Poppins", sans-serif; background: var(--grey); color: var(--black); min-height:100vh; display:flex; flex-direction:column; }

        header {
            background: var(--white);
            border-bottom: 3px solid var(--gold);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            padding: 18px 50px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            position:sticky;
            top:0;
            z-index:100;
        }

        .logo { display:flex; align-items:center; gap:1px; }
        .logo h1 { font-family:"Georgia", serif; font-style:italic; font-weight:900; font-size:2.1rem; color: var(--gold); letter-spacing:0.5px; }
        .logo img { width:35px; height:auto; object-fit:contain; filter: brightness(0) saturate(100%) invert(56%) sepia(12%) saturate(1000%) hue-rotate(5deg) brightness(92%) contrast(88%); }

        nav ul { list-style:none; display:flex; gap:28px; align-items:center; }
        nav ul li a { text-decoration:none; font-weight:600; color: var(--black); position:relative; transition: var(--transition); }
        nav ul li a::after { content:""; position:absolute; bottom:-5px; left:0; width:0%; height:2px; background: var(--gold); transition: width 0.3s ease; }
        nav ul li a:hover::after { width:100%; }

        .logout-btn { background: var(--gold); color: var(--white); padding:10px 20px; border-radius:10px; font-weight:600; border:none; cursor:pointer; transition: var(--transition); text-decoration:none; box-shadow:0 3px 6px rgba(0,0,0,0.15);}
        .logout-btn:hover { background: var(--black); color: var(--gold); transform:scale(1.05); }

        main { flex:1; padding:70px 80px 50px; animation: fadeIn 1s ease forwards; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(15px); } to { opacity:1; transform:translateY(0); } }

        .citizen-box { background: var(--white); border-left:6px solid var(--gold); border-radius:16px; padding:40px; margin-bottom:55px; box-shadow: var(--shadow); text-align:center; }
        .citizen-box h2 { font-family:"Merriweather", serif; font-size:2.1rem; margin-bottom:12px; color: var(--black); }
        .citizen-box p { font-size:1.05rem; color:#555; line-height:1.6; }

        .scrutin-card { background: var(--white); border-radius:16px; padding:30px; margin-bottom:40px; box-shadow: var(--shadow); border:2px solid transparent; transition: var(--transition); }
        .scrutin-card:hover { border-color: var(--gold); }

        .scrutin-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid var(--light-grey); }
        .scrutin-title { font-family:"Merriweather", serif; font-size:1.4rem; color: var(--black); }
        .scrutin-info { display:flex; gap:20px; font-size:0.9rem; color:#666; }
        .scrutin-dates { background: var(--light-grey); padding:10px 15px; border-radius:8px; }

        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:28px; margin-top:20px; }

        .candidate-card { background: var(--white); border-radius:18px; padding:25px; box-shadow: var(--shadow); text-align:center; display:flex; flex-direction:column; align-items:center; gap:10px; }
        .candidate-card img { width:110px; height:110px; border-radius:50%; object-fit:cover; border:3px solid var(--gold); margin-bottom:12px; }
        .candidate-card h4 { font-size:1.1rem; margin-bottom:6px; color: var(--black); font-weight:600; }
        .candidate-card p { font-size:0.9rem; color:#555; margin-bottom:6px; }

        .vote-result { margin-top:8px; font-size:0.9rem; color:#333; }
        .progress-bar { width:100%; background:#e0e0e0; border-radius:10px; overflow:hidden; height:16px; margin-top:6px; }
        .progress-fill { height:100%; background: var(--gold); text-align:right; padding-right:5px; color:#fff; font-size:0.8rem; line-height:16px; }

        .no-results { text-align:center; padding:40px; background: var(--white); border-radius:16px; color:#666; }

        footer { background: var(--white); border-top:2px solid var(--gold); text-align:center; padding:25px; font-size:0.9rem; color:#555; }

        @media (max-width:900px){ main{padding:40px 25px;} header{flex-direction:column; gap:10px; text-align:center;} nav ul{flex-wrap:wrap; justify-content:center;} .scrutin-header{flex-direction:column; gap:15px;} .scrutin-info{flex-direction:column; gap:10px;} }
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
            <h2>Bonjour, <?php echo htmlspecialchars($user_name); ?></h2>
            <p>Consultez ici les résultats des scrutins terminés.</p>
        </div>

        <?php if(count($scrutins_resultats) > 0): ?>
            <?php foreach($scrutins_resultats as $data):
                $scrutin = $data['scrutin'];
                $candidats = $data['candidats'];
                $total_votes = $data['total_votes'];
            ?>
            <section class="scrutin-card">
                <div class="scrutin-header">
                    <div>
                        <h3 class="scrutin-title"><?php echo htmlspecialchars($scrutin['nom']); ?></h3>
                        <p><?php echo htmlspecialchars($scrutin['description']); ?></p>
                    </div>
                    <div class="scrutin-info">
                        <div class="scrutin-dates">
                            <strong>Période :</strong><br>
                            <?php echo date('d/m/Y', strtotime($scrutin['date_debut'])); ?> -
                            <?php echo date('d/m/Y', strtotime($scrutin['date_fin'])); ?>
                        </div>
                        <div class="scrutin-dates">
                            <strong>Total Votes :</strong> <?php echo $total_votes; ?>
                        </div>
                    </div>
                </div>

                <div class="grid">
                    <?php foreach($candidats as $c): 
                        $full_name = $c['prenom'] . ' ' . $c['nom'];
                        $votes = $c['votes_obtenus'];
                        $percent = $total_votes > 0 ? round(($votes / $total_votes) * 100, 1) : 0;
                        $photo_path = $image_base_path . $c['photo_officiel'];
                    ?>
                    <div class="candidate-card">
                        <img src="<?php echo $photo_path; ?>" alt="<?php echo htmlspecialchars($full_name); ?>" onerror="this.src='/vote/assets/img/1.png'">
                        <h4><?php echo htmlspecialchars($full_name); ?></h4>
                        <p><?php echo htmlspecialchars($c['parti_politique']); ?></p>
                        <div class="vote-result">
                            Votes : <?php echo $votes; ?> (<?php echo $percent; ?>%)
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fa-solid fa-calendar-times" style="font-size:3rem; color:#ccc; margin-bottom:20px;"></i>
                <h3>Aucun résultat disponible</h3>
                <p>Il n'y a actuellement aucun scrutin terminé.</p>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        © 2025 République du Sénégal — Système de Vote Électronique
    </footer>
</body>
</html>
