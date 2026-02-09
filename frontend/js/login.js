// frontend/js/login.js
// Déclaration unique de l'API_BASE_URL
const API_BASE_URL = window.location.origin + '/eduplatform/backend';

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