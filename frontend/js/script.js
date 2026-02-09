// frontend/js/script.js
// Fichier JavaScript principal - Fonctions utilitaires partagées
// NOTE: La constante API_BASE_URL a été supprimée pour éviter les conflits
// Elle est maintenant définie uniquement dans login.js et register.js

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Affiche une notification temporaire
 * @param {string} message - Le message à afficher
 * @param {string} type - Le type de notification ('success', 'error', 'warning', 'info')
 */
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

/**
 * Formate une date en format français
 * @param {string} dateString - La date à formater
 * @returns {string} La date formatée
 */
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Formate une date et heure en format français
 * @param {string} dateString - La date à formater
 * @returns {string} La date et heure formatées
 */
function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Échappe le HTML pour prévenir les attaques XSS
 * @param {string} text - Le texte à échapper
 * @returns {string} Le texte échappé
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Tronque un texte à une longueur maximale
 * @param {string} text - Le texte à tronquer
 * @param {number} maxLength - La longueur maximale
 * @returns {string} Le texte tronqué
 */
function truncateText(text, maxLength = 100) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength).trim() + '...';
}

// ============================================
// GESTION DE L'AUTHENTIFICATION
// ============================================

/**
 * Vérifie si l'utilisateur est connecté
 * @returns {boolean} true si connecté, false sinon
 */
function isLoggedIn() {
    return localStorage.getItem('token') !== null && localStorage.getItem('token') !== 'undefined';
}

/**
 * Récupère les informations de l'utilisateur connecté
 * @returns {object|null} Les informations utilisateur ou null
 */
function getUserInfo() {
    const userJson = localStorage.getItem('user');
    try {
        return userJson ? JSON.parse(userJson) : null;
    } catch (e) {
        console.error('Erreur parsing user info:', e);
        return null;
    }
}

/**
 * Récupère le token d'authentification
 * @returns {string|null} Le token ou null
 */
function getToken() {
    return localStorage.getItem('token');
}

/**
 * Déconnecte l'utilisateur et redirige
 */
function logout() {
    // Appel API de déconnexion (optionnel)
    const token = getToken();
    if (token) {
        $.ajax({
            url: 'backend/api/auth/logout.php',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            complete: function() {
                // Toujours nettoyer localStorage même si l'API échoue
                clearAuthData();
            }
        });
    } else {
        clearAuthData();
    }
}

/**
 * Nettoie les données d'authentification du localStorage
 */
function clearAuthData() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'index.html';
}

/**
 * Vérifie l'authentification pour les pages protégées
 * @param {array} allowedRoles - Les rôles autorisés
 */
function checkAuth(allowedRoles = []) {
    if (!isLoggedIn()) {
        showNotification('Veuillez vous connecter', 'warning');
        window.location.href = 'login.html';
        return false;
    }

    const user = getUserInfo();
    if (!user) {
        clearAuthData();
        return false;
    }

    if (allowedRoles.length > 0 && !allowedRoles.includes(user.role)) {
        showNotification('Accès non autorisé', 'error');
        window.location.href = 'index.html';
        return false;
    }

    return true;
}

/**
 * Redirige l'utilisateur vers sa page appropriée selon son rôle
 */
function redirectBasedOnRole() {
    const user = getUserInfo();
    if (!user) return;

    switch(user.role) {
        case 'student':
            window.location.href = 'student.html';
            break;
        case 'teacher':
            window.location.href = 'teacher.html';
            break;
        case 'admin':
            window.location.href = 'admin.html';
            break;
        default:
            window.location.href = 'index.html';
    }
}

// ============================================
// MISE À JOUR DE L'INTERFACE
// ============================================

/**
 * Met à jour la barre de navigation selon l'état de connexion
 */
