<?php
// ==========================================================
// gestion.php — moteur commun de gestion de dossiers/fichiers
// Utilisé par cours.php, tp.php, projets.php et documents.php
// ==========================================================

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Les mots de passe ne sont PLUS dans le code : ils sont dans config.php
// (fichier ignoré par git, à créer sur le serveur à partir de config.example.php).
$fichierConfig = __DIR__ . '/config.php';
if (!is_file($fichierConfig)) {
    http_response_code(500);
    exit('Configuration manquante : copie config.example.php en config.php et définis tes mots de passe.');
}
require $fichierConfig;
define('MOT_DE_PASSE_ADMIN', $motDePasseAdmin);
define('MOT_DE_PASSE_MODERATEUR', $motDePasseProf);
// Mot de passe du Sanctuaire (jeux.php). Vide ou absent de config.php = réservé à l'admin.
define('MOT_DE_PASSE_JEU', (string) ($motDePasseJeu ?? ''));

// Catégories autorisées (sécurité : on n'accepte pas n'importe quel nom)
$CATEGORIES_AUTORISEES = ['cours', 'tp', 'projets', 'documents'];

$RACINE_FICHIERS = __DIR__ . '/fichiers/';
if (!is_dir($RACINE_FICHIERS)) {
    mkdir($RACINE_FICHIERS, 0755, true);
}

define('TAILLE_MAX_FICHIER', 10 * 1024 * 1024); // 10 Mo, PDF uniquement

// Le dossier fichiers/ ne doit servir que des PDF (jamais de PHP) : on écrit son .htaccess si besoin.
if (!is_file($RACINE_FICHIERS . '.htaccess')) {
    @file_put_contents($RACINE_FICHIERS . '.htaccess',
        "Options -Indexes -ExecCGI\n"
      . "RemoveHandler .php .phtml .php3 .php4 .php5 .phar\n"
      . "<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n"
      . "Require all denied\n"
      . "<FilesMatch \"\\.(?i:pdf)$\">\nRequire all granted\n</FilesMatch>\n");
}

function nettoyerNom($nom) {
    $nom = trim($nom);
    $nom = preg_replace('/[^A-Za-z0-9_\- ]/', '', $nom);
    return $nom;
}

// ----------------------------------------------------------
// Authentification
// ----------------------------------------------------------

// Renvoie true si l'utilisateur est actuellement connecté (admin OU modérateur)
function estConnecte() {
    return !empty($_SESSION['role']);
}

// Renvoie true seulement si l'utilisateur connecté est l'admin (toi)
function estAdmin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

// Anti brute-force : au bout de 5 mots de passe faux d'affilée, on bloque
// les nouvelles tentatives pendant 5 minutes.
define('TENTATIVES_MAX', 5);
define('BLOCAGE_SECONDES', 300);

function estBloque() {
    $tentatives = $_SESSION['tentatives_ratees'] ?? 0;
    $depuis = $_SESSION['derniere_tentative'] ?? 0;
    if ($tentatives >= TENTATIVES_MAX && (time() - $depuis) < BLOCAGE_SECONDES) {
        return true;
    }
    // Le blocage a expiré : on remet le compteur à zéro
    if ($tentatives >= TENTATIVES_MAX) {
        $_SESSION['tentatives_ratees'] = 0;
    }
    return false;
}

function secondesAvantDeblocage() {
    $depuis = $_SESSION['derniere_tentative'] ?? 0;
    return max(0, BLOCAGE_SECONDES - (time() - $depuis));
}

function champCsrf() {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '">';
}

function verifierCsrf() {
    return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '');
}

// Traite connexion / déconnexion (à appeler en tout début de page).
// Retourne un message d'erreur éventuel, sinon une chaîne vide.
function traiterConnexion() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '';
    }
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['connexion', 'deconnexion'], true)) {
        return '';
    }
    if (!verifierCsrf()) {
        return "Session expirée : recharge la page et réessaie.";
    }

    if ($action === 'deconnexion') {
        unset($_SESSION['role']);
        session_regenerate_id(true);
        return '';
    }
    if (estBloque()) {
        $minutes = ceil(secondesAvantDeblocage() / 60);
        return "Trop de tentatives ratées. Réessaie dans environ $minutes minute(s).";
    }

    if ($action === 'connexion') {
        $mdp = $_POST['mot_de_passe'] ?? '';
        if (hash_equals(MOT_DE_PASSE_ADMIN, $mdp)) {
            session_regenerate_id(true);
            $_SESSION['role'] = 'admin';
            $_SESSION['tentatives_ratees'] = 0;
            return '';
        }
        if (hash_equals(MOT_DE_PASSE_MODERATEUR, $mdp)) {
            // Le prof se connecte directement avec son mot de passe.
            session_regenerate_id(true);
            $_SESSION['role'] = 'moderateur';
            $_SESSION['tentatives_ratees'] = 0;
            return '';
        }
        $_SESSION['tentatives_ratees'] = ($_SESSION['tentatives_ratees'] ?? 0) + 1;
        $_SESSION['derniere_tentative'] = time();
        return "Mot de passe incorrect.";
    }

    return '';
}

