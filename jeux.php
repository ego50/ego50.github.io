<?php
// ==========================================================
// jeux.php — Sanctuaire des Kami (protégé côté serveur)
// Le code du jeu n'est envoyé qu'après vérification du mot de passe
// (défini dans config.php : $motDePasseJeu). L'admin connecté entre directement.
// ==========================================================
require 'gestion.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'quitter_jeu' && verifierCsrf()) {
        unset($_SESSION['jeu_ok']);
        session_regenerate_id(true);
        header('Location: jeux.php');
        exit;
    }
    if ($action === 'entrer_jeu') {
        if (!verifierCsrf()) {
            $erreur = 'Session expirée : recharge la page et réessaie.';
        } elseif (estBloque()) {
            $erreur = 'Trop de tentatives ratées. Réessaie dans environ ' . ceil(secondesAvantDeblocage() / 60) . ' minute(s).';
        } else {
            $mdp = (string) ($_POST['mot_de_passe_jeu'] ?? '');
            if (MOT_DE_PASSE_JEU !== '' && hash_equals(MOT_DE_PASSE_JEU, $mdp)) {
                session_regenerate_id(true);
                $_SESSION['jeu_ok'] = true;
                $_SESSION['tentatives_ratees'] = 0;
                header('Location: jeux.php');
                exit;
            }
            $_SESSION['tentatives_ratees'] = ($_SESSION['tentatives_ratees'] ?? 0) + 1;
            $_SESSION['derniere_tentative'] = time();
            $erreur = 'Sceau incorrect. Recommencez.';
        }
    }
}
$autorise = estAdmin() || !empty($_SESSION['jeu_ok']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Sanctuaire des Kami - Mon classeur numérique</title>
  <link rel="icon" href="logo.svg">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Shippori+Mincho:wght@500;600;700&family=Noto+Serif+JP:wght@400;500;700&display=swap');

    :root {
      --bg-color: #fdf4f6;
      --card-bg: rgba(255, 251, 252, 0.92);
      --text-light: #3b2230;
      --text-soft: #6b4a5a;
      --accent: #b8456a;
      --accent-2: #c9577a;
      --sakura: #e58fa7;
      --sakura-light: #f9d3dd;
      --matcha: #7a9a6b;
      --glow: rgba(229, 143, 167, 0.45);
      --border-color: rgba(217, 112, 143, 0.45);
      --petale: 16px 3px 16px 3px;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      user-select: none;
      -webkit-user-select: none;
      touch-action: manipulation;
    }

    body {
      background:
        radial-gradient(760px 520px at 8% 0%, rgba(249, 211, 221, 0.75), transparent 65%),
        radial-gradient(900px 620px at 100% 100%, rgba(196, 217, 184, 0.45), transparent 62%),
        linear-gradient(180deg, #fffafb 0%, #fdf4f6 55%, #fae9ee 100%) fixed;
      color: var(--text-light);
      font-family: 'Noto Serif JP', serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 15px;
      overflow-x: hidden;
    }

    /* Branche de cerisier (même que le reste du site) */
    body::after {
      content: "";
      position: fixed; top: 0; right: 0; z-index: 0; pointer-events: none;
      width: clamp(200px, 32vw, 460px); aspect-ratio: 420 / 320;
      background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 420 320'%3E%3Cdefs%3E%3Cg id='f'%3E%3Cg fill='%23f9d3dd' stroke='%23e58fa7' stroke-width='1.2'%3E%3Cellipse cy='-11' rx='7' ry='11'/%3E%3Cellipse cy='-11' rx='7' ry='11' transform='rotate(72)'/%3E%3Cellipse cy='-11' rx='7' ry='11' transform='rotate(144)'/%3E%3Cellipse cy='-11' rx='7' ry='11' transform='rotate(216)'/%3E%3Cellipse cy='-11' rx='7' ry='11' transform='rotate(288)'/%3E%3C/g%3E%3Ccircle r='3' fill='%23c94f72'/%3E%3C/g%3E%3C/defs%3E%3Cg fill='none' stroke='%234e332f' stroke-linecap='round'%3E%3Cpath d='M425 26 C335 40 262 92 202 152 S92 250 18 264' stroke-width='7'/%3E%3Cpath d='M305 66 C290 42 272 26 240 12' stroke-width='4'/%3E%3Cpath d='M232 126 C218 172 194 204 166 234' stroke-width='4'/%3E%3Cpath d='M150 206 C124 198 96 206 62 198' stroke-width='3'/%3E%3Cpath d='M262 92 C280 120 282 142 276 170' stroke-width='3'/%3E%3C/g%3E%3Cuse href='%23f' transform='translate(352 38) scale(1.1)'/%3E%3Cuse href='%23f' transform='translate(304 64) rotate(20)'/%3E%3Cuse href='%23f' transform='translate(268 30) scale(.9) rotate(40)'/%3E%3Cuse href='%23f' transform='translate(240 13) scale(1.05) rotate(10)'/%3E%3Cuse href='%23f' transform='translate(262 94) scale(.85) rotate(60)'/%3E%3Cuse href='%23f' transform='translate(278 168) scale(1.1) rotate(30)'/%3E%3Cuse href='%23f' transform='translate(216 130) scale(1.15) rotate(15)'/%3E%3Cuse href='%23f' transform='translate(186 156) scale(.8) rotate(50)'/%3E%3Cuse href='%23f' transform='translate(166 234) scale(1.05) rotate(25)'/%3E%3Cuse href='%23f' transform='translate(122 202) scale(.9)'/%3E%3Cuse href='%23f' transform='translate(64 198) scale(1.1) rotate(35)'/%3E%3Cuse href='%23f' transform='translate(100 246) scale(.8) rotate(12)'/%3E%3Cuse href='%23f' transform='translate(24 262) scale(.85) rotate(48)'/%3E%3Cg fill='%23f3a4ba'%3E%3Ccircle cx='328' cy='52' r='4'/%3E%3Ccircle cx='214' cy='100' r='4'/%3E%3Ccircle cx='148' cy='196' r='3.5'/%3E%3Ccircle cx='42' cy='236' r='3.5'/%3E%3C/g%3E%3C/svg%3E") center / contain no-repeat;
      filter: drop-shadow(0 8px 10px rgba(120, 60, 80, 0.18));
    }

    /* Pétales */
    .sakura-container {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      pointer-events: none; z-index: 0; overflow: hidden;
    }
    .petal {
      position: absolute;
      background: linear-gradient(135deg, #fde6ec, #f3a4ba);
      box-shadow: 0 1px 3px rgba(150, 60, 90, 0.25);
      border-radius: 15px 0 15px 0;
      opacity: 0.3;
      animation: fall linear infinite;
    }
    @keyframes fall {
      0% { transform: translateY(-10vh) rotate(0deg); opacity: 0; }
      10% { opacity: 0.85; }
      100% { transform: translateY(105vh) rotate(360deg); opacity: 0; }
    }

    /* ÉCRAN DE VERROUILLAGE */
    #password-modal {
      position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(253, 244, 246, 0.9);
      backdrop-filter: blur(8px);
      z-index: 999;
      display: flex; justify-content: center; align-items: center;
      padding: 20px;
    }
    .pass-box {
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      box-shadow: 0 24px 60px rgba(120, 60, 80, 0.22), inset 0 0 0 5px rgba(255, 255, 255, 0.6);
      padding: 30px;
      border-radius: 6px 34px 6px 34px;
      text-align: center;
      max-width: 400px; width: 100%;
    }
    .pass-box h2 {
      font-family: 'Shippori Mincho', serif;
      color: var(--text-light);
      margin-bottom: 10px;
      font-size: 1.5rem;
      letter-spacing: 0.08em;
    }
    .pass-input {
      width: 100%; padding: 12px; margin: 15px 0;
      background: #fff;
      border: 1px solid var(--border-color);
      color: var(--text-light);
      font-family: 'Shippori Mincho', serif;
      font-size: 1.1rem; text-align: center;
      border-radius: 8px 2px 8px 2px;
      outline: none;
    }
    .pass-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--glow); }
    .pass-error { color: #9c1f18; font-size: 0.85rem; margin-top: 8px; }
    .quitter-form { margin-top: 8px; display: inline-block; }
    .quitter-form .retour { font-family: inherit; cursor: pointer; }

    /* CONTENU PRINCIPAL MASQUÉ SI VERROUILLÉ */
    #app-container {
      display: flex; width: 100%; max-width: 950px;
      flex-direction: column; align-items: center; z-index: 10;
    }

    header {
      text-align: center; margin-bottom: 20px;
      border-bottom: 2px solid var(--sakura);
      padding-bottom: 12px; width: 100%;
    }
    h1 {
      font-family: 'Shippori Mincho', serif;
      font-size: 2.2rem; font-weight: 600;
      color: var(--text-light);
      text-shadow: 0 1px 0 #fff, 0 0 18px rgba(249, 211, 221, 0.9);
      letter-spacing: 0.15em; margin-bottom: 5px;
    }
    .subtitle { font-size: 0.9rem; color: var(--text-soft); font-style: italic; }
    .retour {
      display: inline-block; margin-top: 8px; padding: 6px 14px; min-height: 36px;
      color: var(--accent); text-decoration: none; font-size: 0.85rem;
      border: 1px solid var(--border-color); border-radius: 999px; background: #fff;
    }
    .retour:hover { background: var(--sakura-light); }

    /* Navigation */
    nav { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-bottom: 20px; width: 100%; }
    .nav-btn {
      background: #fff;
      border: 1px solid var(--border-color);
      color: var(--accent);
      padding: 8px 16px; min-height: 40px;
      font-family: 'Shippori Mincho', serif; font-size: 0.9rem; font-weight: 600;
      cursor: pointer; border-radius: 999px;
      transition: background 0.2s ease, color 0.2s ease;
    }
    .nav-btn:hover { background: var(--sakura-light); }
    .nav-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); box-shadow: 0 6px 14px rgba(184, 69, 106, 0.3); }

    /* Espace de jeu */
    main {
      width: 100%; background: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: 6px 34px 6px 34px;
      padding: 20px;
      box-shadow: 0 24px 60px rgba(120, 60, 80, 0.2), inset 0 0 0 5px rgba(255, 255, 255, 0.6);
      display: flex; flex-direction: column; align-items: center;
    }
    .tab-content { display: none; width: 100%; flex-direction: column; align-items: center; }
    .tab-content.active { display: flex; }

    .game-header {
      display: flex; justify-content: space-between; align-items: center; gap: 12px;
      width: 100%; max-width: 500px; margin-bottom: 12px; padding: 6px 12px;
      background: rgba(253, 236, 241, 0.8);
      border-left: 3px solid var(--accent);
      border-right: 3px solid var(--matcha);
      border-radius: 4px;
    }
    .game-header h2 { font-family: 'Shippori Mincho', serif; font-size: 1.05rem; color: var(--text-light); }
    .score-board { font-size: 0.95rem; color: var(--accent); font-weight: 700; }

    canvas {
      background: #fff7f9;
      border: 2px solid var(--sakura);
      border-radius: 4px 18px 4px 18px;
      box-shadow: 0 8px 22px rgba(150, 60, 90, 0.18);
      max-width: 100%; height: auto;
    }

    .btn-action {
      margin-top: 15px; padding: 10px 20px; min-height: 44px;
      background: linear-gradient(135deg, #c9577a, #b8456a);
      border: none; color: #fff;
      font-family: 'Shippori Mincho', serif; font-weight: 700;
      cursor: pointer; transition: 0.25s;
      border-radius: var(--petale);
      box-shadow: 0 6px 16px rgba(184, 69, 106, 0.35);
    }
    .btn-action:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(184, 69, 106, 0.4); }

    /* Contrôles tactiles */
    .touch-controls { display: flex; flex-direction: column; align-items: center; margin-top: 15px; gap: 8px; width: 100%; max-width: 320px; }
    .touch-row { display: flex; justify-content: center; gap: 10px; width: 100%; }
    .touch-btn {
      background: #fff; border: 1px solid var(--sakura); color: var(--accent);
      font-size: 1.3rem; padding: 12px; min-width: 55px; min-height: 55px;
      display: flex; align-items: center; justify-content: center;
      border-radius: 10px 2px 10px 2px; cursor: pointer; user-select: none;
    }
    .touch-btn:active { background: var(--accent); color: #fff; border-color: var(--accent); }
    .touch-btn-wide { flex: 1; font-family: 'Shippori Mincho', serif; font-size: 0.9rem; font-weight: bold; }

    /* Relique */
    .relic-card { text-align: center; padding: 15px; }
    .relic-img-container {
      margin: 15px 0; border: 1px solid var(--border-color);
      padding: 8px; background: #fff; display: inline-block;
      border-radius: 4px 18px 4px 18px;
      box-shadow: 0 8px 22px rgba(150, 60, 90, 0.18);
    }
    .relic-img { max-width: 100%; height: auto; max-height: 250px; display: block; border-radius: 2px 12px 2px 12px; }
    .relic-link {
      display: inline-block; margin-top: 10px; word-break: break-all;
      color: var(--accent); text-decoration: none; font-weight: bold;
      border: 1px solid var(--border-color); padding: 8px 12px;
      border-radius: 8px 2px 8px 2px; background: #fff;
    }
    .relic-link:hover { background: var(--sakura-light); }

    /* Mémoire */
    .memory-grid { display: grid; grid-template-columns: repeat(4, 70px); gap: 10px; margin-top: 15px; }
    .memory-card {
      width: 70px; height: 70px; background: #fff;
      border: 2px solid var(--sakura); font-size: 1.8rem;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; border-radius: 12px 3px 12px 3px;
    }
    .memory-card.flipped { background: var(--sakura-light); border-color: var(--accent); }


    /* Nouveaux jeux */
    .msg-jeu { min-height: 1.5em; margin-top: 10px; color: var(--accent); font-weight: 700; text-align: center; }
    .board-2048 { display: grid; grid-template-columns: repeat(4, 70px); gap: 8px; padding: 8px; margin-top: 6px; background: #f3d3dc; border-radius: 4px 18px 4px 18px; touch-action: none; }
    .tile-2048 { width: 70px; height: 70px; display: flex; align-items: center; justify-content: center; font-family: 'Shippori Mincho', serif; font-weight: 700; font-size: 1.4rem; border-radius: 10px 2px 10px 2px; background: #fbe9ee; color: var(--text-light); }
    .mines-grid { display: grid; grid-template-columns: repeat(9, 1fr); gap: 3px; width: 100%; max-width: 340px; margin-top: 6px; padding: 6px; background: #f3d3dc; border-radius: 4px 16px 4px 16px; }
    .mine-cell { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid var(--sakura); border-radius: 6px 1px 6px 1px; font-weight: 700; font-size: 1rem; cursor: pointer; }
    .mine-cell.open { background: #fdeef2; border-color: #f3d3dc; cursor: default; }
    .mine-cell.boom { background: #f3a4ba; }
    .simon-grid { display: grid; grid-template-columns: repeat(2, 130px); gap: 12px; margin-top: 10px; }
    .simon-pad { width: 130px; height: 130px; opacity: 0.55; border: 3px solid rgba(255, 255, 255, 0.85); cursor: pointer; transition: opacity 0.1s, transform 0.1s; box-shadow: 0 6px 16px rgba(150, 60, 90, 0.2); }
    .simon-pad.lit { opacity: 1; transform: scale(1.06); box-shadow: 0 0 26px rgba(229, 143, 167, 0.9); }
    .simon-pad[data-i="0"] { background: #e58fa7; border-radius: 130px 14px 14px 14px; }
    .simon-pad[data-i="1"] { background: #7a9a6b; border-radius: 14px 130px 14px 14px; }
    .simon-pad[data-i="2"] { background: #d9b26a; border-radius: 14px 14px 14px 130px; }
    .simon-pad[data-i="3"] { background: #8f7fc0; border-radius: 14px 14px 130px 14px; }

    :focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

    @media (max-width: 600px) {
      h1 { font-size: 1.6rem; letter-spacing: 0.08em; }
      body::after { opacity: 0.45; }
      .memory-grid { grid-template-columns: repeat(4, 55px); }
      .memory-card { width: 55px; height: 55px; font-size: 1.3rem; }
      .board-2048 { grid-template-columns: repeat(4, 60px); }
      .tile-2048 { width: 60px; height: 60px; font-size: 1.15rem; }
      .simon-grid { grid-template-columns: repeat(2, 110px); }
      .simon-pad { width: 110px; height: 110px; }
    }
    @media (prefers-reduced-motion: reduce) {
      .petal { display: none; }
      .btn-action, .nav-btn { transition: none; }
    }
  </style>
</head>
<body>

  <div class="sakura-container" id="sakura"></div>

<?php if (!$autorise): ?>
  <!-- VERROU : le jeu n'est PAS envoyé au navigateur tant que le mot de passe n'est pas validé côté serveur -->
  <div id="password-modal">
    <form class="pass-box" method="post" action="jeux.php">
      <h2>⛩️ SANCTUAIRE DES KAMI ⛩️</h2>
      <p style="font-size:0.85rem; color:#6b4a5a;">Saisissez le sceau sacré pour entrer :</p>
      <input type="hidden" name="action" value="entrer_jeu">
      <?= champCsrf() ?>
      <input type="password" name="mot_de_passe_jeu" class="pass-input" placeholder="Mot de passe..." autocomplete="off" autofocus required>
      <button type="submit" class="btn-action" style="width:100%;">Déverrouiller</button>
      <?php if ($erreur): ?><div class="pass-error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>
      <p style="margin-top:14px;"><a class="retour" href="index.html">← Retour au classeur</a></p>
    </form>
  </div>
</body>
</html>
<?php exit; endif; ?>

  <!-- CONTENU DU SITE -->
  <div id="app-container">
    <header>
      <h1>⛩️ SANCTUAIRE DIVIN ⛩️</h1>
      <p class="subtitle">Espace Arcade & Épreuves Sacrées (PC / Mobile)</p>
      <a class="retour" href="index.html">← Retour au classeur</a>
      <form class="quitter-form" method="post" action="jeux.php">
        <input type="hidden" name="action" value="quitter_jeu">
        <?= champCsrf() ?>
        <button type="submit" class="retour">🔒 Quitter le sanctuaire</button>
      </form>
    </header>

    <nav>
      <button class="nav-btn active" onclick="switchTab('snake')">🐍 Orochi</button>
      <button class="nav-btn" onclick="switchTab('invaders')">👹 Yōkai</button>
      <button class="nav-btn" onclick="switchTab('tetris')">⛩️ Torii</button>
      <button class="nav-btn" onclick="switchTab('pong')">🪞 Miroir</button>
      <button class="nav-btn" onclick="switchTab('memory')">📜 Sceaux</button>
      <button class="nav-btn" onclick="switchTab('breakout')">🧱 Briseur</button>
      <button class="nav-btn" onclick="switchTab('tsuru')">🕊️ Tsuru</button>
      <button class="nav-btn" onclick="switchTab('g2048')">🌸 2048</button>
      <button class="nav-btn" onclick="switchTab('mines')">🌵 Épines</button>
      <button class="nav-btn" onclick="switchTab('simon')">🔔 Cloches</button>
      <button class="nav-btn" onclick="switchTab('relic')">🖼️ Relique</button>
    </nav>

    <main>
      <!-- 1. OROCHI (SNAKE) -->
      <div id="tab-snake" class="tab-content active">
        <div class="game-header">
          <h2>Le Serpent Sacré (Orochi)</h2>
          <div class="score-board">Magatama : <span id="snake-score">0</span></div>
        </div>
        <canvas id="canvas-snake" width="360" height="360"></canvas>
        
        <!-- Contrôles Tactiles Mobile -->
        <div class="touch-controls">
          <div class="touch-row">
            <div class="touch-btn" onclick="triggerKey('snake', 'ArrowUp')">▲</div>
          </div>
          <div class="touch-row">
            <div class="touch-btn" onclick="triggerKey('snake', 'ArrowLeft')">◄</div>
            <div class="touch-btn" onclick="triggerKey('snake', 'ArrowDown')">▼</div>
            <div class="touch-btn" onclick="triggerKey('snake', 'ArrowRight')">►</div>
          </div>
        </div>
        <button class="btn-action" onclick="initSnake()">Réinitialiser le Rite</button>
      </div>

      <!-- 2. YOKAI DEFENSE (SPACE INVADERS) -->
      <div id="tab-invaders" class="tab-content">
        <div class="game-header">
          <h2>Défense des Yōkai</h2>
          <div class="score-board">Purifications : <span id="invaders-score">0</span></div>
        </div>
        <canvas id="canvas-invaders" width="360" height="360"></canvas>
        
        <!-- Contrôles Tactiles Mobile -->
        <div class="touch-controls">
          <div class="touch-row">
            <div class="touch-btn" onclick="triggerKey('invaders', 'ArrowLeft')">◄ Gauche</div>
            <div class="touch-btn touch-btn-wide" onclick="triggerKey('invaders', ' ')">🔥 TIRER</div>
            <div class="touch-btn" onclick="triggerKey('invaders', 'ArrowRight')">Droite ►</div>
          </div>
        </div>
        <button class="btn-action" onclick="initInvaders()">Réinitialiser la Bataille</button>
      </div>

      <!-- 3. BLOC TORII (TETRIS) -->
      <div id="tab-tetris" class="tab-content">
        <div class="game-header">
          <h2>Pierres du Torii</h2>
          <div class="score-board">Énergie : <span id="tetris-score">0</span></div>
        </div>
        <canvas id="canvas-tetris" width="240" height="360"></canvas>
        
        <!-- Contrôles Tactiles Mobile -->
        <div class="touch-controls">
          <div class="touch-row">
            <div class="touch-btn" onclick="triggerKey('tetris', 'ArrowLeft')">◄</div>
            <div class="touch-btn" onclick="triggerKey('tetris', 'ArrowUp')">🔄 Pivoter</div>
            <div class="touch-btn" onclick="triggerKey('tetris', 'ArrowRight')">►</div>
          </div>
          <div class="touch-row">
            <div class="touch-btn touch-btn-wide" onclick="triggerKey('tetris', 'ArrowDown')">▼ Chute Rapide</div>
          </div>
        </div>
        <button class="btn-action" onclick="initTetris()">Réorganiser les Pierres</button>
      </div>

      <!-- 4. MIROIR DIVIN (PONG) -->
      <div id="tab-pong" class="tab-content">
        <div class="game-header">
          <h2>Reflet Yata no Kagami</h2>
          <div class="score-board">Vous : <span id="pong-player">0</span> | Esprit : <span id="pong-ai">0</span></div>
        </div>
        <canvas id="canvas-pong" width="360" height="300"></canvas>
        
        <!-- Contrôles Tactiles Mobile -->
        <div class="touch-controls">
          <div class="touch-row">
            <div class="touch-btn touch-btn-wide" onclick="triggerKey('pong', 'ArrowUp')">▲ Monter</div>
            <div class="touch-btn touch-btn-wide" onclick="triggerKey('pong', 'ArrowDown')">▼ Descendre</div>
          </div>
        </div>
        <button class="btn-action" onclick="initPong()">Relancer l'Orbe Divin</button>
      </div>

      <!-- 5. SCEAUX KUJI-IN (MEMORY) -->
      <div id="tab-memory" class="tab-content">
        <div class="game-header">
          <h2>Alignement des Sceaux</h2>
          <div class="score-board">Paires : <span id="memory-score">0</span> / 8</div>
        </div>
        <div class="memory-grid" id="memory-board"></div>
        <button class="btn-action" onclick="initMemory()">Mélanger les Sceaux</button>
      </div>

      <!-- 6. BRISEUR DE SCEAUX (CASSE-BRIQUES) -->
      <div id="tab-breakout" class="tab-content">
        <div class="game-header">
          <h2>Briseur de Sceaux</h2>
          <div class="score-board">Sceaux : <span id="breakout-score">0</span> | Vies : <span id="breakout-lives">3</span></div>
        </div>
        <canvas id="canvas-breakout" width="360" height="360"></canvas>
        <div class="touch-controls">
          <div class="touch-row">
            <div class="touch-btn touch-btn-wide" onclick="triggerKey('breakout', 'ArrowLeft')">◄ Gauche</div>
            <div class="touch-btn touch-btn-wide" onclick="triggerKey('breakout', 'ArrowRight')">Droite ►</div>
          </div>
        </div>
        <button class="btn-action" onclick="initBreakout()">Recommencer le Rite</button>
      </div>

      <!-- 7. VOL DU TSURU (FLAPPY) -->
      <div id="tab-tsuru" class="tab-content">
        <div class="game-header">
          <h2>Le Vol du Tsuru</h2>
          <div class="score-board">Bambous : <span id="tsuru-score">0</span> | Record : <span id="tsuru-best">0</span></div>
        </div>
        <canvas id="canvas-tsuru" width="360" height="400"></canvas>
        <div class="touch-controls">
          <div class="touch-row">
            <div class="touch-btn touch-btn-wide" onclick="triggerKey('tsuru', ' ')">🕊️ BATTRE DES AILES</div>
          </div>
        </div>
        <p class="msg-jeu">Espace, ▲ ou toucher l'écran</p>
      </div>

      <!-- 8. FUSION DES PÉTALES (2048) -->
      <div id="tab-g2048" class="tab-content">
        <div class="game-header">
          <h2>Fusion des Pétales</h2>
          <div class="score-board">Score : <span id="score-2048">0</span></div>
        </div>
        <div class="board-2048" id="board-2048"></div>
        <div class="msg-jeu" id="msg-2048"></div>
        <div class="touch-controls">
          <div class="touch-row">
            <div class="touch-btn" onclick="triggerKey('g2048', 'ArrowUp')">▲</div>
          </div>
          <div class="touch-row">
            <div class="touch-btn" onclick="triggerKey('g2048', 'ArrowLeft')">◄</div>
            <div class="touch-btn" onclick="triggerKey('g2048', 'ArrowDown')">▼</div>
            <div class="touch-btn" onclick="triggerKey('g2048', 'ArrowRight')">►</div>
          </div>
        </div>
        <button class="btn-action" onclick="init2048()">Nouvelle floraison</button>
      </div>

      <!-- 9. JARDIN AUX ÉPINES (DÉMINEUR) -->
      <div id="tab-mines" class="tab-content">
        <div class="game-header">
          <h2>Jardin aux Épines</h2>
          <div class="score-board">Drapeaux : <span id="mines-left">10</span></div>
        </div>
        <div class="mines-grid" id="mines-board"></div>
        <div class="msg-jeu" id="mines-msg"></div>
        <button class="btn-action" id="mines-flag-btn" onclick="if (minesToggle) minesToggle()">🚩 Mode drapeau : NON</button>
        <button class="btn-action" onclick="initMines()">Replanter le jardin</button>
      </div>

      <!-- 10. CLOCHES SACRÉES (SIMON) -->
      <div id="tab-simon" class="tab-content">
        <div class="game-header">
          <h2>Les Cloches Sacrées</h2>
          <div class="score-board">Manche : <span id="simon-score">0</span> | Record : <span id="simon-best">0</span></div>
        </div>
        <div class="simon-grid">
          <div class="simon-pad" data-i="0"></div>
          <div class="simon-pad" data-i="1"></div>
          <div class="simon-pad" data-i="2"></div>
          <div class="simon-pad" data-i="3"></div>
        </div>
        <div class="msg-jeu" id="simon-msg"></div>
        <button class="btn-action" onclick="startSimon()">Commencer</button>
      </div>

      <!-- 11. RELIQUE DIVIN -->
      <div id="tab-relic" class="tab-content">
        <div class="relic-card">
          <h2 style="color: var(--text-light); font-family: 'Shippori Mincho', serif;">🖼️ Relique Sacrée Shinto</h2>
          <p style="margin-top: 8px; color: #6b4a5a; font-size: 0.85rem;">Artéfact mystique conservé dans l'enceinte :</p>
          
          <div class="relic-img-container">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTcS0HeGwAqWR0vDBAzjflymZVYp2B0VB_1d1kzD5FFnw&s=10" alt="Relique Divin" class="relic-img">
          </div>

          <br>
          <a href="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTcS0HeGwAqWR0vDBAzjflymZVYp2B0VB_1d1kzD5FFnw&s=10" target="_blank" class="relic-link">
            🔗 Ouvrir la Relique (Lien Direct)
          </a>
        </div>
      </div>
    </main>
  </div>

  <script>
    /* ==========================================
       1. SYSTÈME AUDIO (Web Audio API)
    ========================================== */
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    let audioCtx = null;

    function playSound(freq, type = 'sine', duration = 0.08) {
      if (!audioCtx) audioCtx = new AudioCtx();
      try {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.04, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + duration);
      } catch (e) {}
    }

    /* ==========================================
       2. PETALES DE CERISIER
    ========================================== */
    function createPetals() {
      const container = document.getElementById('sakura');
      for (let i = 0; i < 20; i++) {
        const petal = document.createElement('div');
        petal.className = 'petal';
        petal.style.left = Math.random() * 100 + 'vw';
        petal.style.width = (Math.random() * 8 + 6) + 'px';
        petal.style.height = (Math.random() * 10 + 8) + 'px';
        petal.style.animationDuration = (Math.random() * 5 + 5) + 's';
        petal.style.animationDelay = (Math.random() * 5) + 's';
        container.appendChild(petal);
      }
    }
    createPetals();

    /* ==========================================
       3. GESTION DES ONGLETS & TOUCHES MULTI-PLATEFORME
    ========================================== */
    let currentLoop = null;
    let snakeInterval = null;
    let simonTimers = [];
    let minesToggle = null;

    function switchTab(tabId) {
      if (currentLoop) cancelAnimationFrame(currentLoop);
      if (snakeInterval) clearInterval(snakeInterval);
      simonTimers.forEach(clearTimeout);
      simonTimers = [];
      window.onkeyup = null;

      document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));

      document.getElementById('tab-' + tabId).classList.add('active');
      event.currentTarget.classList.add('active');

      if (tabId === 'snake') initSnake();
      if (tabId === 'invaders') initInvaders();
      if (tabId === 'tetris') initTetris();
      if (tabId === 'pong') initPong();
      if (tabId === 'memory') initMemory();
      if (tabId === 'breakout') initBreakout();
      if (tabId === 'tsuru') initTsuru();
      if (tabId === 'g2048') init2048();
      if (tabId === 'mines') initMines();
      if (tabId === 'simon') initSimon();
    }

    let activeHandler = null;
    function triggerKey(game, key) {
      if (activeHandler) activeHandler({ key });
    }

    /* ==========================================
       JEU 1 : OROCHI (SNAKE)
    ========================================== */
    function initSnake() {
      const canvas = document.getElementById('canvas-snake');
      const ctx = canvas.getContext('2d');
      const grid = 18;
      let snake = [{x: 10, y: 10}, {x: 9, y: 10}];
      let food = {x: 14, y: 10};
      let dx = 1, dy = 0;
      let score = 0;
      document.getElementById('snake-score').innerText = score;

      if (snakeInterval) clearInterval(snakeInterval);

      function placeFood() {
        food = {
          x: Math.floor(Math.random() * (canvas.width / grid)),
          y: Math.floor(Math.random() * (canvas.height / grid))
        };
      }

      function gameStep() {
        const head = {x: snake[0].x + dx, y: snake[0].y + dy};

        if (head.x < 0 || head.x >= canvas.width / grid || head.y < 0 || head.y >= canvas.height / grid) {
          playSound(150, 'sawtooth', 0.2);
          initSnake();
          return;
        }

        for (let segment of snake) {
          if (segment.x === head.x && segment.y === head.y) {
            playSound(150, 'sawtooth', 0.2);
            initSnake();
            return;
          }
        }

        snake.unshift(head);

        if (head.x === food.x && head.y === food.y) {
          score += 10;
          document.getElementById('snake-score').innerText = score;
          playSound(520, 'sine', 0.08);
          placeFood();
        } else {
          snake.pop();
        }

        ctx.fillStyle = '#fff7f9';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        snake.forEach((part, i) => {
          ctx.fillStyle = i === 0 ? '#b8456a' : '#e58fa7';
          ctx.beginPath();
          ctx.arc((part.x + 0.5) * grid, (part.y + 0.5) * grid, grid/2 - 1, 0, Math.PI * 2);
          ctx.fill();
        });

        ctx.fillStyle = '#7a9a6b';
        ctx.beginPath();
        ctx.arc((food.x + 0.5) * grid, (food.y + 0.5) * grid, grid/3, 0, Math.PI * 2);
        ctx.fill();
      }

      activeHandler = (e) => {
        if ((e.key === 'ArrowUp' || e.key === 'z') && dy === 0) { dx = 0; dy = -1; }
        if ((e.key === 'ArrowDown' || e.key === 's') && dy === 0) { dx = 0; dy = 1; }
        if ((e.key === 'ArrowLeft' || e.key === 'q') && dx === 0) { dx = -1; dy = 0; }
        if ((e.key === 'ArrowRight' || e.key === 'd') && dx === 0) { dx = 1; dy = 0; }
      };
      window.onkeydown = activeHandler;

      snakeInterval = setInterval(gameStep, 110);
    }

    /* ==========================================
       JEU 2 : YOKAI DEFENSE (SPACE INVADERS)
    ========================================== */
    function initInvaders() {
      const canvas = document.getElementById('canvas-invaders');
      const ctx = canvas.getContext('2d');
      let player = { x: canvas.width / 2 - 15, y: canvas.height - 25, w: 30, h: 12 };
      let bullets = [];
      let yokais = [];
      let score = 0;
      let keys = {};
      let dir = 1;

      document.getElementById('invaders-score').innerText = score;

      for (let r = 0; r < 3; r++) {
        for (let c = 0; c < 6; c++) {
          yokais.push({ x: 40 + c * 48, y: 30 + r * 35, alive: true });
        }
      }

      window.onkeydown = (e) => keys[e.key] = true;
      window.onkeyup = (e) => keys[e.key] = false;

      activeHandler = (e) => {
        if (e.key === 'ArrowLeft') player.x = Math.max(0, player.x - 12);
        if (e.key === 'ArrowRight') player.x = Math.min(canvas.width - player.w, player.x + 12);
        if (e.key === ' ') {
          bullets.push({ x: player.x + player.w / 2 - 2, y: player.y, w: 4, h: 8 });
          playSound(750, 'square', 0.05);
        }
      };

      function loop() {
        if ((keys['ArrowLeft'] || keys['q']) && player.x > 0) player.x -= 3;
        if ((keys['ArrowRight'] || keys['d']) && player.x < canvas.width - player.w) player.x += 3;

        bullets.forEach(b => b.y -= 4.5);
        bullets = bullets.filter(b => b.y > 0);

        let moveDown = false;
        yokais.forEach(y => {
          if (y.alive) {
            y.x += dir * 0.7;
            if (y.x > canvas.width - 25 || y.x < 10) moveDown = true;
          }
        });

        if (moveDown) {
          dir *= -1;
          yokais.forEach(y => y.y += 10);
        }

        bullets.forEach(b => {
          yokais.forEach(y => {
            if (y.alive && b.x > y.x && b.x < y.x + 22 && b.y > y.y && b.y < y.y + 22) {
              y.alive = false;
              b.y = -100;
              score += 20;
              document.getElementById('invaders-score').innerText = score;
              playSound(300, 'sine', 0.08);
            }
          });
        });

        ctx.fillStyle = '#fff7f9';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.fillStyle = '#b8456a';
        ctx.fillRect(player.x, player.y, player.w, player.h);

        ctx.fillStyle = '#e58fa7';
        bullets.forEach(b => ctx.fillRect(b.x, b.y, b.w, b.h));

        yokais.forEach(y => {
          if (y.alive) {
            ctx.fillStyle = '#7a9a6b';
            ctx.beginPath();
            ctx.arc(y.x + 10, y.y + 10, 8, 0, Math.PI * 2);
            ctx.fill();
          }
        });

        currentLoop = requestAnimationFrame(loop);
      }
      currentLoop = requestAnimationFrame(loop);
    }

    /* ==========================================
       JEU 3 : BLOC TORII (TETRIS)
    ========================================== */
    function initTetris() {
      const canvas = document.getElementById('canvas-tetris');
      const ctx = canvas.getContext('2d');
      const cols = 10, rows = 15, size = 24;
      let grid = Array(rows).fill(null).map(() => Array(cols).fill(0));
      let score = 0;
      let dropCounter = 0;
      let lastTime = 0;

      document.getElementById('tetris-score').innerText = score;

      const shapes = [
        [[1, 1, 1, 1]],
        [[1, 1], [1, 1]],
        [[0, 1, 0], [1, 1, 1]],
        [[1, 0, 0], [1, 1, 1]]
      ];

      let piece = newPiece();

      function newPiece() {
        const shape = shapes[Math.floor(Math.random() * shapes.length)];
        return { shape, x: Math.floor(cols / 2) - 1, y: 0 };
      }

      function collide(p, gx, gy) {
        for (let r = 0; r < p.shape.length; r++) {
          for (let c = 0; c < p.shape[r].length; c++) {
            if (p.shape[r][c]) {
              let newX = p.x + c + gx;
              let newY = p.y + r + gy;
              if (newX < 0 || newX >= cols || newY >= rows || (newY >= 0 && grid[newY][newX])) {
                return true;
              }
            }
          }
        }
        return false;
      }

      function merge() {
        piece.shape.forEach((row, r) => {
          row.forEach((val, c) => {
            if (val) grid[piece.y + r][piece.x + c] = 1;
          });
        });

        for (let r = rows - 1; r >= 0; r--) {
          if (grid[r].every(v => v === 1)) {
            grid.splice(r, 1);
            grid.unshift(Array(cols).fill(0));
            score += 100;
            document.getElementById('tetris-score').innerText = score;
            playSound(600, 'sine', 0.12);
          }
        }
        piece = newPiece();
        if (collide(piece, 0, 0)) grid = Array(rows).fill(null).map(() => Array(cols).fill(0));
      }

      activeHandler = (e) => {
        if (e.key === 'ArrowLeft' && !collide(piece, -1, 0)) piece.x--;
        if (e.key === 'ArrowRight' && !collide(piece, 1, 0)) piece.x++;
        if (e.key === 'ArrowDown' && !collide(piece, 0, 1)) piece.y++;
        if (e.key === 'ArrowUp') {
          const rotated = piece.shape[0].map((_, i) => piece.shape.map(row => row[i]).reverse());
          const old = piece.shape;
          piece.shape = rotated;
          if (collide(piece, 0, 0)) piece.shape = old;
        }
      };
      window.onkeydown = activeHandler;

      function update(time = 0) {
        const dt = time - lastTime;
        lastTime = time;
        dropCounter += dt;

        if (dropCounter > 650) {
          if (!collide(piece, 0, 1)) {
            piece.y++;
          } else {
            merge();
          }
          dropCounter = 0;
        }

        ctx.fillStyle = '#fff7f9';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        grid.forEach((row, r) => {
          row.forEach((val, c) => {
            if (val) {
              ctx.fillStyle = '#e58fa7';
              ctx.fillRect(c * size, r * size, size - 1, size - 1);
            }
          });
        });

        ctx.fillStyle = '#b8456a';
        piece.shape.forEach((row, r) => {
          row.forEach((val, c) => {
            if (val) ctx.fillRect((piece.x + c) * size, (piece.y + r) * size, size - 1, size - 1);
          });
        });

        currentLoop = requestAnimationFrame(update);
      }
      update();
    }

    /* ==========================================
       JEU 4 : MIROIR DIVIN (PONG)
    ========================================== */
    function initPong() {
      const canvas = document.getElementById('canvas-pong');
      const ctx = canvas.getContext('2d');
      let pY = 110, aiY = 110;
      let ball = { x: 180, y: 150, dx: 2.8, dy: 2.8 };
      let pScore = 0, aiScore = 0;
      let keys = {};

      window.onkeydown = (e) => keys[e.key] = true;
      window.onkeyup = (e) => keys[e.key] = false;

      activeHandler = (e) => {
        if (e.key === 'ArrowUp') pY = Math.max(0, pY - 25);
        if (e.key === 'ArrowDown') pY = Math.min(canvas.height - 60, pY + 25);
      };

      function loop() {
        if (keys['ArrowUp'] && pY > 0) pY -= 3.5;
        if (keys['ArrowDown'] && pY < canvas.height - 60) pY += 3.5;

        if (aiY + 30 < ball.y) aiY += 2.2;
        else if (aiY + 30 > ball.y) aiY -= 2.2;

        ball.x += ball.dx;
        ball.y += ball.dy;

        if (ball.y <= 0 || ball.y >= canvas.height) ball.dy *= -1;

        if (ball.x <= 18 && ball.y >= pY && ball.y <= pY + 60) {
          ball.dx = Math.abs(ball.dx);
          playSound(400, 'sine', 0.05);
        }

        if (ball.x >= canvas.width - 18 && ball.y >= aiY && ball.y <= aiY + 60) {
          ball.dx = -Math.abs(ball.dx);
          playSound(350, 'sine', 0.05);
        }

        if (ball.x < 0) {
          aiScore++;
          document.getElementById('pong-ai').innerText = aiScore;
          ball = { x: 180, y: 150, dx: 2.8, dy: 2.8 };
        }
        if (ball.x > canvas.width) {
          pScore++;
          document.getElementById('pong-player').innerText = pScore;
          ball = { x: 180, y: 150, dx: -2.8, dy: 2.8 };
        }

        ctx.fillStyle = '#fff7f9';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.fillStyle = '#b8456a';
        ctx.fillRect(8, pY, 8, 60);
        ctx.fillRect(canvas.width - 16, aiY, 8, 60);

        ctx.beginPath();
        ctx.arc(ball.x, ball.y, 6, 0, Math.PI * 2);
        ctx.fillStyle = '#e58fa7';
        ctx.fill();

        currentLoop = requestAnimationFrame(loop);
      }
      loop();
    }

    /* ==========================================
       JEU 5 : SCEAUX KUJI-IN (MEMORY)
    ========================================== */
    function initMemory() {
      const board = document.getElementById('memory-board');
      board.innerHTML = '';
      const symbols = ['⛩️', '🌸', '☯️', '⚔️', '👺', '🐉', '🌕', '🔥'];
      const cardsData = [...symbols, ...symbols].sort(() => Math.random() - 0.5);

      let flipped = [];
      let matchedCount = 0;
      document.getElementById('memory-score').innerText = 0;

      cardsData.forEach((sym) => {
        const card = document.createElement('div');
        card.className = 'memory-card';
        card.dataset.symbol = sym;
        card.innerText = '❓';

        card.onclick = () => {
          if (flipped.length < 2 && !card.classList.contains('flipped')) {
            card.classList.add('flipped');
            card.innerText = sym;
            flipped.push(card);
            playSound(500, 'sine', 0.05);

            if (flipped.length === 2) {
              if (flipped[0].dataset.symbol === flipped[1].dataset.symbol) {
                matchedCount++;
                document.getElementById('memory-score').innerText = matchedCount;
                flipped = [];
                playSound(800, 'sine', 0.1);
              } else {
                setTimeout(() => {
                  flipped.forEach(c => {
                    c.classList.remove('flipped');
                    c.innerText = '❓';
                  });
                  flipped = [];
                }, 700);
              }
            }
          }
        };
        board.appendChild(card);
      });
    }

    /* ==========================================
       JEU 6 : BRISEUR DE SCEAUX (CASSE-BRIQUES)
    ========================================== */
    function initBreakout() {
      const canvas = document.getElementById('canvas-breakout');
      const ctx = canvas.getContext('2d');
      const W = canvas.width, H = canvas.height;
      const paddle = { x: W / 2 - 32, y: H - 22, w: 64, h: 8 };
      const cols = 6, rows = 4, bw = 50, bh = 16, gap = 6;
      const offX = (W - (cols * bw + (cols - 1) * gap)) / 2, offY = 40;
      const rowColors = ['#b8456a', '#d9708f', '#e58fa7', '#7a9a6b'];
      let ball, bricks, score = 0, lives = 3, level = 1, keys = {};

      function resetBall() {
        ball = { x: W / 2, y: paddle.y - 10, dx: (Math.random() < 0.5 ? -1 : 1) * 2.4, dy: -(3 + (level - 1) * 0.4), r: 5 };
      }
      function buildBricks() {
        bricks = [];
        for (let r = 0; r < rows; r++) {
          for (let c = 0; c < cols; c++) {
            bricks.push({ x: offX + c * (bw + gap), y: offY + r * (bh + gap), color: rowColors[r], alive: true });
          }
        }
      }
      function updateHud() {
        document.getElementById('breakout-score').innerText = score;
        document.getElementById('breakout-lives').innerText = lives;
      }
      buildBricks(); resetBall(); updateHud();

      window.onkeydown = (e) => { keys[e.key] = true; if (e.key.indexOf('Arrow') === 0) e.preventDefault(); };
      window.onkeyup = (e) => { keys[e.key] = false; };
      activeHandler = (e) => {
        if (e.key === 'ArrowLeft') paddle.x = Math.max(0, paddle.x - 30);
        if (e.key === 'ArrowRight') paddle.x = Math.min(W - paddle.w, paddle.x + 30);
      };
      canvas.style.touchAction = 'none';
      canvas.onpointermove = (e) => {
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) * (W / rect.width);
        paddle.x = Math.min(W - paddle.w, Math.max(0, x - paddle.w / 2));
      };

      function loop() {
        if ((keys['ArrowLeft'] || keys['q']) && paddle.x > 0) paddle.x -= 5;
        if ((keys['ArrowRight'] || keys['d']) && paddle.x < W - paddle.w) paddle.x += 5;

        ball.x += ball.dx;
        ball.y += ball.dy;
        if (ball.x < ball.r || ball.x > W - ball.r) {
          ball.dx *= -1;
          ball.x = Math.max(ball.r, Math.min(W - ball.r, ball.x));
        }
        if (ball.y < ball.r) ball.dy = Math.abs(ball.dy);

        if (ball.dy > 0 && ball.y + ball.r >= paddle.y && ball.y + ball.r <= paddle.y + paddle.h + 6 &&
            ball.x >= paddle.x - 2 && ball.x <= paddle.x + paddle.w + 2) {
          const off = (ball.x - (paddle.x + paddle.w / 2)) / (paddle.w / 2);
          ball.dx = off * 3.6;
          ball.dy = -(3 + (level - 1) * 0.4);
          playSound(400, 'sine', 0.05);
        }

        for (const b of bricks) {
          if (b.alive && ball.x + ball.r > b.x && ball.x - ball.r < b.x + bw && ball.y + ball.r > b.y && ball.y - ball.r < b.y + bh) {
            b.alive = false;
            ball.dy *= -1;
            score += 10;
            updateHud();
            playSound(560, 'sine', 0.06);
            break;
          }
        }

        if (ball.y > H + 10) {
          lives--;
          playSound(150, 'sawtooth', 0.2);
          if (lives <= 0) { score = 0; lives = 3; level = 1; buildBricks(); }
          updateHud();
          resetBall();
        }
        if (bricks.every(b => !b.alive)) {
          level++;
          buildBricks();
          resetBall();
          playSound(800, 'sine', 0.15);
        }

        ctx.fillStyle = '#fff7f9';
        ctx.fillRect(0, 0, W, H);
        bricks.forEach(b => {
          if (b.alive) { ctx.fillStyle = b.color; ctx.fillRect(b.x, b.y, bw, bh); }
        });
        ctx.fillStyle = '#b8456a';
        ctx.fillRect(paddle.x, paddle.y, paddle.w, paddle.h);
        ctx.beginPath();
        ctx.arc(ball.x, ball.y, ball.r, 0, Math.PI * 2);
        ctx.fillStyle = '#3b2230';
        ctx.fill();

        currentLoop = requestAnimationFrame(loop);
      }
      currentLoop = requestAnimationFrame(loop);
    }

    /* ==========================================
       JEU 7 : LE VOL DU TSURU (FLAPPY)
    ========================================== */
    let tsuruBest = 0;
    function initTsuru() {
      const canvas = document.getElementById('canvas-tsuru');
      const ctx = canvas.getContext('2d');
      const W = canvas.width, H = canvas.height;
      const gap = 112, pw = 48, ground = 14;
      let bird, pipes, frame, score, state, deadAt;

      function reset() {
        bird = { x: 80, y: H / 2, vy: 0 };
        pipes = []; frame = 0; score = 0; state = 'ready'; deadAt = 0;
        document.getElementById('tsuru-score').innerText = 0;
      }
      reset();

      function flap() {
        if (state === 'dead') {
          if (Date.now() - deadAt > 500) reset();
          return;
        }
        if (state === 'ready') state = 'play';
        bird.vy = -5.4;
        playSound(620, 'sine', 0.05);
      }
      function die() {
        state = 'dead';
        deadAt = Date.now();
        if (score > tsuruBest) tsuruBest = score;
        document.getElementById('tsuru-best').innerText = tsuruBest;
        playSound(150, 'sawtooth', 0.25);
      }

      window.onkeydown = (e) => {
        if ([' ', 'ArrowUp', 'z'].includes(e.key)) { e.preventDefault(); if (!e.repeat) flap(); }
      };
      activeHandler = (e) => { if (e.key === ' ' || e.key === 'ArrowUp') flap(); };
      canvas.style.touchAction = 'none';
      canvas.onpointerdown = (e) => { e.preventDefault(); flap(); };

      function drawPipe(x, y1, y2) {
        ctx.fillStyle = '#7a9a6b';
        ctx.fillRect(x, y1, pw, y2 - y1);
        ctx.fillStyle = 'rgba(255,255,255,0.35)';
        for (let y = y1 + 22; y < y2; y += 32) ctx.fillRect(x, y, pw, 3);
      }

      function loop() {
        if (state === 'play') {
          frame++;
          bird.vy += 0.32;
          bird.y += bird.vy;
          if (frame % 88 === 1) pipes.push({ x: W, top: 50 + Math.random() * (H - gap - 100 - ground), passed: false });
          pipes.forEach(p => p.x -= 2.3);
          pipes = pipes.filter(p => p.x > -pw - 6);
          if (bird.y + 11 > H - ground || bird.y - 11 < 0) die();
          pipes.forEach(p => {
            if (bird.x + 11 > p.x && bird.x - 11 < p.x + pw && (bird.y - 11 < p.top || bird.y + 11 > p.top + gap)) die();
            if (!p.passed && p.x + pw < bird.x) {
              p.passed = true;
              score++;
              document.getElementById('tsuru-score').innerText = score;
              playSound(700, 'sine', 0.06);
            }
          });
        } else if (state === 'ready') {
          frame++;
          bird.y = H / 2 + Math.sin(frame * 0.08) * 6;
        }

        ctx.fillStyle = '#fff7f9';
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = '#f9d3dd';
        ctx.beginPath(); ctx.arc(W - 70, 70, 34, 0, Math.PI * 2); ctx.fill();

        pipes.forEach(p => {
          drawPipe(p.x, 0, p.top);
          drawPipe(p.x, p.top + gap, H - ground);
          ctx.fillStyle = '#5f7f52';
          ctx.fillRect(p.x - 4, p.top - 10, pw + 8, 10);
          ctx.fillRect(p.x - 4, p.top + gap, pw + 8, 10);
        });
        ctx.fillStyle = '#e9c9d3';
        ctx.fillRect(0, H - ground, W, ground);

        ctx.save();
        ctx.translate(bird.x, bird.y);
        ctx.rotate(Math.max(-0.5, Math.min(0.9, bird.vy * 0.07)));
        ctx.fillStyle = '#e58fa7';
        ctx.beginPath(); ctx.ellipse(0, 0, 13, 10, 0, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#b8456a';
        ctx.beginPath(); ctx.ellipse(-3, 2, 7, 4, -0.4, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#d9b26a';
        ctx.beginPath(); ctx.moveTo(12, -2); ctx.lineTo(21, 1); ctx.lineTo(12, 4); ctx.fill();
        ctx.fillStyle = '#3b2230';
        ctx.beginPath(); ctx.arc(6, -3, 2, 0, Math.PI * 2); ctx.fill();
        ctx.restore();

        if (state !== 'play') {
          ctx.fillStyle = '#3b2230';
          ctx.font = '700 17px "Shippori Mincho", serif';
          ctx.textAlign = 'center';
          ctx.fillText(state === 'ready' ? 'Touche pour battre des ailes' : 'Perdu · Touche pour rejouer', W / 2, H / 2 + 70);
        }
        currentLoop = requestAnimationFrame(loop);
      }
      currentLoop = requestAnimationFrame(loop);
    }

    /* ==========================================
       JEU 8 : FUSION DES PÉTALES (2048)
    ========================================== */
    function init2048() {
      const boardEl = document.getElementById('board-2048');
      const msgEl = document.getElementById('msg-2048');
      const palette = { 2: '#fdeef2', 4: '#f9d3dd', 8: '#f3a4ba', 16: '#e58fa7', 32: '#d9708f', 64: '#c9577a', 128: '#b8456a', 256: '#8f3a58', 512: '#7a9a6b', 1024: '#5f7f52', 2048: '#3b2230' };
      let grid = Array.from({ length: 4 }, () => Array(4).fill(0));
      let score = 0, won = false;
      msgEl.innerText = '';

      function addTile() {
        const empty = [];
        grid.forEach((row, r) => row.forEach((v, c) => { if (!v) empty.push([r, c]); }));
        if (!empty.length) return;
        const [r, c] = empty[Math.floor(Math.random() * empty.length)];
        grid[r][c] = Math.random() < 0.9 ? 2 : 4;
      }
      function slideLine(line) {
        const vals = line.filter(Boolean);
        let gained = 0;
        for (let i = 0; i < vals.length - 1; i++) {
          if (vals[i] === vals[i + 1]) {
            vals[i] *= 2;
            gained += vals[i];
            vals.splice(i + 1, 1);
          }
        }
        while (vals.length < 4) vals.push(0);
        return { line: vals, gained };
      }
      function canMove() {
        for (let r = 0; r < 4; r++) {
          for (let c = 0; c < 4; c++) {
            if (!grid[r][c]) return true;
            if (c < 3 && grid[r][c] === grid[r][c + 1]) return true;
            if (r < 3 && grid[r][c] === grid[r + 1][c]) return true;
          }
        }
        return false;
      }
      function render() {
        boardEl.innerHTML = '';
        grid.forEach(row => row.forEach(v => {
          const d = document.createElement('div');
          d.className = 'tile-2048';
          if (v) {
            d.textContent = v;
            d.style.background = palette[v] || '#3b2230';
            d.style.color = v <= 16 ? '#3b2230' : '#fff';
            if (v >= 1000) d.style.fontSize = '1.05rem';
          }
          boardEl.appendChild(d);
        }));
        document.getElementById('score-2048').innerText = score;
      }
      function move(dir) {
        let moved = false;
        for (let i = 0; i < 4; i++) {
          let line;
          if (dir === 'L') line = grid[i].slice();
          if (dir === 'R') line = grid[i].slice().reverse();
          if (dir === 'U') line = grid.map(row => row[i]);
          if (dir === 'D') line = grid.map(row => row[i]).reverse();
          const res = slideLine(line);
          score += res.gained;
          let out = res.line;
          if (dir === 'R' || dir === 'D') out = out.reverse();
          for (let j = 0; j < 4; j++) {
            const r = (dir === 'L' || dir === 'R') ? i : j;
            const c = (dir === 'L' || dir === 'R') ? j : i;
            if (grid[r][c] !== out[j]) { moved = true; grid[r][c] = out[j]; }
          }
        }
        if (moved) { addTile(); playSound(440, 'sine', 0.05); }
        render();
        if (!won && grid.some(row => row.includes(2048))) { won = true; msgEl.innerText = 'Bravo, tu as atteint 2048 ! 🌸'; }
        else if (!canMove()) msgEl.innerText = 'Plus aucun mouvement possible…';
      }
      function handle(e) {
        const k = e.key;
        if (msgEl.innerText.indexOf('Plus aucun') === 0) return;
        if (k === 'ArrowLeft' || k === 'q') move('L');
        else if (k === 'ArrowRight' || k === 'd') move('R');
        else if (k === 'ArrowUp' || k === 'z') move('U');
        else if (k === 'ArrowDown' || k === 's') move('D');
      }
      window.onkeydown = (e) => { if (e.key.indexOf('Arrow') === 0) e.preventDefault(); handle(e); };
      activeHandler = handle;

      let sx = 0, sy = 0;
      boardEl.ontouchstart = (e) => { const t = e.touches[0]; sx = t.clientX; sy = t.clientY; };
      boardEl.ontouchend = (e) => {
        const t = e.changedTouches[0];
        const dx = t.clientX - sx, dy = t.clientY - sy;
        if (Math.max(Math.abs(dx), Math.abs(dy)) < 30) return;
        if (msgEl.innerText.indexOf('Plus aucun') === 0) return;
        if (Math.abs(dx) > Math.abs(dy)) move(dx > 0 ? 'R' : 'L'); else move(dy > 0 ? 'D' : 'U');
      };

      addTile(); addTile(); render();
    }

    /* ==========================================
       JEU 9 : JARDIN AUX ÉPINES (DÉMINEUR)
    ========================================== */
    function initMines() {
      const ROWS = 9, COLS = 9, MINES = 10;
      const board = document.getElementById('mines-board');
      const msg = document.getElementById('mines-msg');
      const flagBtn = document.getElementById('mines-flag-btn');
      const colors = ['', '#3b6fb0', '#3f7f3a', '#c0392b', '#5b3a8f', '#8f3a3a', '#2a7f7f', '#3b2230', '#6b4a5a'];
      let started = false, over = false, flagMode = false, flags = 0, opened = 0;
      const cells = Array.from({ length: ROWS * COLS }, (_, i) => ({ i, r: Math.floor(i / COLS), c: i % COLS, mine: false, open: false, flag: false, n: 0, el: null }));

      msg.innerText = '';
      board.innerHTML = '';
      window.onkeydown = null;
      activeHandler = null;

      function neighbors(cell) {
        const out = [];
        for (let dr = -1; dr <= 1; dr++) {
          for (let dc = -1; dc <= 1; dc++) {
            if (!dr && !dc) continue;
            const r = cell.r + dr, c = cell.c + dc;
            if (r >= 0 && r < ROWS && c >= 0 && c < COLS) out.push(cells[r * COLS + c]);
          }
        }
        return out;
      }
      function updateHud() {
        document.getElementById('mines-left').innerText = MINES - flags;
        flagBtn.innerText = '🚩 Mode drapeau : ' + (flagMode ? 'OUI' : 'NON');
      }
      function draw(cell) {
        const el = cell.el;
        el.className = 'mine-cell' + (cell.open ? ' open' : '');
        el.style.color = '';
        if (cell.open) {
          if (cell.mine) { el.className += ' boom'; el.textContent = '🌵'; }
          else if (cell.n) { el.textContent = cell.n; el.style.color = colors[cell.n]; }
          else el.textContent = '';
        } else {
          el.textContent = cell.flag ? '🚩' : '';
        }
      }
      function placeMines(safe) {
        const forbidden = new Set([safe.i].concat(neighbors(safe).map(n => n.i)));
        let placed = 0;
        while (placed < MINES) {
          const k = Math.floor(Math.random() * cells.length);
          if (forbidden.has(k) || cells[k].mine) continue;
          cells[k].mine = true;
          placed++;
        }
        cells.forEach(c => { c.n = neighbors(c).filter(x => x.mine).length; });
      }
      function reveal(start) {
        const stack = [start];
        while (stack.length) {
          const cell = stack.pop();
          if (cell.open || cell.flag) continue;
          cell.open = true;
          opened++;
          draw(cell);
          if (cell.n === 0) neighbors(cell).forEach(nb => { if (!nb.open && !nb.mine) stack.push(nb); });
        }
      }
      function toggleFlag(cell) {
        if (over || cell.open) return;
        cell.flag = !cell.flag;
        flags += cell.flag ? 1 : -1;
        updateHud();
        draw(cell);
        playSound(cell.flag ? 520 : 380, 'sine', 0.04);
      }
      function click(cell) {
        if (over) return;
        if (flagMode) { toggleFlag(cell); return; }
        if (cell.flag || cell.open) return;
        if (!started) { placeMines(cell); started = true; }
        if (cell.mine) {
          over = true;
          cells.forEach(c => { if (c.mine) { c.open = true; draw(c); } });
          msg.innerText = 'Aïe, une épine ! Replante le jardin.';
          playSound(150, 'sawtooth', 0.3);
          return;
        }
        reveal(cell);
        playSound(500, 'sine', 0.04);
        if (opened === ROWS * COLS - MINES) {
          over = true;
          msg.innerText = 'Jardin purifié ! 🌸';
          playSound(800, 'sine', 0.2);
        }
      }

      cells.forEach(cell => {
        const el = document.createElement('div');
        el.className = 'mine-cell';
        el.onclick = () => click(cell);
        el.oncontextmenu = (e) => { e.preventDefault(); toggleFlag(cell); };
        cell.el = el;
        board.appendChild(el);
      });
      minesToggle = () => { flagMode = !flagMode; updateHud(); };
      updateHud();
    }

    /* ==========================================
       JEU 10 : LES CLOCHES SACRÉES (SIMON)
    ========================================== */
    let simonSeq = [], simonPos = 0, simonLocked = true, simonBest = 0;
    const SIMON_TONES = [262, 330, 392, 523];

    function simonLater(fn, ms) { simonTimers.push(setTimeout(fn, ms)); }
    function simonMsg(t) { document.getElementById('simon-msg').innerText = t; }
    function litPad(i, ms) {
      const pad = document.querySelectorAll('.simon-pad')[i];
      pad.classList.add('lit');
      playSound(SIMON_TONES[i], 'sine', ms / 1000);
      simonLater(() => pad.classList.remove('lit'), ms);
    }
    function initSimon() {
      simonTimers.forEach(clearTimeout);
      simonTimers = [];
      simonSeq = []; simonPos = 0; simonLocked = true;
      document.getElementById('simon-score').innerText = 0;
      document.getElementById('simon-best').innerText = simonBest;
      simonMsg('Appuie sur « Commencer »');
      document.querySelectorAll('.simon-pad').forEach((p, i) => { p.onclick = () => simonPress(i); p.classList.remove('lit'); });
      window.onkeydown = (e) => { const k = parseInt(e.key, 10); if (k >= 1 && k <= 4) simonPress(k - 1); };
      activeHandler = null;
    }
    function startSimon() {
      initSimon();
      simonLater(nextSimonRound, 400);
    }
    function nextSimonRound() {
      simonSeq.push(Math.floor(Math.random() * 4));
      simonLocked = true;
      simonPos = 0;
      document.getElementById('simon-score').innerText = simonSeq.length;
      simonMsg('Écoute…');
      simonSeq.forEach((p, k) => simonLater(() => litPad(p, 380), 600 + k * 650));
      simonLater(() => { simonLocked = false; simonMsg('À toi !'); }, 600 + simonSeq.length * 650);
    }
    function simonPress(i) {
      if (simonLocked) return;
      if (i !== simonSeq[simonPos]) {
        simonLocked = true;
        playSound(150, 'sawtooth', 0.3);
        const done = simonSeq.length - 1;
        if (done > simonBest) simonBest = done;
        document.getElementById('simon-best').innerText = simonBest;
        simonMsg('Raté ! ' + done + ' manche(s) réussie(s).');
        return;
      }
      litPad(i, 220);
      simonPos++;
      if (simonPos === simonSeq.length) {
        simonLocked = true;
        simonMsg('Bien joué !');
        simonLater(nextSimonRound, 800);
      }
    }

    /* Le serveur a déjà validé l'accès : on lance le premier jeu */
    initSnake();
  </script>
</body>
</html>
