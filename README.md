# Gestion des vignettes carburant — Lot 1

Application web multi-utilisateurs remplaçant le suivi Excel des vignettes carburant
(vignettes papier et e-vignettes). Ce lot couvre le **socle sécurité / accès**
(authentification, rôles, journal d'audit) et les **référentiels** (véhicules,
bénéficiaires, types de vignette, motifs, fournisseurs, exercices, services, sites),
avec import initial Excel/CSV.

## Architecture

| Composant | Technologie | Dossier |
|---|---|---|
| API back-end | Laravel (PHP 8.2+), sessions serveur via Sanctum SPA | `backend/` |
| Front-end | React 19 + Vite (SPA, français) | `frontend/` |
| Base de données | PostgreSQL 15+ (extensions `citext`, `unaccent`, `fuzzystrmatch`) | — |

Sécurité intégrée : Argon2id, verrouillage après 5 échecs (15 min), limitation par IP,
2FA TOTP + codes de secours, session inactivité 30 min (paramétrable) et durée maximale
12 h, changement de mot de passe forcé à la première connexion, interdiction des
3 derniers mots de passe, journal d'audit en ajout seul (trigger PostgreSQL),
permissions atomiques `domaine.action` vérifiées sur chaque route, CSRF, en-têtes
de sécurité, suppression logique uniquement.

## Installation (développement)

Prérequis : PHP 8.2+ (extensions `pdo_pgsql`, `intl`, `mbstring`, `fileinfo`),
Composer, Node 20+, PostgreSQL 15+.

```bash
# 1. Base de données
psql -U postgres -c "CREATE DATABASE vignettes_carburant ENCODING 'UTF8';"

# 2. Back-end
cd backend
composer install
cp .env.example .env        # puis renseigner DB_USERNAME / DB_PASSWORD
php artisan key:generate
php artisan migrate --seed  # schéma + rôles, permissions, motifs, types, exercice 2026
php artisan serve           # http://localhost:8000

# 3. Front-end (autre terminal)
cd frontend
npm install
npm run dev                 # http://localhost:5173
```

Compte initial : identifiant `admin`, mot de passe `Admin#2026!vignettes`
(ou `SEED_ADMIN_PASSWORD` du `.env`). **Le changement de mot de passe est forcé
à la première connexion**, puis l'enrôlement 2FA (obligatoire pour le rôle
Administrateur).

## Tests

```bash
psql -U postgres -c "CREATE DATABASE vignettes_carburant_test ENCODING 'UTF8';"
cd backend
php artisan test
```

La suite couvre les critères d'acceptation du cahier des charges : 403 en appel
direct de l'API pour le rôle Consultation, verrouillage après 6 tentatives, jeton
de réinitialisation à usage unique, dernier administrateur non désactivable,
unicité garantie par la base, avertissement de similarité (M214134 / M2214134),
recherche insensible aux accents, audit avant/après, import atomique.

## Déploiement (production)

1. `APP_ENV=production`, `APP_DEBUG=false`, HTTPS obligatoire,
   `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN` et `SANCTUM_STATEFUL_DOMAINS`
   sur le domaine réel, SMTP configuré.
2. `composer install --no-dev --optimize-autoloader` puis
   `php artisan config:cache route:cache`.
3. `php artisan migrate --seed --force`.
4. `cd frontend && npm run build` → servir `dist/` (nginx/Apache) ; proxy `/api`
   et `/sanctum` vers le back-end.
5. Vérifier les en-têtes de sécurité (HSTS activé automatiquement en production).

## Documentation

- [docs/openapi.yaml](docs/openapi.yaml) — spécification OpenAPI de l'API REST
- [docs/MANUEL_ADMINISTRATEUR.md](docs/MANUEL_ADMINISTRATEUR.md) — manuel du rôle Administrateur
- `backend/.env.example` — variables d'environnement documentées

## Périmètre

**Inclus (lot 1)** : authentification complète, gestion des utilisateurs, RBAC
4 rôles / permissions atomiques, journal d'audit, tous les référentiels, import
Excel/CSV, exports Excel.

**Lot 2 (préparé, non inclus)** : entrées/sorties de stock, workflow de
validation, tableau de bord, clôture d'exercice, éditions PDF. Les permissions
(`sortie.valider`…), motifs à indicateurs et exercices sont déjà en place.
