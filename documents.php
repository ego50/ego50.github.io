<?php
require 'gestion.php';
$erreurConnexion = traiterConnexion();

list($message, $erreur) = traiterFormulaires('documents');$dossiersDocuments = listerDossiers('documents');

$dossiersCours    = listerDossiers('cours');
$dossiersTp       = listerDossiers('tp');$dossiersProjets  = listerDossiers('projets');
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

  <div class="sakura-container" aria-hidden="true"></div>

  <div class="dragon-zone" aria-hidden="true">
    <div class="dragon">
      <div class="dragon-body"></div>
      <div class="dragon-head">
        <span class="dragon-horn horn-one"></span>
        <span class="dragon-horn horn-two"></span>
      </div>
      <div class="dragon-mane"></div>
      <div class="dragon-spikes"></div>
      <div class="dragon-tail"></div>
    </div>
  </div>

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

    <?php afficherBarreConnexion($erreurConnexion); ?>

    <?php if ($message): ?><p class="msg-succes"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($erreur): ?><p class="msg-erreur"><?= htmlspecialchars($erreur) ?></p><?php endif; ?>

    <h3 class="sous-titre">Ajouter un fichier libre (non lié à une page précise)</h3>
    <?php afficherFormulaires('documents', $dossiersDocuments); ?>

    <h3 class="sous-titre">Tous tes fichiers</h3>
    <?php
      $tousLesDossiers = [];
      foreach ($dossiersCours as$nom => $fichiers)    {$tousLesDossiers[] = ['categorie' => 'cours',     'etiquette' => 'Cours',    'nom' => $nom, 'fichiers' =>$fichiers]; }
      foreach ($dossiersTp as$nom => $fichiers)       {$tousLesDossiers[] = ['categorie' => 'tp',        'etiquette' => 'TP',       'nom' => $nom, 'fichiers' =>$fichiers]; }
      foreach ($dossiersProjets as$nom => $fichiers)  {$tousLesDossiers[] = ['categorie' => 'projets',   'etiquette' => 'Projets',  'nom' => $nom, 'fichiers' =>$fichiers]; }
      foreach ($dossiersDocuments as$nom => $fichiers){$tousLesDossiers[] = ['categorie' => 'documents', 'etiquette' => 'Libre',    'nom' => $nom, 'fichiers' =>$fichiers]; }
    ?>

    <?php if (count($tousLesDossiers) === 0): ?>
      <p class="intro">Aucun fichier pour le moment sur tout le site.</p>
    <?php else: ?>
      <div class="grille-cartes">
        <?php foreach ($tousLesDossiers as$d): ?>
          <div class="carte">
            <h3><span class="etiquette"><?= htmlspecialchars($d['etiquette']) ?></span> 📁 <?= htmlspecialchars($d['nom']) ?></h3>
            <?php if (count($d['fichiers']) === 0): ?>
              <p>Dossier vide.</p>
            <?php else: ?>
              <ul class="liste-fichiers">
                <?php foreach ($d['fichiers'] as$fichier): ?>
                  <?= afficherLigneFichier($d['categorie'], $d['nom'],$fichier) ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- Bouton secret vers le jeu -->
  <a href="jeux.html" id="bouton-secret" title="Sanctuaire des Kami" style="position:fixed; bottom:20px; left:20px; z-index:999; width:44px; height:44px; border-radius:8px; background:#0c0818; border:1px solid #ffd700; color:#ffd700; display:flex; align-items:center; justify-content:center; font-size:22px; text-decoration:none; box-shadow:0 0 12px rgba(255, 215, 0, 0.4); backdrop-filter:blur(4px); transition:transform 0.3s ease, box-shadow 0.3s ease;">⛩️</a>

  <script>
    const sakuraContainer = document.querySelector(".sakura-container");
    const PETAL_COUNT = 35;

    for (let i = 0; i < PETAL_COUNT; i++) {
      const petal = document.createElement("span");
      petal.className = "sakura-petal";

      const size = Math.random() * 7 + 7;
      const left = Math.random() * 100;
      const fallDuration = Math.random() * 12 + 10;
      const swayDuration = Math.random() * 3 + 2;
      const delay = Math.random() * -20;
      const opacity = Math.random() * 0.45 + 0.35;

      petal.style.left = `${left}%`;
      petal.style.width = `${size}px`;
      petal.style.height = `${size * 0.65}px`;
      petal.style.opacity = opacity;
      petal.style.animationDuration = `${fallDuration}s, ${swayDuration}s`;
      petal.style.animationDelay = `${delay}s, ${Math.random() * -5}s`;

      sakuraContainer.appendChild(petal);
    }
  </script>
</body>
</html>
