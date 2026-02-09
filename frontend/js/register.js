// frontend/js/register.js
// Déclaration unique de l'API_BASE_URL
const API_BASE_URL = window.location.origin + '/eduplatform/backend';

console.log('Register.js chargé - Version corrigée');

$(document).ready(function() {
    console.log('Document ready - Initialisation du formulaire d\'inscription');
    
    // Vérifier que jQuery fonctionne
    if (typeof $ === 'undefined') {
        console.error('jQuery non chargé !');
        alert('Erreur: jQuery n\'est pas chargé');
        return;
    }
    
    // Vérifier les éléments existants
    console.log('studentFields existe:', $('#studentFields').length > 0);
    console.log('teacherFields existe:', $('#teacherFields').length > 0);
    console.log('Radio buttons existent:', $('input[name="role"]').length > 0);
    
    // Initialiser l'affichage - ÉTUDIANT par défaut
    $('#studentFields').css('display', 'block');
    $('#teacherFields').css('display', 'none');
    $('#studentCard, #year').prop('required', true);
    $('#specialty').prop('required', false);
    
    // Gestion du changement de rôle - VERSION CORRIGÉE
    $(document).on('change', 'input[name="role"]', function() {
        const role = $(this).val();
        console.log('=== CHANGEMENT DE RÔLE ===');
        console.log('Nouveau rôle:', role);
        
        if (role === 'student') {
            console.log('Affichage champs ÉTUDIANT');
            $('#studentFields').css('display', 'block');
            $('#teacherFields').css('display', 'none');
            
            // Gérer les champs requis
            $('#studentCard, #year').prop('required', true);
            $('#specialty, #phone').prop('required', false);
            
            // Vider les champs enseignant pour éviter confusion
            $('#specialty').val('');
            $('#phone').val('');
            
        } else if (role === 'teacher') {
            console.log('Affichage champs ENSEIGNANT');
            $('#studentFields').css('display', 'none');
            $('#teacherFields').css('display', 'block');
            
            // Gérer les champs requis
            $('#studentCard, #year').prop('required', false);
            $('#specialty').prop('required', true);
            $('#phone').prop('required', false);
            
            // Vider les champs étudiant pour éviter confusion
            $('#studentCard').val('');
            $('#year').val('');
        }
        
        // Animation pour rendre le changement visible
        $('.role-fields:visible').hide().fadeIn(300);
    });
    
    // Validation du mot de passe
    $('#password').on('input', function() {
        const val = $(this).val();
        const strength = calculatePasswordStrength(val);
        updatePasswordIndicator(strength);
    });
    
    // Validation confirmation mot de passe
    $('#confirmPassword').on('input', function() {
        const pass = $('#password').val();
        const confirm = $(this).val();
        
        if (confirm && pass !== confirm) {
            $(this).css('border-color', '#dc3545');
        } else if (confirm) {
            $(this).css('border-color', '#28a745');
        } else {
            $(this).css('border-color', '#e0e0e0');
        }
    });
    
    // Soumission du formulaire
    $('#registerForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Soumission du formulaire...');
        
        // Récupérer les valeurs de base
        const email = $('#email').val().trim();
        const password = $('#password').val();
        const confirmPassword = $('#confirmPassword').val();
        const firstName = $('#firstName').val().trim();
        const lastName = $('#lastName').val().trim();
        const role = $('input[name="role"]:checked').val();
        
        console.log('Rôle sélectionné:', role);
        
        // Validation de base
        if (!email || !password || !firstName || !lastName) {
            showError('Veuillez remplir tous les champs obligatoires');
            return;
        }
        
        if (password !== confirmPassword) {
            showError('Les mots de passe ne correspondent pas');
            return;
        }
        
        if (password.length < 6) {
            showError('Le mot de passe doit contenir au moins 6 caractères');
            return;
        }
        
        // Validation spécifique au rôle
        let roleData = {};
        
        if (role === 'student') {
            const studentCard = $('#studentCard').val().trim();
            const year = $('#year').val();
            
            if (!studentCard) {
                showError('Veuillez entrer votre numéro de carte étudiant');
                $('#studentCard').focus();
                return;
            }
            if (!year) {
                showError('Veuillez sélectionner votre année d\'études');
                $('#year').focus();
                return;
            }
            
            roleData = { studentCard, year };
            
        } else if (role === 'teacher') {
            const specialty = $('#specialty').val().trim();
            const phone = $('#phone').val().trim();
            
            if (!specialty) {
                showError('Veuillez entrer votre spécialité');
                $('#specialty').focus();
                return;
            }
            
            roleData = { specialty, phone };
        }
        
        // Désactiver le bouton
        const submitBtn = $('#submitBtn');
        submitBtn.prop('disabled', true).text('Création en cours...');
        
        // Préparer les données
        const data = {
            email: email,
            password: password,
            firstName: firstName,
            lastName: lastName,
            role: role,
            ...roleData
        };
        
        console.log('Données envoyées:', data);
        
        // Appel API
        $.ajax({
            url: `${API_BASE_URL}/api/auth/register.php`,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                console.log('Réponse serveur:', response);
                
                if (response.status === 'success') {
                    showSuccess('Compte créé avec succès ! Redirection...');
                    
                    // Stocker les infos
                    localStorage.setItem('token', response.data.token);
                    localStorage.setItem('user', JSON.stringify(response.data.user));
                    
                    // Redirection
                    setTimeout(() => {
                        const redirectUrl = role === 'student' ? 'student.html' : 'teacher.html';
                        window.location.href = redirectUrl;
                    }, 1500);
                } else {
                    showError(response.message || 'Erreur lors de l\'inscription');
                    submitBtn.prop('disabled', false).text('Créer mon compte');
                }
            },
            error: function(xhr, status, error) {
                console.error('Erreur AJAX:', { xhr, status, error });
                let message = 'Erreur de connexion au serveur';
                try {
                    const response = JSON.parse(xhr.responseText);
                    message = response.message || message;
                } catch(e) {}
                
                showError(message);
                submitBtn.prop('disabled', false).text('Créer mon compte');
            }
        });
    });
});

// Fonction pour calculer la force du mot de passe
function calculatePasswordStrength(password) {
    if (!password) return 0;
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    return strength;
}

// Fonction pour mettre à jour l'indicateur de force
function updatePasswordIndicator(strength) {
    const passwordInput = $('#password');
    
    // Retirer les classes précédentes
    passwordInput.removeClass('strength-weak strength-medium strength-strong');
    
    if (strength === 0) {
        passwordInput.css('border-color', '#e0e0e0');
    } else if (strength <= 2) {
        passwordInput.css('border-color', '#dc3545'); // Rouge
    } else if (strength === 3) {
        passwordInput.css('border-color', '#ffc107'); // Jaune
    } else {
        passwordInput.css('border-color', '#28a745'); // Vert
    }
}

// Fonction pour afficher les erreurs
function showError(message) {
    console.log('Erreur:', message);
    const errorDiv = $('#errorMessage');
    const successDiv = $('#successMessage');
    
    errorDiv.text(message).show();
    successDiv.hide();
    
    // Scroll vers le message d'erreur
    $('html, body').animate({
        scrollTop: errorDiv.offset().top - 100
    }, 300);
    
    setTimeout(() => errorDiv.fadeOut(), 5000);
}

// Fonction pour afficher les succès
function showSuccess(message) {
    console.log('Succès:', message);
    const successDiv = $('#successMessage');
    const errorDiv = $('#errorMessage');
    
    successDiv.text(message).show();
    errorDiv.hide();
    
    setTimeout(() => successDiv.fadeOut(), 5000);
}