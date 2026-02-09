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

// Vérifier l'authentification par email (plus fiable que la session)
if (empty($data['teacher_email'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Email enseignant requis']);
    exit();
}

// Validation des champs
if (empty($data['title']) || empty($data['description']) || empty($data['category'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Titre, description et catégorie requis']);
    exit();
}

$teacher_email = sanitize($data['teacher_email']);
$title = sanitize($data['title']);
$description = sanitize($data['description']);
$category = sanitize($data['category']);
$is_public = isset($data['is_public']) ? (bool)$data['is_public'] : true;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer l'enseignant par email
    $sql = "SELECT t.id as teacher_id, u.id as user_id 
            FROM teachers t 
            JOIN users u ON t.user_id = u.id 
            WHERE u.email = :email AND u.role = 'teacher'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $teacher_email);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Enseignant non trouvé avec cet email']);
        exit();
    }
    
    $teacher = $stmt->fetch();
    $teacher_id = $teacher['teacher_id'];
    
    // Insérer le cours
    $insert_sql = "INSERT INTO courses (title, description, category, teacher_id, is_public, created_at, updated_at) 
                   VALUES (:title, :description, :category, :teacher_id, :is_public, NOW(), NOW())";
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bindParam(':title', $title);
    $insert_stmt->bindParam(':description', $description);
    $insert_stmt->bindParam(':category', $category);
    $insert_stmt->bindParam(':teacher_id', $teacher_id);
    $insert_stmt->bindParam(':is_public', $is_public, PDO::PARAM_BOOL);
    $insert_stmt->execute();
    
    $course_id = $conn->lastInsertId();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Cours créé avec succès',
        'data' => [
            'course_id' => $course_id,
            'title' => $title
        ]
    ]);
    
} catch(PDOException $e) {
    error_log('Erreur PDO create-course: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur base de données: ' . $e->getMessage()]);
} catch(Exception $e) {
    error_log('Erreur create-course: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>