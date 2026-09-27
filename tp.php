<?php
require 'gestion.php';
$erreurConnexion = traiterConnexion();
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

  <audio id="musique-fond" loop autoplay muted>
    <source src="musique/musique-fond.mp3" type="audio/mpeg">
  </audio>
  <button id="bouton-musique" title="Activer / couper la musique" aria-label="Activer ou couper la musique" style="position:fixed; bottom:20px; right:20px; z-index:999; width:50px; height:50px; border-radius:50%; border:none; background:#ffffffdd; font-size:22px; cursor:pointer; box-shadow:0 2px 10px rgba(0,0,0,0.25);">🔇</button>

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

    <?php afficherBarreConnexion($erreurConnexion); ?>

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

    const musique = document.getElementById("musique-fond");
    const boutonMusique = document.getElementById("bouton-musique");
    boutonMusique.addEventListener("click", () => {
      musique.muted = !musique.muted;
      if (!musique.muted) { musique.play(); }
      boutonMusique.textContent = musique.muted ? "🔇" : "🔊";
    });
  </script>
</body>
</html>
