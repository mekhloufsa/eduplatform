<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
        
        if (!$course_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID du cours requis']);
            exit();
        }
        
        $sql = "SELECT * FROM course_materials 
                WHERE course_id = :course_id AND is_published = 1
                ORDER BY order_index ASC, upload_date DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        $materials = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $materials]);
    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_TEACHER) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
            exit();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['course_id']) || !isset($data['title']) || !isset($data['file_path'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Cours, titre et fichier requis']);
            exit();
        }
        
        $course_id = intval($data['course_id']);
        $title = sanitize($data['title']);
        $description = isset($data['description']) ? sanitize($data['description']) : null;
        $file_type = sanitize($data['file_type']);
        $file_path = sanitize($data['file_path']);
        $file_size = intval($data['file_size']);
        
        $sql = "INSERT INTO course_materials (course_id, title, description, file_type, file_path, file_size) 
                VALUES (:course_id, :title, :description, :file_type, :file_path, :file_size)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':file_type', $file_type);
        $stmt->bindParam(':file_path', $file_path);
        $stmt->bindParam(':file_size', $file_size);
        $stmt->execute();
        
        $material_id = $conn->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Support ajouté avec succès',
            'data' => ['material_id' => $material_id]
        ]);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
