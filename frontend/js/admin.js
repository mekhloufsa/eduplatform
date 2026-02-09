// admin.js - Version corrigée pour l'API
console.log('👑 admin.js chargé - Version API');

document.addEventListener('DOMContentLoaded', async function() {
    // Vérifier l'authentification
    if (!api.isAuthenticated()) {
        alert('Veuillez vous connecter pour accéder à cette page.');
        window.location.href = 'login.html';
        return;
    }

    const userRole = api.getUserRole();
    if (userRole !== 'admin') {
        alert('Accès réservé aux administrateurs.');
        window.location.href = 'index.html';
        return;
    }

    // Afficher le nom de l'admin
    const userName = localStorage.getItem('userName');
    if (userName) {
        const adminName = document.querySelector('.admin-name');
        if (adminName) adminName.textContent = userName;
    }

    // Initialisation
    await loadInitialData();
    initForms();
});

async function loadInitialData() {
    try {
        // Charger les enseignants
        const teachersResult = await api.request('admin/teachers.php');
        if (teachersResult.status === 'success') {
            renderTeachersList(teachersResult.data.teachers || []);
        }

        // Charger les étudiants
        const studentsResult = await api.request('admin/students.php');
        if (studentsResult.status === 'success') {
            renderStudentsList(studentsResult.data.students || []);
        }

        // Charger les cours
        const coursesResult = await api.request('admin/courses.php');
        if (coursesResult.status === 'success') {
            renderCoursesList(coursesResult.data.courses || []);
        }
    } catch (error) {
        console.error('Erreur de chargement des données:', error);
        showError('Erreur de chargement des données');
    }
}