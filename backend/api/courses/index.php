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

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Paramètres de pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;
    
    // Paramètres de filtre
    $category = isset($_GET['category']) ? sanitize($_GET['category']) : null;
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : null;
    $teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : null;
    
    // Construction de la requête
    $where = ["c.is_public = 1"];
    $params = [];
    
    if ($category) {
        $where[] = "c.category = :category";
        $params[':category'] = $category;
    }
    
    if ($search) {
        $where[] = "(c.title LIKE :search OR c.description LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($teacher_id) {
        $where[] = "c.teacher_id = :teacher_id";
        $params[':teacher_id'] = $teacher_id;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Compter le nombre total de cours
    $count_sql = "SELECT COUNT(*) as total FROM courses c WHERE $whereClause";
    $count_stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total = $count_stmt->fetch()['total'];
    
    // Récupérer les cours
    $sql = "SELECT c.*, 
                   u.first_name as teacher_first_name, 
                   u.last_name as teacher_last_name,
                   t.specialty as teacher_specialty,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'active') as enrollment_count
            FROM courses c
            JOIN teachers t ON c.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE $whereClause
            ORDER BY c.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $courses = $stmt->fetchAll();
    
    // Formater les données
    $formatted_courses = array_map(function($course) {
        return [
            'id' => $course['id'],
            'title' => $course['title'],
            'description' => $course['description'],
            'category' => $course['category'],
            'teacher' => $course['teacher_first_name'] . ' ' . $course['teacher_last_name'],
            'specialty' => $course['teacher_specialty'],
            'requires_key' => (bool)$course['requires_key'],
            'enrollment_key' => $course['enrollment_key'],
            'max_enrollments' => $course['max_enrollments'],
            'is_public' => (bool)$course['is_public'],
            'enrollment_count' => $course['enrollment_count'],
            'created_at' => $course['created_at'],
            'updated_at' => $course['updated_at']
        ];
    }, $courses);
    
    echo json_encode([
        'status' => 'success',
        'data' => $formatted_courses,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
