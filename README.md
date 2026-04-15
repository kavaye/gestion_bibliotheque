# Système de Gestion de Bibliothèque

Une application web complète pour la gestion d'une bibliothèque.

## Fonctionnalités

### Gestion des Livres
- Ajouter, modifier et supprimer des livres
- Rechercher des livres par titre, auteur ou ISBN
- Visualiser le statut des livres (disponible/emprunté)

### Gestion des Membres
- Inscrire de nouveaux membres
- Modifier les informations des membres
- Supprimer des membres
- Rechercher des membres

### Gestion des Emprunts
- Enregistrer de nouveaux emprunts
- Gérer les retours de livres
- Visualiser les emprunts en cours et en retard
- Voir l'historique des emprunts

### Statistiques
- Top 5 des livres les plus empruntés
- Graphique des emprunts par mois
- Statistiques globales

## Technologies Utilisées

- HTML5
- CSS3
- JavaScript (ES6)
- Chart.js pour les graphiques
- Font Awesome pour les icônes
- LocalStorage pour la persistance des données

## Installation

1. Clonez ce repository
2. Ouvrez le fichier `index.html` dans un navigateur web
3. Aucune installation supplémentaire n'est requise

## Utilisation

### Navigation
- Utilisez les onglets pour naviguer entre les différentes sections
- Les données sont automatiquement sauvegardées dans le localStorage

### Ajout de Livres
1. Allez dans l'onglet "Livres"
2. Cliquez sur "Ajouter un livre"
3. Remplissez le formulaire
4. Cliquez sur "Enregistrer"

### Gestion des Emprunts
1. Allez dans l'onglet "Emprunts"
2. Cliquez sur "Nouvel emprunt"
3. Sélectionnez un livre disponible et un membre
4. Définissez la date de retour
5. Confirmez l'emprunt

### Retour de Livres
- Dans l'onglet "Emprunts", cliquez sur "Retourner" pour le livre concerné

## Structure des Données

### Livre
- id (auto-incrémenté)
- titre
- auteur
- ISBN
- année de publication
- statut (disponible/emprunté)

### Membre
- id (auto-incrémenté)
- nom complet
- email
- téléphone
- date d'inscription
- liste des livres empruntés

### Emprunt
- id (auto-incrémenté)
- id du livre
- id du membre
- date d'emprunt
- date de retour prévue
- date de retour effective
- statut (actif/retourné)

## Fonctionnalités à Développer

- [ ] Authentification des utilisateurs
- [ ] Export des données en CSV/PDF
- [ ] Notifications par email pour les retards
- [ ] Génération de rapports personnalisés
- [ ] Interface multilingue
- [ ] Mode sombre
- [ ] Sauvegarde en ligne (backend)

## Auteur

Développé par [GANDI HASSAN KAVAYE ALPHONSE ]

## Licence

MIT
