// student.js - Version complète avec Quiz et Devoirs
console.log('🎓 student.js chargé');

const API_BASE_URL = window.location.origin + '/eduplatform/backend/api';

// Variables globales
let enrolledCourses = [];
let availableCourses = [];
let selectedCourseId = null;
let selectedCourseRequiresKey = false;
let currentCourseDetails = null;
let currentQuizData = null;
let currentAssignmentData = null;
let quizTimer = null;
let quizStartTime = null;
let selectedFile = null;

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

function isAuthenticated() {
    return !!localStorage.getItem('token');
}

function getUserRole() {
    try {
        const user = JSON.parse(localStorage.getItem('user') || '{}');
        return user.role || null;
    } catch(e) {
        return null;
    }
}

function getUserInfo() {
    try {
        return JSON.parse(localStorage.getItem('user') || '{}');
    } catch(e) {
        return null;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength).trim() + '...';
}

function formatDate(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('fr-FR');
}

function formatDateTime(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString('fr-FR');
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.style.background = type === 'success' ? '#4caf50' : '#f44336';
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.padding = '15px 20px';
    notification.style.borderRadius = '8px';
    notification.style.color = 'white';
    notification.style.zIndex = '9999';
    notification.style.boxShadow = '0 3px 15px rgba(0,0,0,0.3)';
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// ============================================
// NAVIGATION
// ============================================

function showSection(sectionName) {
    console.log('Navigation vers:', sectionName);
    
    // Cacher toutes les sections
    document.querySelectorAll('.section').forEach(function(section) {
        section.style.display = 'none';
    });
    
    // Afficher la section demandée
    const target = document.getElementById(sectionName + '-section');
    if (target) {
        target.style.display = 'block';
    }
    
    // Mettre à jour nav active
    document.querySelectorAll('.nav-links a').forEach(function(link) {
        link.classList.remove('active');
    });
    
    const navLink = document.getElementById('nav-' + sectionName);
    if (navLink) {
        navLink.classList.add('active');
    }
    
    // Charger données si besoin
    if (sectionName === 'enroll') {
        loadAvailableCourses();
    } else if (sectionName === 'my-courses') {
        loadEnrolledCourses();
    }
}

function switchTab(tabName) {
    // Désactiver tous les boutons
    document.querySelectorAll('.tab-button').forEach(function(btn) {
        btn.classList.remove('active');
    });
    
    // Cacher tous les contenus
    document.querySelectorAll('.tab-content').forEach(function(content) {
        content.classList.remove('active');
    });
    
    // Activer le tab sélectionné
    const tabButton = document.querySelector('.tab-button[onclick*="' + tabName + '"]');
    if (tabButton) tabButton.classList.add('active');
    
    const tabContent = document.getElementById('tab-' + tabName);
    if (tabContent) tabContent.classList.add('active');
    
    // Charger les données si nécessaire
    if (tabName === 'quizzes' && currentCourseDetails) {
        loadQuizzes(currentCourseDetails.id);
    } else if (tabName === 'assignments' && currentCourseDetails) {
        loadAssignments(currentCourseDetails.id);
    }
}

// ============================================
// CHARGEMENT COURS
// ============================================

async function loadEnrolledCourses() {
    const container = document.getElementById('enrolled-courses-list');
    if (!container) return;
    
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i><p>Chargement...</p></div>';
    
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/dashboard/student.php', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        const result = await response.json();
        console.log('Cours inscrits:', result);
        
        if (result.status === 'success') {
            enrolledCourses = result.data.courses || [];
            renderEnrolledCourses();
        } else {
            container.innerHTML = '<div class="no-courses"><i class="fas fa-exclamation-circle"></i><h3>Erreur</h3><p>' + (result.message || 'Chargement échoué') + '</p></div>';
        }
    } catch (error) {
        console.error('Erreur chargement cours:', error);
        container.innerHTML = '<div class="no-courses"><i class="fas fa-exclamation-circle"></i><h3>Erreur de connexion</h3><p>Impossible de charger vos cours</p></div>';
    }
}

