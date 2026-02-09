<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
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
        // Liste de tous les cours
        $sql = "SELECT c.*, u.first_name as teacher_first_name, u.last_name as teacher_last_name,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as enrollment_count
                FROM courses c
                JOIN teachers t ON c.teacher_id = t.id
                JOIN users u ON t.user_id = u.id
                ORDER BY c.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $courses = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $courses]);
    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Supprimer un cours
        $data = json_decode(file_get_contents('php://input'), true);
        $course_id = intval($data['course_id']);
        
        $sql = "DELETE FROM courses WHERE id = :course_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Cours supprimé']);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
