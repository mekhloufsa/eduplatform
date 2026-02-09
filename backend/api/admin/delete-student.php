<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit();
}

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token manquant']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$student_id = isset($data['student_id']) ? intval($data['student_id']) : 0;

if (!$student_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de l\'étudiant requis']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Vérifier que l'étudiant existe
    $check_sql = "SELECT s.id, s.user_id, u.first_name, u.last_name 
                  FROM students s 
                  JOIN users u ON s.user_id = u.id 
                  WHERE s.id = :student_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':student_id', $student_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Étudiant non trouvé']);
        exit();
    }
    
    $student = $check_stmt->fetch();
    $user_id = $student['user_id'];
    $student_name = $student['first_name'] . ' ' . $student['last_name'];
    
    // Supprimer les soumissions de devoirs
    $conn->prepare("DELETE FROM assignment_submissions WHERE student_id = :student_id")
         ->execute([':student_id' => $student_id]);
    
    // Supprimer les soumissions de quiz
    $conn->prepare("DELETE FROM quiz_submissions WHERE student_id = :student_id")
         ->execute([':student_id' => $student_id]);
    
    // Supprimer les ressources complétées
    $conn->prepare("DELETE FROM completed_resources WHERE student_id = :student_id")
         ->execute([':student_id' => $student_id]);
    
    // Supprimer les inscriptions
    $conn->prepare("DELETE FROM enrollments WHERE student_id = :student_id")
         ->execute([':student_id' => $student_id]);
    
    // Supprimer les likes du forum
    $conn->prepare("DELETE FROM forum_likes WHERE user_id = :user_id")
         ->execute([':user_id' => $user_id]);
    
    // Supprimer les posts du forum
    $conn->prepare("DELETE FROM forum_posts WHERE user_id = :user_id")
         ->execute([':user_id' => $user_id]);
    
    // Supprimer l'étudiant
    $conn->prepare("DELETE FROM students WHERE id = :student_id")
         ->execute([':student_id' => $student_id]);
    
    // Supprimer l'utilisateur
    $conn->prepare("DELETE FROM users WHERE id = :user_id")
         ->execute([':user_id' => $user_id]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Étudiant "' . $student_name . '" supprimé avec succès'
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>