async function loadAvailableCourses() {
    const container = document.getElementById('available-courses-list');
    if (!container) return;
    
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i><p>Chargement...</p></div>';
    
    try {
        const response = await fetch(API_BASE_URL + '/courses/index.php');
        const result = await response.json();
        
        if (result.status === 'success') {
            const enrolledIds = enrolledCourses.map(function(c) { return parseInt(c.id); });
            availableCourses = result.data.filter(function(c) {
                return !enrolledIds.includes(parseInt(c.id));
            });
            renderAvailableCourses();
        } else {
            container.innerHTML = '<div class="no-courses"><i class="fas fa-exclamation-circle"></i><h3>Erreur</h3><p>Impossible de charger les cours</p></div>';
        }
    } catch (error) {
        console.error('Erreur chargement cours disponibles:', error);
        container.innerHTML = '<div class="no-courses"><i class="fas fa-exclamation-circle"></i><h3>Erreur de connexion</h3><p>Impossible de charger les cours disponibles</p></div>';
    }
}

async function loadCourseDetails(courseId) {
    console.log('Chargement détails cours:', courseId);
    
    try {
        const token = localStorage.getItem('token');
        
        // Charger les détails du cours
        const courseResponse = await fetch(API_BASE_URL + '/courses/index.php?id=' + courseId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const courseResult = await courseResponse.json();
        
        // Charger les ressources du cours
        const materialsResponse = await fetch(API_BASE_URL + '/courses/materials.php?course_id=' + courseId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const materialsResult = await materialsResponse.json();
        
        if (courseResult.status === 'success') {
            currentCourseDetails = {
                ...courseResult.data,
                materials: materialsResult.status === 'success' ? materialsResult.data : []
            };
            renderCourseDetails();
        } else {
            showNotification('Erreur lors du chargement du cours', 'error');
        }
    } catch (error) {
        console.error('Erreur chargement détails:', error);
        showNotification('Erreur de connexion', 'error');
    }
}

// ============================================
// CHARGEMENT QUIZ ET DEVOIRS
// ============================================

async function loadQuizzes(courseId) {
    const container = document.getElementById('quizzes-list');
    if (!container) return;
    
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><p>Chargement des quiz...</p></div>';
    
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/quizzes/create.php?course_id=' + courseId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        const result = await response.json();
        
        if (result.status === 'success' && result.data && result.data.length > 0) {
            renderQuizzes(result.data);
        } else {
            container.innerHTML = '<div class="no-courses"><i class="fas fa-question-circle"></i><p>Aucun quiz disponible pour le moment</p></div>';
        }
    } catch (error) {
        console.error('Erreur chargement quiz:', error);
        container.innerHTML = '<div class="no-courses"><i class="fas fa-exclamation-circle"></i><p>Erreur de chargement des quiz</p></div>';
    }
}

async function loadAssignments(courseId) {
    const container = document.getElementById('assignments-list');
    if (!container) return;
    
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><p>Chargement des devoirs...</p></div>';
    
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/assignments/create.php?course_id=' + courseId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        const result = await response.json();
        
        if (result.status === 'success' && result.data && result.data.length > 0) {
            renderAssignments(result.data);
        } else {
            container.innerHTML = '<div class="no-courses"><i class="fas fa-tasks"></i><p>Aucun devoir disponible pour le moment</p></div>';
        }
    } catch (error) {
        console.error('Erreur chargement devoirs:', error);
        container.innerHTML = '<div class="no-courses"><i class="fas fa-exclamation-circle"></i><p>Erreur de chargement des devoirs</p></div>';
    }
}

// ============================================
// AFFICHAGE
// ============================================

