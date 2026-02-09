<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée']);
    exit();
}

$query = isset($_GET['q']) ? sanitize($_GET['q']) : '';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : null;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $sql = "SELECT c.*, u.first_name as teacher_first_name, u.last_name as teacher_last_name,
                   t.specialty as teacher_specialty
            FROM courses c
            JOIN teachers t ON c.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE c.is_public = 1 
            AND (c.title LIKE :query OR c.description LIKE :query OR u.first_name LIKE :query OR u.last_name LIKE :query)";
    
    $params = [':query' => "%$query%"];
    
    if ($category) {
        $sql .= " AND c.category = :category";
        $params[':category'] = $category;
    }
    
    $sql .= " ORDER BY c.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $courses = $stmt->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'data' => $courses,
        'count' => count($courses)
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
