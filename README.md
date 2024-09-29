# Test technique Alten Shop

## But du Projet

Ce projet est une API RESTful construite avec Symfony, permettant la gestion des produits dans un inventaire. Les utilisateurs peuvent effectuer des opérations de base telles que la création, la récupération, la mise à jour et la suppression de produits. L'API prend également en charge la recherche et la pagination des produits.

## Endpoints

### 1. Récupérer tous les produits
- **URL**: `/api/products`
- **Méthode**: `GET`
- **Paramètres**:
  - `page` (optionnel): Le numéro de la page à récupérer (défaut: 1)
  - `limit` (optionnel): Le nombre d'éléments par page (défaut: 10)
  - `search` (optionnel): Un terme de recherche pour filtrer les produits par nom, code ou catégorie.

### 2. Récupérer un produit par ID
- **URL**: `/api/products/{id}`
- **Méthode**: `GET`
- **Paramètres**:
  - `id`: L'ID du produit à récupérer.

### 3. Créer un nouveau produit
- **URL**: `/api/products`
- **Méthode**: `POST`
- **Corps de la Requête**:
  - `name`: Nom du produit.
  - `description`: Description du produit (optionnel).
  - `image`: Fichier image du produit (optionnel).
  - `category`: Catégorie du produit (optionnel).
  - `price`: Prix du produit (doit être positif).
  - `quantity`: Quantité du produit (doit être positif ou zéro).
  - `internalReference`: Référence interne du produit (optionnel).
  - `shellId`: ID de l'interface utilisateur (optionnel, défaut: 15).
  - `inventoryStatus`: Statut d'inventaire (`INSTOCK`, `LOWSTOCK`, `OUTOFSTOCK`).

### 4. Mettre à jour un produit existant
- **URL**: `/api/products/{id}`
- **Méthode**: `POST`
- **Corps de la Requête**: Identique à celui de la création d'un produit, avec les champs que vous souhaitez mettre à jour.

### 5. Supprimer un produit
- **URL**: `/api/products/{id}`
- **Méthode**: `DELETE`
- **Paramètres**:
  - `id`: L'ID du produit à supprimer.

# Instructions pour Configurer le Projet Localement

## Prérequis
- PHP 8.2
- Composer 2

## Instructions
1. **Configuration CORS**

Pour configurer CORS (Cross-Origin Resource Sharing) dans votre projet Symfony, vous devez modifier le fichier `config/packages/nelmio_cors.yaml`. Voici comment configurer CORS pour votre API :

. Ouvrez le fichier `config/packages/nelmio_cors.yaml`

. modifiez la configuration suivante :

```yaml
nelmio_cors:
    defaults:
        allow_credentials: true
        allow_origin: ['http://localhost:4200'] # Remplacez par l'URL de votre frontend si différente
        allow_headers: ['Content-Type', 'Authorization']
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
        expose_headers: ['Link']
        max_age: 3600
    paths:
        '^/api/':
            allow_origin: ['http://localhost:4200']
            allow_headers: ['Content-Type', 'Authorization']
            allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
            expose_headers: ['Link']
            max_age: 3600
```

2. **Cloner le dépôt et installer les dépendances**
   ```bash
   git clone https://github.com/anas919/shop-backend
   cd shop-backend
   composer install
   ```

3. **Configurer l'environnement**
   - Renommez le fichier `.env.example` en `.env`.
   - Modifiez les paramètres de connexion à la base de données dans le fichier `.env` selon vos besoins :
     ```
     DATABASE_URL="mysql://<mysql_user>:<mysql_password>@127.0.0.1:3306/<dbname>"
     ```

4. **Créer la base de données et appliquer les migrations**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Lancer le serveur de développement**
   ```bash
   symfony server:start
   ```

Vous pouvez maintenant accéder à l'API à l'adresse http://localhost:8000/api/products
