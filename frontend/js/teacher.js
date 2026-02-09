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
document.addEventListener('DOMContentLoaded', function() {
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
});