function renderEnrolledCourses() {
    const container = document.getElementById('enrolled-courses-list');
    if (!container) return;
    
    if (enrolledCourses.length === 0) {
        container.innerHTML = '<div class="no-courses"><i class="fas fa-book-open"></i><h3>Aucun cours inscrit</h3><p>Cliquez sur "S\'inscrire à un Cours" pour commencer</p></div>';
        return;
    }
    
    container.innerHTML = enrolledCourses.map(function(course) {
        const teacherName = course.teacher_first_name && course.teacher_last_name 
            ? course.teacher_first_name + ' ' + course.teacher_last_name 
            : course.teacher || 'Enseignant';
        
        return '<div class="course-item" onclick="viewCourseDetails(' + course.id + ')">' +
            '<h3>' + escapeHtml(course.title) + '</h3>' +
            '<p class="course-teacher"><i class="fas fa-user-tie"></i> ' + escapeHtml(teacherName) + '</p>' +
            (course.description ? '<p class="course-description">' + escapeHtml(truncateText(course.description, 150)) + '</p>' : '') +
            '<div class="course-meta">' +
                '<span><i class="fas fa-layer-group"></i> ' + (course.category || 'Général') + '</span>' +
                '<span><i class="fas fa-calendar"></i> ' + formatDate(course.created_at) + '</span>' +
            '</div>' +
            '</div>';
    }).join('');
}

