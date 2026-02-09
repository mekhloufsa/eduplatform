// student.js - Version complètement fonctionnelle et corrigée
console.log('🎓 student.js chargé');

const API_BASE_URL = window.location.origin + '/eduplatform/backend/api';

// Variables globales
let enrolledCourses = [];
let availableCourses = [];
let selectedCourseId = null;
let selectedCourseRequiresKey = false;
let currentCourseDetails = null;
let currentQuizId = null;
let currentAssignmentId = null;

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
    // Supprimer les notifications existantes
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();
    
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInRight 0.3s ease-out;
        background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#ff9800'};
    `;
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
    // Mettre à jour les boutons
    document.querySelectorAll('.tab-button').forEach(function(btn) {
        btn.classList.remove('active');
    });
    
    // Trouver le bouton correspondant et l'activer
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(function(btn) {
        if (btn.getAttribute('onclick').includes(tabName)) {
            btn.classList.add('active');
        }
    });
    
    // Cacher tous les contenus
    document.querySelectorAll('.tab-content').forEach(function(content) {
        content.classList.remove('active');
    });
    
    // Afficher le contenu demandé
    const target = document.getElementById('tab-' + tabName);
    if (target) {
        target.classList.add('active');
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
        console.log('Cours disponibles:', result);
        
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

// ============================================
// CHARGEMENT QUIZ ET DEVOIRS - CORRIGÉ
// ============================================

async function loadCourseQuizzes(courseId) {
    const container = document.getElementById('quizzes-list');
    if (!container) return;
    
    container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Chargement des quiz...</p>';
    
    try {
        const token = localStorage.getItem('token');
        // IMPORTANT: Utiliser le bon endpoint
        const response = await fetch(API_BASE_URL + '/quizzes/index.php?course_id=' + courseId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        const result = await response.json();
        console.log('Quiz du cours:', result);
        
        if (result.status === 'success') {
            if (result.data && result.data.length > 0) {
                renderQuizzes(result.data);
            } else {
                container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;"><i class="fas fa-info-circle"></i> Aucun quiz disponible pour ce cours</p>';
            }
        } else {
            container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;">' + (result.message || 'Aucun quiz disponible') + '</p>';
        }
    } catch (error) {
        console.error('Erreur chargement quiz:', error);
        container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;">Erreur lors du chargement des quiz</p>';
    }
}

async function loadCourseAssignments(courseId) {
    const container = document.getElementById('assignments-list');
    if (!container) return;
    
    container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Chargement des devoirs...</p>';
    
    try {
        const token = localStorage.getItem('token');
        // Utiliser le nouvel endpoint assignments/index.php
        const response = await fetch(API_BASE_URL + '/assignments/index.php?course_id=' + courseId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        const result = await response.json();
        console.log('Devoirs du cours:', result);
        
        if (result.status === 'success') {
            if (result.data && result.data.length > 0) {
                renderAssignments(result.data);
            } else {
                container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;"><i class="fas fa-info-circle"></i> Aucun devoir disponible pour ce cours</p>';
            }
        } else {
            container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;">' + (result.message || 'Aucun devoir disponible') + '</p>';
        }
    } catch (error) {
        console.error('Erreur chargement devoirs:', error);
        container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;">Erreur lors du chargement des devoirs</p>';
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

function renderQuizzes(quizzes) {
    const container = document.getElementById('quizzes-list');
    if (!container) return;
    
    console.log('Rendu des quiz:', quizzes); // Debug
    
    container.innerHTML = '<div class="quiz-list">' + quizzes.map(function(quiz) {
        const timeLimit = quiz.time_limit > 0 ? quiz.time_limit + ' min' : 'Illimité';
        const questionsCount = quiz.questions_count || 0;
        
        return '<div class="quiz-item">' +
            '<div class="quiz-info">' +
                '<div class="quiz-title">' + escapeHtml(quiz.title) + '</div>' +
                '<div class="quiz-meta">' +
                    '<span><i class="fas fa-question-circle"></i> ' + questionsCount + ' question(s)</span>' +
                    '<span><i class="fas fa-clock"></i> ' + timeLimit + '</span>' +
                    (quiz.passing_score ? '<span><i class="fas fa-check-circle"></i> ' + quiz.passing_score + '% pour réussir</span>' : '') +
                '</div>' +
                (quiz.description ? '<p style="margin-top: 8px; color: #666; font-size: 0.9rem;">' + escapeHtml(truncateText(quiz.description, 100)) + '</p>' : '') +
            '</div>' +
            '<button class="btn-start-quiz" onclick="startQuiz(' + quiz.id + ')">' +
                '<i class="fas fa-play"></i> Commencer' +
            '</button>' +
        '</div>';
    }).join('') + '</div>';
}

function renderAssignments(assignments) {
    const container = document.getElementById('assignments-list');
    if (!container) return;
    
    container.innerHTML = '<div class="assignment-list">' + assignments.map(function(assignment) {
        const dueDate = new Date(assignment.due_date);
        const now = new Date();
        const isLate = now > dueDate;
        let statusClass = 'status-pending';
        let statusText = 'À faire';
        
        if (assignment.submission_status === 'submitted' || assignment.submission_status === 'graded') {
            statusClass = 'status-completed';
            statusText = assignment.grade ? 'Noté: ' + assignment.grade + '/' + assignment.max_points : 'Soumis';
        } else if (isLate) {
            statusClass = 'status-overdue';
            statusText = 'En retard';
        }
        
        return '<div class="assignment-item">' +
            '<div class="assignment-info">' +
                '<div class="assignment-title">' + escapeHtml(assignment.title) + '</div>' +
                '<div class="assignment-meta">' +
                    '<span><i class="fas fa-calendar-alt"></i> À rendre: ' + formatDateTime(assignment.due_date) + '</span>' +
                    '<span><i class="fas fa-star"></i> ' + assignment.max_points + ' points</span>' +
                    '<span class="status-badge ' + statusClass + '">' + statusText + '</span>' +
                '</div>' +
                (assignment.description ? '<p style="margin-top: 8px; color: #666; font-size: 0.9rem;">' + escapeHtml(truncateText(assignment.description, 100)) + '</p>' : '') +
            '</div>' +
            (assignment.submission_status !== 'submitted' && assignment.submission_status !== 'graded' ? 
                '<button class="btn-submit-assignment" onclick="openAssignmentModal(' + assignment.id + ')">' +
                    '<i class="fas fa-upload"></i> Rendre' +
                '</button>' :
                '<button class="btn-submit-assignment" style="background: #28a745;" disabled>' +
                    '<i class="fas fa-check"></i> Soumis' +
                '</button>'
            ) +
        '</div>';
    }).join('') + '</div>';
}

function renderCourseDetails() {
    if (!currentCourseDetails) return;
    
    const course = currentCourseDetails;
    const teacherName = course.teacher_first_name && course.teacher_last_name 
        ? course.teacher_first_name + ' ' + course.teacher_last_name 
        : course.teacher || 'Enseignant';
    
    document.getElementById('course-detail-title').textContent = course.title;
    document.getElementById('course-detail-meta').innerHTML = 
        '<div style="display: flex; gap: 20px; margin: 15px 0; color: #666; flex-wrap: wrap;">' +
            '<span><i class="fas fa-layer-group"></i> ' + (course.category || 'Général') + '</span>' +
            '<span><i class="fas fa-user-tie"></i> ' + escapeHtml(teacherName) + '</span>' +
            '<span><i class="fas fa-calendar"></i> Créé le ' + formatDate(course.created_at) + '</span>' +
        '</div>';
    
    // Ressources
    renderCourseResources(course.materials || []);
}

function renderCourseResources(materials) {
    const container = document.getElementById('resources-list');
    if (!container) return;
    
    if (!materials || materials.length === 0) {
        container.innerHTML = '<p style="color: #888; text-align: center; padding: 20px;"><i class="fas fa-info-circle"></i> Aucune ressource disponible pour le moment</p>';
        return;
    }
    
    container.innerHTML = '<div class="resource-list">' + materials.map(function(material) {
        let icon = 'fa-file';
        let typeLabel = 'Document';
        
        if (material.file_type === 'video' || material.type === 'video') {
            icon = 'fa-video';
            typeLabel = 'Vidéo';
        } else if (material.file_type === 'pdf' || material.type === 'pdf') {
            icon = 'fa-file-pdf';
            typeLabel = 'PDF';
        } else if (material.file_type === 'document' || material.type === 'document') {
            icon = 'fa-file-alt';
            typeLabel = 'Document';
        } else if (material.file_type === 'link' || material.type === 'link') {
            icon = 'fa-link';
            typeLabel = 'Lien';
        }
        
        return '<div class="resource-item" onclick="viewResource(' + material.id + ', \'' + (material.file_path || '') + '\')">' +
            '<div class="resource-icon"><i class="fas ' + icon + '"></i></div>' +
            '<h4>' + escapeHtml(material.title) + '</h4>' +
            '<small>' + formatDate(material.upload_date || material.created_at) + '</small>' +
            '<span class="resource-type-badge">' + typeLabel + '</span>' +
        '</div>';
    }).join('') + '</div>';
}

// ============================================
// DÉTAILS DU COURS - CORRIGÉ
// ============================================

async function loadCourseDetails(courseId) {
    console.log('Chargement détails cours:', courseId);
    selectedCourseId = courseId;
    
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
            
            // Charger les quiz et devoirs - C'EST ICI LE CORRECTION PRINCIPALE
            await loadCourseQuizzes(courseId);
            await loadCourseAssignments(courseId);
            
            // Afficher la section détails
            showSection('course-details');
        } else {
            showNotification('Erreur lors du chargement du cours', 'error');
        }
    } catch (error) {
        console.error('Erreur chargement détails:', error);
        showNotification('Erreur de connexion', 'error');
    }
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
        console.log('Résultat inscription:', result);
        
        if (result.status === 'success') {
            showNotification('Inscription réussie ! Bienvenue dans votre nouveau cours.', 'success');
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
// QUIZ ET DEVOIRS - FONCTIONNALITÉS
// ============================================

async function startQuiz(quizId) {
    console.log('Démarrage du quiz:', quizId);
    currentQuizId = quizId;
    
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/quizzes/questions.php?quiz_id=' + quizId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        const result = await response.json();
        
        if (result.status === 'success' && result.data && result.data.length > 0) {
            renderQuizModal(result.data);
        } else {
            showNotification('Ce quiz ne contient pas encore de questions', 'warning');
        }
    } catch (error) {
        console.error('Erreur chargement quiz:', error);
        showNotification('Erreur lors du chargement du quiz', 'error');
    }
}

function renderQuizModal(questions) {
    const modal = document.getElementById('quiz-modal');
    const title = document.getElementById('quiz-modal-title');
    const content = document.getElementById('quiz-content');
    const submitBtn = document.getElementById('btn-submit-quiz');
    
    if (title) title.textContent = 'Quiz - ' + questions.length + ' question(s)';
    if (submitBtn) submitBtn.style.display = 'block';
    
    if (content) {
        content.innerHTML = questions.map(function(q, index) {
            let optionsHtml = '';
            
            if (q.question_type === 'true_false') {
                optionsHtml = '<div class="question-options">' +
                    '<label class="option-item"><input type="radio" name="q' + q.id + '" value="1"> Vrai</label>' +
                    '<label class="option-item"><input type="radio" name="q' + q.id + '" value="0"> Faux</label>' +
                '</div>';
            } else {
                optionsHtml = '<div class="question-options">' + 
                    (q.options ? q.options.map(function(opt) {
                        const inputType = q.question_type === 'multiple_answer' ? 'checkbox' : 'radio';
                        return '<label class="option-item">' +
                            '<input type="' + inputType + '" name="q' + q.id + (inputType === 'checkbox' ? '[]' : '') + '" value="' + opt.id + '"> ' +
                            escapeHtml(opt.option_text) +
                        '</label>';
                    }).join('') : '') +
                '</div>';
            }
            
            return '<div class="quiz-question" data-question-id="' + q.id + '">' +
                '<div class="question-text"><strong>Question ' + (index + 1) + ':</strong> ' + escapeHtml(q.question) + '</div>' +
                optionsHtml +
            '</div>';
        }).join('');
    }
    
    if (modal) modal.classList.add('active');
}

function closeQuizModal() {
    const modal = document.getElementById('quiz-modal');
    if (modal) modal.classList.remove('active');
    currentQuizId = null;
}

async function submitQuiz() {
    if (!currentQuizId) return;
    
    // Collecter les réponses
    const answers = {};
    document.querySelectorAll('.quiz-question').forEach(function(qEl) {
        const qId = qEl.getAttribute('data-question-id');
        const inputs = qEl.querySelectorAll('input:checked');
        
        if (inputs.length > 0) {
            if (inputs.length === 1) {
                answers[qId] = inputs[0].value;
            } else {
                answers[qId] = Array.from(inputs).map(function(i) { return i.value; });
            }
        }
    });
    
    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/quizzes/submit.php', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                quiz_id: currentQuizId,
                answers: answers
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification('Quiz soumis ! Score: ' + result.data.score.toFixed(1) + '%', 'success');
            closeQuizModal();
            // Recharger les quiz pour mettre à jour le statut
            if (selectedCourseId) loadCourseQuizzes(selectedCourseId);
        } else {
            showNotification(result.message || 'Erreur lors de la soumission', 'error');
        }
    } catch (error) {
        console.error('Erreur soumission quiz:', error);
        showNotification('Erreur lors de la soumission du quiz', 'error');
    }
}

function openAssignmentModal(assignmentId) {
    currentAssignmentId = assignmentId;
    const modal = document.getElementById('assignment-modal');
    if (modal) modal.classList.add('active');
}

function closeAssignmentModal() {
    const modal = document.getElementById('assignment-modal');
    if (modal) modal.classList.remove('active');
    currentAssignmentId = null;
    
    // Réinitialiser le formulaire
    const textArea = document.getElementById('assignment-text');
    const fileInput = document.getElementById('assignment-file');
    const fileDiv = document.getElementById('selected-file');
    
    if (textArea) textArea.value = '';
    if (fileInput) fileInput.value = '';
    if (fileDiv) fileDiv.style.display = 'none';
}

function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        const fileDiv = document.getElementById('selected-file');
        const fileName = document.getElementById('file-name');
        if (fileDiv) fileDiv.style.display = 'flex';
        if (fileName) fileName.textContent = file.name;
    }
}

function removeFile() {
    const fileInput = document.getElementById('assignment-file');
    const fileDiv = document.getElementById('selected-file');
    if (fileInput) fileInput.value = '';
    if (fileDiv) fileDiv.style.display = 'none';
}

async function submitAssignment() {
    if (!currentAssignmentId) return;
    
    const text = document.getElementById('assignment-text') ? document.getElementById('assignment-text').value : '';
    const fileInput = document.getElementById('assignment-file');
    const btn = document.getElementById('btn-submit-assignment-btn');
    
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Envoi en cours...';
    }
    
    try {
        const token = localStorage.getItem('token');
        const formData = new FormData();
        formData.append('assignment_id', currentAssignmentId);
        formData.append('submission_text', text);
        
        if (fileInput && fileInput.files[0]) {
            formData.append('file', fileInput.files[0]);
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
            // Recharger les devoirs
            if (selectedCourseId) loadCourseAssignments(selectedCourseId);
        } else {
            showNotification(result.message || 'Erreur lors de la soumission', 'error');
        }
    } catch (error) {
        console.error('Erreur soumission devoir:', error);
        showNotification('Erreur lors de la soumission du devoir', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Soumettre le Devoir';
        }
    }
}

function viewResource(resourceId, filePath) {
    if (filePath) {
        window.open(filePath, '_blank');
    } else {
        showNotification('Ressource non disponible', 'error');
    }
}

function viewCourseDetails(courseId) {
    console.log('Affichage cours:', courseId);
    loadCourseDetails(courseId);
}

// ============================================
// DÉMARRAGE
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initialisation student.js...');
    
    // Ajouter les styles CSS pour les notifications
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
    
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