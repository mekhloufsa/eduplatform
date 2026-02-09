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
        $topic_id = isset($_GET['topic_id']) ? intval($_GET['topic_id']) : null;
        
        if (!$topic_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID du topic requis']);
            exit();
        }
        
        $sql = "SELECT fp.*, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM forum_likes fl WHERE fl.post_id = fp.id) as like_count
                FROM forum_posts fp
                JOIN users u ON fp.user_id = u.id
                WHERE fp.topic_id = :topic_id
                ORDER BY fp.created_at ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':topic_id', $topic_id);
        $stmt->execute();
        $posts = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $posts]);
    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Non authentifié']);
            exit();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['topic_id']) || !isset($data['content'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Topic et contenu requis']);
            exit();
        }
        
        $topic_id = intval($data['topic_id']);
        $content = sanitize($data['content']);
        $parent_id = isset($data['parent_id']) ? intval($data['parent_id']) : null;
        $user_id = $_SESSION['user_id'];
        
        $sql = "INSERT INTO forum_posts (topic_id, user_id, content, parent_id) 
                VALUES (:topic_id, :user_id, :content, :parent_id)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':topic_id', $topic_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':parent_id', $parent_id);
        $stmt->execute();
        
        $post_id = $conn->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Message posté avec succès',
            'data' => ['post_id' => $post_id]
        ]);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
