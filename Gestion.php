<?php
// ==========================================================
// gestion.php — moteur commun de gestion de dossiers/fichiers
// Utilisé par cours.php, tp.php, projets.php et documents.php
// ==========================================================

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

// Traite les formulaires POST (création de dossier + upload) pour UNE catégorie donnée.
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

        // Création d'un dossier
        if (($_POST['action'] ?? '') === 'creer_dossier') {
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

        // Upload d'un fichier
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

// Affiche les 2 formulaires (créer dossier / uploader fichier) pour une catégorie
function afficherFormulaires($categorie, $dossiers) {
    $cible = htmlspecialchars(basename($_SERVER['PHP_SELF']));
?>
    <form class="formulaire" method="post" action="<?= $cible ?>">
      <input type="hidden" name="categorie" value="<?= htmlspecialchars($categorie) ?>">
      <input type="hidden" name="action" value="creer_dossier">
      <input type="text" name="nom_dossier" placeholder="Nom du nouveau dossier" required>
      <button type="submit" class="document-link">Créer le dossier</button>
    </form>

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
    <?php else: ?>
      <p class="intro">Crée d'abord un dossier pour pouvoir y ajouter des fichiers.</p>
    <?php endif;
}

// Affiche la grille des dossiers/fichiers pour une catégorie
function afficherDossiers($categorie, $dossiers, $etiquette = null) {
    if (count($dossiers) === 0) {
        echo '<p class="intro">Aucun dossier pour le moment.</p>';
        return;
    }
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
                $lien = 'fichiers/' . rawurlencode($categorie) . '/' . rawurlencode($nom) . '/' . rawurlencode($fichier);
                echo '<li><a href="' . $lien . '" target="_blank">' . htmlspecialchars($fichier) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }
    echo '</div>';
}
