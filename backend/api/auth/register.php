<?php
// Afficher les erreurs pour le debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Données JSON invalides']);
    exit();
}

// Validation des données requises
$required_fields = ['email', 'password', 'firstName', 'lastName', 'role'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Le champ ' . $field . ' est requis']);
        exit();
    }
}

$email = sanitize($data['email']);
$password = $data['password'];
$firstName = sanitize($data['firstName']);
$lastName = sanitize($data['lastName']);
$role = sanitize($data['role']);

// Validation de l'email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email invalide']);
    exit();
}

// Validation du rôle
if (!in_array($role, [ROLE_STUDENT, ROLE_TEACHER])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Rôle invalide: ' . $role]);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Vérifier si l'email existe déjà
    $check_sql = "SELECT id FROM users WHERE email = :email";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Cet email est déjà utilisé']);
        exit();
    }
    
    // Commencer la transaction
    $conn->beginTransaction();
    
    // Hasher le mot de passe
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insérer l'utilisateur
    $user_sql = "INSERT INTO users (email, password, role, first_name, last_name) 
                 VALUES (:email, :password, :role, :first_name, :last_name)";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bindParam(':email', $email);
    $user_stmt->bindParam(':password', $hashed_password);
    $user_stmt->bindParam(':role', $role);
    $user_stmt->bindParam(':first_name', $firstName);
    $user_stmt->bindParam(':last_name', $lastName);
    $user_stmt->execute();
    
    $user_id = $conn->lastInsertId();
    
    // Insérer les informations spécifiques au rôle
    if ($role === ROLE_STUDENT) {
        // Validation des champs étudiant
        if (!isset($data['studentCard']) || !isset($data['year'])) {
            $conn->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Informations étudiant incomplètes']);
            exit();
        }
        
        $student_card = sanitize($data['studentCard']);
        $year = sanitize($data['year']);
        
        // Vérifier si la carte étudiante existe déjà
        $check_card_sql = "SELECT id FROM students WHERE student_card = :student_card";
        $check_card_stmt = $conn->prepare($check_card_sql);
        $check_card_stmt->bindParam(':student_card', $student_card);
        $check_card_stmt->execute();
        
        if ($check_card_stmt->rowCount() > 0) {
            $conn->rollBack();
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Cette carte étudiante est déjà utilisée']);
            exit();
        }
        
        $student_sql = "INSERT INTO students (user_id, student_card, year, enrollment_date) 
                       VALUES (:user_id, :student_card, :year, CURDATE())";
        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bindParam(':user_id', $user_id);
        $student_stmt->bindParam(':student_card', $student_card);
        $student_stmt->bindParam(':year', $year);
        $student_stmt->execute();
        
    } elseif ($role === ROLE_TEACHER) {
        // Validation des champs enseignant
        if (!isset($data['specialty'])) {
            $conn->rollBack();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Spécialité requise']);
            exit();
        }
        
        $specialty = sanitize($data['specialty']);
        $grade = isset($data['grade']) ? sanitize($data['grade']) : null;
        $phone = isset($data['phone']) ? sanitize($data['phone']) : null;
        $bio = isset($data['bio']) ? sanitize($data['bio']) : null;
        
        $teacher_sql = "INSERT INTO teachers (user_id, specialty, grade, phone, bio) 
                       VALUES (:user_id, :specialty, :grade, :phone, :bio)";
        $teacher_stmt = $conn->prepare($teacher_sql);
        $teacher_stmt->bindParam(':user_id', $user_id);
        $teacher_stmt->bindParam(':specialty', $specialty);
        $teacher_stmt->bindParam(':grade', $grade);
        $teacher_stmt->bindParam(':phone', $phone);
        $teacher_stmt->bindParam(':bio', $bio);
        $teacher_stmt->execute();
    }
    
    // Valider la transaction
    $conn->commit();
    
    // Créer une session
    session_start();
    $token = generateToken();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_role'] = $role;
    $_SESSION['user_email'] = $email;
    $_SESSION['token'] = $token;
    
    // Préparer la réponse
    $response = [
        'status' => 'success',
        'message' => 'Inscription réussie',
        'data' => [
            'token' => $token,
            'user' => [
                'id' => $user_id,
                'email' => $email,
                'role' => $role,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'fullName' => $firstName . ' ' . $lastName
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch(PDOException $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    http_response_code(500);
    error_log("Registration error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur base de données: ' . $e->getMessage()]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
