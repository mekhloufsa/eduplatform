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
        // Liste des étudiants
        $sql = "SELECT s.*, u.email, u.first_name, u.last_name, u.is_active
                FROM students s
                JOIN users u ON s.user_id = u.id
                ORDER BY u.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $students = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $students]);
    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Supprimer un étudiant
        $data = json_decode(file_get_contents('php://input'), true);
        $student_id = intval($data['student_id']);
        
        $sql = "DELETE FROM users WHERE id = (SELECT user_id FROM students WHERE id = :student_id)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        
        echo json_encode(['status' => 'success', 'message' => 'Étudiant supprimé']);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