function renderAvailableCourses() {
    const container = document.getElementById('available-courses-list');
    if (!container) return;
    
    if (availableCourses.length === 0) {
        container.innerHTML = '<div class="no-courses"><i class="fas fa-graduation-cap"></i><h3>Aucun cours disponible</h3><p>Tous les cours sont déjà dans votre liste</p></div>';
        return;
    }
    
    container.innerHTML = '<div class="available-courses-grid">' + availableCourses.map(function(course) {
        const requiresKey = course.requires_key === 1 || course.requires_key === true || course.requires_key === '1';
        const teacherName = course.teacher_first_name && course.teacher_last_name 
            ? course.teacher_first_name + ' ' + course.teacher_last_name 
            : course.teacher || 'Enseignant';
        
        let icon = 'fa-book';
        if (course.category === 'Informatique') icon = 'fa-laptop-code';
        else if (course.category === 'Mathématiques') icon = 'fa-calculator';
        else if (course.category === 'Sciences') icon = 'fa-flask';
        else if (course.category === 'Langues') icon = 'fa-language';
        
        return '<div class="course-card">' +
            '<div class="course-image"><i class="fas ' + icon + '"></i></div>' +
            '<div class="course-content">' +
                '<div class="course-title">' + escapeHtml(course.title) + '</div>' +
                '<div class="course-teacher"><i class="fas fa-user-tie"></i> ' + escapeHtml(teacherName) + '</div>' +
                (course.category ? '<span class="course-category">' + escapeHtml(course.category) + '</span>' : '') +
                (course.description ? '<div class="course-description">' + escapeHtml(truncateText(course.description, 100)) + '</div>' : '') +
                '<div class="course-footer">' +
                    '<span class="enrollment-badge ' + (requiresKey ? 'badge-key' : 'badge-free') + '">' +
                        '<i class="fas fa-' + (requiresKey ? 'key' : 'unlock') + '"></i> ' +
                        (requiresKey ? 'Clé requise' : 'Accès libre') +
                    '</span>' +
                    '<button class="btn-enroll" onclick="openEnrollmentModal(' + course.id + ', \'' + escapeHtml(course.title).replace(/'/g, "\\'") + '\', \'' + escapeHtml(teacherName).replace(/'/g, "\\'") + '\', ' + requiresKey + ')">' +
                        '<i class="fas fa-user-plus"></i> S\'inscrire' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '</div>';
    }).join('') + '</div>';
}

function renderCourseDetails() {
    if (!currentCourseDetails) return;
    
    const course = currentCourseDetails;
    const teacherName = course.teacher_first_name && course.teacher_last_name 
        ? course.teacher_first_name + ' ' + course.teacher_last_name 
        : course.teacher || 'Enseignant';
    
    // Titre et meta
    document.getElementById('course-detail-title').textContent = course.title;
    document.getElementById('course-detail-meta').innerHTML = 
        '<div style="display: flex; gap: 20px; margin: 15px 0; color: #666; flex-wrap: wrap;">' +
            '<span><i class="fas fa-user-tie"></i> ' + escapeHtml(teacherName) + '</span>' +
            '<span><i class="fas fa-layer-group"></i> ' + (course.category || 'Général') + '</span>' +
            '<span><i class="fas fa-calendar"></i> ' + formatDate(course.created_at) + '</span>' +
        '</div>' +
        (course.description ? '<p style="color: #555; line-height: 1.6; margin-top: 15px;">' + escapeHtml(course.description) + '</p>' : '');
    
    // Ressources
    renderCourseResources(course.materials || []);
}

function renderCourseResources(materials) {
    const container = document.getElementById('resources-list');
    if (!container) return;
    
    if (!materials || materials.length === 0) {
        container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;">Aucune ressource disponible pour le moment</p>';
        return;
    }
    
    container.innerHTML = '<div class="resource-list">' + materials.map(function(material) {
        let icon = 'fa-file';
        let typeLabel = 'Document';
        
        if (material.type === 'video' || material.file_type === 'video') {
            icon = 'fa-video';
            typeLabel = 'Vidéo';
        } else if (material.type === 'pdf' || material.file_type === 'pdf') {
            icon = 'fa-file-pdf';
            typeLabel = 'PDF';
        } else if (material.type === 'document' || material.file_type === 'document') {
            icon = 'fa-file-alt';
            typeLabel = 'Document';
        } else if (material.type === 'link') {
            icon = 'fa-link';
            typeLabel = 'Lien';
        }
        
        return '<div class="resource-item" onclick="viewResource(' + material.id + ')">' +
            '<div class="resource-icon"><i class="fas ' + icon + '"></i></div>' +
            '<h4>' + escapeHtml(material.title) + '</h4>' +
            '<small>' + formatDate(material.upload_date || material.created_at) + '</small>' +
            '<span class="resource-type-badge" style="display: inline-block; background: #e8f0fe; color: #1967d2; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; margin-top: 5px;">' + typeLabel + '</span>' +
            '</div>';
    }).join('') + '</div>';
}

function renderQuizzes(quizzes) {
    const container = document.getElementById('quizzes-list');
    if (!container) return;
    
    container.innerHTML = '<div class="quiz-list">' + quizzes.map(function(quiz) {
        const isPassed = quiz.is_published === 1 || quiz.is_published === true;
        const hasSubmission = quiz.submission_status === 'submitted' || quiz.submission_status === 'graded';
        
        let statusBadge = '';
        let actionButton = '';
        
        if (hasSubmission) {
            statusBadge = '<span class="status-badge status-completed"><i class="fas fa-check"></i> Complété</span>';
            if (quiz.score) {
                statusBadge += ' <span style="margin-left: 10px; font-weight: bold; color: #5f6cff;">Score: ' + quiz.score + '%</span>';
            }
        } else if (isPassed) {
            statusBadge = '<span class="status-badge status-pending"><i class="fas fa-clock"></i> En attente</span>';
            actionButton = '<button class="btn-start-quiz" onclick="startQuiz(' + quiz.id + ')"><i class="fas fa-play"></i> Démarrer</button>';
        }
        
        return '<div class="quiz-item">' +
            '<div class="quiz-info">' +
                '<div class="quiz-title">' + escapeHtml(quiz.title) + '</div>' +
                '<div class="quiz-meta">' +
                    (quiz.quiz_type ? '<span><i class="fas fa-tag"></i> ' + quiz.quiz_type + '</span>' : '') +
                    (quiz.time_limit ? '<span><i class="fas fa-clock"></i> ' + quiz.time_limit + ' min</span>' : '') +
                    (quiz.passing_score ? '<span><i class="fas fa-trophy"></i> Score requis: ' + quiz.passing_score + '%</span>' : '') +
                '</div>' +
                statusBadge +
            '</div>' +
            (actionButton ? '<div>' + actionButton + '</div>' : '') +
            '</div>';
    }).join('') + '</div>';
}

function renderAssignments(assignments) {
    const container = document.getElementById('assignments-list');
    if (!container) return;
    
    const now = new Date();
    
    container.innerHTML = '<div class="assignment-list">' + assignments.map(function(assignment) {
        const dueDate = new Date(assignment.due_date);
        const isOverdue = dueDate < now;
        const hasSubmission = assignment.submission_status === 'submitted' || assignment.submission_status === 'graded';
        
        let statusBadge = '';
        let actionButton = '';
        
        if (hasSubmission) {
            statusBadge = '<span class="status-badge status-completed"><i class="fas fa-check"></i> Soumis</span>';
            if (assignment.grade) {
                statusBadge += ' <span style="margin-left: 10px; font-weight: bold; color: #5f6cff;">Note: ' + assignment.grade + '/' + assignment.max_points + '</span>';
            }
        } else if (isOverdue) {
            statusBadge = '<span class="status-badge status-overdue"><i class="fas fa-exclamation-triangle"></i> En retard</span>';
            if (assignment.allow_late_submission) {
                actionButton = '<button class="btn-submit-assignment" onclick="openAssignmentModal(' + assignment.id + ')"><i class="fas fa-upload"></i> Soumettre</button>';
            }
        } else {
            statusBadge = '<span class="status-badge status-pending"><i class="fas fa-clock"></i> À faire</span>';
            actionButton = '<button class="btn-submit-assignment" onclick="openAssignmentModal(' + assignment.id + ')"><i class="fas fa-upload"></i> Soumettre</button>';
        }
        
        return '<div class="assignment-item">' +
            '<div class="assignment-info">' +
                '<div class="assignment-title">' + escapeHtml(assignment.title) + '</div>' +
                '<div class="assignment-meta">' +
                    '<span><i class="fas fa-calendar-alt"></i> Échéance: ' + formatDateTime(assignment.due_date) + '</span>' +
                    '<span><i class="fas fa-star"></i> Points: ' + assignment.max_points + '</span>' +
                '</div>' +
                statusBadge +
            '</div>' +
            (actionButton ? '<div>' + actionButton + '</div>' : '') +
            '</div>';
    }).join('') + '</div>';
}

// ============================================
// MODAL INSCRIPTION
// ============================================

function openEnrollmentModal(courseId, title, teacher, requiresKey) {
    selectedCourseId = courseId;
    selectedCourseRequiresKey = requiresKey;
    
    const modalTitle = document.getElementById('modal-course-title');
    const modalTeacher = document.getElementById('modal-course-teacher');
    const keySection = document.getElementById('key-required-section');
    const freeSection = document.getElementById('free-enrollment-section');
    const modal = document.getElementById('enrollment-modal');
    const keyError = document.getElementById('key-error');
    const keyInput = document.getElementById('input-enrollment-key');
    
    if (modalTitle) modalTitle.textContent = title;
    if (modalTeacher) modalTeacher.textContent = 'Par ' + teacher;
    if (keySection) keySection.style.display = requiresKey ? 'block' : 'none';
    if (freeSection) freeSection.style.display = requiresKey ? 'none' : 'block';
    if (keyError) keyError.style.display = 'none';
    if (keyInput) keyInput.value = '';
    if (modal) modal.classList.add('active');
}

function closeEnrollmentModal() {
    const modal = document.getElementById('enrollment-modal');
    if (modal) modal.classList.remove('active');
    selectedCourseId = null;
    selectedCourseRequiresKey = false;
}

async function confirmEnrollment() {
    if (!selectedCourseId) return;
    
    const token = localStorage.getItem('token');
    const btnConfirm = document.getElementById('btn-confirm-enrollment');
    let enrollmentKey = null;
    
    if (selectedCourseRequiresKey) {
        const input = document.getElementById('input-enrollment-key');
        enrollmentKey = input ? input.value.trim() : '';
        if (!enrollmentKey) {
            const error = document.getElementById('key-error');
            if (error) {
                error.textContent = 'Veuillez entrer la clé d\'inscription';
                error.style.display = 'block';
            }
            return;
        }
    }
    
    if (btnConfirm) {
        btnConfirm.disabled = true;
        btnConfirm.textContent = 'Inscription en cours...';
    }
    
    try {
        const response = await fetch(API_BASE_URL + '/courses/enroll.php', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                course_id: selectedCourseId,
                enrollment_key: enrollmentKey
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification('Inscription réussie !', 'success');
            closeEnrollmentModal();
            await loadEnrolledCourses();
            showSection('my-courses');
        } else {
            const error = document.getElementById('key-error');
            if (error && selectedCourseRequiresKey) {
                error.textContent = result.message || 'Clé d\'inscription invalide';
                error.style.display = 'block';
            } else {
                showNotification(result.message || 'Erreur lors de l\'inscription', 'error');
            }
        }
    } catch (error) {
        console.error('Erreur inscription:', error);
        showNotification('Erreur de connexion au serveur', 'error');
    } finally {
        if (btnConfirm) {
            btnConfirm.disabled = false;
            btnConfirm.textContent = 'S\'inscrire';
        }
    }
}

// ============================================
// QUIZ FONCTIONS
// ============================================

async function startQuiz(quizId) {
    console.log('Démarrer quiz:', quizId);
    
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/quizzes/questions.php?quiz_id=' + quizId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            currentQuizData = {
                quizId: quizId,
                quiz: result.quiz,
                questions: result.data
            };
            displayQuiz();
        } else {
            showNotification('Erreur lors du chargement du quiz', 'error');
        }
    } catch (error) {
        console.error('Erreur chargement quiz:', error);
        showNotification('Erreur de connexion', 'error');
    }
}

