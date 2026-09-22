<?php
require 'gestion.php';

// Documents a aussi son propre espace "libre" (fichiers non liés à une page précise)
list($message, $erreur) = traiterFormulaires('documents');
$dossiersDocuments = listerDossiers('documents');

// On récupère aussi tout ce qu'il y a dans les autres catégories, pour la vue globale
$dossiersCours    = listerDossiers('cours');
$dossiersTp       = listerDossiers('tp');
$dossiersProjets  = listerDossiers('projets');
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
      <a href="cours.php">Cours</a>
      <a href="tp.php">Mes TP</a>
      <a href="projets.php">Projets</a>
      <a href="documents.php">Documents</a>
    </nav>
  </header>

  <main>
    <h2>Documents</h2>
    <p class="intro">Vue globale de tous tes fichiers (Cours, TP, Projets et fichiers libres), sans tri particulier.</p>

    <?php if ($message): ?><p class="msg-succes"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($erreur): ?><p class="msg-erreur"><?= htmlspecialchars($erreur) ?></p><?php endif; ?>

    <h3 class="sous-titre">Ajouter un fichier libre (non lié à une page précise)</h3>
    <?php afficherFormulaires('documents', $dossiersDocuments); ?>

    <h3 class="sous-titre">Tous tes fichiers</h3>
    <?php
      $tousLesDossiers = [];
      foreach ($dossiersCours as $nom => $fichiers)    { $tousLesDossiers[] = ['categorie' => 'cours',     'etiquette' => 'Cours',    'nom' => $nom, 'fichiers' => $fichiers]; }
      foreach ($dossiersTp as $nom => $fichiers)       { $tousLesDossiers[] = ['categorie' => 'tp',        'etiquette' => 'TP',       'nom' => $nom, 'fichiers' => $fichiers]; }
      foreach ($dossiersProjets as $nom => $fichiers)  { $tousLesDossiers[] = ['categorie' => 'projets',   'etiquette' => 'Projets',  'nom' => $nom, 'fichiers' => $fichiers]; }
      foreach ($dossiersDocuments as $nom => $fichiers){ $tousLesDossiers[] = ['categorie' => 'documents', 'etiquette' => 'Libre',    'nom' => $nom, 'fichiers' => $fichiers]; }
    ?>

    <?php if (count($tousLesDossiers) === 0): ?>
      <p class="intro">Aucun fichier pour le moment sur tout le site.</p>
    <?php else: ?>
      <div class="grille-cartes">
        <?php foreach ($tousLesDossiers as $d): ?>
          <div class="carte">
            <h3><span class="etiquette"><?= htmlspecialchars($d['etiquette']) ?></span> 📁 <?= htmlspecialchars($d['nom']) ?></h3>
            <?php if (count($d['fichiers']) === 0): ?>
              <p>Dossier vide.</p>
            <?php else: ?>
              <ul class="liste-fichiers">
                <?php foreach ($d['fichiers'] as $fichier): ?>
                  <li><a href="fichiers/<?= rawurlencode($d['categorie']) ?>/<?= rawurlencode($d['nom']) ?>/<?= rawurlencode($fichier) ?>" target="_blank"><?= htmlspecialchars($fichier) ?></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
