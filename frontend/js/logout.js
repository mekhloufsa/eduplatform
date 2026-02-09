// Fonction de déconnexion commune à toutes les pages
function logout() {
    console.log('🚪 Tentative de déconnexion...');
    
    if (confirm('Voulez-vous vraiment vous déconnecter ?')) {
        // Appel API de déconnexion (optionnel)
        const token = localStorage.getItem('token');
        if (token) {
            // Tentative de déconnexion côté serveur
            fetch('backend/api/auth/logout.php', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token
                }
            }).catch(() => {}); // Ignorer les erreurs
        }
        
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

// Vérifier l'état de connexion au chargement des pages protégées
document.addEventListener('DOMContentLoaded', function() {
    // Ajouter un gestionnaire d'événement pour les boutons de déconnexion
    const logoutButtons = document.querySelectorAll('.logout, [onclick*="logout"]');
    
    logoutButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });
    });
    
    // Vérifier l'état de connexion pour les pages protégées
    checkAuthStatus();
});

// Vérifier si l'utilisateur est connecté
function checkAuthStatus() {
    const token = localStorage.getItem('token');
    const user = localStorage.getItem('user');
    const currentPage = window.location.pathname.split('/').pop();
    
    // Pages qui nécessitent une authentification
    const protectedPages = ['student.html', 'teacher.html', 'admin.html'];
    
    if (protectedPages.includes(currentPage)) {
        if (!token || !user) {
            // Pas de token ou d'utilisateur - rediriger vers login
            alert('Session expirée. Veuillez vous reconnecter.');
            window.location.href = 'login.html';
            return;
        }
        
        // Vérifier si le rôle correspond à la page
        try {
            const userData = JSON.parse(user);
            const pageRole = currentPage.replace('.html', '');
            
            if (userData.role !== pageRole) {
                // Rediriger vers la bonne page selon le rôle
                if (userData.role === 'student') {
                    window.location.href = 'student.html';
                } else if (userData.role === 'teacher') {
                    window.location.href = 'teacher.html';
                } else if (userData.role === 'admin') {
                    window.location.href = 'admin.html';
                } else {
                    window.location.href = 'index.html';
                }
            }
        } catch(e) {
            console.error('Erreur parsing user data:', e);
            localStorage.clear();
            window.location.href = 'login.html';
        }
    }
}

// Fonction pour obtenir le token d'authentification
function getAuthToken() {
    return localStorage.getItem('token');
}

// Fonction pour obtenir le rôle de l'utilisateur
function getUserRole() {
    const user = localStorage.getItem('user');
    if (user) {
        try {
            return JSON.parse(user).role;
        } catch(e) {
            return null;
        }
    }
    return null;
}

// Fonction pour obtenir le nom de l'utilisateur
function getUserName() {
    const user = localStorage.getItem('user');
    if (user) {
        try {
            const userData = JSON.parse(user);
            return userData.firstName + ' ' + userData.lastName;
        } catch(e) {
            return null;
        }
    }
    return null;
}

// Fonction pour obtenir l'ID de l'utilisateur
function getUserId() {
    const user = localStorage.getItem('user');
    if (user) {
        try {
            return JSON.parse(user).id;
        } catch(e) {
            return null;
        }
    }
    return null;
}

// Fonction utilitaire pour vérifier si l'utilisateur est authentifié
function isAuthenticated() {
    return !!localStorage.getItem('token');
}