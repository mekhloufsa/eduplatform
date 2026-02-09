<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer le token depuis le header Authorization
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token manquant']);
    exit();
}

$email = isset($_GET['email']) ? sanitize($_GET['email']) : null;

if (!$email) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer l'enseignant par email
    $user_sql = "SELECT u.id, t.id as teacher_id 
                 FROM users u 
                 JOIN teachers t ON u.id = t.user_id 
                 WHERE u.email = :email AND u.role = 'teacher'";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bindParam(':email', $email);
    $user_stmt->execute();
    
    if ($user_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Enseignant non trouvé']);
        exit();
    }
    
    $user = $user_stmt->fetch();
    $teacher_id = $user['teacher_id'];
    
    // Récupérer tous les quiz des cours de l'enseignant
    $sql = "SELECT q.*, c.title as course_title, c.category,
                   (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as questions_count
            FROM quizzes q
            JOIN courses c ON q.course_id = c.id
            WHERE c.teacher_id = :teacher_id
            ORDER BY q.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->execute();
    $quizzes = $stmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => $quizzes
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>