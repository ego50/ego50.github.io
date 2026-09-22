<?php
// ---------- CONFIGURATION ----------
$dossierBase = __DIR__ . '/fichiers/';
if (!is_dir($dossierBase)) {
    mkdir($dossierBase, 0755, true);
}

// Extensions interdites pour l'upload (sécurité : on évite les fichiers exécutables)
$extensionsInterdites = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'sh', 'bat'];

$message = '';
$erreur = '';

// ---------- FONCTIONS UTILES ----------
function nettoyerNomDossier($nom) {
    // On garde uniquement lettres, chiffres, tirets et underscores
    $nom = trim($nom);
    $nom = preg_replace('/[^A-Za-z0-9_\- ]/', '', $nom);
    return $nom;
}

// ---------- TRAITEMENT DES FORMULAIRES ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Création d'un dossier
    if (isset($_POST['action']) && $_POST['action'] === 'creer_dossier') {
        $nomDossier = nettoyerNomDossier($_POST['nom_dossier'] ?? '');

        if ($nomDossier === '') {
            $erreur = "Le nom du dossier ne peut pas être vide ou contenir des caractères spéciaux.";
        } else {
            $chemin = $dossierBase . $nomDossier;
            if (is_dir($chemin)) {
                $erreur = "Ce dossier existe déjà.";
            } else {
                mkdir($chemin, 0755, true);
                $message = "Dossier « " . htmlspecialchars($nomDossier) . " » créé avec succès.";
            }
        }
    }

    // Upload d'un fichier dans un dossier existant
    if (isset($_POST['action']) && $_POST['action'] === 'uploader_fichier') {
        $nomDossier = nettoyerNomDossier($_POST['dossier_cible'] ?? '');
        $cheminDossier = $dossierBase . $nomDossier;

        if ($nomDossier === '' || !is_dir($cheminDossier)) {
            $erreur = "Dossier cible invalide.";
        } elseif (!isset($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
            $erreur = "Aucun fichier valide n'a été envoyé.";
        } else {
            $nomFichier = basename($_FILES['fichier']['name']);
            $extension = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));

            if (in_array($extension, $extensionsInterdites)) {
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

// ---------- LECTURE DES DOSSIERS EXISTANTS ----------
$dossiers = [];
foreach (scandir($dossierBase) as $entree) {
    if ($entree !== '.' && $entree !== '..' && is_dir($dossierBase . $entree)) {
        $fichiers = array_values(array_diff(scandir($dossierBase . $entree), ['.', '..']));
        $dossiers[$entree] = $fichiers;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="stylesheet" href="style.css">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Documents - Mon classeur numérique</title>
</head>
<body>
  <header>
    <h1>Mon classeur numérique</h1>
    <nav>
      <a href="index.html">Accueil</a>
      <a href="cours.html">Cours</a>
      <a href="tp.html">Mes TP</a>
      <a href="projets.html">Projets</a>
      <a href="documents.php">Documents</a>
    </nav>
  </header>

  <main>
    <h2>Documents</h2>
    <p class="intro">Crée des dossiers et ajoute tes fichiers directement depuis le navigateur.</p>

    <?php if ($message): ?>
      <p class="msg-succes"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <?php if ($erreur): ?>
      <p class="msg-erreur"><?= htmlspecialchars($erreur) ?></p>
    <?php endif; ?>

    <!-- Formulaire : créer un dossier -->
    <form class="formulaire" method="post" action="documents.php">
      <input type="hidden" name="action" value="creer_dossier">
      <input type="text" name="nom_dossier" placeholder="Nom du nouveau dossier" required>
      <button type="submit" class="document-link">Créer le dossier</button>
    </form>

    <!-- Formulaire : uploader un fichier -->
    <?php if (count($dossiers) > 0): ?>
    <form class="formulaire" method="post" action="documents.php" enctype="multipart/form-data">
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
    <?php endif; ?>

    <!-- Liste des dossiers et fichiers -->
    <div class="grille-cartes">
      <?php foreach ($dossiers as $nom => $fichiers): ?>
        <div class="carte">
          <h3>📁 <?= htmlspecialchars($nom) ?></h3>
          <?php if (count($fichiers) === 0): ?>
            <p>Dossier vide.</p>
          <?php else: ?>
            <ul class="liste-fichiers">
              <?php foreach ($fichiers as $fichier): ?>
                <li><a href="fichiers/<?= rawurlencode($nom) ?>/<?= rawurlencode($fichier) ?>" target="_blank"><?= htmlspecialchars($fichier) ?></a></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</body>
</html>
