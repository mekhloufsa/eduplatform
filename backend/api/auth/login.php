<?php
// Afficher les erreurs pour le debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gérer les requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit();
}

// Inclure les fichiers de configuration
try {
    require_once '../../config/database.php';
    require_once '../../config/constants.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur de configuration: ' . $e->getMessage()]);
    exit();
}

// Récupérer les données
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email et mot de passe requis']);
    exit();
}

$email = sanitize($data['email']);
$password = $data['password'];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Rechercher l'utilisateur
    $sql = "SELECT u.*, 
                   s.id as student_id, s.student_card, s.year,
                   t.id as teacher_id, t.specialty, t.grade
            FROM users u
            LEFT JOIN students s ON u.id = s.user_id
            LEFT JOIN teachers t ON u.id = t.user_id
            WHERE u.email = :email AND u.is_active = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        
        // Vérifier le mot de passe
        if (password_verify($password, $user['password'])) {
            
            // Créer un token de session
            $token = generateToken();
            
            // Enregistrer la session
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['token'] = $token;
            
            // Préparer la réponse
            $response = [
                'status' => 'success',
                'message' => 'Connexion réussie',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'firstName' => $user['first_name'],
                        'lastName' => $user['last_name'],
                        'fullName' => $user['first_name'] . ' ' . $user['last_name']
                    ]
                ]
            ];
            
            // Ajouter les informations spécifiques au rôle
            if ($user['role'] === 'student') {
                $response['data']['user']['studentId'] = $user['student_id'];
                $response['data']['user']['studentCard'] = $user['student_card'];
                $response['data']['user']['year'] = $user['year'];
            } elseif ($user['role'] === 'teacher') {
                $response['data']['user']['teacherId'] = $user['teacher_id'];
                $response['data']['user']['specialty'] = $user['specialty'];
                $response['data']['user']['grade'] = $user['grade'];
            }
            
            echo json_encode($response);
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Mot de passe incorrect']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Utilisateur non trouvé']);
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur base de données: ' . $e->getMessage()]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
