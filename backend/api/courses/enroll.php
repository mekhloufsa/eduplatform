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
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Non authentifié']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['course_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID du cours requis']);
    exit();
}

$course_id = intval($data['course_id']);
$enrollment_key = isset($data['enrollment_key']) ? sanitize($data['enrollment_key']) : null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Récupérer l'ID de l'étudiant depuis le token (simplifié)
    // En production, vous devriez vérifier le token dans une table de sessions
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Session invalide']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Vérifier que l'utilisateur est un étudiant
    $student_sql = "SELECT id FROM students WHERE user_id = :user_id";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bindParam(':user_id', $user_id);
    $student_stmt->execute();
    
    if ($student_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Seuls les étudiants peuvent s\'inscrire']);
        exit();
    }
    
    $student = $student_stmt->fetch();
    $student_id = $student['id'];
    
    // Vérifier si le cours existe
    $course_sql = "SELECT * FROM courses WHERE id = :course_id";
    $course_stmt = $conn->prepare($course_sql);
    $course_stmt->bindParam(':course_id', $course_id);
    $course_stmt->execute();
    
    if ($course_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Cours non trouvé']);
        exit();
    }
    
    $course = $course_stmt->fetch();
    
    // Vérifier si une clé est requise
    if ($course['requires_key']) {
        if (!$enrollment_key) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Clé d\'inscription requise']);
            exit();
        }
        
        if ($enrollment_key !== $course['enrollment_key']) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Clé d\'inscription invalide']);
            exit();
        }
    }
    
    // Vérifier la limite d'inscriptions
    if ($course['max_enrollments'] > 0) {
        $count_sql = "SELECT COUNT(*) as count FROM enrollments WHERE course_id = :course_id AND status = 'active'";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bindParam(':course_id', $course_id);
        $count_stmt->execute();
        $count = $count_stmt->fetch()['count'];
        
        if ($count >= $course['max_enrollments']) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Ce cours a atteint sa limite d\'inscriptions']);
            exit();
        }
    }
    
    // Vérifier si déjà inscrit
    $check_sql = "SELECT * FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':student_id', $student_id);
    $check_stmt->bindParam(':course_id', $course_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Vous êtes déjà inscrit à ce cours']);
        exit();
    }
    
    // Créer l'inscription
    $enroll_sql = "INSERT INTO enrollments (student_id, course_id, enrollment_key_used, status) 
                   VALUES (:student_id, :course_id, :enrollment_key, 'active')";
    $enroll_stmt = $conn->prepare($enroll_sql);
    $enroll_stmt->bindParam(':student_id', $student_id);
    $enroll_stmt->bindParam(':course_id', $course_id);
    $enroll_stmt->bindParam(':enrollment_key', $enrollment_key);
    $enroll_stmt->execute();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Inscription réussie',
        'data' => [
            'enrollment_id' => $conn->lastInsertId(),
            'course_id' => $course_id,
            'enrollment_date' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
