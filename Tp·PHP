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
</body>
</html>