// Affiche la barre de connexion : connecté, blocage, ou mot de passe.
function afficherBarreConnexion($erreurConnexion = '') {
    $cible = htmlspecialchars(basename($_SERVER['PHP_SELF']));

    echo '<div class="barre-connexion">';

    if (estConnecte()) {
        $libelleRole = estAdmin() ? 'Admin' : 'Modérateur';
        echo '<form class="formulaire formulaire-connexion" method="post" action="' . $cible . '">';
        echo '<input type="hidden" name="action" value="deconnexion">' . champCsrf();
        echo '<span class="msg-succes">🔓 Connecté (' . htmlspecialchars($libelleRole) . ')</span> ';
        echo '<button type="submit" class="document-link">Se déconnecter</button>';
        echo '</form>';

    } elseif (estBloque()) {
        $minutes = ceil(secondesAvantDeblocage() / 60);
        echo '<p class="msg-erreur">🔒 Trop de tentatives ratées. Réessaie dans environ ' . $minutes . ' minute(s).</p>';

    } else {
        echo '<form class="formulaire formulaire-connexion" method="post" action="' . $cible . '">';
        echo '<input type="hidden" name="action" value="connexion">' . champCsrf();
        echo '<input type="password" name="mot_de_passe" placeholder="Code d\'accès" required>';
        echo '<button type="submit" class="document-link">Se connecter</button>';
        echo '</form>';
        if ($erreurConnexion) {
            echo '<p class="msg-erreur">' . htmlspecialchars($erreurConnexion) . '</p>';
        }
    }

    echo '</div>';
}

// ----------------------------------------------------------
// Gestion des dossiers / fichiers
// ----------------------------------------------------------