function displayQuiz() {
    if (!currentQuizData) return;
    
    const modal = document.getElementById('quiz-modal');
    const modalTitle = document.getElementById('quiz-modal-title');
    const quizContent = document.getElementById('quiz-content');
    const submitBtn = document.getElementById('btn-submit-quiz');
    
    modalTitle.textContent = currentQuizData.quiz.title;
    
    let html = '';
    
    if (currentQuizData.quiz.description) {
        html += '<div style="background: #f8f9ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">' +
            '<p>' + escapeHtml(currentQuizData.quiz.description) + '</p>' +
            '</div>';
    }
    
    html += '<div style="margin-bottom: 20px; padding: 12px; background: #e8f0fe; border-radius: 6px;">' +
        '<strong>Instructions:</strong><br>' +
        'Questions: ' + currentQuizData.questions.length + '<br>' +
        (currentQuizData.quiz.time_limit ? 'Durée: ' + currentQuizData.quiz.time_limit + ' minutes<br>' : '') +
        'Score requis: ' + (currentQuizData.quiz.passing_score || 70) + '%' +
        '</div>';
    
    currentQuizData.questions.forEach(function(question, index) {
        html += '<div class="quiz-question">' +
            '<div class="question-text"><strong>Question ' + (index + 1) + ':</strong> ' + escapeHtml(question.question) + '</div>' +
            '<div class="question-options">';
        
        if (question.question_type === 'multiple_choice' && question.options) {
            question.options.forEach(function(option, optIndex) {
                html += '<label class="option-item">' +
                    '<input type="radio" name="question_' + question.id + '" value="' + option.id + '">' +
                    '<span>' + escapeHtml(option.option_text) + '</span>' +
                    '</label>';
            });
        } else if (question.question_type === 'true_false') {
            html += '<label class="option-item">' +
                '<input type="radio" name="question_' + question.id + '" value="true">' +
                '<span>Vrai</span>' +
                '</label>' +
                '<label class="option-item">' +
                '<input type="radio" name="question_' + question.id + '" value="false">' +
                '<span>Faux</span>' +
                '</label>';
        } else {
            html += '<textarea class="answer-text" id="answer_' + question.id + '" rows="4" placeholder="Votre réponse..."></textarea>';
        }
        
        html += '</div></div>';
    });
    
    quizContent.innerHTML = html;
    submitBtn.style.display = 'block';
    modal.classList.add('active');
    
    // Démarrer le timer si nécessaire
    if (currentQuizData.quiz.time_limit) {
        startQuizTimer(currentQuizData.quiz.time_limit * 60);
    }
}

