<?php
require 'gestion.php';
list($message, $erreur) = traiterFormulaires('projets');
$dossiers = listerDossiers('projets');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="stylesheet" href="style.css">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Projets - Mon classeur numérique</title>
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
    <h2>Projets</h2>
    <p class="intro">Range ici tes projets, organisés par dossier.</p>

    <?php if ($message): ?><p class="msg-succes"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($erreur): ?><p class="msg-erreur"><?= htmlspecialchars($erreur) ?></p><?php endif; ?>

    <?php afficherFormulaires('projets', $dossiers); ?>
    <?php afficherDossiers('projets', $dossiers); ?>
  </main>
</body>
</html>
