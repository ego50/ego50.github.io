<?php
require 'gestion.php';
$erreurConnexion = traiterConnexion();
list($message, $erreur) = traiterFormulaires('cours');$dossiers = listerDossiers('cours');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="stylesheet" href="style.css">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cours - Mon classeur numérique</title>
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
    <h2>Cours</h2>
    <p class="intro">Range ici tes fichiers de cours, organisés par dossier.</p>

    <?php afficherBarreConnexion($erreurConnexion); ?>

    <?php if ($message): ?><p class="msg-succes"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($erreur): ?><p class="msg-erreur"><?= htmlspecialchars($erreur) ?></p><?php endif; ?>

    <?php afficherFormulaires('cours', $dossiers); ?>
    <?php afficherDossiers('cours', $dossiers); ?>
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
