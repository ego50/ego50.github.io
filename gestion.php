<?php
// ==========================================================
// gestion.php — moteur commun de gestion de dossiers/fichiers
// Utilisé par cours.php, tp.php, projets.php et documents.php
// ==========================================================

session_start();

// ⚠️ CHANGE CES MOTS DE PASSE avant de mettre le site en ligne !
// - Mot de passe ADMIN (le tien) : accès complet — créer des dossiers,
//   ajouter des fichiers, ET les supprimer.
// - Mot de passe MODÉRATEUR (le prof) : peut seulement ajouter des fichiers
//   dans des dossiers que tu as déjà créés. Il ne peut ni créer de dossier,
//   ni supprimer quoi que ce soit.
define('MOT_DE_PASSE_ADMIN', 'lex4');
define('MOT_DE_PASSE_MODERATEUR', 'Profsin2026');

// Catégories autorisées (sécurité : on n'accepte pas n'importe quel nom)
$CATEGORIES_AUTORISEES = ['cours', 'tp', 'projets', 'documents'];

$RACINE_FICHIERS = __DIR__ . '/fichiers/';
if (!is_dir($RACINE_FICHIERS)) {
    mkdir($RACINE_FICHIERS, 0755, true);
}

$EXTENSIONS_INTERDITES = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'sh', 'bat'];

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

// Traite le formulaire de connexion / déconnexion (à appeler en tout début de page).
// Retourne un message d'erreur éventuel, sinon une chaîne vide.
function traiterConnexion() {
    $erreur = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'connexion') {
        $motDePasseSaisi = $_POST['mot_de_passe'] ?? '';

        if (hash_equals(MOT_DE_PASSE_ADMIN, $motDePasseSaisi)) {
            $_SESSION['role'] = 'admin';
        } elseif (hash_equals(MOT_DE_PASSE_MODERATEUR, $motDePasseSaisi)) {
            $_SESSION['role'] = 'moderateur';
        } else {
            $erreur = "Mot de passe incorrect.";
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deconnexion') {
        unset($_SESSION['role']);
    }

    return $erreur;
}

// Affiche la barre de connexion : mot de passe, ou saisie du code, ou statut connecté
function afficherBarreConnexion($erreurConnexion = '') {
    $cible = htmlspecialchars(basename($_SERVER['PHP_SELF']));

    echo '<div class="barre-connexion">';

    if (estConnecte()) {
        $libelleRole = estAdmin() ? 'Admin' : 'Modérateur';
        echo '<form class="formulaire formulaire-connexion" method="post" action="' . $cible . '">';
        echo '<input type="hidden" name="action" value="deconnexion">';
        echo '<span class="msg-succes">🔓 Connecté (' . htmlspecialchars($libelleRole) . ')</span> ';
        echo '<button type="submit" class="document-link">Se déconnecter</button>';
        echo '</form>';

    } else {
        echo '<form class="formulaire formulaire-connexion" method="post" action="' . $cible . '">';
        echo '<input type="hidden" name="action" value="connexion">';
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
    global $RACINE_FICHIERS, $EXTENSIONS_INTERDITES, $CATEGORIES_AUTORISEES;

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
                $nomFichier = basename($_FILES['fichier']['name']);
                $extension = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));
                if (in_array($extension, $EXTENSIONS_INTERDITES)) {
                    $erreur = "Ce type de fichier n'est pas autorisé.";
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
      <input type="text" name="nom_dossier" placeholder="Nom du nouveau dossier" required>
      <button type="submit" class="document-link">Créer le dossier</button>
    </form>
    <?php endif; ?>

    <?php if (count($dossiers) > 0): ?>
    <form class="formulaire" method="post" action="<?= $cible ?>" enctype="multipart/form-data">
      <input type="hidden" name="categorie" value="<?= htmlspecialchars($categorie) ?>">
      <input type="hidden" name="action" value="uploader_fichier">
      <select name="dossier_cible" required>
        <option value="" disabled selected>Choisir un dossier</option>
        <?php foreach ($dossiers as $nom => $fichiers): ?>
          <option value="<?= htmlspecialchars($nom) ?>"><?= htmlspecialchars($nom) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="file" name="fichier" required>
      <button type="submit" class="document-link">Ajouter le fichier</button>
    </form>
    <?php elseif (estAdmin()): ?>
      <p class="intro">Crée d'abord un dossier pour pouvoir y ajouter des fichiers.</p>
    <?php else: ?>
      <p class="intro">Aucun dossier n'a encore été créé par l'admin — reviens plus tard.</p>
    <?php endif;
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
        echo '<h3>' . $prefixe . '📁 ' . htmlspecialchars($nom) . '</h3>';
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

    $html = '<li><a href="' . $lien . '" target="_blank">' . htmlspecialchars($fichier) . '</a>';

    if (estAdmin()) {
        $html .= ' <form class="formulaire-suppression" method="post" action="' . $cible . '" style="display:inline" onsubmit="return confirm(\'Supprimer ce fichier ?\');">';
        $html .= '<input type="hidden" name="categorie" value="' . htmlspecialchars($categorie) . '">';
        $html .= '<input type="hidden" name="action" value="supprimer_fichier">';
        $html .= '<input type="hidden" name="dossier_cible" value="' . htmlspecialchars($nomDossier) . '">';
        $html .= '<input type="hidden" name="nom_fichier" value="' . htmlspecialchars($fichier) . '">';
        $html .= '<button type="submit" class="document-link bouton-supprimer">🗑</button>';
        $html .= '</form>';
    }

    $html .= '</li>';
    return $html;
}