function updateNavigation() {
    const navLinks = $('.nav-links');
    if (!navLinks.length) return;

    const user = getUserInfo();

    if (user) {
        // Utilisateur connecté
        const roleIcons = {
            student: '🎓',
            teacher: '👨‍🏫',
            admin: '⚙️'
        };

        navLinks.html(`
            <a href="index.html" ${window.location.pathname.includes('index.html') ? 'class="active"' : ''}>Accueil</a>
            <a href="index.html#courses">Cours</a>
            <a href="${user.role}.html" class="user-menu">
                ${roleIcons[user.role] || '👤'} ${escapeHtml(user.firstName)}
            </a>
            <a href="#" onclick="logout(); return false;" class="btn-login">Déconnexion</a>
        `);
    } else {
        // Utilisateur non connecté
        const isLoginPage = window.location.pathname.includes('login.html');
        const isRegisterPage = window.location.pathname.includes('register.html');

        navLinks.html(`
            <a href="index.html" ${window.location.pathname.includes('index.html') ? 'class="active"' : ''}>Accueil</a>
            <a href="index.html#courses">Cours</a>
            <a href="login.html" class="btn-login ${isLoginPage ? 'active' : ''}">Connexion</a>
            <a href="register.html" class="btn-register ${isRegisterPage ? 'active' : ''}">Inscription</a>
        `);
    }
}

/**
 * Met à jour l'interface utilisateur complète
 */
function updateUIForAuth() {
    updateNavigation();
    
    // Ajouter le nom de l'utilisateur dans les pages appropriées
    const user = getUserInfo();
    if (user) {
        $('.user-name').text(user.firstName + ' ' + user.lastName);
        $('.user-role').text(user.role === 'student' ? 'Étudiant' : 
                           user.role === 'teacher' ? 'Enseignant' : 'Administrateur');
    }
}

// ============================================
// FONCTIONS POUR LES COURS (Page d'accueil)
// ============================================

/**
 * Charge et affiche les cours sur la page d'accueil
 */
function loadCourses(page = 1, category = 'all', search = '') {
    const coursesGrid = $('#courses-grid');
    if (!coursesGrid.length) return;

    // Afficher le loader
    coursesGrid.html(`
        <div class="loading-courses" style="grid-column: 1 / -1; text-align: center; padding: 60px;">
            <div class="spinner" style="width: 50px; height: 50px; border: 3px solid #f3f3f3; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p>Chargement des cours...</p>
        </div>
    `);

    // Construction des paramètres
    let params = `page=${page}&limit=9`;
    if (category && category !== 'all') {
        params += `&category=${encodeURIComponent(category)}`;
    }
    if (search) {
        params += `&search=${encodeURIComponent(search)}`;
    }

    // Appel API
    $.ajax({
        url: `backend/api/courses/index.php?${params}`,
        method: 'GET',
        success: function(response) {
            if (response.status === 'success') {
                displayCourses(response.data, response.pagination);
            } else {
                showNoCoursesMessage();
            }
        },
        error: function() {
            coursesGrid.html(`
                <div class="error-message" style="grid-column: 1 / -1; text-align: center; padding: 60px;">
                    <div style="font-size: 48px; margin-bottom: 20px;">😕</div>
                    <h3>Erreur de chargement</h3>
                    <p>Impossible de charger les cours. Veuillez réessayer.</p>
                    <button onclick="loadCourses(${page}, '${category}', '${search}')" class="btn-retry" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px;">Réessayer</button>
                </div>
            `);
        }
    });
}

/**
 * Affiche les cours dans la grille
 */
