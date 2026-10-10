# Mettre WYLDE en ligne sur OVHcloud (hébergement « Starter »)

Ce guide explique, pas à pas, comment mettre le site **WYLDE** en ligne sur un
hébergement mutualisé **OVHcloud (offre Eco « Starter »)**, avec le nom de
domaine **wylde-dakar.com**. Il ne suppose aucune expérience préalable en
hébergement : chaque action indique où cliquer dans l'espace client OVHcloud et
ce que vous devez obtenir.

> Durée totale estimée : **1 h 30 à 3 h**, hors attente de propagation
> (DNS/SSL, de quelques minutes à quelques heures).
> Gardez ce document ouvert : les sections **4**, **7** et **12** contiennent
> les points où l'on se trompe le plus souvent.

---

## Sommaire

0. [Ce qu'il faut avoir avant de commencer](#0-prérequis)
1. [Comprendre l'architecture du site](#1-comprendre-larchitecture)
2. [Commander l'hébergement et le domaine](#2-commander-hébergement--domaine)
3. [Rassembler les identifiants OVHcloud](#3-rassembler-les-identifiants)
4. [Préparer le paquet de fichiers](#4-préparer-le-paquet-de-fichiers)
5. [Envoyer les fichiers par FTP](#5-envoyer-les-fichiers-par-ftp)
6. [Créer la base de données MySQL](#6-créer-la-base-de-données)
7. [Importer le schéma et les données](#7-importer-le-schéma-et-les-données)
8. [Associer le domaine et activer le SSL](#8-associer-le-domaine-et-activer-le-ssl)
9. [Créer le fichier `.env` de production](#9-créer-le-fichier-env-de-production)
10. [Choisir la version de PHP](#10-choisir-la-version-de-php)
11. [Créer le compte administrateur](#11-créer-le-compte-administrateur)
12. [Vérifier la mise en ligne](#12-vérifier-la-mise-en-ligne)
13. [Sécurité, sauvegardes et maintenance](#13-sécurité-sauvegardes-maintenance)
14. [Mettre à jour le site plus tard](#14-mettre-à-jour-le-site)
15. [Dépannage](#15-dépannage)
- [Annexe A — Commandes utiles](#annexe-a--commandes-utiles)
- [Annexe B — Liens officiels OVHcloud](#annexe-b--liens-officiels-ovhcloud)
- [Annexe C — Glossaire](#annexe-c--glossaire)

---

## 0. Prérequis

Cochez tout avant de continuer :

- [ ] Un **compte client OVHcloud** (identifiant `xxxxxx-ovh`).
- [ ] Un **hébergement web** commandé sur l'offre **Eco → Starter**.
- [ ] Le **nom de domaine `wylde-dakar.com`** acheté (chez OVHcloud de
      préférence — c'est plus simple pour le DNS).
- [ ] Un logiciel FTP. **FileZilla** (gratuit) est recommandé :
      <https://filezilla-project.org>.
- [ ] PHP installé sur votre ordinateur (déjà présent dans Wamp/XAMPP sur ce
      projet : `C:\wamp64\bin\php\php8.4.20\php.exe`).
- [ ] Ce dépôt de projet, à jour, sur votre ordinateur.

Ce dont le Starter dispose (offre Eco) :

| Élément | Starter |
|---|---|
| Site(s) web | 1 |
| Base de données MySQL/MariaDB | 1 base (250 Mo) |
| Accès fichiers | FTP (pas de SSH) |
| Certificat SSL Let's Encrypt | Oui (gratuit) |
| Comptes e-mail | Inclus dans l'offre |

Aucune dépendance Composer n'est utilisée : **pas besoin de `composer install`**.

---

## 1. Comprendre l'architecture

Comme la majorité des applications PHP modernes, WYLDE sépare :

- **`public/`** : la seule partie **publique** du site (le « document root »).
  Elle contient `index.php` (point d'entrée) et les assets (CSS, JS, images).
- **le reste** (`app/`, `config/`, `resources/`, `routes/`, `storage/`,
  `database/`, `.env`) : le code et les **secrets**, qui ne doivent **jamais**
  être accessibles depuis un navigateur.

Sur le FTP OVHcloud, tout le projet ira donc dans le dossier `www/`, et l'on
indiquera à OVHcloud que le site doit être servi depuis le sous-dossier
**`public`**. C'est le rôle du **Multi-site** (section 8).

```
FTP OVHcloud
└── www/                     ← on dépose tout le projet ici
    ├── .env                 ← secrets (base de données, clé) — jamais public
    ├── app/  config/  resources/  routes/  storage/  database/
    └── public/              ← ⇦ document root du site (via Multi-site)
        ├── index.php
        ├── .htaccess
        └── assets/          ← CSS, JS, images
```

La base de données sera une base **MySQL OVHcloud**, distincte de votre base
locale `wylde`.

---

## 2. Commander hébergement + domaine

1. Connectez-vous à <https://www.ovh.com/manager>.
2. **Commander** → **Hébergements web** → gamme **Eco** → offre **Starter**.
3. Pendant la commande, choisissez **« connecter un domaine »** si vous avez
   déjà acheté `wylde-dakar.com` ; sinon enregistrez le domaine dans le même
   panier (onglet « Noms de domaine »).
4. Validez le paiement. Vous recevez ensuite un **e-mail avec vos accès FTP** :
   hôte, identifiant, mot de passe.

> Si le domaine a été acheté **ailleurs** qu'OVHcloud, il faudra plus tard
> changer ses **serveurs DNS** (section 8) pour pointner vers OVH.

---

## 3. Rassembler les identifiants

Créez un fichier local (jamais dans le dépôt Git) et notez-y les valeurs lues
dans le Manager. Les noms de serveur ne sont **pas** `localhost` ici — c'est
la principale différence avec votre machine.

| Information | Où la trouver dans le Manager | Exemple (le vôtre sera différent) |
|---|---|---|
| Domaine | Multi-site / Domaine | `wylde-dakar.com` |
| Hôte FTP | E-mail de création / onglet **FTP-SSH** | `ftp.cluster0XX.hosting.ovh.net` |
| Identifiant FTP | E-mail / **FTP-SSH** | `wylde-dakar` |
| Mot de passe FTP | Vous l'avez défini à la commande | `••••••••` |
| Hôte MySQL | Onglet **Bases de données** | `wylde-dakar.mysql.db` |
| Port MySQL | Onglet **Bases de données** | `3306` |
| Nom de la base | Onglet **Bases de données** | `wyldedakar` (souvent = utilisateur) |
| Utilisateur BDD | Onglet **Bases de données** | `wyldedakar` |
| Mot de passe BDD | Défini à la création de la base | `••••••••` |

> Note OVHcloud : le **nom de la base est identique au nom d'utilisateur**.
> Recopiez exactement ce que l'espace client affiche, ne le devinez pas.

---

## 3bis. (Rappel) Rien à compiler

Le projet n'utilise **aucune** bibliothèque externe. Ne cherchez pas de
`composer install` ni de `npm install` : tout le code est déjà dans le dépôt.

---

## 4. Préparer le paquet de fichiers

**Objectif :** produire une archive ZIP propre du site, sans secrets ni
fichiers inutiles (`/.env`, `/.git`, logs, anciens médias de test…), prête à
envoyer par FTP.

### 4.1 Générer l'archive

Ouvrez un terminal **à la racine du projet** et lancez le script fourni :

```powershell
C:\wamp64\bin\php\php8.4.20\php.exe tools\deploy-package.php
```

Résultat attendu :

```
  Archive créée : deploy-wylde.zip
  Fichiers      : 248
  Taille        : 578 Ko
  Médias        : exclus (--with-media pour les inclure)
```

Le fichier **`deploy-wylde.zip`** est créé à la racine du projet. Il contient
`app/`, `config/`, `public/`, `resources/`, `routes/`, `storage/`, `database/`,
`.env.example`, etc., **mais pas** votre `.env` local.

> Si vous souhaitez aussi embarquer les **photos produits** déjà présentes en
> local, ajoutez l'option `--with-media` :
> `php tools\deploy-package.php --with-media`

### 4.2 (Facultatif) Partir plutôt de Git

Si vous préférez construire le ZIP vous-même, il doit contenir tous les
dossiers ci-dessus **sauf** : `.git/`, `.env`, `vendor/`, `tests/`, le contenu
de `storage/logs/` et `storage/cache/`, et les médias de test.

### 4.3 Vérifier avant d'envoyer

- [ ] L'archive **ne contient pas** de fichier `.env`.
- [ ] Elle contient bien un dossier `public/` avec `index.php` et `.htaccess`.
- [ ] Vous avez généré les assets minifiés (sinon optionnel) :
      `php tools\minify.php`.

---

## 5. Envoyer les fichiers par FTP

### 4.2.1 Se connecter avec FileZilla

Ouvrez FileZilla → **Fichier → Gestionnaire de sites** → **Nouveau site** :

| Champ | Valeur |
|---|---|
| Protocole | FTP - File Transfer Protocol |
| Hôte | l'hôte FTP OVHcloud (ex. `ftp.cluster0XX.hosting.ovh.net`) |
| Port | `21` |
| Chiffrement | « FTP explicite sur TLS » si proposé, sinon « Utiliser le FTP simple » |
| Authentification | Normale |
| Identifiant | l'identifiant FTP OVHcloud |
| Mot de passe | votre mot de passe FTP |

Cliquez **Connexion**. Si Windows demande d'autoriser FileZilla à passer le
pare-feu, acceptez.

### 4.4 Envoyer le contenu

1. Dans le panneau **droit** (le serveur), ouvrez le dossier **`www`**.
   (S'il n'existe pas, vous êtes peut-être déjà dedans.)
2. Videz-le s'il contient le placeholder OVH `index.html` (optionnel, mais
   conseillé : on le supprimera au profit de notre `index.php`).
2. Faites glisser le **contenu** de `deploy-wylde.zip` (pas le zip lui-même)
   dans `www/`.

   **Méthode la plus fiable :** décompressez `deploy-wylde.zip` dans un dossier
   local, puis, dans FileZilla, sélectionnez tous les éléments
   (`app`, `config`, `public`, `resources`, `routes`, `storage`, `database`,
   `tools`, `.env.example`, `.gitignore`, `.htaccess`, `README.md`) et
   glissez-les dans `www/`.

   > ⚠️ `deploy-wylde.zip` est exclu automatiquement du paquet : ne l'envoyez
   > pas et ne le laissez pas traîner sur le serveur.

3. Attendez la fin des transferts (« Aucune file d'attente »). Le transfert
   peut prendre plusieurs minutes (plusieurs centaines de fichiers).

Résultat attendu côté serveur :

```
/www/index.php      ❌  (ne doit PAS exister à la racine)
/www/public/index.php  ✔
/www/app/            ✔
/www/.env.example    ✔   (.env viendra en section 9)
```

> **Ne mettez pas le contenu de `public/` directement dans `www/`.** Le code
> (`app/`, `config/`…) resterait introuvable et le site ne démarrerait pas.

---

## 5bis. Droits d'écriture du dossier `storage`

WYLDE écrit dans `storage/` (images produits, preuves de paiement, logs,
cache). Sur OVHcloud mutualisé, ces droits sont normalement déjà bons. Si plus
tard un envoi d'image échoue avec une erreur d'écriture, sélectionnez le
dossier `storage` dans FileZilla → clic droit → **Attributs du fichier** →
`705` (ou `755`), récursivement.

---

## 6. Créer la base de données

1. Espace client OVHcloud → **Web Cloud** → **Hébergements** → cliquez sur
   votre hébergement.
2. Onglet **Bases de données** (ou menu **Bases de données** → **Créer une
   base de données**).
3. Cliquez **Créer une base de données** et renseignez :
   - **Type de moteur** : **MySQL**.
   - **Version** : choisissez la plus récente proposée (MySQL 8.x, sinon la
     plus haute disponible).
   - **Taille** : selon votre besoin (l'offre inclut 250 Mo ; +100 Mo
     possibles, payants).
   - **Identifiant / Utilisateur** et **mot de passe** : notez-les
     soigneusement. Le nom de la base sera identique à l'utilisateur.
4. Validez. OVHcloud vous **affiche l'hôte du serveur** (ex.
   `wylde-dakar.mysql.db`), le port `3306`, le nom de la base et l'utilisateur.
   Notez-les dans la section 3.

> ⚠️ Deux pièges fréquents : (1) l'**hôte n'est pas `localhost`** ; (2) la
> **version du moteur ne peut plus être changée** après création.

Accès à phpMyAdmin : sur la page de la base, bouton/engrenage
**« Accéder à phpMyAdmin »**, ou lien direct depuis l'onglet Bases de données.
Connectez-vous avec l'utilisateur de la base.

---

## 7. Importer le schéma et les données

Vous allez importer **`database/schema.sql`** (la structure : tables, index…) —
c'est **obligatoire**. Puis, au choix, `database/seed.sql` (données de
démonstration).

### 7.1 Importer le schéma (obligatoire)

1. phpMyAdmin → sélectionnez votre base dans la colonne de gauche.
2. Onglet **Importer** (en haut).
3. **Choisir un fichier** → `database/schema.sql` de votre ordinateur.
4. Laissez les options par défaut, cliquez **Importer**.
5. Message attendu : *« Import terminé avec succès, N requêtes exécutées. »*

### 7.2 Importer les données — **choisissez UNE des deux options**

**Option A — Départ boutique vierge (recommandé pour un vrai lancement)**
: ne rien importer de plus. Vous ajouterez ensuite vos produits, photos, prix
depuis le back-office (section 11). La base contient alors uniquement la
structure + les zones de livraison créées par `schema.sql`.

**Option B — Catalogue de démonstration**
: importez aussi `database/seed.sql` (même procédure : onglet **Importer**).
Vous obtiendrez 6 produits de démo, les tailles et 3 zones de livraison.
Les produits de démo n'ont **pas** de photo (un visuel de remplacement
s'affiche) : il faudra les éditer dans le back-office pour ajouter de vraies
images.

> Vous **pouvez** combiner : importer le seed pour tester, puis remplacer les
> produits par les vôtres depuis le back-office.

### 6bis. Emporter votre base locale (facultatif)

Si votre catalogue/produits existent déjà dans votre base locale et que vous
voulez les conserver en ligne :

1. Sur votre ordinateur, exportez la base locale `wylde` en SQL (phpMyAdmin
   local → **Exporter** → au format SQL).
2. Importez ce fichier sur OVHcloud (onglet **Importer** de phpMyAdmin en
   ligne), **après** le schéma.
3. Copiez aussi les **images produits** : envoyez par FTP le contenu de
   `storage/uploads/products/` local vers
   `/www/storage/uploads/products/` sur le serveur.

> N'exportez/importez **pas** les lignes de `users` si l'administrateur doit
> être créé proprement à l'étape 11 (sinon adaptez : vous pouvez les garder).

---

## 7bis. Vérifier l'import

Dans phpMyAdmin, ouvrez la base créée : vous devez voir les tables `products`,
`product_variants`, `categories`, `orders`, `order_items`, `users`,
`shipping_zones`, etc.

---

## 8. Associer le domaine et activer le SSL

### 8.1 Multi-site : indiquer que le site est dans `public/`

1. Espace client → **Hébergements** → votre hébergement → onglet
   **Multi-site**.
2. Une entrée existe déjà pour **wylde-dakar.com** (créée automatiquement).
   Cliquez sur le crayon (**Modifier**) — ou **Ajouter un domaine** si elle
   n'existe pas.
3. Renseignez :
   - **Domaine** : `wylde-dakar.com`
   - **Dossier racine** : **`/www/public`**
   - Laissez les autres champs par défaut.
4. Validez. Répétez si besoin avec l'entrée `www.wylde-dakar.com` (même
   dossier racine).

> Le « dossier racine » est exprimé **depuis l'hébergement**, en incluant
> `www/`. Saisir `public` seul ferait pointer le site sur le mauvais dossier.

### 8.2 Vérifier le DNS

- **Domaine acheté chez OVHcloud** : la zone DNS pointe déjà vers
  l'hébergement — rien à faire.
- **Domaine acheté ailleurs** : dans l'espace du registrar, faites pointer les
  serveurs DNS vers ceux indiqués par OVHcloud (onglet **Zone DNS** ou
  **Domaines** → votre domaine → *Serveurs DNS*).

Propagation : de quelques minutes à **quelques heures**. Testez avec :

```powershell
nslookup wylde-dakar.com
```

Chaque ordinateur devrait répondre une adresse IP attribuée par OVHcloud.

### 8.3 Activer / vérifier le certificat SSL

1. Espace client → **Hébergements** → votre hébergement → onglet
   **Certificat SSL**.
2. Si le certificat **Let's Encrypt** n'est pas déjà actif :
   cliquez **Activer le certificat SSL** → choisissez **Let's Encrypt** →
   **Valider**. Attendez la délivrance (quelques minutes à quelques heures).
3. Depuis 2025, OVHcloud active automatiquement Let's Encrypt pour les
   nouveaux domaines : vérifiez simplement qu'il est bien **actif**.

### 8.4 Forcer HTTPS

Le fichier `public/.htaccess` livré **redirige déjà** `http://wylde-dakar.com`
vers `https://wylde-dakar.com`. Une fois le SSL valide :

- ouvrez <http://wylde-dakar.com> → l'adresse doit basculer en **https** ;
- en cas de certificat non valide d'abord, re-testez après propagation.

---

## 9. Créer le fichier `.env` de production

Le fichier `.env` contient les **secrets** (mot de passe de base de données,
clé de chiffrement). Il n'est pas dans le dépôt : vous le créez à la main.

### 9.1 Générer une clé d'application

Sur votre ordinateur :

```powershell
C:\wamp64\bin\php\php8.4.20\php.exe -r "echo base64_encode(random_bytes(32));"
```

Copiez la longue chaîne produite : elle servira de `APP_KEY`.

### 9.2 Créer le fichier en local

Créez un fichier texte nommé exactement **`.env`** (sans extension `.txt`) sur
votre bureau, avec ce contenu en **remplaçant les valeurs** :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wylde-dakar.com
APP_NAME="WYLDE"
APP_TIMEZONE=Africa/Dakar

APP_URL_FROM_REQUEST=false
APP_TRUSTED_HOSTS=wylde-dakar.com,www.wylde-dakar.com

APP_KEY=COLLEZ_ICI_LA_CLE_GENEREE

DB_HOST=wylde-dakar.mysql.db
DB_PORT=3306
DB_DATABASE=REMPLACEZ_PAR_LE_NOM_DE_LA_BASE
DB_USERNAME=REMPLACEZ_PAR_L_UTILISATEUR
DB_PASSWORD=REMPLACEZ_PAR_LE_MOT_DE_PASSE
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

CURRENCY=XOF
CURRENCY_POSITION=after
CURRENCY_DECIMALS=0
CURRENCY_SEPARATOR="\u{202F}"
STOCK_LOW_THRESHOLD=5

WAVE_PAYMENT_LINK=https://pay.wave.com/m/REMPLACEZ_PAR_VOTRE_LIEN

SESSION_SECURE=true
SESSION_LIFETIME=120
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
CSRF_LIFETIME=7200
RATE_LIMIT_MAX=60
RATE_LIMIT_WINDOW=60
```

Les points **obligatoires** en production :

| Variable | Valeur | Pourquoi |
|---|---|---|
| `APP_ENV` | `production` | Active les réglages de production |
| `APP_DEBUG` | `false` | N'affiche **jamais** les erreurs techniques aux visiteurs |
| `APP_URL` | `https://wylde-dakar.com` | Génère les liens absolus corrects |
| `APP_KEY` | votre clé | Chiffrement des sessions |
| `SESSION_SECURE` | `true` | Cookies uniquement en HTTPS |
| `DB_HOST` | l'hôte MySQL OVH | **Pas `127.0.0.1`** |
| `WAVE_PAYMENT_LINK` | votre lien Wave Business | Affiché au client au paiement |

> `WAVE_PAYMENT_LINK` : récupérez le lien de paiement dans votre application
> **Wave Business**. Laissez vide pour désactiver l'étape.

### 9.3 Envoyer le fichier

Dans FileZilla, allez dans `/www/` et envoyez votre **`.env`** (glisser-déposer).
Vérifiez que le fichier apparaît bien **à la racine de `www/`**, à côté de
`public/`, `app/`, `config/`.

> ⚠️ Le fichier `.env` commence par un point : s'il n'apparaît pas dans
> FileZilla, activez **Serveur → Forcer l'affichage des fichiers cachés**.

---

## 10. Choisir la version de PHP

1. Espace client → **Hébergements** → votre hébergement → onglet
   **Informations générales** (ou **Configuration** / **Version PHP**).
2. Choisissez l'**environnement** et la **version** de PHP :
   - **Environnement** : **Stable64** (nécessaire pour PHP 8.x).
   - **Version** : la plus récente proposée (PHP 8.1, 8.2, 8.3…).
2. Validez. Le changement peut prendre **quelques minutes** pour s'appliquer.

> WYLDE nécessite **PHP 8.1 ou supérieur**. En cas de page blanche après ce
> réglage, vérifiez d'abord que la version PHP est bien ≥ 8.1.

---

## 11. Créer le compte administrateur

L'offre Starter n'a **pas d'accès SSH**, donc le script
`database/create-admin.php` (en ligne de commande) ne peut pas être lancé sur
le serveur. On va donc créer le compte administrateur via phpMyAdmin.

### 11.1 Générer le mot de passe chiffré (sur votre ordinateur)

Les mots de passe sont hachés (jamais stockés en clair). Choisissez un mot de
passe répondant aux règles : **au moins 8 caractères, au moins une lettre et
un chiffre**. Puis :

```powershell
C:\wamp64\bin\php\php8.4.20\php.exe -r "echo password_hash('VotreMotDePasse123', PASSWORD_DEFAULT);"
```

Exemple de sortie :

```
$2y$10$9aBc...une très longue chaîne...Zx78
```

Copiez **toute** cette ligne (hash).

### 10.1 Créer le compte dans phpMyAdmin

1. Ouvrez **phpMyAdmin** sur votre base (section 6).
2. Onglet **SQL**.
3. Collez la requête suivante, en remplaçant le hash et l'e-mail :

```sql
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `status`)
VALUES (
  'Administrateur WYLDE',
  'admin@wylde-dakar.com',
  '$2y$10$9aBc...collez ici la chaine complete generee...Zx78',
  'admin',
  'active'
);
```

4. Cliquez **Exécuter**. Message attendu : `1 ligne insérée`.

Vous pouvez vous connecter sur **<https://wylde-dakar.com/admin/login>** avec
l'e-mail et le mot de passe choisis.

> **Alternative** si un jour vous passez sur une offre avec SSH (Perso/Pro) :
> lancez `php database/create-admin.php` sur le serveur — mais cela n'est pas
> possible ni nécessaire sur Starter.

### 10.2 Après la première connexion

- Retournez dans **Profil** (back-office) pour changer le mot de passe si
  celui-ci a transité par copier-coller, et renseignez vos informations.
- Ajoutez vos produits, catégories, zones de livraison et photos depuis le
  back-office (`/admin`).

---

## 11bis. Si vous avez importé la base locale

Si vous avez importé vos propres lignes `users` (section 6bis), connectez-vous
déjà avec votre ancien compte administrateur local, puis changez son mot de
passe depuis le back-office. Sinon, créez le compte comme ci-dessus.

---

## 12. Vérifier la mise en ligne

Parcourez ces contrôles **dans l'ordre**. Tout doit se comporter comme en
local.

### 12.1 Contrôles navigateur

- [ ] <https://wylde-dakar.com> s'affiche (accueil, images, CSS bien chargés).
- [ ] Le **cadenas** HTTPS est présent ; l'URL reste en `https` (pas de
      message « contenu mixte »).
- [ ] Une page produit s'ouvre (photo, prix en **FCFA**).
- [ ] **Ajouter au panier** puis **commande** : le tunnel de commande se
      déroule jusqu'au bout.
- [ ] L'étape **paiement Wave** affiche le bon lien (`WAVE_PAYMENT_LINK`).
- [ ] L'envoi d'une **preuve de paiement** fonctionne.
- [ ] La page de **suivi** `/order/tracking/<référence>` se charge.
- [ ] <https://wylde-dakar.com/admin/login> permet la connexion.
- [ ] Dans la console développeur (F12 → onglet **Console**), aucune erreur
      rouge.

### 12.2 Contrôles serveur

- [ ] **Aucune erreur PHP visible** à l'écran (normal : `APP_DEBUG=false`).
- [ ] Les médias s'affichent (`/media/...`) : le dossier
      `/www/storage/uploads/` est accessible en écriture.
- [ ] Consultez les journaux en cas de doute : `/www/storage/logs/` (via FTP).

### 12.3 Référencement (facultatif)

- [ ] <https://wylde-dakar.com/sitemap.xml> répond.
- [ ] Déclarez le sitemap dans **Google Search Console**.

---

## 13. Sécurité, sauvegardes, maintenance

### 13.1 À faire

- [ ] Ne **jamais** activer `APP_DEBUG=true` en production.
- [ ] Changer le mot de passe administrateur après la première connexion.
- [ ] Activer la **double authentification (2FA)** sur le compte OVHcloud.
- [ ] Sauvegarder régulièrement (voir 13.2).
- [ ] Ne jamais partager le fichier `.env` ni mot de passe de base de données.

### 13.2 Sauvegardes

- **Base de données** : phpMyAdmin → base → onglet **Exporter** → méthode
  *Rapide* → format *SQL* → **Exécuter**. Conservez le `.sql` téléchargé.
  (À faire après chaque modification importante du catalogue.)
- **Fichiers** : téléchargez par FTP le contenu de
  `/www/storage/uploads/` (images produits et preuves de paiement).
- OVHcloud conserve aussi des sauvegardes automatiques de l'hébergement dans
  l'onglet dédié ; ne vous y fiez pas seul.

### 13.3 Restauration

1. Réimportez la dernière sauvegarde `.sql` (phpMyAdmin → **Importer**).
2. Re-déposez le dossier `storage/uploads/` sauvegardé par FTP.

---

## 14. Mettre à jour le site

Lorsque vous modifiez le site (code), ne renvoyez **pas** tout au hasard :

1. Envoyez uniquement les fichiers **modifiés** par FTP, en respectant
   l'arborescence (`/www/app/...`, `/www/public/...`).
2. **N'écrasez jamais** `/www/.env`, ni `/www/storage/uploads/`.
3. Si vous utilisez `tools/deploy-package.php`, régénérez l'archive, mais
   avant de la re-décompresser, **conservez `.env`** et le dossier
   `storage/uploads/` en place.
4. Après une mise à jour du schéma de base, réimportez la modification
   correspondante dans phpMyAdmin.

---

## 15. Dépannage

| Symptôme | Cause probable | Solution |
|---|---|---|
| Page blanche / erreur 500 | `APP_DEBUG=false` masque l'erreur | Ouvrir `/www/storage/logs/` en FTP pour lire l'erreur ; vérifier `.env` |
| « Internal Server Error » à l'installation | Mauvaise **version PHP** (< 8) | Section 10 : choisir PHP 8.x |
| « Unknown database » / « Access denied » | `DB_*` faux, ou hôte `localhost` | Corriger `.env` avec l'hôte **MySQL OVH** (pas `127.0.0.1`) |
| Aller sur le domaine affiche la liste des fichiers ou une page OVH | Document root mal réglé | Section 8 : **Dossier racine = `/www/public`** |
| CSS/JS non chargés (page « brute ») | Assets `*.min.css/js` absents, ou cache navigateur | Régénérer `tools/minify.php` localement puis renvoyer ; vider le cache (Ctrl+F5) |
| `http://` ne bascule pas en `https://` | SSL non délivré, ou règle `.htaccess` non déployée | Section 8.3/8.4 ; vérifier que `public/.htaccess` contient bien la règle HTTPS |
| Impossible d'ajouter une image produit | Droits d'écriture de `storage/` | Section 5bis : chmod `storage` en `705`/`755` |
| « Too many connections » | Trop de connexions simultanées à la base | Réduire le nombre de scripts ouverts ; normalement sans objet en usage courant |
| Erreur au login admin | Mot de passe/hash mal collé dans la requête SQL | Régénérer le hash (section 11.1) et refaire l'`INSERT` |

---

## Annexe A — Commandes utiles

**Générer la clé d'application :**

```powershell
C:\wamp64\bin\php\php8.4.20\php.exe -r "echo base64_encode(random_bytes(32));"
```

**Générer un hash de mot de passe pour l'admin :**

```powershell
C:\wamp64\bin\php\php8.4.20\php.exe -r "echo password_hash('VotreMotDePasse123', PASSWORD_DEFAULT);"
```

**Créer le paquet de déploiement :**

```powershell
C:\wamp64\bin\php\php8.4.20\php.exe tools\deploy-package.php
```

**Créer le paquet en incluant les images produits locales :**

```powershell
C:\wamp64\bin\php\php8.4.20\php.exe tools\deploy-package.php --with-media
```

**Vérifier le DNS du domaine :**

```powershell
nslookup wylde-dakar.com
```

---

## Annexe B — Liens officiels OVHcloud

- Espace client : <https://www.ovh.com/manager>
- Premiers pas avec un hébergement web :
  <https://help.ovhcloud.com/csm/fr-documentation-web-cloud-web-hosting>
- Ajouter / modifier un Multi-site :
  <https://help.ovhcloud.com/csm/fr-web-hosting-multisite>
- Créer une base de données :
  <https://help.ovhcloud.com/csm/fr-web-hosting-sql-create-database>
- Activer le HTTPS sur un site web :
  <https://help.ovhcloud.com/csm/fr-web-hosting-ssl-https>
- Gérer les serveurs DNS / la zone DNS :
  <https://help.ovhcloud.com/csm/fr-web-hosting-change-dns-servers>

---

## Annexe C — Glossaire

| Terme | Définition |
|---|---|
| **Hébergement mutualisé** | Serveur partagé par plusieurs sites ; vous n'avez que le FTP et le Manager, pas la main sur le système. |
| **FTP / SFTP** | Protocole d'envoi de fichiers entre votre ordinateur et le serveur. |
| **Document root (racine web)** | Dossier dont le contenu est réellement exposé sur Internet. Ici : `public/`. |
| **Multi-site (OVHcloud)** | Réglage qui associe un domaine à un dossier du serveur. |
| **DNS** | Annuaire qui traduit `wylde-dakar.com` en adresse IP du serveur. |
| **SSL / HTTPS** | Chiffrement de la connexion (cadenas) ; certificat gratuit Let's Encrypt. |
| **`.env`** | Fichier de configuration secret (mot de passe base, clé). Jamais public. |
| **Migration** | Script SQL qui crée la structure de la base. |

---

*Document généré pour le projet WYLDE. En cas de doute, la règle d'or :
`APP_DEBUG=false` en production, et ne jamais exposer le fichier `.env`.*