function startQuizTimer(seconds) {
    quizStartTime = Date.now();
    const timerElement = document.getElementById('quiz-timer');
    const displayElement = document.getElementById('timer-display');
    
    if (!timerElement || !displayElement) return;
    
    timerElement.style.display = 'block';
    
    quizTimer = setInterval(function() {
        const elapsed = Math.floor((Date.now() - quizStartTime) / 1000);
        const remaining = seconds - elapsed;
        
        if (remaining <= 0) {
            clearInterval(quizTimer);
            displayElement.textContent = '00:00';
            timerElement.classList.add('warning');
            showNotification('Temps écoulé !', 'error');
            submitQuiz();
        } else {
            const minutes = Math.floor(remaining / 60);
            const secs = remaining % 60;
            displayElement.textContent = String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
            
            if (remaining < 60) {
                timerElement.classList.add('warning');
            }
        }
    }, 1000);
}

async function submitQuiz() {
    if (!currentQuizData) return;
    
    // Collecter les réponses
    const answers = {};
    currentQuizData.questions.forEach(function(question) {
        if (question.question_type === 'multiple_choice' || question.question_type === 'true_false') {
            const selected = document.querySelector('input[name="question_' + question.id + '"]:checked');
            if (selected) {
                answers[question.id] = selected.value;
            }
        } else {
            const textarea = document.getElementById('answer_' + question.id);
            if (textarea) {
                answers[question.id] = textarea.value;
            }
        }
    });
    
    // Arrêter le timer
    if (quizTimer) {
        clearInterval(quizTimer);
        document.getElementById('quiz-timer').style.display = 'none';
    }
    
    // Envoyer au serveur
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/quizzes/submit.php', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                quiz_id: currentQuizData.quizId,
                answers: answers,
                time_taken: quizStartTime ? Math.floor((Date.now() - quizStartTime) / 1000) : 0
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification('Quiz soumis avec succès !', 'success');
            closeQuizModal();
            loadQuizzes(currentCourseDetails.id);
        } else {
            showNotification('Erreur: ' + (result.message || 'Échec de soumission'), 'error');
        }
    } catch (error) {
        console.error('Erreur soumission quiz:', error);
        showNotification('Erreur de connexion', 'error');
    }
}

