<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if (!$token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token manquant']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Statistiques globales
    $stats = [];
    
    // Total enseignants
    $stmt = $conn->query("SELECT COUNT(*) as total FROM teachers");
    $stats['total_teachers'] = $stmt->fetch()['total'];
    
    // Total étudiants
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students");
    $stats['total_students'] = $stmt->fetch()['total'];
    
    // Total cours
    $stmt = $conn->query("SELECT COUNT(*) as total FROM courses");
    $stats['total_courses'] = $stmt->fetch()['total'];
    
    // Cours publics vs privés
    $stmt = $conn->query("SELECT 
        SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END) as public,
        SUM(CASE WHEN is_public = 0 THEN 1 ELSE 0 END) as private
        FROM courses");
    $courseVisibility = $stmt->fetch();
    $stats['public_courses'] = $courseVisibility['public'];
    $stats['private_courses'] = $courseVisibility['private'];
    
    // Total inscriptions
    $stmt = $conn->query("SELECT COUNT(*) as total FROM enrollments");
    $stats['total_enrollments'] = $stmt->fetch()['total'];
    
    // Total devoirs
    $stmt = $conn->query("SELECT COUNT(*) as total FROM assignments");
    $stats['total_assignments'] = $stmt->fetch()['total'];
    
    // Total quiz
    $stmt = $conn->query("SELECT COUNT(*) as total FROM quizzes");
    $stats['total_quizzes'] = $stmt->fetch()['total'];
    
    // Soumissions en attente de notation
    $stmt = $conn->query("SELECT COUNT(*) as total FROM assignment_submissions WHERE status = 'submitted'");
    $stats['pending_grading'] = $stmt->fetch()['total'];
    
    // Utilisateurs actifs vs inactifs
    $stmt = $conn->query("SELECT 
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
        FROM users");
    $userStatus = $stmt->fetch();
    $stats['active_users'] = $userStatus['active'];
    $stats['inactive_users'] = $userStatus['inactive'];
    
    echo json_encode([
        'status' => 'success',
        'data' => $stats
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>