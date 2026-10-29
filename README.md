# BddSwap — Sélecteur de base de données pour Dolibarr ERP/CRM

**BddSwap** (ID module Eoxia : `436390`) est un module externe pour Dolibarr ERP/CRM permettant aux administrateurs et développeurs de basculer instantanément entre plusieurs bases de données Dolibarr présentes sur le même serveur MySQL/MariaDB en modifiant `$dolibarr_main_db_name` dans `htdocs/conf/conf.php`.

---

## Fonctionnalités

- **Sélecteur dans la barre supérieure (`printTopRightMenu`)** :
  - Liste déroulante affichant toutes les bases de données Dolibarr détectées sur le serveur MySQL/MariaDB ainsi que leur version (`MAIN_VERSION_LAST_UPGRADE` / `MAIN_VERSION_LAST_INSTALL`).
  - Bascule en un clic avec protection CSRF (`newToken()`).
  - Badge d'avertissement si la version majeure de la base diffère de la version des sources PHP (`DOL_VERSION`).
- **Sélecteur et alerte sur la page d'installation / mise à jour (`/install/check.php`)** :
  - Lorsque l'on bascule sur une base d'une version antérieure ou différente (ex. base en v22 ou v23 avec des scripts en v24) et que Dolibarr redirige vers `/install/check.php`, le sélecteur reste affiché sous le logo Dolibarr.
  - Affiche un bandeau d'alerte explicite :
    > **⚠ Attention : vous avez une BDD en X pour des scripts en Y ! Si vous faites la mise à jour, vous perdrez la BDD en X !**
  - Permet de revenir immédiatement sur une base compatible sans lancer la migration par erreur.
- **Page d'administration du module (`/custom/bddswap/admin/setup.php`)** :
  - Vue d'ensemble de toutes les bases Dolibarr disponibles (nom de la base, version Dolibarr, nom de la société `MAIN_INFO_SOCIETE_NOM`, statut actif/inactif et bouton de bascule).
- **Propagation automatique de l'activation** :
  - Lors du changement de base, les constantes `MAIN_MODULE_BDDSWAP*` sont automatiquement copiées dans la table `llx_const` de la base cible afin que le sélecteur reste actif sur toutes les bases.
  - Conserve les lignes `$dolibarr_main_db_name` commentées dans `htdocs/conf/conf.php`.
- **Traductions incluses** :
  - Français (`fr_FR`) et Anglais (`en_US`).

---

## Installation

1. Cloner le dépôt dans le dossier `htdocs/custom/` de votre installation Dolibarr :
   ```bash
   cd htdocs/custom
   git clone https://github.com/lmag/bddswap.git
   ```
2. Vérifier que le fichier `htdocs/conf/conf.php` autorise le dossier `custom` (`$dolibarr_main_url_root_alt` et `$dolibarr_main_document_root_alt`) et est accessible en écriture par le serveur web.
3. Dans Dolibarr, aller dans **Accueil → Configuration → Modules/Applications**, rechercher **BddSwap** (famille **Eoxia**) et activer le module.
4. *(Automatique après le premier switch)* Pour disposer également du sélecteur sur `/install/check.php`, BddSwap ajoute automatiquement à la fin de `htdocs/conf/conf.php` :
   ```php
   if (defined('DOL_INC_FOR_VERSION_ERROR') && file_exists(__DIR__.'/../custom/bddswap/lib/bddswap_install.inc.php')) {
   	include_once __DIR__.'/../custom/bddswap/lib/bddswap_install.inc.php';
   }
   ```

---

## Structure du module

```text
bddswap/
├── admin/
│   ├── about.php                  # Page À propos du module
│   └── setup.php                  # Page de configuration et liste des bases
├── class/
│   └── actions_bddswap.class.php  # Hook printTopRightMenu (sélecteur barre supérieure)
├── core/
│   └── modules/
│       └── modBddSwap.class.php   # Descripteur du module (ID 436390 - Eoxia)
├── css/
│   ├── bddswap.css                # Styles du sélecteur dans la barre supérieure
│   └── bddswap.css.php            # Wrapper CSS dynamique Dolibarr
├── js/
│   └── bddswap.js                 # Soumission automatique au changement de base
├── langs/
│   ├── en_US/
│   │   └── bddswap.lang           # Traductions anglaises
│   └── fr_FR/
│       └── bddswap.lang           # Traductions françaises
├── lib/
│   ├── bddswap.lib.php            # Détection des BDD et mise à jour de conf.php
│   └── bddswap_install.inc.php    # Injection du sélecteur et alerte sur /install/
├── swap.php                       # Endpoint POST sécurisé de changement de BDD
└── README.md
```

---

## Licence

GNU General Public License v3.0 ou ultérieure (`GPL-3.0-or-later`).
