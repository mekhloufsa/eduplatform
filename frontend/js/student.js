// student.js - Version corrigée pour l'API
console.log('🎓 student.js chargé - Version API');

let enrolledCourses = [];
let currentViewingCourse = null;

document.addEventListener('DOMContentLoaded', async function() {
    // Vérifier l'authentification
    if (!api.isAuthenticated()) {
        alert('Veuillez vous connecter pour accéder à cette page.');
        window.location.href = 'login.html';
        return;
    }

    const userRole = api.getUserRole();
    if (userRole !== 'student') {
        alert('Accès réservé aux étudiants.');
        window.location.href = 'index.html';
        return;
    }

    // Afficher le nom de l'étudiant
    const userName = localStorage.getItem('userName');
    if (userName) {
        const userElements = document.querySelectorAll('.student-name, .user-info');
        userElements.forEach(el => {
            el.textContent = userName;
        });
    }

    // Initialisation
    await loadEnrolledCourses();
    renderEnrolledCourses();
});

async function loadEnrolledCourses() {
    try {
        const result = await api.getStudentDashboard();
        
        if (result.status === 'success') {
            enrolledCourses = result.data.courses || [];
            renderEnrolledCourses();
        }
    } catch (error) {
        console.error('Erreur de chargement des cours:', error);
        showError('Erreur de chargement des cours');
    }
}