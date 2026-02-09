// frontend/js/login.js
// Déclaration unique de l'API_BASE_URL
const API_BASE_URL = window.location.origin + '/eduplatform/backend';

// Fonction pour afficher les notifications (définie ici pour éviter les dépendances)
function showNotification(message, type = 'success') {
    // Supprimer les notifications existantes
    $('.notification').remove();
    
    const notification = $('<div>')
        .addClass(`notification ${type}`)
        .text(message)
        .css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 25px',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '500',
            zIndex: '9999',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            animation: 'slideInRight 0.3s ease-out'
        });

    // Couleurs selon le type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    notification.css('background', colors[type] || colors.success);

    $('body').append(notification);

    setTimeout(() => {
        notification.fadeOut(300, function() {
            $(this).remove();
        });
    }, 3000);
}

$(document).ready(function() {
    // Toggle mot de passe
    $('#togglePassword').on('click', function() {
        const passwordInput = $('#password');
        const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
        passwordInput.attr('type', type);
        $(this).text(type === 'password' ? '👁️' : '🙈');
    });

    // Soumission du formulaire
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        // Récupérer les valeurs
        const email = $('#email').val().trim();
        const password = $('#password').val();
        const role = $('input[name="role"]:checked').val();
        const rememberMe = $('#rememberMe').is(':checked');
        
        // Validation
        if (!email || !password) {
            showError('Veuillez remplir tous les champs');
            return;
        }
        
        // Désactiver le bouton
        const submitBtn = $('.btn-submit');
        submitBtn.prop('disabled', true).text('Connexion en cours...');
        
        // Appel API
        $.ajax({
            url: `${API_BASE_URL}/api/auth/login.php`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                email: email,
                password: password
            }),
            success: function(response) {
                if (response.status === 'success') {
                    // Stocker le token et les infos utilisateur
                    localStorage.setItem('token', response.data.token);
                    localStorage.setItem('user', JSON.stringify(response.data.user));
                    
                    // Redirection selon le rôle
                    let redirectUrl = 'index.html';
                    
                    switch(response.data.user.role) {
                        case 'student':
                            redirectUrl = 'student.html';
                            break;
                        case 'teacher':
                            redirectUrl = 'teacher.html';
                            break;
                        case 'admin':
                            redirectUrl = 'admin.html';
                            break;
                    }
                    
                    showNotification('Connexion réussie ! Redirection...', 'success');
                    
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 1000);
                } else {
                    showError(response.message || 'Erreur de connexion');
                    submitBtn.prop('disabled', false).text('Se connecter');
                }
            },
            error: function(xhr) {
                let message = 'Erreur serveur';
                try {
                    const response = JSON.parse(xhr.responseText);
                    message = response.message || message;
                } catch(e) {}
                
                showError(message);
                submitBtn.prop('disabled', false).text('Se connecter');
            }
        });
    });
    
    function showError(message) {
        const errorDiv = $('#errorMessage');
        errorDiv.text(message).show();
        setTimeout(() => errorDiv.hide(), 5000);
    }
});