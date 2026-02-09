// frontend/js/register.js
// Déclaration unique de l'API_BASE_URL
const API_BASE_URL = window.location.origin + '/eduplatform/backend';

// VARIABLES GLOBALES - Initialisation correcte
let currentStep = 1;
const totalSteps = 3;

$(document).ready(function() {
    // Gestion du changement de rôle
    $('input[name="role"]').on('change', function() {
        const role = $(this).val();
        
        if (role === 'student') {
            $('#studentFields').show();
            $('#teacherFields').hide();
            // Rendre les champs requis
            $('#studentCard, #year').prop('required', true);
            $('#specialty').prop('required', false);
        } else {
            $('#studentFields').hide();
            $('#teacherFields').show();
            // Rendre les champs requis
            $('#studentCard, #year').prop('required', false);
            $('#specialty').prop('required', true);
        }
        
        updateSummary();
    });
    
    // Validation en temps réel du mot de passe
    $('#password').on('input', function() {
        checkPasswordStrength($(this).val());
    });
    
    // Validation de la confirmation du mot de passe
    $('#confirmPassword').on('input', function() {
        validatePasswordMatch();
    });
    
    // Mise à jour du résumé quand les champs changent
    $('#registerForm input, #registerForm select').on('change input', function() {
        updateSummary();
    });
    
    // Navigation entre les étapes
    $('.btn-next').on('click', function(e) {
        e.preventDefault();
        if (validateCurrentStep()) {
            nextStep();
        }
    });
    
    $('.btn-prev').on('click', function(e) {
        e.preventDefault();
        prevStep();
    });
    
    // Soumission du formulaire
    $('#registerForm').on('submit', function(e) {
        e.preventDefault();
        
        if (!validateCurrentStep()) {
            return;
        }
        
        // Vérifier que les mots de passe correspondent
        if ($('#password').val() !== $('#confirmPassword').val()) {
            showError('Les mots de passe ne correspondent pas');
            return;
        }
        
        // Vérifier les conditions
        if (!$('#terms').is(':checked')) {
            showError('Vous devez accepter les conditions d\'utilisation');
            return;
        }
        
        submitForm();
    });
    
    // Initialiser l'affichage
    updateStepDisplay();
    updateSummary();
});

// FONCTIONS DE NAVIGATION

function nextStep() {
    if (currentStep < totalSteps) {
        currentStep++;
        updateStepDisplay();
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        updateStepDisplay();
    }
}

function updateStepDisplay() {
    // Cacher toutes les étapes
    $('.form-step').removeClass('active');
    
    // Afficher l'étape courante
    $(`#step-${currentStep}`).addClass('active');
    
    // Mettre à jour l'indicateur de progression
    $('.step').removeClass('active completed');
    for (let i = 1; i <= totalSteps; i++) {
        if (i < currentStep) {
            $(`.step:nth-child(${i * 2 - 1})`).addClass('completed');
        } else if (i === currentStep) {
            $(`.step:nth-child(${i * 2 - 1})`).addClass('active');
        }
    }
    
    // Gérer les boutons
    $('.btn-prev').toggle(currentStep > 1);
    $('.btn-next').toggle(currentStep < totalSteps);
    $('.btn-submit').toggle(currentStep === totalSteps);
}

function validateCurrentStep() {
    let isValid = true;
    const step = $(`#step-${currentStep}`);
    
    // Vérifier tous les champs requis de l'étape courante
    step.find('input[required], select[required]').each(function() {
        if (!$(this).val().trim()) {
            isValid = false;
            $(this).addClass('error');
            
            // Animation d'erreur
            $(this).shake();
        } else {
            $(this).removeClass('error');
        }
    });
    
    // Validation spécifique selon l'étape
    if (currentStep === 1) {
        // Validation email
        const email = $('#email').val();
        if (email && !isValidEmail(email)) {
            isValid = false;
            showError('Veuillez entrer un email valide');
        }
    }
    
    if (currentStep === 2) {
        // Validation mot de passe
        const password = $('#password').val();
        if (password && password.length < 6) {
            isValid = false;
            showError('Le mot de passe doit contenir au moins 6 caractères');
        }
        
        // Validation correspondance
        if ($('#password').val() !== $('#confirmPassword').val()) {
            isValid = false;
            showError('Les mots de passe ne correspondent pas');
        }
    }
    
    if (!isValid) {
        showError('Veuillez remplir tous les champs obligatoires');
    }
    
    return isValid;
}

