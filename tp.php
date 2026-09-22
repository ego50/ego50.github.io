<?php
require 'gestion.php';
list($message, $erreur) = traiterFormulaires('tp');
$dossiers = listerDossiers('tp');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="stylesheet" href="style.css">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mes TP</title>
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
    <h2>Mes travaux pratiques</h2>
    <p class="intro">Range ici tes TP, organisés par dossier.</p>

    <?php if ($message): ?><p class="msg-succes"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($erreur): ?><p class="msg-erreur"><?= htmlspecialchars($erreur) ?></p><?php endif; ?>

    <?php afficherFormulaires('tp', $dossiers); ?>
    <?php afficherDossiers('tp', $dossiers); ?>
  </main>

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
