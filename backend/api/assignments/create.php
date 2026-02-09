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

// Récupérer les données JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Données JSON invalides']);
    exit();
}

// Vérifier l'authentification par email
if (empty($data['teacher_email'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Email enseignant requis']);
    exit();
}

if (!isset($data['course_id']) || !isset($data['title'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cours et titre requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Vérifier que l'enseignant existe et a le bon rôle
    $teacher_email = sanitize($data['teacher_email']);
    $user_sql = "SELECT u.id, u.role, t.id as teacher_id 
                 FROM users u 
                 JOIN teachers t ON u.id = t.user_id 
                 WHERE u.email = :email AND u.role = 'teacher'";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bindParam(':email', $teacher_email);
    $user_stmt->execute();
    
    if ($user_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Enseignant non trouvé']);
        exit();
    }
    
    $user = $user_stmt->fetch();
    
    // Vérifier que le cours appartient à cet enseignant
    $course_id = intval($data['course_id']);
    $check_sql = "SELECT id FROM courses WHERE id = :course_id AND teacher_id = :teacher_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':course_id', $course_id);
    $check_stmt->bindParam(':teacher_id', $user['teacher_id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Cours non trouvé ou accès refusé']);
        exit();
    }
    
    $title = sanitize($data['title']);
    $description = isset($data['description']) ? sanitize($data['description']) : null;
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