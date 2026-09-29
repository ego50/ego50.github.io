<?php
require 'gestion.php';
$erreurConnexion = traiterConnexion();
list($message, $erreur) = traiterFormulaires('projets');$dossiers = listerDossiers('projets');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="sakura-fleur.css">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Projets - Mon classeur numérique</title>
  <link rel="icon" href="logo.svg">
</head>
<body>
  <div id="veil" aria-hidden="true"></div>
  <script src="transitions.js"></script>

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
    <h2>Projets</h2>
    <p class="intro">Range ici tes projets, organisés par dossier.</p>

    <?php afficherBarreConnexion($erreurConnexion); ?>

    <?php if ($message): ?><p class="msg-succes"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($erreur): ?><p class="msg-erreur"><?= htmlspecialchars($erreur) ?></p><?php endif; ?>

    <?php afficherFormulaires('projets', $dossiers); ?>
    <?php afficherDossiers('projets', $dossiers); ?>
  </main>

  <footer class="bas-de-page">
    <div><a href="index.html">Accueil</a></div>
  </footer>

  <!-- Bouton secret vers le jeu -->
  <a href="jeux.php" id="bouton-secret" title="Sanctuaire des Kami" style="position:fixed; bottom:20px; left:20px; z-index:999; width:44px; height:44px; border-radius:8px; background:#0c0818; border:1px solid #ffd700; color:#ffd700; display:flex; align-items:center; justify-content:center; font-size:22px; text-decoration:none; box-shadow:0 0 12px rgba(255, 215, 0, 0.4); backdrop-filter:blur(4px); transition:transform 0.3s ease, box-shadow 0.3s ease;">⛩️</a>

  <script src="sakura.js" defer></script>
</body>
</html>