// FONCTIONS UTILITAIRES

function updateSummary() {
    const role = $('input[name="role"]:checked').val();
    const roleText = role === 'student' ? 'Étudiant' : 'Enseignant';
    
    $('#summary-role').text(roleText);
    $('#summary-name').text(`${$('#firstName').val()} ${$('#lastName').val()}`);
    $('#summary-email').text($('#email').val());
    
    if (role === 'student') {
        $('#summary-extra').text(`Carte: ${$('#studentCard').val()} | Année: ${$('#year').val()}`);
    } else {
        $('#summary-extra').text(`Spécialité: ${$('#specialty').val()}`);
    }
}

function checkPasswordStrength(password) {
    const strengthBar = $('.strength-bar');
    const strengthText = $('.strength-text');
    
    // Réinitialiser
    strengthBar.removeClass('weak medium strong');
    strengthText.removeClass('weak medium strong').text('');
    
    if (password.length === 0) return;
    
    let strength = 0;
    
    // Critères
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    // Appliquer la classe
    if (strength <= 1) {
        strengthBar.addClass('weak');
        strengthText.addClass('weak').text('Faible');
    } else if (strength === 2 || strength === 3) {
        strengthBar.addClass('medium');
        strengthText.addClass('medium').text('Moyen');
    } else {
        strengthBar.addClass('strong');
        strengthText.addClass('strong').text('Fort');
    }
}

function validatePasswordMatch() {
    const password = $('#password').val();
    const confirm = $('#confirmPassword').val();
    
    if (confirm && password !== confirm) {
        $('#confirmPassword').addClass('error');
        return false;
    } else {
        $('#confirmPassword').removeClass('error');
        return true;
    }
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Plugin jQuery pour l'animation shake
$.fn.shake = function() {
    this.each(function() {
        $(this).css('position', 'relative');
        for (let i = 0; i < 3; i++) {
            $(this).animate({ left: -5 }, 50)
                   .animate({ left: 5 }, 50)
                   .animate({ left: 0 }, 50);
        }
    });
    return this;
};

function showError(message) {
    const errorDiv = $('#errorMessage');
    errorDiv.text(message).show();
    setTimeout(() => errorDiv.hide(), 5000);
}

function showSuccess(message) {
    const successDiv = $('#successMessage');
    successDiv.text(message).show();
    setTimeout(() => successDiv.hide(), 5000);
}

function submitForm() {
    const submitBtn = $('#submitBtn');
    submitBtn.prop('disabled', true).text('Création en cours...');
    
    const role = $('input[name="role"]:checked').val();
    
    const data = {
        email: $('#email').val().trim(),
        password: $('#password').val(),
        firstName: $('#firstName').val().trim(),
        lastName: $('#lastName').val().trim(),
        role: role
    };
    
    // Ajouter les champs spécifiques au rôle
    if (role === 'student') {
        data.studentCard = $('#studentCard').val().trim();
        data.year = $('#year').val();
    } else {
        data.specialty = $('#specialty').val().trim();
        data.phone = $('#phone').val().trim();
    }
    
    $.ajax({
        url: `${API_BASE_URL}/api/auth/register.php`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
        success: function(response) {
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
        error: function(xhr) {
            let message = 'Erreur serveur';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {}
            
            showError(message);
            submitBtn.prop('disabled', false).text('Créer mon compte');
        }
    });
}