// Traite les formulaires POST (création de dossier + upload + suppression) pour UNE catégorie.
// Retourne [message_succes, message_erreur]
function traiterFormulaires($categorie) {
    global $RACINE_FICHIERS, $CATEGORIES_AUTORISEES;

    if (!in_array($categorie, $CATEGORIES_AUTORISEES)) {
        return ['', "Catégorie invalide."];
    }

    $dossierCategorie = $RACINE_FICHIERS . $categorie . '/';
    if (!is_dir($dossierCategorie)) {
        mkdir($dossierCategorie, 0755, true);
    }

    $message = '';
    $erreur = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['categorie'] ?? '') === $categorie) {

        // 🔒 Sécurité : on bloque toute action si pas connecté,
        // même si quelqu'un envoie directement une requête POST sans passer par le formulaire.
        if (!estConnecte()) {
            return ['', "Tu dois être connecté pour faire ça."];
        }
        if (!verifierCsrf()) {
            return ['', "Session expirée : recharge la page et réessaie."];
        }

        // Création d'un dossier (ADMIN UNIQUEMENT)
        if (($_POST['action'] ?? '') === 'creer_dossier') {
            if (!estAdmin()) {
                return ['', "Seul l'admin peut créer un dossier."];
            }
            $nomDossier = nettoyerNom($_POST['nom_dossier'] ?? '');
            if ($nomDossier === '') {
                $erreur = "Le nom du dossier ne peut pas être vide ou contenir des caractères spéciaux.";
            } else {
                $chemin = $dossierCategorie . $nomDossier;
                if (is_dir($chemin)) {
                    $erreur = "Ce dossier existe déjà.";
                } else {
                    mkdir($chemin, 0755, true);
                    $message = "Dossier « " . htmlspecialchars($nomDossier) . " » créé.";
                }
            }
        }

        // Upload d'un fichier (admin + modérateur)
        if (($_POST['action'] ?? '') === 'uploader_fichier') {
            $nomDossier = nettoyerNom($_POST['dossier_cible'] ?? '');
            $cheminDossier = $dossierCategorie . $nomDossier;

            if ($nomDossier === '' || !is_dir($cheminDossier)) {
                $erreur = "Dossier cible invalide.";
            } elseif (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
                $erreur = "Aucun fichier valide n'a été envoyé.";
            } else {
                $nomFichier = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['fichier']['name']));
                $extension = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));
                $mime = function_exists('finfo_open')
                    ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $_FILES['fichier']['tmp_name'])
                    : 'application/pdf';
                if ($extension !== 'pdf' || $mime !== 'application/pdf') {
                    $erreur = "Seuls les fichiers PDF sont autorisés.";
                } elseif ($_FILES['fichier']['size'] > TAILLE_MAX_FICHIER) {
                    $erreur = "Fichier trop lourd (10 Mo maximum).";
                } else {
                    $destination = $cheminDossier . '/' . $nomFichier;
                    if (move_uploaded_file($_FILES['fichier']['tmp_name'], $destination)) {
                        $message = "Fichier « " . htmlspecialchars($nomFichier) . " » ajouté dans « " . htmlspecialchars($nomDossier) . " ».";
                    } else {
                        $erreur = "Erreur lors de l'envoi du fichier.";
                    }
                }
            }
        }

        // Suppression d'un fichier (ADMIN UNIQUEMENT)
        if (($_POST['action'] ?? '') === 'supprimer_fichier') {
            if (!estAdmin()) {
                return ['', "Seul l'admin peut supprimer un fichier."];
            }
            $nomDossier = nettoyerNom($_POST['dossier_cible'] ?? '');
            $nomFichier = basename($_POST['nom_fichier'] ?? '');
            $chemin = $dossierCategorie . $nomDossier . '/' . $nomFichier;

            if ($nomDossier === '' || $nomFichier === '' || !is_file($chemin)) {
                $erreur = "Fichier introuvable.";
            } else {
                if (unlink($chemin)) {
                    $message = "Fichier « " . htmlspecialchars($nomFichier) . " » supprimé.";
                } else {
                    $erreur = "Erreur lors de la suppression.";
                }
            }
        }

        // Suppression d'un dossier entier avec ses fichiers (ADMIN UNIQUEMENT)
        if (($_POST['action'] ?? '') === 'supprimer_dossier') {
            if (!estAdmin()) {
                return ['', "Seul l'admin peut supprimer un dossier."];
            }
            $nomDossier = nettoyerNom($_POST['dossier_cible'] ?? '');
            $chemin = $dossierCategorie . $nomDossier;
            if ($nomDossier === '' || !is_dir($chemin)) {
                $erreur = "Dossier introuvable.";
            } else {
                $ok = true;
                foreach (scandir($chemin) as $element) {
                    if ($element === '.' || $element === '..') {
                        continue;
                    }
                    $sous = $chemin . '/' . $element;
                    if (is_file($sous)) {
                        if (!unlink($sous)) { $ok = false; }
                    } else {
                        $ok = false; // on ne touche pas aux sous-dossiers inattendus
                    }
                }
                if ($ok && rmdir($chemin)) {
                    $message = "Dossier « " . htmlspecialchars($nomDossier) . " » supprimé.";
                } else {
                    $erreur = "Impossible de supprimer entièrement ce dossier.";
                }
            }
        }
    }

    return [$message, $erreur];
}

// Liste les dossiers et fichiers d'UNE catégorie donnée
function listerDossiers($categorie) {
    global $RACINE_FICHIERS;
    $dossierCategorie = $RACINE_FICHIERS . $categorie . '/';
    if (!is_dir($dossierCategorie)) {
        mkdir($dossierCategorie, 0755, true);
    }
    $dossiers = [];
    foreach (scandir($dossierCategorie) as $entree) {
        if ($entree !== '.' && $entree !== '..' && is_dir($dossierCategorie . $entree)) {
            $fichiers = array_values(array_diff(scandir($dossierCategorie . $entree), ['.', '..']));
            $dossiers[$entree] = $fichiers;
        }
    }
    return $dossiers;
}

// Affiche les formulaires pour une catégorie.
// "Créer un dossier" : admin uniquement.
// "Ajouter un fichier" : admin ET modérateur (mais seulement s'il existe déjà des dossiers).
function afficherFormulaires($categorie, $dossiers) {
    if (!estConnecte()) {
        echo '<p class="intro">🔒 Connecte-toi (ci-dessus) pour pouvoir ajouter des fichiers.</p>';
        return;
    }

    $cible = htmlspecialchars(basename($_SERVER['PHP_SELF']));
?>
    <?php if (estAdmin()): ?>
    <form class="formulaire" method="post" action="<?= $cible ?>">
      <input type="hidden" name="categorie" value="<?= htmlspecialchars($categorie) ?>">
      <input type="hidden" name="action" value="creer_dossier">
      <?= champCsrf() ?>
      <input type="text" name="nom_dossier" placeholder="Nom du nouveau dossier" required>
      <button type="submit" class="document-link">Créer le dossier</button>
    </form>
    <?php endif; ?>

    <?php if (count($dossiers) > 0): ?>
    <form class="formulaire" method="post" action="<?= $cible ?>" enctype="multipart/form-data">
      <input type="hidden" name="categorie" value="<?= htmlspecialchars($categorie) ?>">
      <input type="hidden" name="action" value="uploader_fichier">
      <?= champCsrf() ?>
      <select name="dossier_cible" required>
        <option value="" disabled selected>Choisir un dossier</option>
        <?php foreach ($dossiers as $nom => $fichiers): ?>
          <option value="<?= htmlspecialchars($nom) ?>"><?= htmlspecialchars($nom) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="file" name="fichier" accept=".pdf,application/pdf" required>
      <button type="submit" class="document-link">Ajouter le fichier</button>
    </form>
    <?php elseif (estAdmin()): ?>
      <p class="intro">Crée d'abord un dossier pour pouvoir y ajouter des fichiers.</p>
    <?php else: ?>
      <p class="intro">Aucun dossier n'a encore été créé par l'admin — reviens plus tard.</p>
    <?php endif;
}

