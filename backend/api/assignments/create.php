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

if (!isset($data['course_id']) || !isset($data['title']) || !isset($data['description'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cours, titre et description requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $course_id = intval($data['course_id']);
    $title = sanitize($data['title']);
    $description = sanitize($data['description']);
    $due_date = isset($data['due_date']) ? sanitize($data['due_date']) : null;
    $max_points = isset($data['max_points']) ? intval($data['max_points']) : 100;
    
    $sql = "INSERT INTO assignments (course_id, title, description, due_date, max_points) 
            VALUES (:course_id, :title, :description, :due_date, :max_points)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':due_date', $due_date);
    $stmt->bindParam(':max_points', $max_points);
    $stmt->execute();
    
    $assignment_id = $conn->lastInsertId();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Devoir créé avec succès',
        'data' => ['assignment_id' => $assignment_id]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
