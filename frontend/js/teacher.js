// teacher.js - Version corrigée pour l'API
console.log('👨‍🏫 teacher.js chargé - Version API');

let currentCourseId = null;
let currentQuizId = null;
let questions = [];
let currentTab = 'dashboard';

document.addEventListener('DOMContentLoaded', async function() {
    // Vérifier l'authentification
    if (!api.isAuthenticated()) {
        alert('Veuillez vous connecter pour accéder à cette page.');
        window.location.href = 'login.html';
        return;
    }

    const userRole = api.getUserRole();
    if (userRole !== 'teacher') {
        alert('Accès réservé aux enseignants.');
        window.location.href = 'index.html';
        return;
    }

    // Afficher le nom de l'enseignant
    const userName = localStorage.getItem('userName');
    const userEmail = localStorage.getItem('userEmail');
    if (userName) {
        const userElements = document.querySelectorAll('.teacher-name, .user-info');
        userElements.forEach(el => {
            el.textContent = userName;
        });
    }

    // Initialisation
    initNavigation();
    await loadTeacherDashboard();
    await loadTeacherCourses();
    initCourseForm();
});

async function loadTeacherDashboard() {
    try {
        const result = await api.getTeacherDashboard();
        
        if (result.status === 'success') {
            const data = result.data;
            
            // Mettre à jour les statistiques
            const elements = {
                'total-courses': data.totalCourses || 0,
                'total-students': data.totalStudents || 0,
                'total-quizzes': data.totalQuizzes || 0,
                'pending-assignments': data.pendingAssignments || 0
            };
            
            Object.keys(elements).forEach(id => {
                const element = document.getElementById(id);
                if (element) element.textContent = elements[id];
            });
            
            // Mettre à jour les activités récentes
            const activitiesContainer = document.getElementById('recent-activities');
            if (data.recentActivities && activitiesContainer) {
                activitiesContainer.innerHTML = data.recentActivities.map(activity => `
                    <div class="activity-item">
                        <div class="activity-icon">
                            ${getActivityIcon(activity.type)}
                        </div>
                        <div class="activity-content">
                            <p>${activity.description}</p>
                            <small>${formatDate(activity.date)}</small>
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Erreur lors du chargement du tableau de bord:', error);
        showError('Erreur de chargement du tableau de bord');
    }
}

async function loadTeacherCourses() {
    try {
        const result = await api.request('courses/teacher.php');
        
        if (result.status === 'success') {
            const courses = result.data.courses || [];
            
            // Rendre les cours
            const activeContainer = document.getElementById('active-courses-list');
            if (activeContainer) {
                activeContainer.innerHTML = courses.map(course => renderCourseCard(course)).join('');
            }
        }
    } catch (error) {
        console.error('Erreur lors du chargement des cours:', error);
        showError('Erreur de chargement des cours');
    }
}

// ... (gardez le reste de votre code teacher.js mais modifiez les appels API)