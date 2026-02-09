// teacher.js - Version complète et corrigée
console.log('👨‍🏫 teacher.js chargé');

const API_BASE_URL = window.location.origin + '/eduplatform/backend';

// Navigation entre les sections principales
function showSection(sectionName) {
    console.log('Navigation vers:', sectionName);

    // Cacher toutes les sections
    const sections = document.querySelectorAll('.section');
    sections.forEach(section => {
        section.classList.remove('active');
    });

    // Afficher la section demandée
    const targetSection = document.getElementById(sectionName + '-section');
    if (targetSection) {
        targetSection.classList.add('active');
    }

    // Mettre à jour la navigation active
    const navLinks = document.querySelectorAll('.nav-links a');
    navLinks.forEach(link => {
        link.classList.remove('active');
    });

    const activeNav = document.getElementById('nav-' + sectionName);
    if (activeNav) {
        activeNav.classList.add('active');
    }

    // Charger les données si nécessaire
    if (sectionName === 'courses') {
        loadTeacherCourses();
    } else if (sectionName === 'dashboard') {
        loadTeacherDashboard();
    } else if (sectionName === 'quizzes') {
        loadCoursesForSelect();
    }
}

// Gestion des onglets (tabs)
function switchTab(tabElement, contentId) {
    // Retirer la classe active de tous les onglets
    const parent = tabElement.parentElement;
    const tabs = parent.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
    });

    // Ajouter la classe active à l'onglet cliqué
    tabElement.classList.add('active');

    // Cacher tous les contenus
    const section = tabElement.closest('.section');
    const contents = section.querySelectorAll('.tab-content');
    contents.forEach(content => {
        content.classList.remove('active');
    });

    // Afficher le contenu sélectionné
    const targetContent = document.getElementById(contentId);
    if (targetContent) {
        targetContent.classList.add('active');
    }
}

// Chargement du tableau de bord
async function loadTeacherDashboard() {
    try {
        const token = localStorage.getItem('token');

        const response = await fetch(API_BASE_URL + '/api/teacher/teacher.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();

        if (result.status === 'success') {
            const data = result.data;

            // Mettre à jour les statistiques
            const elements = {
                'total-courses': data.stats?.total_courses || 0,
                'total-students': data.stats?.total_students || 0,
                'total-quizzes': data.stats?.total_quizzes || 0,
                'pending-assignments': data.stats?.pending_assignments || 0
            };

            for (const [id, value] of Object.entries(elements)) {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value;
                }
            }
        }
    } catch (error) {
        console.error('Erreur chargement dashboard:', error);
    }
}

