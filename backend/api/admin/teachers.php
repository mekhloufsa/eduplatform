<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_ADMIN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Liste des enseignants
        $sql = "SELECT t.*, u.email, u.first_name, u.last_name, u.is_active
                FROM teachers t
                JOIN users u ON t.user_id = u.id
                ORDER BY u.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $teachers = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $teachers]);
    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Créer un enseignant
        $data = json_decode(file_get_contents('php://input'), true);
        
        $email = sanitize($data['email']);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $first_name = sanitize($data['first_name']);
        $last_name = sanitize($data['last_name']);
        $specialty = sanitize($data['specialty']);
        
        $conn->beginTransaction();
        
        // Créer l'utilisateur
        $user_sql = "INSERT INTO users (email, password, role, first_name, last_name) 
                     VALUES (:email, :password, 'teacher', :first_name, :last_name)";
        $user_stmt = $conn->prepare($user_sql);
        $user_stmt->bindParam(':email', $email);
        $user_stmt->bindParam(':password', $password);
        $user_stmt->bindParam(':first_name', $first_name);
        $user_stmt->bindParam(':last_name', $last_name);
        $user_stmt->execute();
        
        $user_id = $conn->lastInsertId();
        
        // Créer l'enseignant
        $teacher_sql = "INSERT INTO teachers (user_id, specialty) VALUES (:user_id, :specialty)";
        $teacher_stmt = $conn->prepare($teacher_sql);
        $teacher_stmt->bindParam(':user_id', $user_id);
        $teacher_stmt->bindParam(':specialty', $specialty);
        $teacher_stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Enseignant créé avec succès',
            'data' => ['user_id' => $user_id]
        ]);
    }
    
} catch(Exception $e) {
    if (isset($conn)) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
