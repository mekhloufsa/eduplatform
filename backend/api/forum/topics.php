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
        
        if ($course_id) {
            $sql = "SELECT ft.*, u.first_name, u.last_name,
                           (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id) as post_count
                    FROM forum_topics ft
                    JOIN users u ON ft.user_id = u.id
                    WHERE ft.course_id = :course_id
                    ORDER BY ft.is_pinned DESC, ft.created_at DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':course_id', $course_id);
        } else {
            $sql = "SELECT ft.*, u.first_name, u.last_name,
                           (SELECT COUNT(*) FROM forum_posts fp WHERE fp.topic_id = ft.id) as post_count
                    FROM forum_topics ft
                    JOIN users u ON ft.user_id = u.id
                    ORDER BY ft.is_pinned DESC, ft.created_at DESC";
            $stmt = $conn->prepare($sql);
        }
        
        $stmt->execute();
        $topics = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $topics]);
    }
    
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Non authentifié']);
            exit();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['course_id']) || !isset($data['title']) || !isset($data['content'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Cours, titre et contenu requis']);
            exit();
        }
        
        $course_id = intval($data['course_id']);
        $title = sanitize($data['title']);
        $content = sanitize($data['content']);
        $user_id = $_SESSION['user_id'];
        
        $sql = "INSERT INTO forum_topics (course_id, user_id, title, content) 
                VALUES (:course_id, :user_id, :title, :content)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        $stmt->execute();
        
        $topic_id = $conn->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Topic créé avec succès',
            'data' => ['topic_id' => $topic_id]
        ]);
    }
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
