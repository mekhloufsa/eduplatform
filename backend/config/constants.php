<?php
// constants.php - Constantes de l'application

// Rôles utilisateurs
define('ROLE_STUDENT', 'student');
define('ROLE_TEACHER', 'teacher');
define('ROLE_ADMIN', 'admin');

// Statuts
define('STATUS_ACTIVE', 'active');
define('STATUS_PENDING', 'pending');
define('STATUS_COMPLETED', 'completed');
define('STATUS_DROPPED', 'dropped');

// Types de quiz
define('QUIZ_TYPE_PRACTICE', 'practice');
define('QUIZ_TYPE_EXAM', 'exam');
define('QUIZ_TYPE_GRADED', 'graded');

// Types de questions
define('QUESTION_TYPE_MULTIPLE', 'multiple_choice');
define('QUESTION_TYPE_TRUE_FALSE', 'true_false');
define('QUESTION_TYPE_SHORT', 'short_answer');
define('QUESTION_TYPE_ESSAY', 'essay');

// Chemins
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('COURSES_UPLOAD_PATH', UPLOAD_PATH . 'courses/');
define('MATERIALS_UPLOAD_PATH', UPLOAD_PATH . 'materials/');
define('ASSIGNMENTS_UPLOAD_PATH', UPLOAD_PATH . 'assignments/');
define('PROFILE_PICS_PATH', UPLOAD_PATH . 'profile_pics/');

// Limites
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 Mo
define('MAX_UPLOAD_FILES', 5);

// Pagination
define('ITEMS_PER_PAGE', 10);

// Session
define('SESSION_LIFETIME', 3600); // 1 heure
?>
