# EduPlatform - Instructions d'Installation

## Structure du projet corrigée

```
eduplatform/
├── index.html              # Page d'accueil
├── index.php               # Redirection vers index.html
├── login.html              # Page de connexion
├── register.html           # Page d'inscription
├── student.html            # Espace étudiant
├── teacher.html            # Espace enseignant
├── admin.html              # Espace administrateur
├── frontend/
│   ├── css/
│   │   ├── style.css       # Styles principaux
│   │   ├── login.css       # Styles login
│   │   ├── register.css    # Styles register
│   │   ├── student.css     # Styles étudiant
│   │   ├── teacher.css     # Styles enseignant
│   │   └── admin.css       # Styles admin
│   └── js/
│       ├── script.js       # Script principal (corrigé)
│       ├── login.js        # Script login (corrigé)
│       ├── register.js     # Script register (corrigé)
│       ├── student.js      # Script étudiant
│       ├── teacher.js      # Script enseignant
│       ├── admin.js        # Script admin
│       └── logout.js       # Script déconnexion
├── backend/
│   ├── config/
│   │   ├── database.php    # Configuration BDD (corrigé)
│   │   └── constants.php   # Constantes (NOUVEAU)
│   ├── api/
│   │   └── auth/
│   │       ├── login.php   # API login (corrigé)
│   │       ├── register.php # API register (corrigé)
│   │       └── logout.php  # API logout
│   └── uploads/            # Dossier uploads
└── database/
    └── eduplatform.sql     # Script SQL
```

## Corrections apportées

### 1. Fichier constants.php manquant ✅
- **Problème** : `login.php` et `registre.php` requéraient `../../config/constants.php` qui n'existait pas
- **Solution** : Création du fichier `backend/config/constants.php` avec toutes les constantes nécessaires

### 2. Fichier register.php manquant ✅
- **Problème** : Le fichier s'appelait `registre.php` (avec un 'e') mais le JS appelait `register.php`
- **Solution** : Création du fichier `backend/api/auth/register.php`

### 3. Chemins API incorrects ✅
- **Problème** : Les fichiers JS utilisaient des chemins absolus `http://localhost/eduplatform/backend/api/...`
- **Solution** : Utilisation de chemins relatifs `backend/api/...` pour plus de flexibilité

### 4. Configuration BDD ✅
- **Problème** : Le mot de passe dans `database.php` ne correspondait pas à XAMPP par défaut
- **Solution** : Configuration pour XAMPP (root / sans mot de passe)

### 5. Fichier .htaccess ✅
- **Problème** : Pas de configuration CORS pour les requêtes API
- **Solution** : Ajout du fichier `.htaccess` avec configuration CORS

## Installation

### 1. Copier les fichiers
Copiez le dossier `eduplatform` dans `C:\xampp\htdocs\` (Windows) ou `/opt/lampp/htdocs/` (Linux/Mac)

### 2. Créer la base de données
1. Ouvrez phpMyAdmin : http://localhost/phpmyadmin
2. Cliquez sur "Nouvelle base de données"
3. Nommez-la `eduplatform`
4. Cliquez sur "Créer"
5. Sélectionnez la base `eduplatform`
6. Cliquez sur "Importer"
7. Sélectionnez le fichier `database/eduplatform.sql`
8. Cliquez sur "Exécuter"

### 3. Vérifier la configuration
Si votre MySQL a un mot de passe différent, modifiez :
- Fichier : `backend/config/database.php`
- Ligne 6 : `private $password = "";`

### 4. Accéder à l'application
Ouvrez votre navigateur et allez à :
```
http://localhost/eduplatform/
```

## Compte administrateur par défaut

- **Email** : admin@eduplatform.com
- **Mot de passe** : admin123

## Test de l'inscription

1. Allez sur http://localhost/eduplatform/register.html
2. Remplissez le formulaire
3. Cliquez sur "Créer mon compte"
4. Vous devriez voir un message de succès

## Dépannage

### Erreur "Database connection failed"
Vérifiez que :
- MySQL est démarré dans XAMPP
- La base de données `eduplatform` existe
- Les identifiants dans `backend/config/database.php` sont corrects

### Erreur CORS / API ne répond pas
Vérifiez que :
- Le fichier `.htaccess` existe dans `backend/`
- Le module `mod_rewrite` est activé dans Apache
- Le module `mod_headers` est activé dans Apache

### Erreur 404 sur les API
Vérifiez que :
- Les fichiers PHP sont bien dans `backend/api/auth/`
- La structure des dossiers est correcte

## Fonctionnalités

### Frontend
- ✅ Page d'accueil avec liste des cours
- ✅ Filtres par catégorie
- ✅ Recherche de cours
- ✅ Inscription (étudiant/enseignant)
- ✅ Connexion
- ✅ Espace étudiant
- ✅ Espace enseignant
- ✅ Espace administrateur

### Backend API
- ✅ `/backend/api/auth/register.php` - Inscription
- ✅ `/backend/api/auth/login.php` - Connexion
- ✅ `/backend/api/auth/logout.php` - Déconnexion

### Base de données
- ✅ Tables users, students, teachers
- ✅ Tables courses, enrollments
- ✅ Tables quizzes, assignments
- ✅ Tables forum, notifications
