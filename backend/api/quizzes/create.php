<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit();
}

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_TEACHER) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['course_id']) || !isset($data['title'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cours et titre requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $course_id = intval($data['course_id']);
    $title = sanitize($data['title']);
    $description = isset($data['description']) ? sanitize($data['description']) : null;
    $quiz_type = isset($data['quiz_type']) ? sanitize($data['quiz_type']) : 'practice';
    $time_limit = isset($data['time_limit']) ? intval($data['time_limit']) : 0;
    $passing_score = isset($data['passing_score']) ? intval($data['passing_score']) : 60;
    
    $sql = "INSERT INTO quizzes (course_id, title, description, quiz_type, time_limit, passing_score) 
            VALUES (:course_id, :title, :description, :quiz_type, :time_limit, :passing_score)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':quiz_type', $quiz_type);
    $stmt->bindParam(':time_limit', $time_limit);
    $stmt->bindParam(':passing_score', $passing_score);
    $stmt->execute();
    
    $quiz_id = $conn->lastInsertId();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Quiz créé avec succès',
        'data' => ['quiz_id' => $quiz_id]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