function closeQuizModal() {
    const modal = document.getElementById('quiz-modal');
    if (modal) modal.classList.remove('active');
    
    if (quizTimer) {
        clearInterval(quizTimer);
        document.getElementById('quiz-timer').style.display = 'none';
    }
    
    currentQuizData = null;
    quizStartTime = null;
}

// ============================================
// DEVOIRS FONCTIONS
// ============================================

async function openAssignmentModal(assignmentId) {
    console.log('Ouvrir modal devoir:', assignmentId);
    
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/assignments/create.php?id=' + assignmentId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            currentAssignmentData = result.data;
            displayAssignmentModal();
        } else {
            showNotification('Erreur lors du chargement du devoir', 'error');
        }
    } catch (error) {
        console.error('Erreur chargement devoir:', error);
        showNotification('Erreur de connexion', 'error');
    }
}

function displayAssignmentModal() {
    if (!currentAssignmentData) return;
    
    const modal = document.getElementById('assignment-modal');
    const modalTitle = document.getElementById('assignment-modal-title');
    const descriptionDiv = document.getElementById('assignment-description');
    
    modalTitle.textContent = currentAssignmentData.title;
    
    let descHtml = '<h4 style="color: #5f6cff; margin-bottom: 10px;">Description du devoir</h4>' +
        '<p>' + escapeHtml(currentAssignmentData.description) + '</p>' +
        '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0; display: flex; gap: 20px; font-size: 0.9rem; color: #666;">' +
        '<span><i class="fas fa-calendar-alt"></i> Échéance: ' + formatDateTime(currentAssignmentData.due_date) + '</span>' +
        '<span><i class="fas fa-star"></i> Points: ' + currentAssignmentData.max_points + '</span>' +
        '</div>';
    
    descriptionDiv.innerHTML = descHtml;
    
    // Réinitialiser le formulaire
    document.getElementById('assignment-text').value = '';
    document.getElementById('assignment-file').value = '';
    document.getElementById('selected-file').style.display = 'none';
    selectedFile = null;
    
    modal.classList.add('active');
}