function displayCourses(courses, pagination) {
    const coursesGrid = $('#courses-grid');
    const noCoursesMsg = $('#no-courses-message');
    const paginationDiv = $('#courses-pagination');

    if (!courses || courses.length === 0) {
        showNoCoursesMessage();
        return;
    }

    // Masquer le message "aucun cours"
    if (noCoursesMsg.length) noCoursesMsg.hide();
    if (paginationDiv.length) paginationDiv.show();

    // Générer le HTML des cours
    const coursesHtml = courses.map(course => {
        const categoryLabels = {
            'Développement Web': 'Web',
            'Data Science': 'Data',
            'Intelligence Artificielle': 'IA',
            'Cybersécurité': 'Cyber',
            'Développement Mobile': 'Mobile'
        };

        const categoryClass = course.category ? course.category.toLowerCase().replace(/\s+/g, '-') : 'default';
        
        return `
            <div class="course-card" data-category="${escapeHtml(course.category || '')}">
                <div class="course-image">
                    <img src="https://via.placeholder.com/400x200/667eea/ffffff?text=${encodeURIComponent(course.category || 'Cours')}" alt="${escapeHtml(course.title)}">
                    ${course.requires_key ? '<span class="free-badge" style="position: absolute; top: 10px; right: 10px; background: linear-gradient(45deg, #ff6b6b, #ffa726); color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;">🔒 Clé requise</span>' : '<span class="free-badge" style="position: absolute; top: 10px; right: 10px; background: linear-gradient(45deg, #27ae60, #2ecc71); color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;">✓ Libre</span>'}
                    <span class="category-badge" style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 500;">${escapeHtml(categoryLabels[course.category] || course.category || 'Cours')}</span>
                </div>
                <div class="course-content" style="padding: 1.5rem;">
                    <div class="course-header">
                        <h4 style="font-size: 1.2rem; margin-bottom: 5px; color: #333;">${escapeHtml(truncateText(course.title, 50))}</h4>
                        <div class="course-meta" style="display: flex; gap: 15px; font-size: 0.85rem; color: #666;">
                            <span>👤 ${escapeHtml(course.teacher || 'Professeur')}</span>
                            <span>📚 ${course.enrollment_count || 0} étudiants</span>
                        </div>
                    </div>
                    <p class="course-description" style="color: #555; margin: 1rem 0; line-height: 1.6; font-size: 0.9rem;">${escapeHtml(truncateText(course.description, 120))}</p>
                    <div class="course-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee;">
                        <span class="course-level level-beginner" style="background: #e8f0fe; color: #1967d2; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">${escapeHtml(course.category || 'Général')}</span>
                        <button onclick="openEnrollmentModal(${course.id}, '${escapeHtml(course.title)}', '${escapeHtml(course.teacher)}', ${course.requires_key}, '${escapeHtml(course.enrollment_key || '')}')" class="btn-enroll" style="background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; transition: all 0.3s;">S'inscrire</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    coursesGrid.html(coursesHtml);

    // Mettre à jour la pagination
    updatePagination(pagination);
}

/**
 * Affiche le message "Aucun cours disponible"
 */
function showNoCoursesMessage() {
    const coursesGrid = $('#courses-grid');
    const noCoursesMsg = $('#no-courses-message');
    const paginationDiv = $('#courses-pagination');

    coursesGrid.empty();
    if (noCoursesMsg.length) noCoursesMsg.show();
    if (paginationDiv.length) paginationDiv.hide();
}

/**
 * Met à jour les boutons de pagination
 */
function updatePagination(pagination) {
    const paginationDiv = $('#courses-pagination');
    if (!paginationDiv.length || !pagination) return;

    const prevBtn = $('#prev-page');
    const nextBtn = $('#next-page');
    const pageInfo = $('#page-info');

    if (prevBtn.length) {
        prevBtn.prop('disabled', pagination.page <= 1);
        prevBtn.off('click').on('click', () => loadCourses(pagination.page - 1));
    }

    if (nextBtn.length) {
        prevBtn.prop('disabled', pagination.page >= pagination.total_pages);
        nextBtn.off('click').on('click', () => loadCourses(pagination.page + 1));
    }

    if (pageInfo.length) {
        pageInfo.text(`Page ${pagination.page} sur ${pagination.total_pages}`);
    }
}

// ============================================
// MODAL D'INSCRIPTION AUX COURS
// ============================================

let selectedCourseId = null;
let selectedCourseRequiresKey = false;

/**
 * Ouvre le modal d'inscription à un cours
 */
function openEnrollmentModal(courseId, title, teacher, requiresKey, enrollmentKey) {
    selectedCourseId = courseId;
    selectedCourseRequiresKey = requiresKey;

    $('#modal-course-title').text(title);
    $('#modal-course-teacher').text('Par ' + teacher);

    if (requiresKey) {
        $('#key-required-section').show();
        $('#free-enrollment-section').hide();
        $('#input-enrollment-key').val('').focus();
    } else {
        $('#key-required-section').hide();
        $('#free-enrollment-section').show();
    }

    $('#enrollment-modal').css('display', 'flex');
    $('#key-error').hide();
}

/**
 * Ferme le modal d'inscription
 */
function closeEnrollmentModal() {
    $('#enrollment-modal').hide();
    selectedCourseId = null;
    selectedCourseRequiresKey = false;
}

/**
 * Confirme l'inscription au cours
 */
function confirmEnrollment() {
    if (!selectedCourseId) return;

    const token = getToken();
    if (!token) {
        showNotification('Veuillez vous connecter pour vous inscrire', 'warning');
        closeEnrollmentModal();
        setTimeout(() => window.location.href = 'login.html', 1000);
        return;
    }

    let enrollmentKey = null;
    if (selectedCourseRequiresKey) {
        enrollmentKey = $('#input-enrollment-key').val().trim();
        if (!enrollmentKey) {
            $('#key-error').text('Veuillez entrer la clé d\'inscription').show();
            return;
        }
    }

    $('#btn-confirm-enrollment').prop('disabled', true).text('Inscription en cours...');

    $.ajax({
        url: 'backend/api/courses/enroll.php',
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        data: JSON.stringify({
            course_id: selectedCourseId,
            enrollment_key: enrollmentKey
        }),
        success: function(response) {
            if (response.status === 'success') {
                showNotification('Inscription réussie !', 'success');
                closeEnrollmentModal();
                // Recharger les cours pour mettre à jour l'interface
                setTimeout(() => loadCourses(), 500);
            } else {
                if (selectedCourseRequiresKey) {
                    $('#key-error').text(response.message || 'Clé invalide').show();
                } else {
                    showNotification(response.message || 'Erreur lors de l\'inscription', 'error');
                }
            }
        },
        error: function(xhr) {
            let message = 'Erreur serveur';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {}
            
            if (selectedCourseRequiresKey) {
                $('#key-error').text(message).show();
            } else {
                showNotification(message, 'error');
            }
        },
        complete: function() {
            $('#btn-confirm-enrollment').prop('disabled', false).text('S\'inscrire');
        }
    });
}

// ============================================
// RECHERCHE
// ============================================

/**
 * Initialise la barre de recherche
 */
function initSearch() {
    const searchBtn = $('#searchBtn');
    const searchInput = $('#searchInput');

    if (!searchBtn.length || !searchInput.length) return;

    searchBtn.on('click', function() {
        performSearch(searchInput.val());
    });

    searchInput.on('keypress', function(e) {
        if (e.which === 13) {
            performSearch($(this).val());
        }
    });
}

/**
 * Effectue la recherche
 */
function performSearch(query) {
    if (!query.trim()) {
        loadCourses(1, 'all');
        return;
    }

    // Scroll vers la section des cours
    $('html, body').animate({
        scrollTop: $('#courses').offset().top - 100
    }, 500);

    loadCourses(1, 'all', query);
}

// ============================================
// FILTRES PAR CATÉGORIE
// ============================================

/**
 * Initialise les filtres de catégorie
 */
function initFilters() {
    $('.filter-btn').on('click', function() {
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        
        const category = $(this).data('specialty');
        loadCourses(1, category);
    });
}

// ============================================
// ANIMATIONS ET EFFETS
// ============================================

/**
 * Ajoute des animations au scroll
 */
function initScrollAnimations() {
    $(window).on('scroll', function() {
        const scrollTop = $(this).scrollTop();
        
        // Header fixe avec effet
        if (scrollTop > 50) {
            $('.header').addClass('scrolled');
        } else {
            $('.header').removeClass('scrolled');
        }
    });
}

// ============================================
// INITIALISATION AU CHARGEMENT
// ============================================

$(document).ready(function() {
    // Mettre à jour la navigation selon l'état de connexion
    updateUIForAuth();
    
    // Initialiser la recherche
    initSearch();
    
    // Initialiser les filtres
    initFilters();
    
    // Initialiser les animations
    initScrollAnimations();
    
    // Charger les cours si on est sur la page d'accueil
    if ($('#courses-grid').length && window.location.pathname.includes('index.html')) {
        loadCourses();
    }
    
    // Fermer le modal en cliquant en dehors
    $(window).on('click', function(e) {
        if ($(e.target).is('#enrollment-modal')) {
            closeEnrollmentModal();
        }
    });
    
    // Ajouter les styles CSS dynamiques pour les animations
    const style = $('<style>').text(`
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .header.scrolled {
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .notification {
            transition: opacity 0.3s;
        }
    `);
    $('head').append(style);
});