// Bouton « supprimer le dossier » (visible uniquement pour l'admin).
function boutonSupprimerDossier($categorie, $nomDossier, $nbFichiers, $cible = null) {
    if (!estAdmin()) {
        return '';
    }
    if ($cible === null) {
        $cible = htmlspecialchars(basename($_SERVER['PHP_SELF']));
    }
    $question = $nbFichiers > 0
        ? "Supprimer ce dossier ET ses " . $nbFichiers . " fichier(s) ? Action définitive."
        : "Supprimer ce dossier vide ?";
    $js = htmlspecialchars(json_encode($question, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
    $html  = ' <form class="formulaire-suppression" method="post" action="' . $cible . '" style="display:inline" onsubmit="return confirm(' . $js . ');">';
    $html .= '<input type="hidden" name="categorie" value="' . htmlspecialchars($categorie) . '">';
    $html .= '<input type="hidden" name="action" value="supprimer_dossier">' . champCsrf();
    $html .= '<input type="hidden" name="dossier_cible" value="' . htmlspecialchars($nomDossier) . '">';
    $html .= '<button type="submit" class="document-link bouton-supprimer" title="Supprimer le dossier">🗑 Dossier</button>';
    $html .= '</form>';
    return $html;
}

// Affiche la grille des dossiers/fichiers pour une catégorie.
// Le bouton de suppression n'apparaît que pour l'admin.
function afficherDossiers($categorie, $dossiers, $etiquette = null) {
    if (count($dossiers) === 0) {
        echo '<p class="intro">Aucun dossier pour le moment.</p>';
        return;
    }
    $cible = htmlspecialchars(basename($_SERVER['PHP_SELF']));
    echo '<div class="grille-cartes">';
    foreach ($dossiers as $nom => $fichiers) {
        echo '<div class="carte">';
        $prefixe = $etiquette ? '<span class="etiquette">' . htmlspecialchars($etiquette) . '</span> ' : '';
        echo '<h3>' . $prefixe . '📁 ' . htmlspecialchars($nom) . boutonSupprimerDossier($categorie, $nom, count($fichiers), $cible) . '</h3>';
        if (count($fichiers) === 0) {
            echo '<p>Dossier vide.</p>';
        } else {
            echo '<ul class="liste-fichiers">';
            foreach ($fichiers as $fichier) {
                echo afficherLigneFichier($categorie, $nom, $fichier, $cible);
            }
            echo '</ul>';
        }
        echo '</div>';
    }
    echo '</div>';
}

// Affiche une ligne <li> pour un fichier, avec le bouton supprimer si admin.
// Réutilisée par afficherDossiers() et par documents.php.
function afficherLigneFichier($categorie, $nomDossier, $fichier, $cible = null) {
    if ($cible === null) {
        $cible = htmlspecialchars(basename($_SERVER['PHP_SELF']));
    }
    $lien = 'fichiers/' . rawurlencode($categorie) . '/' . rawurlencode($nomDossier) . '/' . rawurlencode($fichier);

    $html = '<li><a href="' . $lien . '" target="_blank" rel="noopener">' . htmlspecialchars($fichier) . '</a>';

    if (estAdmin()) {
        $html .= ' <form class="formulaire-suppression" method="post" action="' . $cible . '" style="display:inline" onsubmit="return confirm(\'Supprimer ce fichier ?\');">';
        $html .= '<input type="hidden" name="categorie" value="' . htmlspecialchars($categorie) . '">';
        $html .= '<input type="hidden" name="action" value="supprimer_fichier">' . champCsrf();
        $html .= '<input type="hidden" name="dossier_cible" value="' . htmlspecialchars($nomDossier) . '">';
        $html .= '<input type="hidden" name="nom_fichier" value="' . htmlspecialchars($fichier) . '">';
        $html .= '<button type="submit" class="document-link bouton-supprimer">🗑</button>';
        $html .= '</form>';
    }

    $html .= '</li>';
    return $html;
}