function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        selectedFile = input.files[0];
        const fileNameSpan = document.getElementById('file-name');
        const selectedFileDiv = document.getElementById('selected-file');
        
        if (fileNameSpan) {
            fileNameSpan.textContent = selectedFile.name + ' (' + Math.round(selectedFile.size / 1024) + ' KB)';
        }
        if (selectedFileDiv) {
            selectedFileDiv.style.display = 'flex';
        }
    }
}

function removeFile() {
    selectedFile = null;
    document.getElementById('assignment-file').value = '';
    document.getElementById('selected-file').style.display = 'none';
}

async function submitAssignment() {
    if (!currentAssignmentData) return;
    
    const text = document.getElementById('assignment-text').value.trim();
    
    if (!text && !selectedFile) {
        showNotification('Veuillez fournir une réponse ou un fichier', 'error');
        return;
    }
    
    const btnSubmit = document.getElementById('btn-submit-assignment-btn');
    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Envoi en cours...';
    }
    
    try {
        const token = localStorage.getItem('token');
        const formData = new FormData();
        formData.append('assignment_id', currentAssignmentData.id);
        formData.append('submission_text', text);
        
        if (selectedFile) {
            formData.append('file', selectedFile);
        }
        
        const response = await fetch(API_BASE_URL + '/assignments/submit.php', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            body: formData
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification('Devoir soumis avec succès !', 'success');
            closeAssignmentModal();
            loadAssignments(currentCourseDetails.id);
        } else {
            showNotification('Erreur: ' + (result.message || 'Échec de soumission'), 'error');
        }
    } catch (error) {
        console.error('Erreur soumission devoir:', error);
        showNotification('Erreur de connexion', 'error');
    } finally {
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Soumettre le Devoir';
        }
    }
}

function closeAssignmentModal() {
    const modal = document.getElementById('assignment-modal');
    if (modal) modal.classList.remove('active');
    currentAssignmentData = null;
    selectedFile = null;
}

// ============================================
// VISUALISATION COURS
// ============================================

function viewCourseDetails(courseId) {
    console.log('Affichage cours:', courseId);
    loadCourseDetails(courseId);
    showSection('course-details');
}

function viewResource(resourceId) {
    console.log('Affichage ressource:', resourceId);
    if (!currentCourseDetails || !currentCourseDetails.materials) return;
    
    const resource = currentCourseDetails.materials.find(function(m) { return parseInt(m.id) === parseInt(resourceId); });
    if (!resource) return;
    
    if (resource.file_path) {
        window.open(resource.file_path, '_blank');
    } else if (resource.url) {
        window.open(resource.url, '_blank');
    } else {
        showNotification('Ressource non disponible', 'error');
    }
}

// ============================================
// DÉMARRAGE
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initialisation student.js...');
    
    if (!isAuthenticated()) {
        console.log('Non authentifié, redirection...');
        window.location.href = 'login.html';
        return;
    }
    
    const role = getUserRole();
    console.log('Rôle utilisateur:', role);
    
    if (role !== 'student') {
        console.log('Rôle incorrect, redirection...');
        window.location.href = 'index.html';
        return;
    }
    
    // Charger les cours inscrits au démarrage
    loadEnrolledCourses();
});