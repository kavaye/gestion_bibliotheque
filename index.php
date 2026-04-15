<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$libFunc = new LibraryFunctions($db);

// Récupérer les livres populaires
$popularBooks = $libFunc->getPopularBooks(6);

// Récupérer les dernières nouveautés
$query = "SELECT * FROM books ORDER BY created_at DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$newBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories
$query = "SELECT * FROM categories";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le nombre de livres par catégorie
$query = "SELECT category_id, COUNT(*) as count FROM books GROUP BY category_id";
$stmt = $db->prepare($query);
$stmt->execute();
$category_counts = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $category_counts[$row['category_id']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque - Accueil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            border-radius: 20px;
            margin-bottom: 40px;
        }
        
        .choice-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-choice {
            padding: 15px 40px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 50px;
            transition: transform 0.3s;
        }
        
        .btn-choice:hover {
            transform: translateY(-5px);
        }
        
        .btn-user {
            background: white;
            color: #667eea;
            border: 2px solid white;
        }
        
        .btn-user:hover {
            background: transparent;
            color: white;
        }
        
        .btn-admin {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-admin:hover {
            background: white;
            color: #667eea;
        }
        
        /* Styles pour les sections déroulantes */
        .dropdown-section {
            margin-bottom: 30px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .dropdown-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            user-select: none;
        }
        
        .dropdown-header:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a3f8f 100%);
            transform: scale(1.01);
        }
        
        .dropdown-header h2 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dropdown-header h2 i {
            font-size: 1.8rem;
        }
        
        .dropdown-icon {
            font-size: 1.5rem;
            transition: transform 0.3s ease;
        }
        
        .dropdown-icon.rotated {
            transform: rotate(180deg);
        }
        
        .dropdown-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out;
            background: white;
        }
        
        .dropdown-content.show {
            max-height: 2000px;
            transition: max-height 0.8s ease-in;
        }
        
        .content-inner {
            padding: 30px;
        }
        
        .book-card {
            transition: all 0.3s;
            height: 100%;
            cursor: pointer;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .category-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: #f8f9fa;
            transition: all 0.3s;
            cursor: pointer;
            height: 100%;
        }
        
        .category-card:hover {
            background: #667eea;
            color: white;
            transform: translateY(-5px);
        }
        
        .category-card:hover i,
        .category-card:hover h6,
        .category-card:hover p {
            color: white;
        }
        
        .category-card i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .category-card h6 {
            margin: 10px 0 5px;
            font-weight: bold;
        }
        
        .category-card p {
            font-size: 0.85rem;
            color: #6c757d;
            margin: 0;
        }
        
        .stat-box {
            text-align: center;
            padding: 20px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        
        .welcome-message {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 50px 0;
            border-radius: 20px;
            margin: 40px 0;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .book-card, .category-card {
            animation: fadeInUp 0.5s ease-out;
        }
        
        @media (max-width: 768px) {
            .dropdown-header h2 {
                font-size: 1.2rem;
            }
            
            .content-inner {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-book"></i> Bibliothèque
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home"></i> Accueil
                        </a>
                    </li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user/search.php">
                                <i class="fas fa-search"></i> Rechercher
                            </a>
                        </li>
                        <?php if($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'librarian'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/index.php">
                                    <i class="fas fa-cog"></i> Administration
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="user/profile.php">Mon profil</a></li>
                                <li><a class="dropdown-item" href="user/my-loans.php">Mes emprunts</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Déconnexion</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main>
        <!-- Hero Section avec choix de connexion -->
        <div class="hero-section">
            <div class="container text-center">
                <h1 class="display-3 mb-4">
                    <i class="fas fa-book-open"></i> Bienvenue à la Bibliothèque
                </h1>
                <p class="lead mb-4">Découvrez notre collection de livres et gérez vos emprunts en ligne.</p>
                
                <?php if(!isset($_SESSION['user_id'])): ?>
                    <div class="choice-buttons">
                        <a href="user/login.php" class="btn btn-choice btn-user">
                            <i class="fas fa-user"></i> Espace Utilisateur
                        </a>
                        <a href="admin/login.php" class="btn btn-choice btn-admin">
                            <i class="fas fa-user-shield"></i> Espace Administrateur
                        </a>
                    </div>
                    <div class="text-center mt-3">
                        <small>
                            <a href="user/register.php" class="text-white">Créer un compte utilisateur</a>
                            &nbsp;|&nbsp;
                            <a href="user/register-admin.php" class="text-white">
                                <i class="fas fa-key"></i> Devenir administrateur (code requis)
                            </a>
                        </small>
                    </div>
                <?php else: ?>
                    <div class="welcome-message">
                        <h3>Bonjour, <?php echo $_SESSION['user_name']; ?> !</h3>
                        <p class="mb-3">Bienvenue dans votre espace personnel.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="user/search.php" class="btn btn-light">
                                <i class="fas fa-search"></i> Rechercher des livres
                            </a>
                            <a href="user/my-loans.php" class="btn btn-outline-light">
                                <i class="fas fa-book"></i> Mes emprunts
                            </a>
                            <?php if($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'librarian'): ?>
                                <a href="admin/index.php" class="btn btn-warning">
                                    <i class="fas fa-cog"></i> Administration
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="container">
            <!-- Statistiques -->
            <div class="stats-section">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <i class="fas fa-book fa-3x text-primary mb-3"></i>
                            <div class="stat-number"><?php echo $libFunc->getTotalBooks(); ?></div>
                            <p class="text-muted">Livres disponibles</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <i class="fas fa-users fa-3x text-success mb-3"></i>
                            <div class="stat-number"><?php echo $libFunc->getTotalMembers(); ?></div>
                            <p class="text-muted">Membres actifs</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <i class="fas fa-exchange-alt fa-3x text-info mb-3"></i>
                            <div class="stat-number"><?php echo $libFunc->getActiveLoans(); ?></div>
                            <p class="text-muted">Emprunts en cours</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                            <div class="stat-number"><?php echo $libFunc->getOverdueLoans(); ?></div>
                            <p class="text-muted">Emprunts en retard</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Livres les plus populaires (déroulante) -->
            <div class="dropdown-section" id="popularBooksSection">
                <div class="dropdown-header" onclick="toggleDropdown('popularBooks')">
                    <h2>
                        <i class="fas fa-fire"></i> 
                        Livres les plus populaires
                        <span class="badge bg-light text-dark ms-2"><?php echo count($popularBooks); ?> livres</span>
                    </h2>
                    <i class="fas fa-chevron-down dropdown-icon" id="popularBooksIcon"></i>
                </div>
                <div class="dropdown-content" id="popularBooksContent">
                    <div class="content-inner">
                        <div class="row">
                            <?php foreach($popularBooks as $book): ?>
                                <div class="col-md-2 col-6 mb-3">
                                    <div class="book-card card h-100" onclick="window.location.href='user/book-details.php?id=<?php echo $book['id']; ?>'">
                                        <div class="card-body text-center">
                                            <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                            <h6 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h6>
                                            <p class="card-text small text-muted">
                                                <?php echo htmlspecialchars($book['author']); ?>
                                            </p>
                                            <div class="mt-2">
                                                <span class="badge bg-success">
                                                    <?php echo $book['available_quantity']; ?>/<?php echo $book['quantity']; ?>
                                                </span>
                                                <br>
                                                <small class="text-warning">
                                                    <i class="fas fa-chart-line"></i> <?php echo $book['loan_count']; ?> emprunts
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="user/search.php" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i> Voir tous les livres
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Catégories populaires (déroulante) -->
            <div class="dropdown-section" id="categoriesSection">
                <div class="dropdown-header" onclick="toggleDropdown('categories')">
                    <h2>
                        <i class="fas fa-tags"></i> 
                        Catégories populaires
                        <span class="badge bg-light text-dark ms-2"><?php echo count($categories); ?> catégories</span>
                    </h2>
                    <i class="fas fa-chevron-down dropdown-icon" id="categoriesIcon"></i>
                </div>
                <div class="dropdown-content" id="categoriesContent">
                    <div class="content-inner">
                        <div class="row">
                            <?php foreach($categories as $cat): 
                                $bookCount = isset($category_counts[$cat['id']]) ? $category_counts[$cat['id']] : 0;
                            ?>
                                <div class="col-md-2 col-4 mb-3">
                                    <div class="category-card" onclick="window.location.href='user/search.php?category=<?php echo $cat['id']; ?>'">
                                        <i class="fas fa-folder-open"></i>
                                        <h6><?php echo htmlspecialchars($cat['name']); ?></h6>
                                        <p><?php echo $bookCount; ?> livre(s)</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="user/search.php" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i> Explorer toutes les catégories
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Nouveautés (déroulante) -->
            <div class="dropdown-section" id="newBooksSection">
                <div class="dropdown-header" onclick="toggleDropdown('newBooks')">
                    <h2>
                        <i class="fas fa-star"></i> 
                        Nouveautés
                        <span class="badge bg-light text-dark ms-2"><?php echo count($newBooks); ?> nouveaux livres</span>
                    </h2>
                    <i class="fas fa-chevron-down dropdown-icon" id="newBooksIcon"></i>
                </div>
                <div class="dropdown-content" id="newBooksContent">
                    <div class="content-inner">
                        <div class="row">
                            <?php foreach($newBooks as $book): ?>
                                <div class="col-md-2 col-6 mb-3">
                                    <div class="book-card card h-100" onclick="window.location.href='user/book-details.php?id=<?php echo $book['id']; ?>'">
                                        <div class="card-body text-center">
                                            <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                            <h6 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h6>
                                            <p class="card-text small text-muted">
                                                <?php echo htmlspecialchars($book['author']); ?>
                                            </p>
                                            <div class="mt-2">
                                                <span class="badge bg-info">
                                                    <i class="fas fa-calendar"></i> <?php echo $book['publication_year']; ?>
                                                </span>
                                                <br>
                                                <span class="badge bg-success mt-1">
                                                    <?php echo $book['available_quantity']; ?>/<?php echo $book['quantity']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="user/search.php?sort=new" class="btn btn-outline-primary">
                                <i class="fas fa-star"></i> Voir toutes les nouveautés
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section des fonctionnalités -->
            <div class="row mb-5 mt-5">
                <div class="col-md-12">
                    <h2 class="text-center mb-4">Nos Services</h2>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card text-center p-4 bg-light rounded">
                        <i class="fas fa-search fa-3x text-primary mb-3"></i>
                        <h4>Recherche avancée</h4>
                        <p>Trouvez facilement les livres que vous cherchez par titre, auteur ou catégorie.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card text-center p-4 bg-light rounded">
                        <i class="fas fa-hand-holding fa-3x text-primary mb-3"></i>
                        <h4>Emprunts en ligne</h4>
                        <p>Empruntez des livres directement depuis notre site web.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card text-center p-4 bg-light rounded">
                        <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                        <h4>Suivi en temps réel</h4>
                        <p>Suivez vos emprunts et gérez vos retours facilement.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Bibliothèque</h5>
                    <p>Votre bibliothèque en ligne pour la gestion et l'emprunt de livres.</p>
                </div>
                <div class="col-md-4">
                    <h5>Liens utiles</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white-50">Accueil</a></li>
                        <?php if(!isset($_SESSION['user_id'])): ?>
                            <li><a href="user/login.php" class="text-white-50">Connexion Utilisateur</a></li>
                            <li><a href="admin/login.php" class="text-white-50">Connexion Administrateur</a></li>
                            <li><a href="user/register.php" class="text-white-50">Inscription</a></li>
                        <?php else: ?>
                            <li><a href="user/search.php" class="text-white-50">Rechercher</a></li>
                            <li><a href="user/my-loans.php" class="text-white-50">Mes emprunts</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Horaires</h5>
                    <p>Lundi - Vendredi: 9h - 18h<br>
                    Samedi: 9h - 12h<br>
                    Dimanche: Fermé</p>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2024 Bibliothèque. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Fonction pour basculer l'affichage des sections déroulantes
        function toggleDropdown(section) {
            const content = document.getElementById(`${section}Content`);
            const icon = document.getElementById(`${section}Icon`);
            
            if (content.classList.contains('show')) {
                content.classList.remove('show');
                icon.classList.remove('rotated');
            } else {
                content.classList.add('show');
                icon.classList.add('rotated');
            }
        }
        
        // Ouvrir la première section par défaut (optionnel)
        document.addEventListener('DOMContentLoaded', function() {
            // Optionnel: ouvrir la section des livres populaires par défaut
            // toggleDropdown('popularBooks');
            
            // Stocker l'état des sections dans localStorage
            const savedState = localStorage.getItem('dropdownState');
            if (savedState) {
                const state = JSON.parse(savedState);
                if (state.popularBooks) toggleDropdown('popularBooks');
                if (state.categories) toggleDropdown('categories');
                if (state.newBooks) toggleDropdown('newBooks');
            }
        });
        
        // Sauvegarder l'état des sections quand l'utilisateur les ferme/ouvre
        function saveDropdownState() {
            const state = {
                popularBooks: document.getElementById('popularBooksContent').classList.contains('show'),
                categories: document.getElementById('categoriesContent').classList.contains('show'),
                newBooks: document.getElementById('newBooksContent').classList.contains('show')
            };
            localStorage.setItem('dropdownState', JSON.stringify(state));
        }
        
        // Écouter les changements sur les sections
        const sections = ['popularBooks', 'categories', 'newBooks'];
        sections.forEach(section => {
            const content = document.getElementById(`${section}Content`);
            const observer = new MutationObserver(saveDropdownState);
            observer.observe(content, { attributes: true, attributeFilter: ['class'] });
        });
        
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.dropdown-section');
            sections.forEach((section, index) => {
                section.style.animation = `fadeInUp 0.5s ease-out ${index * 0.1}s forwards`;
                section.style.opacity = '0';
                section.style.animationFillMode = 'forwards';
            });
        });
        
        // Fonction pour ouvrir une section spécifique depuis un lien externe (optionnel)
        function openSection(section) {
            const content = document.getElementById(`${section}Content`);
            const icon = document.getElementById(`${section}Icon`);
            if (!content.classList.contains('show')) {
                content.classList.add('show');
                icon.classList.add('rotated');
            }
            // Faire défiler jusqu'à la section
            document.getElementById(`${section}Section`).scrollIntoView({ behavior: 'smooth' });
        }
        
        // Ajouter des effets de survol sur les cartes
        document.querySelectorAll('.book-card, .category-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.transition = 'all 0.3s';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Gestion du responsive pour les cartes
        function adjustCardsLayout() {
            const screenWidth = window.innerWidth;
            const cards = document.querySelectorAll('.book-card');
            if (screenWidth < 768) {
                cards.forEach(card => {
                    card.style.fontSize = '0.9rem';
                });
            } else {
                cards.forEach(card => {
                    card.style.fontSize = '1rem';
                });
            }
        }
        
        window.addEventListener('resize', adjustCardsLayout);
        adjustCardsLayout();
    </script>
</body>
</html>