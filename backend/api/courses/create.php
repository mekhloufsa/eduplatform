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

// Vérifier l'authentification
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_TEACHER) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Validation
$required_fields = ['title', 'description', 'category'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Le champ ' . $field . ' est requis']);
        exit();
    }
}

$title = sanitize($data['title']);
$description = sanitize($data['description']);
$category = sanitize($data['category']);
$requires_key = isset($data['requires_key']) ? (bool)$data['requires_key'] : false;
$enrollment_key = isset($data['enrollment_key']) ? sanitize($data['enrollment_key']) : null;
$max_enrollments = isset($data['max_enrollments']) ? intval($data['max_enrollments']) : 0;
$is_public = isset($data['is_public']) ? (bool)$data['is_public'] : true;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer l'ID de l'enseignant
    $teacher_sql = "SELECT id FROM teachers WHERE user_id = :user_id";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $teacher_stmt->execute();
    
    if ($teacher_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Enseignant non trouvé']);
        exit();
    }
    
    $teacher = $teacher_stmt->fetch();
    $teacher_id = $teacher['id'];
    
    // Insérer le cours
    $sql = "INSERT INTO courses (title, description, category, teacher_id, requires_key, enrollment_key, max_enrollments, is_public) 
            VALUES (:title, :description, :category, :teacher_id, :requires_key, :enrollment_key, :max_enrollments, :is_public)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->bindParam(':requires_key', $requires_key, PDO::PARAM_BOOL);
    $stmt->bindParam(':enrollment_key', $enrollment_key);
    $stmt->bindParam(':max_enrollments', $max_enrollments);
    $stmt->bindParam(':is_public', $is_public, PDO::PARAM_BOOL);
    $stmt->execute();
    
    $course_id = $conn->lastInsertId();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Cours créé avec succès',
        'data' => [
            'course_id' => $course_id,
            'title' => $title,
            'teacher_id' => $teacher_id
        ]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