// Chargement des cours
async function loadTeacherCourses() {
    try {
        const token = localStorage.getItem('token');

        const response = await fetch(API_BASE_URL + '/api/teacher/teacher.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();

        if (result.status === 'success') {
            const courses = result.data.courses || [];

            const container = document.getElementById('active-courses-list');

            if (courses.length === 0) {
                container.innerHTML = '<p style="color: #666; text-align: center; padding: 40px;">Vous n\'avez pas encore créé de cours</p>';
            } else {
                container.innerHTML = courses.map(course => `
                    <div class="course-item">
                        <div class="course-header">
                            <h3>${escapeHtml(course.title)}</h3>
                            <div class="course-actions">
                                <button class="course-btn" onclick="editCourse(${course.id})">
                                    <i class="fas fa-edit"></i> Modifier
                                </button>
                                <button class="course-btn" onclick="deleteCourse(${course.id})" style="background: #dc3545;">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                            </div>
                        </div>
                        <p>${escapeHtml(course.description)}</p>
                        <div class="course-info">
                            <span>📚 ${course.enrollment_count || 0} étudiants</span>
                            <span>📅 ${new Date(course.created_at).toLocaleDateString('fr-FR')}</span>
                            <span>🏷️ ${escapeHtml(course.category)}</span>
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Erreur chargement cours:', error);
        document.getElementById('active-courses-list').innerHTML =
            '<p style="color: red; text-align: center;">Erreur de chargement des cours</p>';
    }
}

// Charger les cours dans les selects
async function loadCoursesForSelect() {
    try {
        const token = localStorage.getItem('token');

        const response = await fetch(API_BASE_URL + '/api/teacher/teacher.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();

        if (result.status === 'success') {
            const courses = result.data.courses || [];
            const options = courses.map(c => `<option value="${c.id}">${escapeHtml(c.title)}</option>`).join('');

            const selects = ['quiz-course', 'assignment-course', 'announcement-course'];
            selects.forEach(id => {
                const select = document.getElementById(id);
                if (select) {
                    select.innerHTML = '<option value="">Choisir un cours</option>' + options;
                }
            });
        }
    } catch (error) {
        console.error('Erreur chargement selects:', error);
    }
}

// Créer un cours
async function createCourse(e) {
    e.preventDefault();

    const token = localStorage.getItem('token');
    const submitBtn = e.target.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création...';

    const data = {
        title: document.getElementById('course-title').value,
        category: document.getElementById('course-category').value,
        description: document.getElementById('course-description').value,
        is_public: document.getElementById('course-visibility').value === 'public'
    };

    try {
        const response = await fetch(API_BASE_URL + '/api/teacher/create.php', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('Cours créé avec succès !');
            showSection('courses');
        } else {
            alert('Erreur: ' + result.message);
        }
    } catch (error) {
        console.error('Erreur création cours:', error);
        alert('Erreur serveur');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Créer le cours';
    }
}

// Fonctions utilitaires
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function logout() {
    localStorage.clear();
    window.location.href = 'login.html';
}

// Initialisation
document.addEventListener('DOMContentLoaded', function () {
    console.log('Initialisation teacher.js');

    // Vérifier l'authentification
    const token = localStorage.getItem('token');
    const user = JSON.parse(localStorage.getItem('user') || '{}');

    if (!token || user.role !== 'teacher') {
        window.location.href = 'login.html';
        return;
    }

    // Charger le dashboard
    loadTeacherDashboard();

    // Attacher les événements aux formulaires
    const courseForm = document.getElementById('create-course-form');
    if (courseForm) {
        courseForm.addEventListener('submit', createCourse);
    }
});// Variable globale pour stocker l'ID du devoir actuel
let currentAssignmentId = null;

// Voir les soumissions d'un devoir
async function viewSubmissions(assignmentId) {
    currentAssignmentId = assignmentId;
    console.log('Affichage des soumissions pour le devoir:', assignmentId);

    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/teachers/submissions.php?assignment_id=' + assignmentId, {
            headers: { 'Authorization': 'Bearer ' + token }
        });

        const result = await response.json();
        console.log('Soumissions:', result);

        if (result.status === 'success') {
            renderSubmissionsModal(result.data);
        } else {
            alert('Erreur: ' + result.message);
        }
    } catch (error) {
        console.error('Erreur chargement soumissions:', error);
        alert('Erreur lors du chargement des soumissions');
    }
}

// Afficher le modal des soumissions
function renderSubmissionsModal(data) {
    const assignment = data.assignment;
    const submissions = data.submissions;

    // Créer le modal s'il n'existe pas
    let modal = document.getElementById('submissions-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'submissions-modal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 900px;">
                <div class="modal-header">
                    <h3>Soumissions du devoir</h3>
                    <button class="close-modal" onclick="closeSubmissionsModal()">×</button>
                </div>
                <div id="submissions-content"></div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    const content = document.getElementById('submissions-content');

    let html = `
        <div style="margin-bottom: 20px; padding: 15px; background: #f8f9ff; border-radius: 8px;">
            <h4>${escapeHtml(assignment.title)}</h4>
            <p style="color: #666; margin: 5px 0;">${escapeHtml(assignment.description)}</p>
            <div style="display: flex; gap: 15px; font-size: 0.9rem; color: #888;">
                <span><i class="fas fa-book"></i> ${escapeHtml(assignment.course_title)}</span>
                <span><i class="fas fa-calendar"></i> Date limite: ${new Date(assignment.due_date).toLocaleString('fr-FR')}</span>
                <span><i class="fas fa-star"></i> ${assignment.max_points} points</span>
            </div>
        </div>
    `;

    if (submissions.length === 0) {
        html += '<p style="text-align: center; color: #666; padding: 40px;">Aucune soumission pour le moment</p>';
    } else {
        html += `<div style="margin-bottom: 10px;"><strong>${submissions.length} soumission(s)</strong></div>`;
        html += '<div style="display: flex; flex-direction: column; gap: 15px;">';

        submissions.forEach(function (sub) {
            const submittedDate = sub.submitted_at ? new Date(sub.submitted_at).toLocaleString('fr-FR') : 'Non soumis';
            const isGraded = sub.status === 'graded';

            html += `
                <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; background: white;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div>
                            <strong style="font-size: 1.1rem;">${escapeHtml(sub.first_name)} ${escapeHtml(sub.last_name)}</strong>
                            <div style="color: #666; font-size: 0.9rem;">
                                <span>${escapeHtml(sub.email)}</span> | 
                                <span>Carte: ${escapeHtml(sub.student_card)}</span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.85rem; color: #888;">Soumis le: ${submittedDate}</div>
                            ${isGraded ?
                    `<span style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem;">Noté: ${sub.grade}/${assignment.max_points}</span>` :
                    `<span style="background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem;">En attente de notation</span>`
                }
                        </div>
                    </div>
                    
                    ${sub.submission_text ? `
                        <div style="margin: 10px 0; padding: 10px; background: #f8f9ff; border-radius: 6px; border-left: 3px solid #5f6cff;">
                            <strong>Réponse:</strong><br>
                            ${escapeHtml(sub.submission_text)}
                        </div>
                    ` : ''}
                    
                    ${sub.file_path ? `
                        <div style="margin: 10px 0;">
                            <a href="${sub.file_path}" target="_blank" style="color: #5f6cff; text-decoration: none;">
                                <i class="fas fa-paperclip"></i> Voir le fichier joint
                            </a>
                        </div>
                    ` : ''}
                    
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="number" id="grade-${sub.id}" placeholder="Note" min="0" max="${assignment.max_points}" 
                                value="${sub.grade || ''}" style="width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <button onclick="gradeSubmission(${sub.id})" style="background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-save"></i> Noter
                            </button>
                            ${sub.file_path ? `
                                <button onclick="downloadSubmission('${sub.file_path}')" style="background: #17a2b8; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                                    <i class="fas fa-download"></i> Télécharger
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
    }

    content.innerHTML = html;
    modal.classList.add('active');
}

function closeSubmissionsModal() {
    const modal = document.getElementById('submissions-modal');
    if (modal) modal.classList.remove('active');
    currentAssignmentId = null;
}

// Noter une soumission
async function gradeSubmission(submissionId) {
    const gradeInput = document.getElementById('grade-' + submissionId);
    const grade = gradeInput ? gradeInput.value : null;

    if (grade === '' || grade === null) {
        alert('Veuillez entrer une note');
        return;
    }

    try {
        const token = localStorage.getItem('token');
        const response = await fetch(API_BASE_URL + '/teachers/grade.php', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                submission_id: submissionId,
                grade: parseFloat(grade)
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('Note enregistrée avec succès !');
            // Recharger les soumissions
            if (currentAssignmentId) {
                viewSubmissions(currentAssignmentId);
            }
        } else {
            alert('Erreur: ' + result.message);
        }
    } catch (error) {
        console.error('Erreur notation:', error);
        alert('Erreur lors de l\'enregistrement de la note');
    }
}

function downloadSubmission(filePath) {
    window.open(filePath, '_blank');
} async function loadTeacherAssignments() {
    try {
        const result = await apiRequest('teachers/assignments.php');
        const container = document.getElementById('assignments-list');

        if (result.status === 'success') {
            const assignments = result.data || [];
            if (assignments.length === 0) {
                container.innerHTML = '<p style="color: #666; text-align: center;">Aucun devoir créé</p>';
            } else {
                container.innerHTML = assignments.map(a => `
                    <div class="assignment-item">
                        <h3>${escapeHtml(a.title)} <span class="badge ${a.submission_count > 0 ? 'badge-warning' : 'badge-success'}">${a.submission_count} soumission(s)</span></h3>
                        <p>${escapeHtml(a.description)}</p>
                        <div class="assignment-meta">
                            <span><i class="fas fa-book"></i> ${escapeHtml(a.course_title)}</span>
                            <span><i class="fas fa-calendar-alt"></i> À rendre: ${new Date(a.due_date).toLocaleString('fr-FR')}</span>
                            <span><i class="fas fa-star"></i> ${a.max_points} points</span>
                            ${a.file_name ? '<span><i class="fas fa-paperclip"></i> Fichier joint</span>' : ''}
                        </div>
                        <div style="margin-top: 15px;">
                            <button onclick="viewSubmissions(${a.id})" class="btn-create" style="padding: 8px 16px; font-size: 0.9rem;">
                                <i class="fas fa-eye"></i> Voir les soumissions
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (e) {
        console.error('Erreur devoirs:', e);
    }
}