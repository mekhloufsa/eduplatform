// Fonction de déconnexion commune à toutes les pages
function logout() {
    console.log('🚪 Tentative de déconnexion...');
    
    if (confirm('Voulez-vous vraiment vous déconnecter ?')) {
        // Supprimer toutes les données de session
        localStorage.clear();
        sessionStorage.clear();
        
        // Supprimer les cookies de session
        document.cookie.split(";").forEach(function(c) {
            document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
        });
        
        console.log('✅ Données de session supprimées');
        
        // Rediriger vers la page de connexion
        window.location.href = 'login.html';
    }
}

// Ajouter un gestionnaire d'événement pour le bouton de déconnexion
document.addEventListener('DOMContentLoaded', function() {
    const logoutButtons = document.querySelectorAll('.logout, [onclick*="logout"]');
    
    logoutButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });
    });
    
    // Vérifier l'état de connexion
    checkAuthStatus();
});

// Vérifier si l'utilisateur est connecté
function checkAuthStatus() {
    const userToken = localStorage.getItem('userToken') || sessionStorage.getItem('userToken');
    const userRole = localStorage.getItem('userRole') || sessionStorage.getItem('userRole');
    const currentPage = window.location.pathname.split('/').pop();
    
    // Pages qui nécessitent une authentification
    const protectedPages = ['student.html', 'teacher.html', 'admin.html'];
    
    if (protectedPages.includes(currentPage)) {
        if (!userToken || !userRole) {
            alert('Session expirée. Veuillez vous reconnecter.');
            window.location.href = 'login.html';
            return;
        }
        
        // Vérifier si le rôle correspond à la page
        const pageRole = currentPage.replace('.html', '');
        if (userRole !== pageRole && !(pageRole === 'student' && userRole === 'teacher')) {
            alert('Accès non autorisé. Redirection...');
            window.location.href = 'index.html';
        }
    }
}

// Fonction pour obtenir le token d'authentification
function getAuthToken() {
    return localStorage.getItem('userToken') || sessionStorage.getItem('userToken');
}

// Fonction pour obtenir le rôle de l'utilisateur
function getUserRole() {
    return localStorage.getItem('userRole') || sessionStorage.getItem('userRole');
}

// Fonction pour obtenir le nom de l'utilisateur
function getUserName() {
    return localStorage.getItem('userName') || sessionStorage.getItem('userName');
}

// Fonction pour obtenir l'ID de l'utilisateur
function getUserId() {
    return localStorage.getItem('userId') || sessionStorage.getItem('userId');
}