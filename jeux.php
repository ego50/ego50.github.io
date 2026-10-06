<?php
// ==========================================================
// jeux.php — Sanctuaire des Kami (protégé côté serveur)
// Mot de passe jeu (config) → compte (login/inscription) ou invité
// Admin : commande secrète pour fixer un score ou bannir
// ==========================================================
require 'gestion.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

// Code secret admin (change-le en production). Exemple : ?api=admin + POST secret + action
if (!defined('CODE_SECRET_ADMIN_JEU')) {
    define('CODE_SECRET_ADMIN_JEU', (string) ($codeSecretAdminJeu ?? 'kami-admin-2026-sceau'));
}

// id => [nom affiché, score maximum plausible]
$JEUX = [
    'snake' => ['Orochi', 100000], 'invaders' => ['Yōkai', 100000], 'blockblast' => ['Bloc Blast', 500000],
    'pong' => ['Miroir', 1000], 'memory' => ['Sceaux', 5000], 'breakout' => ['Briseur', 10000],
    'tsuru' => ['Tsuru', 500], 'g2048' => ['Pétales 2048', 400000], 'flood' => ['Inondation', 5000],
    'simon' => ['Cloches', 100], 'catcher' => ['Pluie de pétales', 3000], 'reflex' => ['Kendo', 1000],
    'taupe' => ['Taupes', 80], 'runner' => ['Ninja', 5000], 'sort' => ['Tri Sakura', 500],
];

// --- Stockage (scores + comptes) ---
define('DOSSIER_SCORES', __DIR__ . '/donnees_jeux');
function assurerDossierScores() {
    if (!is_dir(DOSSIER_SCORES)) {
        @mkdir(DOSSIER_SCORES, 0755, true);
        @file_put_contents(DOSSIER_SCORES . '/.htaccess',
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
}
function fichierScores() {
    assurerDossierScores();
    return DOSSIER_SCORES . '/scores.json';
}
function fichierComptes() {
    assurerDossierScores();
    return DOSSIER_SCORES . '/comptes.json';
}
function lireScores() {
    $f = fichierScores();
    $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
    return is_array($d) ? $d : ['scores' => [], 'recent' => []];
}
function majScores($fonction) {
    $fp = @fopen(fichierScores(), 'c+');
    if (!$fp) { return; }
    flock($fp, LOCK_EX);
    $d = json_decode((string) stream_get_contents($fp), true);
    if (!is_array($d)) { $d = ['scores' => [], 'recent' => []]; }
    $fonction($d);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}
function lireComptes() {
    $f = fichierComptes();
    $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
    return is_array($d) ? $d : ['users' => []];
}
function majComptes($fonction) {
    $fp = @fopen(fichierComptes(), 'c+');
    if (!$fp) { return null; }
    flock($fp, LOCK_EX);
    $d = json_decode((string) stream_get_contents($fp), true);
    if (!is_array($d)) { $d = ['users' => []]; }
    $res = $fonction($d);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $res;
}
function reponseScores($d, $jeu, $pseudo) {
    global $JEUX;
    $liste = array_values($d['scores'][$jeu] ?? []);
    usort($liste, function ($a, $b) { return $b['score'] <=> $a['score']; });
    $top = [];
    foreach (array_slice($liste, 0, 10) as $e) {
        $top[] = ['pseudo' => $e['pseudo'], 'score' => $e['score']];
    }
    $recent = [];
    foreach (array_slice(array_reverse($d['recent'] ?? []), 0, 8) as $r) {
        $recent[] = ['pseudo' => $r['pseudo'], 'jeu' => $JEUX[$r['jeu']][0] ?? $r['jeu'], 'score' => $r['score']];
    }
    $moi = ($pseudo !== '') ? ($d['scores'][$jeu][mb_strtolower($pseudo)]['score'] ?? 0) : 0;
    return ['top' => $top, 'recent' => $recent, 'moi' => $moi];
}
function estBanni($pseudo) {
    if ($pseudo === '') return false;
    $c = lireComptes();
    $u = $c['users'][mb_strtolower($pseudo)] ?? null;
    return $u && !empty($u['banned']);
}

$erreur = '';
$erreurPseudo = '';
$peutEntrer = estAdmin() || !empty($_SESSION['jeu_ok']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'quitter_jeu' && verifierCsrf()) {
        unset($_SESSION['jeu_ok'], $_SESSION['pseudo'], $_SESSION['pseudo_ok'], $_SESSION['compte_ok']);
        session_regenerate_id(true);
        header('Location: jeux.php');
        exit;
    }
    if (in_array($action, ['choisir_pseudo', 'sans_pseudo', 'changer_pseudo', 'login_compte', 'register_compte'], true) && $peutEntrer && verifierCsrf()) {
        if ($action === 'changer_pseudo') {
            unset($_SESSION['pseudo'], $_SESSION['pseudo_ok'], $_SESSION['compte_ok']);
            header('Location: jeux.php');
            exit;
        }
        if ($action === 'sans_pseudo') {
            $_SESSION['pseudo'] = '';
            $_SESSION['pseudo_ok'] = true;
            $_SESSION['compte_ok'] = false;
            header('Location: jeux.php');
            exit;
        }
        if ($action === 'login_compte' || $action === 'register_compte') {
            $p = trim((string) ($_POST['pseudo'] ?? ''));
            $mdp = (string) ($_POST['mdp_compte'] ?? '');
            if (mb_strlen($p) < 2 || mb_strlen($p) > 16 || !preg_match('/^[\p{L}\p{N} _\-]+$/u', $p)) {
                $erreurPseudo = 'Pseudo de 2 à 16 caractères : lettres, chiffres, espace, _ ou -.';
            } elseif (mb_strlen($mdp) < 4) {
                $erreurPseudo = 'Mot de passe compte : au moins 4 caractères.';
            } else {
                $cle = mb_strtolower($p);
                if ($action === 'register_compte') {
                    $ok = majComptes(function (&$d) use ($cle, $p, $mdp) {
                        if (isset($d['users'][$cle])) return false;
                        $d['users'][$cle] = [
                            'pseudo' => $p,
                            'hash' => password_hash($mdp, PASSWORD_DEFAULT),
                            'banned' => false,
                            'created' => time(),
                        ];
                        return true;
                    });
                    if ($ok === false) {
                        $erreurPseudo = 'Ce pseudo existe déjà. Connecte-toi ou choisis un autre.';
                    } elseif ($ok) {
                        $_SESSION['pseudo'] = $p;
                        $_SESSION['pseudo_ok'] = true;
                        $_SESSION['compte_ok'] = true;
                        header('Location: jeux.php');
                        exit;
                    } else {
                        $erreurPseudo = 'Impossible d\'enregistrer le compte.';
                    }
                } else {
                    $c = lireComptes();
                    $u = $c['users'][$cle] ?? null;
                    if (!$u || !password_verify($mdp, $u['hash'] ?? '')) {
                        $erreurPseudo = 'Pseudo ou mot de passe incorrect.';
                    } elseif (!empty($u['banned'])) {
                        $erreurPseudo = 'Ce compte est banni du sanctuaire.';
                    } else {
                        $_SESSION['pseudo'] = $u['pseudo'] ?? $p;
                        $_SESSION['pseudo_ok'] = true;
                        $_SESSION['compte_ok'] = true;
                        header('Location: jeux.php');
                        exit;
                    }
                }
            }
        } elseif ($action === 'choisir_pseudo') {
            // Ancien flux : pseudo sans mot de passe (invité nommé, non persisté entre appareils)
            $p = trim((string) ($_POST['pseudo'] ?? ''));
            if (mb_strlen($p) < 2 || mb_strlen($p) > 16 || !preg_match('/^[\p{L}\p{N} _\-]+$/u', $p)) {
                $erreurPseudo = 'Pseudo de 2 à 16 caractères : lettres, chiffres, espace, _ ou -.';
            } elseif (estBanni($p)) {
                $erreurPseudo = 'Ce pseudo est banni.';
            } else {
                $_SESSION['pseudo'] = $p;
                $_SESSION['pseudo_ok'] = true;
                $_SESSION['compte_ok'] = false;
                header('Location: jeux.php');
                exit;
            }
        }
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

// --- API scores + admin ---
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Admin secret (pas besoin d'être « joueur » : le code suffit)
    if ($_GET['api'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $secret = (string) ($_POST['secret'] ?? '');
        if (!hash_equals(CODE_SECRET_ADMIN_JEU, $secret)) {
            http_response_code(403);
            echo '{"erreur":"secret"}';
            exit;
        }
        $cmd = (string) ($_POST['cmd'] ?? '');
        if ($cmd === 'set_score') {
            $jeu = (string) ($_POST['game'] ?? '');
            $pseudo = trim((string) ($_POST['pseudo'] ?? ''));
            $score = (int) ($_POST['score'] ?? 0);
            if (!isset($JEUX[$jeu]) || $pseudo === '' || $score < 0) {
                echo '{"erreur":"params"}';
                exit;
            }
            majScores(function (&$d) use ($jeu, $pseudo, $score) {
                $cle = mb_strtolower($pseudo);
                $d['scores'][$jeu][$cle] = ['pseudo' => $pseudo, 'score' => $score, 'date' => time()];
                $d['recent'][] = ['pseudo' => $pseudo, 'jeu' => $jeu, 'score' => $score, 'date' => time()];
                $d['recent'] = array_slice($d['recent'], -20);
            });
            echo json_encode(['ok' => true, 'msg' => 'Score fixé'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($cmd === 'ban') {
            $pseudo = trim((string) ($_POST['pseudo'] ?? ''));
            $ban = !isset($_POST['unban']);
            if ($pseudo === '') { echo '{"erreur":"params"}'; exit; }
            majComptes(function (&$d) use ($pseudo, $ban) {
                $cle = mb_strtolower($pseudo);
                if (!isset($d['users'][$cle])) {
                    $d['users'][$cle] = ['pseudo' => $pseudo, 'hash' => '', 'banned' => $ban, 'created' => time()];
                } else {
                    $d['users'][$cle]['banned'] = $ban;
                }
            });
            // Retirer aussi les scores si ban
            if ($ban) {
                majScores(function (&$d) use ($pseudo) {
                    $cle = mb_strtolower($pseudo);
                    foreach ($d['scores'] as $j => $list) {
                        unset($d['scores'][$j][$cle]);
                    }
                });
            }
            echo json_encode(['ok' => true, 'msg' => $ban ? 'Banni' : 'Débanni'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo '{"erreur":"cmd"}';
        exit;
    }

    if (!$autorise || empty($_SESSION['pseudo_ok'])) {
        http_response_code(403);
        echo '{"erreur":"acces"}';
        exit;
    }
    $pseudo = (string) ($_SESSION['pseudo'] ?? '');
    if ($pseudo !== '' && estBanni($pseudo)) {
        http_response_code(403);
        echo '{"erreur":"banni"}';
        exit;
    }
    $jeu = (string) ($_GET['game'] ?? '');
    if ($_GET['api'] === 'score' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifierCsrf()) {
            http_response_code(403);
            echo '{"erreur":"csrf"}';
            exit;
        }
        $jeu = (string) ($_POST['game'] ?? '');
        $score = (int) ($_POST['score'] ?? 0);
        $delai = microtime(true) - ($_SESSION['dernier_score'] ?? 0);
        if ($pseudo !== '' && isset($JEUX[$jeu]) && $score > 0 && $score <= $JEUX[$jeu][1] && $delai > 0.8) {
            $_SESSION['dernier_score'] = microtime(true);
            majScores(function (&$d) use ($jeu, $pseudo, $score) {
                $cle = mb_strtolower($pseudo);
                $actuel = $d['scores'][$jeu][$cle]['score'] ?? 0;
                if ($score > $actuel) {
                    $d['scores'][$jeu][$cle] = ['pseudo' => $pseudo, 'score' => $score, 'date' => time()];
                }
                $dernier = end($d['recent']);
                if (!$dernier || $dernier['pseudo'] !== $pseudo || $dernier['jeu'] !== $jeu || $dernier['score'] !== $score) {
                    $d['recent'][] = ['pseudo' => $pseudo, 'jeu' => $jeu, 'score' => $score, 'date' => time()];
                    $d['recent'] = array_slice($d['recent'], -20);
                }
            });
        }
    }
    echo json_encode(reponseScores(lireScores(), $jeu, $pseudo), JSON_UNESCAPED_UNICODE);
    exit;
}
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


    /* Classement à droite */
    .zone-jeu { display: flex; gap: 20px; align-items: flex-start; width: 100%; }
    .zone-jeu main { flex: 1; min-width: 0; }
    #app-container { max-width: 1200px; }
    #classement { width: 250px; flex-shrink: 0; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 4px 26px 4px 26px; padding: 16px; box-shadow: 0 16px 40px rgba(120, 60, 80, 0.16); }
    #classement h3 { font-family: 'Shippori Mincho', serif; font-size: 1.05rem; color: var(--accent); margin-bottom: 8px; }
    #classement h4 { font-family: 'Shippori Mincho', serif; font-size: 0.9rem; color: var(--text-soft); margin: 14px 0 6px; border-top: 1px solid var(--border-color); padding-top: 10px; }
    #classement ol, #classement ul { list-style: none; font-size: 0.85rem; }
    #classement li { display: flex; justify-content: space-between; gap: 8px; padding: 3px 6px; border-radius: 6px 1px 6px 1px; }
    #classement li.moi { background: var(--sakura-light); font-weight: 700; }
    #classement li span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #classement li span:last-child { color: var(--accent); font-weight: 700; }
    #classement .vide { font-size: 0.85rem; color: var(--text-soft); font-style: italic; }
    @media (max-width: 900px) { .zone-jeu { flex-direction: column; } #classement { width: 100%; } }

    /* Jeux 11 à 15 */
    .taupe-grid { display: grid; grid-template-columns: repeat(3, 90px); gap: 10px; margin-top: 8px; }
    .taupe-hole { width: 90px; height: 90px; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; background: radial-gradient(circle at 50% 60%, #e9c9d3 0, #f3d3dc 60%, #fbe9ee 100%); border: 2px solid var(--sakura); border-radius: 50% 50% 14px 14px; cursor: pointer; }
    .reflex-zone { width: 100%; max-width: 340px; min-height: 170px; display: flex; align-items: center; justify-content: center; text-align: center; padding: 14px; font-family: 'Shippori Mincho', serif; font-weight: 700; font-size: 1.1rem; color: var(--text-light); background: var(--sakura-light); border: 2px solid var(--sakura); border-radius: 6px 30px 6px 30px; cursor: pointer; }
    .reflex-zone.go { background: #b9d8b0; border-color: var(--matcha); }
    .reflex-zone.bad { background: #fdecea; border-color: #f0b9b3; color: #9c1f18; }
    .taquin-grid { display: grid; grid-template-columns: repeat(3, 90px); gap: 8px; padding: 8px; margin-top: 6px; background: #f3d3dc; border-radius: 4px 18px 4px 18px; }
    .taquin-tile { width: 90px; height: 90px; display: flex; align-items: center; justify-content: center; font-family: 'Shippori Mincho', serif; font-weight: 700; font-size: 1.8rem; color: #fff; background: linear-gradient(135deg, #e58fa7, #c9577a); border-radius: 12px 3px 12px 3px; cursor: pointer; }
    .taquin-tile.vide { background: transparent; cursor: default; }

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
      .taupe-grid { grid-template-columns: repeat(3, 80px); }
      .taupe-hole { width: 80px; height: 80px; }
      .taquin-grid { grid-template-columns: repeat(3, 80px); }
      .taquin-tile { width: 80px; height: 80px; }
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
<?php if (empty($_SESSION['pseudo_ok'])): ?>
  <!-- COMPTE / PSEUDO -->
  <div id="password-modal">
    <div class="pass-box" style="max-width:420px;">
      <h2>🌸 Compte joueur 🌸</h2>
      <p style="font-size:0.85rem; color:#6b4a5a; margin-bottom:8px;">Crée un compte pour retrouver tes scores. Ou joue en invité.</p>
      <?php if ($erreurPseudo): ?><div class="pass-error"><?= htmlspecialchars($erreurPseudo) ?></div><?php endif; ?>

      <form method="post" action="jeux.php" style="margin-top:10px;">
        <input type="hidden" name="action" value="login_compte">
        <?= champCsrf() ?>
        <input type="text" name="pseudo" class="pass-input" placeholder="Pseudo..." maxlength="16" autocomplete="username" required>
        <input type="password" name="mdp_compte" class="pass-input" placeholder="Mot de passe du compte..." autocomplete="current-password" required>
        <button type="submit" class="btn-action" style="width:100%;">Se connecter</button>
      </form>

      <form method="post" action="jeux.php" style="margin-top:12px;">
        <input type="hidden" name="action" value="register_compte">
        <?= champCsrf() ?>
        <input type="text" name="pseudo" class="pass-input" placeholder="Nouveau pseudo..." maxlength="16" autocomplete="username" required>
        <input type="password" name="mdp_compte" class="pass-input" placeholder="Choisir un mot de passe (min. 4)..." autocomplete="new-password" required>
        <button type="submit" class="btn-action" style="width:100%;">Créer un compte</button>
      </form>

      <hr style="border:none;border-top:1px solid var(--border-color);margin:16px 0;">

      <form method="post" action="jeux.php">
        <input type="hidden" name="action" value="choisir_pseudo">
        <?= champCsrf() ?>
        <input type="text" name="pseudo" class="pass-input" placeholder="Pseudo invité (sans compte)..." maxlength="16" autocomplete="off">
        <button type="submit" class="btn-action" style="width:100%; background:#fff; color:#b8456a; border:1px solid #e58fa7; box-shadow:none;">Jouer en invité nommé</button>
      </form>
      <form method="post" action="jeux.php" style="margin-top:8px;">
        <input type="hidden" name="action" value="sans_pseudo">
        <?= champCsrf() ?>
        <button type="submit" class="btn-action" style="width:100%; background:#fff; color:#6b4a5a; border:1px solid #e58fa7; box-shadow:none;">Jouer sans pseudo</button>
      </form>
    </div>
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
      <form class="quitter-form" method="post" action="jeux.php">
        <input type="hidden" name="action" value="changer_pseudo">
        <?= champCsrf() ?>
        <button type="submit" class="retour">👤 <?= ($_SESSION['pseudo'] ?? '') !== '' ? htmlspecialchars($_SESSION['pseudo']) . (!empty($_SESSION['compte_ok']) ? ' ✓' : '') : 'Sans pseudo' ?> · changer</button>
      </form>
    </header>

    <nav>
      <button class="nav-btn active" onclick="switchTab('snake')">🐍 Orochi</button>
      <button class="nav-btn" onclick="switchTab('invaders')">👹 Yōkai</button>
      <button class="nav-btn" onclick="switchTab('blockblast')">🧱 Bloc Blast</button>
      <button class="nav-btn" onclick="switchTab('pong')">🪞 Miroir</button>
      <button class="nav-btn" onclick="switchTab('memory')">📜 Sceaux</button>
      <button class="nav-btn" onclick="switchTab('breakout')">🧱 Briseur</button>
      <button class="nav-btn" onclick="switchTab('tsuru')">🕊️ Tsuru</button>
      <button class="nav-btn" onclick="switchTab('g2048')">🌸 2048</button>
      <button class="nav-btn" onclick="switchTab('flood')">🌊 Inondation</button>
      <button class="nav-btn" onclick="switchTab('simon')">🔔 Cloches</button>
      <button class="nav-btn" onclick="switchTab('catcher')">🌺 Pétales</button>
      <button class="nav-btn" onclick="switchTab('reflex')">⚔️ Kendo</button>
      <button class="nav-btn" onclick="switchTab('taupe')">👺 Taupes</button>
      <button class="nav-btn" onclick="switchTab('runner')">🥷 Ninja</button>
      <button class="nav-btn" onclick="switchTab('sort')">🌸 Tri Sakura</button>
      <button class="nav-btn" onclick="switchTab('relic')">🖼️ Relique</button>
    </nav>

    <div class="zone-jeu">
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

      <!-- 3. BLOC BLAST -->
      <div id="tab-blockblast" class="tab-content">
        <div class="game-header">
          <h2>Bloc Blast</h2>
          <div class="score-board">Score : <span id="blockblast-score">0</span></div>
        </div>
        <canvas id="canvas-blockblast" width="320" height="320"></canvas>
        <div id="blockblast-pieces" style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;justify-content:center;"></div>
        <div class="msg-jeu" id="blockblast-msg">Place les blocs pour remplir des lignes ou colonnes</div>
        <button class="btn-action" onclick="initBlockBlast()">Nouvelle partie</button>
      </div>

      <!-- 4. MIROIR DIVIN (PONG) -->
      <div id="tab-pong" class="tab-content">
        <div class="game-header">
          <h2>Reflet Yata no Kagami</h2>
          <div class="score-board">Vous : <span id="pong-player">0</span> | Esprit : <span id="pong-ai">0</span> | Vies : <span id="pong-lives">3</span></div>
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
          <div class="score-board">Paires : <span id="memory-score">0</span> / 8 | Temps : <span id="memory-timer">0</span>s | Score : <span id="memory-points">0</span></div>
        </div>
        <div class="memory-grid" id="memory-board"></div>
        <div class="msg-jeu" id="memory-msg">Plus tu es rapide, plus le score est élevé</div>
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

      <!-- 9. INONDATION (COLOR FLOOD) -->
      <div id="tab-flood" class="tab-content">
        <div class="game-header">
          <h2>Inondation Sacrée</h2>
          <div class="score-board">Coups : <span id="flood-moves">0</span> / <span id="flood-max">25</span> | Score : <span id="flood-score">0</span></div>
        </div>
        <div id="flood-board" style="display:grid;grid-template-columns:repeat(12,24px);gap:2px;margin-top:8px;"></div>
        <div id="flood-colors" style="display:flex;gap:8px;margin-top:12px;justify-content:center;flex-wrap:wrap;"></div>
        <div class="msg-jeu" id="flood-msg">Remplis tout le jardin d'une seule couleur</div>
        <button class="btn-action" onclick="initFlood()">Nouveau jardin</button>
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
        <div id="simon-history" style="display:flex;gap:6px;flex-wrap:wrap;justify-content:center;margin-top:10px;min-height:28px;"></div>
        <div class="msg-jeu" id="simon-msg"></div>
        <button class="btn-action" onclick="startSimon()">Commencer</button>
      </div>

      <!-- 11. PLUIE DE PÉTALES -->
      <div id="tab-catcher" class="tab-content">
        <div class="game-header">
          <h2>Pluie de Pétales</h2>
          <div class="score-board">Score : <span id="catcher-score">0</span> | Vies : <span id="catcher-lives">3</span></div>
        </div>
        <canvas id="canvas-catcher" width="360" height="360"></canvas>
        <div class="touch-controls"><div class="touch-row">
          <div class="touch-btn touch-btn-wide" onclick="triggerKey('catcher', 'ArrowLeft')">◄ Gauche</div>
          <div class="touch-btn touch-btn-wide" onclick="triggerKey('catcher', 'ArrowRight')">Droite ►</div>
        </div></div>
        <p class="msg-jeu">Pétales roses = +10 · Boules rouges = malus −2 pendant 5 s · Évite les cendres</p>
      </div>

      <!-- 12. KENDO (RÉFLEXES) -->
      <div id="tab-reflex" class="tab-content">
        <div class="game-header">
          <h2>Le Coup de Kendo</h2>
          <div class="score-board">Manche : <span id="reflex-round">0</span>/5 | Score : <span id="reflex-score">0</span></div>
        </div>
        <div class="reflex-zone" id="reflex-zone">Touche pour commencer</div>
        <p class="msg-jeu">Touche dès que la zone devient verte. Plus tu es rapide, plus tu marques.</p>
      </div>

      <!-- 13. ONI-TAUPES -->
      <div id="tab-taupe" class="tab-content">
        <div class="game-header">
          <h2>Les Oni Farceurs</h2>
          <div class="score-board">Touchés : <span id="taupe-score">0</span> | Temps : <span id="taupe-time">30</span>s</div>
        </div>
        <div class="taupe-grid" id="taupe-grid"></div>
        <div class="msg-jeu" id="taupe-msg"></div>
        <button class="btn-action" onclick="if (taupeStart) taupeStart()">Commencer (30 s)</button>
      </div>

      <!-- 14. NINJA (RUNNER) -->
      <div id="tab-runner" class="tab-content">
        <div class="game-header">
          <h2>La Course du Ninja</h2>
          <div class="score-board">Distance : <span id="runner-score">0</span> m</div>
        </div>
        <canvas id="canvas-runner" width="360" height="200"></canvas>
        <div class="touch-controls"><div class="touch-row">
          <div class="touch-btn touch-btn-wide" onclick="triggerKey('runner', ' ')">🥷 SAUTER</div>
        </div></div>
        <p class="msg-jeu">Espace, ▲ ou toucher l'écran pour sauter</p>
      </div>

      <!-- 15. TRI SAKURA -->
      <div id="tab-sort" class="tab-content">
        <div class="game-header">
          <h2>Tri Sakura</h2>
          <div class="score-board">Coups : <span id="sort-moves">0</span> | Score : <span id="sort-score">0</span></div>
        </div>
        <div id="sort-tubes" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:10px;"></div>
        <div class="msg-jeu" id="sort-msg">Trie les pétales : une couleur par tube</div>
        <button class="btn-action" onclick="initSort()">Mélanger</button>
      </div>

      <!-- 16. RELIQUE DIVIN -->
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
    <aside id="classement" aria-live="polite"></aside>
    </div>
  </div>

  <script>

    /* ==========================================
       0. SCORES ET CLASSEMENT (enregistrés côté serveur)
    ========================================== */
    const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
    const PSEUDO = <?= json_encode((string) ($_SESSION['pseudo'] ?? '')) ?>;
    const JEUX_NOMS = <?= json_encode(array_map(function ($j) { return $j[0]; }, $JEUX), JSON_UNESCAPED_UNICODE) ?>;
    let jeuCourant = 'snake';
    let scoreHook = null;

    function byId(id) { return document.getElementById(id); }
    function noeud(tag, texte, classe) {
      const n = document.createElement(tag);
      if (texte !== undefined) n.textContent = texte;
      if (classe) n.className = classe;
      return n;
    }
    function afficherClassement(jeu, d) {
      const box = byId('classement');
      box.innerHTML = '';
      box.appendChild(noeud('h3', '🏆 ' + (JEUX_NOMS[jeu] || 'Classement')));
      if (!PSEUDO) box.appendChild(noeud('p', 'Tu joues sans pseudo : tes scores ne sont pas enregistrés.', 'vide'));
      if (JEUX_NOMS[jeu]) {
        if (!d.top || !d.top.length) {
          box.appendChild(noeud('p', 'Aucun score pour l\'instant. Sois le premier !', 'vide'));
        } else {
          const ol = noeud('ol');
          d.top.forEach((t, i) => {
            const li = noeud('li', undefined, PSEUDO && t.pseudo.toLowerCase() === PSEUDO.toLowerCase() ? 'moi' : '');
            li.appendChild(noeud('span', (i + 1) + '. ' + t.pseudo));
            li.appendChild(noeud('span', String(t.score)));
            ol.appendChild(li);
          });
          box.appendChild(ol);
        }
        if (PSEUDO && d.moi) box.appendChild(noeud('p', 'Ton record : ' + d.moi, 'vide'));
      }
      box.appendChild(noeud('h4', '🕒 Dernières parties'));
      if (!d.recent || !d.recent.length) {
        box.appendChild(noeud('p', 'Personne n\'a encore joué.', 'vide'));
      } else {
        const ul = noeud('ul');
        d.recent.forEach(r => {
          const li = noeud('li');
          li.appendChild(noeud('span', r.pseudo + ' · ' + r.jeu));
          li.appendChild(noeud('span', String(r.score)));
          ul.appendChild(li);
        });
        box.appendChild(ul);
      }
    }
    function chargerClassement(jeu) {
      fetch('jeux.php?api=scores&game=' + encodeURIComponent(jeu), { cache: 'no-store' })
        .then(r => r.json()).then(d => { if (jeu === jeuCourant) afficherClassement(jeu, d); }).catch(() => {});
    }
    function submitScore(jeu, score) {
      score = Math.floor(score);
      if (!PSEUDO || !(score > 0)) return;
      const fd = new FormData();
      fd.append('game', jeu); fd.append('score', score); fd.append('csrf', CSRF);
      fetch('jeux.php?api=score', { method: 'POST', body: fd, keepalive: true })
        .then(r => r.json()).then(d => { if (jeu === jeuCourant) afficherClassement(jeu, d); }).catch(() => {});
    }
    function flushScore() {
      if (!scoreHook) return;
      const s = scoreHook();
      scoreHook = null;
      if (s) submitScore(s.game, s.score);
    }
    window.addEventListener('pagehide', flushScore);
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
    let taupeStart = null;

    function switchTab(tabId) {
      flushScore();
      jeuCourant = tabId;
      chargerClassement(tabId);
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
      if (tabId === 'blockblast') initBlockBlast();
      if (tabId === 'pong') initPong();
      if (tabId === 'memory') initMemory();
      if (tabId === 'breakout') initBreakout();
      if (tabId === 'tsuru') initTsuru();
      if (tabId === 'g2048') init2048();
      if (tabId === 'flood') initFlood();
      if (tabId === 'simon') initSimon();
      if (tabId === 'catcher') initCatcher();
      if (tabId === 'reflex') initReflex();
      if (tabId === 'taupe') initTaupe();
      if (tabId === 'runner') initRunner();
      if (tabId === 'sort') initSort();
    }

    let activeHandler = null;
    function triggerKey(game, key) {
      if (activeHandler) activeHandler({ key });
    }

    /* ==========================================
       JEU 1 : OROCHI (SNAKE)
    ========================================== */
    function initSnake() {
      flushScore();
      const canvas = document.getElementById('canvas-snake');
      const ctx = canvas.getContext('2d');
      const grid = 18;
      let snake = [{x: 10, y: 10}, {x: 9, y: 10}];
      let food = {x: 14, y: 10};
      let dx = 1, dy = 0;
      let score = 0;
      scoreHook = () => ({ game: 'snake', score });
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
       JEU 2 : YOKAI — vagues + tirs ennemis + vies
    ========================================== */
    function initInvaders() {
      flushScore();
      const canvas = document.getElementById('canvas-invaders');
      const ctx = canvas.getContext('2d');
      const W = canvas.width, H = canvas.height;
      let player = { x: W / 2 - 15, y: H - 28, w: 30, h: 12 };
      let bullets = [], eBullets = [], yokais = [];
      let score = 0, lives = 3, wave = 1, keys = {}, cooldown = 0, invuln = 0;
      scoreHook = () => ({ game: 'invaders', score });
      const hud = () => { document.getElementById('invaders-score').innerText = score + ' · Vies ' + lives + ' · Vague ' + wave; };
      hud();

      function spawnWave() {
        yokais = [];
        const rows = Math.min(2 + Math.floor(wave / 2), 5);
        const cols = Math.min(5 + Math.floor(wave / 3), 8);
        for (let r = 0; r < rows; r++) {
          for (let c = 0; c < cols; c++) {
            yokais.push({
              x: 30 + c * ((W - 60) / cols), y: 24 + r * 32,
              alive: true, hp: 1 + (wave > 3 && r === 0 ? 1 : 0),
              t: Math.random() * 6
            });
          }
        }
      }
      spawnWave();

      window.onkeydown = (e) => { keys[e.key] = true; if (e.key === ' ') e.preventDefault(); };
      window.onkeyup = (e) => { keys[e.key] = false; };
      activeHandler = (e) => {
        if (e.key === 'ArrowLeft') player.x = Math.max(0, player.x - 14);
        if (e.key === 'ArrowRight') player.x = Math.min(W - player.w, player.x + 14);
        if (e.key === ' ' && cooldown <= 0) {
          bullets.push({ x: player.x + player.w / 2 - 2, y: player.y, w: 4, h: 9 });
          cooldown = 12;
          playSound(750, 'square', 0.05);
        }
      };
      canvas.style.touchAction = 'none';
      canvas.onpointermove = (e) => {
        const r = canvas.getBoundingClientRect();
        player.x = Math.min(W - player.w, Math.max(0, (e.clientX - r.left) * (W / r.width) - player.w / 2));
      };

      function loop() {
        if ((keys['ArrowLeft'] || keys['q']) && player.x > 0) player.x -= 3.5;
        if ((keys['ArrowRight'] || keys['d']) && player.x < W - player.w) player.x += 3.5;
        if (keys[' '] && cooldown <= 0) {
          bullets.push({ x: player.x + player.w / 2 - 2, y: player.y, w: 4, h: 9 });
          cooldown = 12;
          playSound(750, 'square', 0.05);
        }
        if (cooldown > 0) cooldown--;
        if (invuln > 0) invuln--;

        bullets.forEach(b => b.y -= 5.2);
        bullets = bullets.filter(b => b.y > -10);
        eBullets.forEach(b => { b.y += b.vy; b.x += b.vx; });
        eBullets = eBullets.filter(b => b.y < H + 10 && b.x > -10 && b.x < W + 10);

        yokais.forEach(y => {
          if (!y.alive) return;
          y.t += 0.04 + wave * 0.004;
          y.x += Math.sin(y.t) * (0.6 + wave * 0.08);
          if (Math.random() < 0.003 + wave * 0.001) {
            eBullets.push({ x: y.x + 8, y: y.y + 12, vx: (Math.random() - 0.5) * 1.2, vy: 2 + wave * 0.15 });
          }
        });

        bullets.forEach(b => {
          yokais.forEach(y => {
            if (y.alive && b.x > y.x && b.x < y.x + 20 && b.y > y.y && b.y < y.y + 20) {
              y.hp--;
              b.y = -100;
              if (y.hp <= 0) {
                y.alive = false;
                score += 15 + wave * 5;
                hud();
                playSound(300, 'sine', 0.08);
              } else playSound(220, 'triangle', 0.04);
            }
          });
        });

        if (invuln <= 0) {
          eBullets.forEach(b => {
            if (b.x > player.x && b.x < player.x + player.w && b.y > player.y && b.y < player.y + player.h) {
              b.y = H + 50;
              lives--;
              invuln = 90;
              playSound(140, 'sawtooth', 0.25);
              hud();
              if (lives <= 0) { submitScore('invaders', score); lives = 3; score = 0; wave = 1; spawnWave(); eBullets = []; bullets = []; hud(); }
            }
          });
        }

        if (yokais.every(y => !y.alive)) {
          wave++;
          score += wave * 30;
          hud();
          spawnWave();
          playSound(800, 'sine', 0.15);
        }

        ctx.fillStyle = '#fff7f9';
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = invuln > 0 && (invuln % 10 < 5) ? '#e58fa7' : '#b8456a';
        ctx.fillRect(player.x, player.y, player.w, player.h);
        ctx.fillStyle = '#e58fa7';
        bullets.forEach(b => ctx.fillRect(b.x, b.y, b.w, b.h));
        ctx.fillStyle = '#3b2230';
        eBullets.forEach(b => { ctx.beginPath(); ctx.arc(b.x, b.y, 3, 0, Math.PI * 2); ctx.fill(); });
        yokais.forEach(y => {
          if (!y.alive) return;
          ctx.fillStyle = y.hp > 1 ? '#5f7f52' : '#7a9a6b';
          ctx.beginPath();
          ctx.arc(y.x + 10, y.y + 10, 9, 0, Math.PI * 2);
          ctx.fill();
          ctx.fillStyle = '#fff';
          ctx.beginPath(); ctx.arc(y.x + 7, y.y + 8, 2, 0, Math.PI * 2); ctx.arc(y.x + 13, y.y + 8, 2, 0, Math.PI * 2); ctx.fill();
        });
        currentLoop = requestAnimationFrame(loop);
      }
      currentLoop = requestAnimationFrame(loop);
    }

    /* ==========================================
       JEU 3 : BLOC BLAST (placement de polyominos)
    ========================================== */
    function initBlockBlast() {
      flushScore();
      const canvas = byId('canvas-blockblast');
      const ctx = canvas.getContext('2d');
      const N = 8, size = 40;
      const COLORS = ['#e58fa7', '#c9577a', '#7a9a6b', '#d9b26a', '#8f7fc0', '#b8456a'];
      const SHAPES = [
        [[1]], [[1,1]], [[1,1,1]], [[1,1,1,1]],
        [[1,1],[1,1]], [[1,0],[1,0],[1,1]], [[0,1],[0,1],[1,1]],
        [[1,1,1],[0,1,0]], [[1,1],[1,0]], [[1,1],[0,1]],
        [[1,1,1],[1,0,0]], [[1,1,1],[0,0,1]]
      ];
      let grid = Array.from({ length: N }, () => Array(N).fill(0));
      let pieces = [], score = 0, selected = -1, hover = null;
      scoreHook = () => ({ game: 'blockblast', score });
      byId('blockblast-score').innerText = 0;
      byId('blockblast-msg').innerText = 'Clique une pièce puis une case du plateau';
      window.onkeydown = null;
      activeHandler = null;

      function randomPiece() {
        const s = SHAPES[Math.floor(Math.random() * SHAPES.length)].map(r => r.slice());
        return { shape: s, color: COLORS[Math.floor(Math.random() * COLORS.length)] };
      }
      function refillPieces() {
        pieces = [randomPiece(), randomPiece(), randomPiece()];
        renderPieces();
      }
      function canPlace(shape, r0, c0) {
        for (let r = 0; r < shape.length; r++) {
          for (let c = 0; c < shape[r].length; c++) {
            if (!shape[r][c]) continue;
            const rr = r0 + r, cc = c0 + c;
            if (rr < 0 || rr >= N || cc < 0 || cc >= N || grid[rr][cc]) return false;
          }
        }
        return true;
      }
      function place(shape, r0, c0, color) {
        for (let r = 0; r < shape.length; r++) {
          for (let c = 0; c < shape[r].length; c++) {
            if (shape[r][c]) grid[r0 + r][c0 + c] = color;
          }
        }
      }
      function clearLines() {
        const rows = [], cols = [];
        for (let i = 0; i < N; i++) {
          if (grid[i].every(v => v)) rows.push(i);
          if (grid.every(row => row[i])) cols.push(i);
        }
        rows.forEach(r => { for (let c = 0; c < N; c++) grid[r][c] = 0; });
        cols.forEach(c => { for (let r = 0; r < N; r++) grid[r][c] = 0; });
        const n = rows.length + cols.length;
        if (n) {
          score += n * 50 + (n > 1 ? n * 30 : 0);
          byId('blockblast-score').innerText = score;
          playSound(600 + n * 80, 'sine', 0.12);
        }
        return n;
      }
      function anyMoveLeft() {
        return pieces.some(p => {
          if (!p) return false;
          for (let r = 0; r < N; r++) for (let c = 0; c < N; c++) if (canPlace(p.shape, r, c)) return true;
          return false;
        });
      }
      function draw() {
        ctx.fillStyle = '#f3d3dc';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        for (let r = 0; r < N; r++) {
          for (let c = 0; c < N; c++) {
            ctx.fillStyle = grid[r][c] || '#fff7f9';
            ctx.fillRect(c * size + 1, r * size + 1, size - 2, size - 2);
          }
        }
        if (selected >= 0 && hover && pieces[selected]) {
          const p = pieces[selected];
          const { r0, c0 } = hover;
          const ok = canPlace(p.shape, r0, c0);
          for (let r = 0; r < p.shape.length; r++) {
            for (let c = 0; c < p.shape[r].length; c++) {
              if (!p.shape[r][c]) continue;
              ctx.globalAlpha = 0.45;
              ctx.fillStyle = ok ? p.color : '#9c1f18';
              ctx.fillRect((c0 + c) * size + 1, (r0 + r) * size + 1, size - 2, size - 2);
              ctx.globalAlpha = 1;
            }
          }
        }
      }
      function renderPieces() {
        const box = byId('blockblast-pieces');
        box.innerHTML = '';
        pieces.forEach((p, i) => {
          const d = document.createElement('canvas');
          d.width = 72; d.height = 72;
          d.style.border = selected === i ? '2px solid #b8456a' : '1px solid #e58fa7';
          d.style.borderRadius = '8px';
          d.style.background = '#fff';
          d.style.cursor = p ? 'pointer' : 'default';
          d.style.opacity = p ? '1' : '0.3';
          if (p) {
            const cx = d.getContext('2d');
            const sh = p.shape, cs = 16;
            const ow = sh[0].length * cs, oh = sh.length * cs;
            const ox = (72 - ow) / 2, oy = (72 - oh) / 2;
            sh.forEach((row, r) => row.forEach((v, c) => {
              if (v) { cx.fillStyle = p.color; cx.fillRect(ox + c * cs, oy + r * cs, cs - 1, cs - 1); }
            }));
            d.onclick = () => { selected = i; renderPieces(); draw(); };
          }
          box.appendChild(d);
        });
      }
      canvas.onpointermove = (e) => {
        if (selected < 0 || !pieces[selected]) return;
        const rect = canvas.getBoundingClientRect();
        const c0 = Math.floor((e.clientX - rect.left) * (N / rect.width));
        const r0 = Math.floor((e.clientY - rect.top) * (N / rect.height));
        hover = { r0: Math.max(0, Math.min(N - 1, r0)), c0: Math.max(0, Math.min(N - 1, c0)) };
        draw();
      };
      canvas.onpointerdown = () => {
        if (selected < 0 || !pieces[selected] || !hover) return;
        const p = pieces[selected];
        if (!canPlace(p.shape, hover.r0, hover.c0)) { playSound(150, 'sawtooth', 0.1); return; }
        place(p.shape, hover.r0, hover.c0, p.color);
        pieces[selected] = null;
        selected = -1;
        clearLines();
        if (pieces.every(x => !x)) refillPieces();
        else renderPieces();
        draw();
        if (!anyMoveLeft()) {
          byId('blockblast-msg').innerText = 'Plus de place ! Score final : ' + score;
          submitScore('blockblast', score);
          playSound(150, 'sawtooth', 0.3);
        }
      };
      refillPieces();
      draw();
    }

    /* ==========================================
       JEU 4 : MIROIR DIVIN (PONG)
    ========================================== */
    function initPong() {
      flushScore();
      const canvas = document.getElementById('canvas-pong');
      const ctx = canvas.getContext('2d');
      let pY = 110, aiY = 110;
      let baseSpeed = 2.6;
      let ball = { x: 180, y: 150, dx: baseSpeed, dy: baseSpeed };
      let pScore = 0, aiScore = 0, lives = 3, rallies = 0;
      scoreHook = () => ({ game: 'pong', score: pScore });
      let keys = {};
      const hud = () => {
        document.getElementById('pong-player').innerText = pScore;
        document.getElementById('pong-ai').innerText = aiScore;
        const el = document.getElementById('pong-lives');
        if (el) el.innerText = lives;
      };
      hud();

      window.onkeydown = (e) => keys[e.key] = true;
      window.onkeyup = (e) => keys[e.key] = false;

      activeHandler = (e) => {
        if (e.key === 'ArrowUp') pY = Math.max(0, pY - 25);
        if (e.key === 'ArrowDown') pY = Math.min(canvas.height - 60, pY + 25);
      };

      function resetBall(dir) {
        const sp = Math.min(6.5, baseSpeed + rallies * 0.08 + pScore * 0.12);
        ball = { x: 180, y: 150, dx: dir * sp, dy: (Math.random() < 0.5 ? -1 : 1) * sp * 0.85 };
      }

      function loop() {
        if (keys['ArrowUp'] && pY > 0) pY -= 3.5;
        if (keys['ArrowDown'] && pY < canvas.height - 60) pY += 3.5;

        const aiSp = Math.min(4.2, 2.0 + pScore * 0.08 + rallies * 0.02);
        if (aiY + 30 < ball.y) aiY += aiSp;
        else if (aiY + 30 > ball.y) aiY -= aiSp;

        ball.x += ball.dx;
        ball.y += ball.dy;

        if (ball.y <= 0 || ball.y >= canvas.height) ball.dy *= -1;

        if (ball.x <= 18 && ball.y >= pY && ball.y <= pY + 60) {
          ball.dx = Math.abs(ball.dx) * 1.02;
          ball.dy += (ball.y - (pY + 30)) * 0.04;
          rallies++;
          playSound(400, 'sine', 0.05);
        }

        if (ball.x >= canvas.width - 18 && ball.y >= aiY && ball.y <= aiY + 60) {
          ball.dx = -Math.abs(ball.dx) * 1.02;
          rallies++;
          playSound(350, 'sine', 0.05);
        }

        if (ball.x < 0) {
          lives--;
          playSound(160, 'sawtooth', 0.22);
          hud();
          if (lives <= 0) {
            submitScore('pong', pScore);
            pScore = 0; aiScore = 0; lives = 3; rallies = 0;
            hud();
          }
          resetBall(1);
        }
        if (ball.x > canvas.width) {
          pScore++;
          rallies = 0;
          hud();
          playSound(520, 'sine', 0.1);
          resetBall(-1);
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
      let flipped = [], matchedCount = 0, moves = 0, started = false, done = false, t0 = 0, timerId = null;
      document.getElementById('memory-score').innerText = 0;
      document.getElementById('memory-timer').innerText = 0;
      document.getElementById('memory-points').innerText = 0;
      const msg = document.getElementById('memory-msg');
      if (msg) msg.innerText = 'Plus tu es rapide, plus le score est élevé';

      function tick() {
        if (done || !started) return;
        const sec = Math.floor((Date.now() - t0) / 1000);
        document.getElementById('memory-timer').innerText = sec;
        timerId = setTimeout(tick, 250);
        simonTimers.push(timerId);
      }

      cardsData.forEach((sym) => {
        const card = document.createElement('div');
        card.className = 'memory-card';
        card.dataset.symbol = sym;
        card.innerText = '❓';
        card.onclick = () => {
          if (done || flipped.length >= 2 || card.classList.contains('flipped')) return;
          if (!started) { started = true; t0 = Date.now(); tick(); }
          card.classList.add('flipped');
          card.innerText = sym;
          flipped.push(card);
          playSound(500, 'sine', 0.05);
          if (flipped.length === 2) {
            moves++;
            if (flipped[0].dataset.symbol === flipped[1].dataset.symbol) {
              matchedCount++;
              document.getElementById('memory-score').innerText = matchedCount;
              flipped = [];
              playSound(800, 'sine', 0.1);
              if (matchedCount === 8) {
                done = true;
                const sec = Math.max(1, Math.floor((Date.now() - t0) / 1000));
                const points = Math.max(50, Math.round(5000 / sec - moves * 15));
                document.getElementById('memory-points').innerText = points;
                document.getElementById('memory-timer').innerText = sec;
                if (msg) msg.innerText = 'Terminé en ' + sec + ' s · ' + moves + ' coups · Score ' + points;
                submitScore('memory', points);
              }
            } else {
              setTimeout(() => {
                flipped.forEach(c => { c.classList.remove('flipped'); c.innerText = '❓'; });
                flipped = [];
              }, 700);
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
      flushScore();
      const canvas = document.getElementById('canvas-breakout');
      const ctx = canvas.getContext('2d');
      const W = canvas.width, H = canvas.height;
      const paddle = { x: W / 2 - 32, y: H - 22, w: 64, h: 8, baseW: 64 };
      const cols = 6, rows = 4, bw = 50, bh = 16, gap = 6;
      const offX = (W - (cols * bw + (cols - 1) * gap)) / 2, offY = 40;
      const rowColors = ['#b8456a', '#d9708f', '#e58fa7', '#7a9a6b'];
      let balls = [], bricks, powerups = [], score = 0, lives = 3, level = 1, keys = {}, speedMul = 1, effectT = 0, effectType = '';
      scoreHook = () => ({ game: 'breakout', score });

      function makeBall(x, y, dx, dy) {
        return { x, y, dx, dy, r: 5 };
      }
      function resetBall() {
        const sp = (3 + (level - 1) * 0.35) * speedMul;
        balls = [makeBall(W / 2, paddle.y - 10, (Math.random() < 0.5 ? -1 : 1) * 2.4 * speedMul, -sp)];
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
        document.getElementById('breakout-score').innerText = score + (effectType ? ' · ' + effectType : '');
        document.getElementById('breakout-lives').innerText = lives;
      }
      function spawnPower(x, y) {
        const kinds = ['wide', 'multi', 'slow', 'narrow', 'fast', 'shrink'];
        const kind = kinds[Math.floor(Math.random() * kinds.length)];
        powerups.push({ x, y, kind, vy: 1.6 });
      }
      function applyPower(kind) {
        if (kind === 'wide') { paddle.w = Math.min(120, paddle.baseW + 40); effectType = 'Bonus large'; effectT = 400; playSound(700, 'sine', 0.1); }
        else if (kind === 'narrow' || kind === 'shrink') { paddle.w = Math.max(36, paddle.baseW - 24); effectType = 'Malus étroit'; effectT = 350; playSound(180, 'sawtooth', 0.12); }
        else if (kind === 'multi') {
          const b = balls[0];
          if (b) { balls.push(makeBall(b.x, b.y, -b.dx, b.dy)); balls.push(makeBall(b.x, b.y, b.dx * 0.7, b.dy)); }
          effectType = 'Bonus multi'; effectT = 200; playSound(750, 'sine', 0.1);
        } else if (kind === 'slow') { speedMul = 0.7; balls.forEach(b => { b.dx *= 0.7; b.dy *= 0.7; }); effectType = 'Bonus lent'; effectT = 350; playSound(500, 'sine', 0.1); }
        else if (kind === 'fast') { speedMul = 1.35; balls.forEach(b => { b.dx *= 1.25; b.dy *= 1.25; }); effectType = 'Malus rapide'; effectT = 350; playSound(180, 'sawtooth', 0.12); }
        updateHud();
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

        if (effectT > 0) {
          effectT--;
          if (effectT === 0) { paddle.w = paddle.baseW; speedMul = 1; effectType = ''; updateHud(); }
        }
        // Accélération progressive légère
        balls.forEach(b => {
          const lim = 7 + level;
          const mag = Math.hypot(b.dx, b.dy);
          if (mag < lim) { b.dx *= 1.0004; b.dy *= 1.0004; }
        });

        balls.forEach(ball => {
          ball.x += ball.dx; ball.y += ball.dy;
          if (ball.x < ball.r || ball.x > W - ball.r) { ball.dx *= -1; ball.x = Math.max(ball.r, Math.min(W - ball.r, ball.x)); }
          if (ball.y < ball.r) ball.dy = Math.abs(ball.dy);
          if (ball.dy > 0 && ball.y + ball.r >= paddle.y && ball.y + ball.r <= paddle.y + paddle.h + 6 &&
              ball.x >= paddle.x - 2 && ball.x <= paddle.x + paddle.w + 2) {
            const off = (ball.x - (paddle.x + paddle.w / 2)) / (paddle.w / 2);
            ball.dx = off * (3.6 * speedMul);
            ball.dy = -(3 + (level - 1) * 0.4) * speedMul;
            playSound(400, 'sine', 0.05);
          }
          for (const b of bricks) {
            if (b.alive && ball.x + ball.r > b.x && ball.x - ball.r < b.x + bw && ball.y + ball.r > b.y && ball.y - ball.r < b.y + bh) {
              b.alive = false; ball.dy *= -1; score += 10; updateHud(); playSound(560, 'sine', 0.06);
              if (Math.random() < 0.22) spawnPower(b.x + bw / 2, b.y + bh);
              break;
            }
          }
        });
        balls = balls.filter(b => b.y < H + 20);
        powerups.forEach(p => p.y += p.vy);
        powerups = powerups.filter(p => {
          if (p.y + 8 >= paddle.y && p.x >= paddle.x && p.x <= paddle.x + paddle.w) { applyPower(p.kind); return false; }
          return p.y < H + 10;
        });

        if (balls.length === 0) {
          lives--; playSound(150, 'sawtooth', 0.2);
          if (lives <= 0) { submitScore('breakout', score); score = 0; lives = 3; level = 1; speedMul = 1; paddle.w = paddle.baseW; buildBricks(); }
          updateHud(); resetBall();
        }
        if (bricks.every(b => !b.alive)) { level++; buildBricks(); resetBall(); playSound(800, 'sine', 0.15); }

        ctx.fillStyle = '#fff7f9'; ctx.fillRect(0, 0, W, H);
        bricks.forEach(b => { if (b.alive) { ctx.fillStyle = b.color; ctx.fillRect(b.x, b.y, bw, bh); } });
        powerups.forEach(p => {
          const good = ['wide', 'multi', 'slow'].includes(p.kind);
          ctx.fillStyle = good ? '#7a9a6b' : '#9c1f18';
          ctx.beginPath(); ctx.arc(p.x, p.y, 7, 0, Math.PI * 2); ctx.fill();
          ctx.fillStyle = '#fff'; ctx.font = '10px serif'; ctx.textAlign = 'center';
          ctx.fillText(good ? '+' : '−', p.x, p.y + 3);
        });
        ctx.fillStyle = '#b8456a'; ctx.fillRect(paddle.x, paddle.y, paddle.w, paddle.h);
        balls.forEach(ball => {
          ctx.beginPath(); ctx.arc(ball.x, ball.y, ball.r, 0, Math.PI * 2);
          ctx.fillStyle = '#3b2230'; ctx.fill();
        });
        currentLoop = requestAnimationFrame(loop);
      }
      currentLoop = requestAnimationFrame(loop);
    }

    /* ==========================================
       JEU 7 : LE VOL DU TSURU (FLAPPY)
    ========================================== */
    let tsuruBest = 0;
    function initTsuru() {
      flushScore();
      const canvas = document.getElementById('canvas-tsuru');
      const ctx = canvas.getContext('2d');
      const W = canvas.width, H = canvas.height;
      const gap = 112, pw = 48, ground = 14;
      let bird, pipes, frame, score, state, deadAt, shield, shields;

      function reset() {
        bird = { x: 80, y: H / 2, vy: 0 };
        pipes = []; shields = []; frame = 0; score = 0; state = 'ready'; deadAt = 0; shield = 0;
        document.getElementById('tsuru-score').innerText = 0;
      }
      reset();
      scoreHook = () => ({ game: 'tsuru', score });

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
        if (shield > 0) {
          shield--;
          bird.vy = -4;
          playSound(700, 'triangle', 0.12);
          document.getElementById('tsuru-score').innerText = score + (shield ? ' 🛡️' : '');
          return;
        }
        state = 'dead';
        deadAt = Date.now();
        if (score > tsuruBest) tsuruBest = score;
        submitScore('tsuru', score);
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
          if (frame % 220 === 110 && Math.random() < 0.55) {
            shields.push({ x: W, y: 80 + Math.random() * (H - 160), r: 10 });
          }
          pipes.forEach(p => p.x -= 2.3);
          pipes = pipes.filter(p => p.x > -pw - 6);
          shields.forEach(s => s.x -= 2.3);
          shields = shields.filter(s => {
            if (Math.hypot(s.x - bird.x, s.y - bird.y) < 18) {
              shield = Math.min(2, shield + 1);
              document.getElementById('tsuru-score').innerText = score + ' 🛡️';
              playSound(880, 'sine', 0.1);
              return false;
            }
            return s.x > -20;
          });
          if (bird.y + 11 > H - ground || bird.y - 11 < 0) die();
          pipes.forEach(p => {
            if (bird.x + 11 > p.x && bird.x - 11 < p.x + pw && (bird.y - 11 < p.top || bird.y + 11 > p.top + gap)) die();
            if (!p.passed && p.x + pw < bird.x) {
              p.passed = true;
              score++;
              document.getElementById('tsuru-score').innerText = score + (shield ? ' 🛡️' : '');
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

        shields.forEach(s => {
          ctx.strokeStyle = '#d9b26a'; ctx.lineWidth = 2;
          ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2); ctx.stroke();
          ctx.fillStyle = 'rgba(217,178,106,0.35)'; ctx.fill();
        });
        ctx.save();
        ctx.translate(bird.x, bird.y);
        ctx.rotate(Math.max(-0.5, Math.min(0.9, bird.vy * 0.07)));
        if (shield > 0) {
          ctx.strokeStyle = '#d9b26a'; ctx.lineWidth = 2;
          ctx.beginPath(); ctx.arc(0, 0, 18, 0, Math.PI * 2); ctx.stroke();
        }
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
      flushScore();
      const boardEl = document.getElementById('board-2048');
      const msgEl = document.getElementById('msg-2048');
      const palette = { 2: '#fdeef2', 4: '#f9d3dd', 8: '#f3a4ba', 16: '#e58fa7', 32: '#d9708f', 64: '#c9577a', 128: '#b8456a', 256: '#8f3a58', 512: '#7a9a6b', 1024: '#5f7f52', 2048: '#3b2230', 4096: '#1a1020', 8192: '#0d0a12' };
      let grid = Array.from({ length: 4 }, () => Array(4).fill(0));
      let score = 0, won = false;
      scoreHook = () => ({ game: 'g2048', score });
      msgEl.innerText = 'Mode infini : continue après 2048 pour viser plus haut';

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
        if (!won && grid.some(row => row.includes(2048))) { won = true; msgEl.innerText = '2048 atteint ! Continue pour 4096+ 🌸'; }
        if (!canMove()) {
          msgEl.innerText = 'Fin de partie · Score ' + score;
          submitScore('g2048', score);
        }
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
       JEU 9 : INONDATION (COLOR FLOOD)
    ========================================== */
    function initFlood() {
      const N = 12, MAX = 25;
      const palette = ['#e58fa7', '#7a9a6b', '#d9b26a', '#8f7fc0', '#b8456a', '#5f9ea0'];
      const board = byId('flood-board');
      const colorsEl = byId('flood-colors');
      const msg = byId('flood-msg');
      let grid = [], moves = 0, over = false;
      window.onkeydown = null; activeHandler = null;
      board.innerHTML = '';
      colorsEl.innerHTML = '';
      moves = 0; over = false;
      byId('flood-moves').innerText = 0;
      byId('flood-max').innerText = MAX;
      byId('flood-score').innerText = 0;
      msg.innerText = 'Choisis une couleur pour inonder depuis le coin haut-gauche';

      grid = Array.from({ length: N }, () => Array.from({ length: N }, () => Math.floor(Math.random() * palette.length)));
      function render() {
        board.innerHTML = '';
        grid.forEach((row, r) => row.forEach((v, c) => {
          const d = document.createElement('div');
          d.style.width = '24px'; d.style.height = '24px';
          d.style.background = palette[v];
          d.style.borderRadius = '3px';
          board.appendChild(d);
        }));
      }
      function flood(target) {
        if (over) return;
        const origin = grid[0][0];
        if (target === origin) return;
        const stack = [[0, 0]];
        const seen = new Set(['0,0']);
        while (stack.length) {
          const [r, c] = stack.pop();
          grid[r][c] = target;
          [[0,1],[1,0],[0,-1],[-1,0]].forEach(([dr, dc]) => {
            const nr = r + dr, nc = c + dc;
            const k = nr + ',' + nc;
            if (nr >= 0 && nr < N && nc >= 0 && nc < N && !seen.has(k) && grid[nr][nc] === origin) {
              seen.add(k); stack.push([nr, nc]);
            }
          });
        }
        moves++;
        byId('flood-moves').innerText = moves;
        render();
        playSound(440 + target * 40, 'sine', 0.06);
        const uni = grid.every(row => row.every(v => v === target));
        if (uni) {
          over = true;
          const sc = Math.max(50, (MAX - moves + 1) * 80);
          byId('flood-score').innerText = sc;
          msg.innerText = 'Jardin uni en ' + moves + ' coups ! Score ' + sc;
          submitScore('flood', sc);
          playSound(800, 'sine', 0.2);
        } else if (moves >= MAX) {
          over = true;
          msg.innerText = 'Plus de coups… Recommence.';
          playSound(150, 'sawtooth', 0.25);
        }
      }
      palette.forEach((col, i) => {
        const b = document.createElement('button');
        b.className = 'btn-action';
        b.style.background = col; b.style.minWidth = '44px'; b.style.padding = '10px';
        b.onclick = () => flood(i);
        colorsEl.appendChild(b);
      });
      render();
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
    function simonRenderHistory() {
      const h = document.getElementById('simon-history');
      if (!h) return;
      const cols = ['#e58fa7', '#7a9a6b', '#d9b26a', '#8f7fc0'];
      h.innerHTML = '';
      simonSeq.forEach((p, idx) => {
        const d = document.createElement('div');
        d.style.cssText = 'width:22px;height:22px;border-radius:6px;background:' + cols[p] + ';opacity:' + (idx < simonPos ? '1' : '0.45');
        d.title = 'Note ' + (idx + 1);
        h.appendChild(d);
      });
    }
    function initSimon() {
      simonTimers.forEach(clearTimeout);
      simonTimers = [];
      simonSeq = []; simonPos = 0; simonLocked = true;
      document.getElementById('simon-score').innerText = 0;
      document.getElementById('simon-best').innerText = simonBest;
      simonMsg('Appuie sur « Commencer »');
      simonRenderHistory();
      document.querySelectorAll('.simon-pad').forEach((p, i) => { p.onclick = () => simonPress(i); p.classList.remove('lit'); });
      window.onkeydown = (e) => { const k = parseInt(e.key, 10); if (k >= 1 && k <= 4) simonPress(k - 1); };
      activeHandler = null;
      chargerClassement('simon');
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
      simonRenderHistory();
      simonSeq.forEach((p, k) => simonLater(() => litPad(p, 380), 600 + k * 650));
      simonLater(() => { simonLocked = false; simonMsg('À toi !'); simonRenderHistory(); }, 600 + simonSeq.length * 650);
    }
    function simonPress(i) {
      if (simonLocked) return;
      if (i !== simonSeq[simonPos]) {
        simonLocked = true;
        playSound(150, 'sawtooth', 0.3);
        const done = Math.max(0, simonSeq.length - 1);
        if (done > simonBest) simonBest = done;
        submitScore('simon', Math.max(1, done));
        document.getElementById('simon-best').innerText = simonBest;
        simonMsg('Raté ! ' + done + ' manche(s) réussie(s).');
        chargerClassement('simon');
        return;
      }
      litPad(i, 220);
      simonPos++;
      simonRenderHistory();
      if (simonPos === simonSeq.length) {
        simonLocked = true;
        simonMsg('Bien joué !');
        simonLater(nextSimonRound, 800);
      }
    }


    /* ==========================================
       JEU 11 : PLUIE DE PÉTALES
    ========================================== */
    function initCatcher() {
      flushScore();
      const canvas = byId('canvas-catcher');
      const ctx = canvas.getContext('2d');
      const W = canvas.width, H = canvas.height;
      const bask = { x: W / 2 - 32, y: H - 24, w: 64, h: 12 };
      let items = [], score = 0, lives = 3, frame = 0, state = 'play', keys = {}, freezeUntil = 0;
      scoreHook = () => ({ game: 'catcher', score });
      const hud = () => {
        const fr = Date.now() < freezeUntil;
        byId('catcher-score').innerText = score + (fr ? ' ❄️−2' : '');
        byId('catcher-lives').innerText = lives;
      };
      function reset() { items = []; score = 0; lives = 3; frame = 0; state = 'play'; freezeUntil = 0; hud(); }
      reset();

      window.onkeydown = (e) => { keys[e.key] = true; if (e.key.indexOf('Arrow') === 0) e.preventDefault(); if (state === 'over') reset(); };
      window.onkeyup = (e) => { keys[e.key] = false; };
      activeHandler = (e) => {
        if (state === 'over') { reset(); return; }
        if (e.key === 'ArrowLeft') bask.x = Math.max(0, bask.x - 32);
        if (e.key === 'ArrowRight') bask.x = Math.min(W - bask.w, bask.x + 32);
      };
      canvas.style.touchAction = 'none';
      canvas.onpointermove = (e) => {
        const r = canvas.getBoundingClientRect();
        bask.x = Math.min(W - bask.w, Math.max(0, (e.clientX - r.left) * (W / r.width) - bask.w / 2));
      };
      canvas.onpointerdown = () => { if (state === 'over') reset(); };

      function loop() {
        if (state === 'play') {
          if ((keys['ArrowLeft'] || keys['q']) && bask.x > 0) bask.x -= 5;
          if ((keys['ArrowRight'] || keys['d']) && bask.x < W - bask.w) bask.x += 5;
          frame++;
          // Toujours une chance de boule rouge + cendres + pétales
          if (frame % Math.max(14, 40 - Math.floor(score / 60) * 4) === 0) {
            const roll = Math.random();
            let kind = 'petal';
            if (roll < 0.22) kind = 'ash';
            else if (roll < 0.38) kind = 'red'; // boule rouge (malus)
            items.push({ x: 14 + Math.random() * (W - 28), y: -10, kind, v: 1.8 + Math.min(score / 250, 2.4) });
          }
          items.forEach(it => it.y += it.v);
          items = items.filter(it => {
            if (it.y + 8 >= bask.y && it.y <= bask.y + bask.h && it.x >= bask.x - 6 && it.x <= bask.x + bask.w + 6) {
              if (it.kind === 'ash') { lives--; playSound(150, 'sawtooth', 0.2); }
              else if (it.kind === 'red') {
                freezeUntil = Date.now() + 5000;
                score = Math.max(0, score - 2);
                playSound(200, 'triangle', 0.15);
              } else {
                if (Date.now() < freezeUntil) { /* gel : pas de gain */ }
                else { score += 10; playSound(650, 'sine', 0.05); }
              }
              hud();
              return false;
            }
            return it.y < H + 10;
          });
          if (lives <= 0) { state = 'over'; submitScore('catcher', score); }
          hud();
        }
        ctx.fillStyle = '#fff7f9';
        ctx.fillRect(0, 0, W, H);
        items.forEach(it => {
          ctx.beginPath();
          if (it.kind === 'ash') {
            ctx.fillStyle = '#3b2230';
            ctx.moveTo(it.x, it.y - 9); ctx.lineTo(it.x + 8, it.y); ctx.lineTo(it.x, it.y + 9); ctx.lineTo(it.x - 8, it.y); ctx.closePath();
          } else if (it.kind === 'red') {
            ctx.fillStyle = '#c0392b';
            ctx.arc(it.x, it.y, 8, 0, Math.PI * 2);
          } else {
            ctx.fillStyle = '#f3a4ba';
            ctx.ellipse(it.x, it.y, 6, 9, 0.6, 0, Math.PI * 2);
          }
          ctx.fill();
        });
        ctx.fillStyle = '#b8456a';
        ctx.beginPath();
        ctx.moveTo(bask.x, bask.y);
        ctx.lineTo(bask.x + bask.w, bask.y);
        ctx.lineTo(bask.x + bask.w - 10, bask.y + bask.h);
        ctx.lineTo(bask.x + 10, bask.y + bask.h);
        ctx.closePath();
        ctx.fill();
        if (state === 'over') {
          ctx.fillStyle = '#3b2230';
          ctx.font = '700 18px "Shippori Mincho", serif';
          ctx.textAlign = 'center';
          ctx.fillText('Perdu · Touche pour rejouer', W / 2, H / 2);
        }
        currentLoop = requestAnimationFrame(loop);
      }
      currentLoop = requestAnimationFrame(loop);
    }

    /* ==========================================
       JEU 12 : LE COUP DE KENDO (RÉFLEXES)
    ========================================== */
    function initReflex() {
      const z = byId('reflex-zone');
      let round = 0, times = [], state = 'idle', t0 = 0, timer = null;
      z.className = 'reflex-zone';
      z.textContent = 'Touche pour commencer';
      byId('reflex-round').innerText = 0;
      window.onkeydown = null;
      activeHandler = null;

      function arm() {
        state = 'wait';
        z.className = 'reflex-zone';
        z.textContent = 'Attends le signal…';
        timer = setTimeout(() => { state = 'go'; z.className = 'reflex-zone go'; z.textContent = 'COUPE !'; t0 = performance.now(); }, 1200 + Math.random() * 2600);
        simonTimers.push(timer);
      }
      z.onpointerdown = (e) => {
        e.preventDefault();
        if (state === 'idle' || state === 'done') { round = 0; times = []; byId('reflex-round').innerText = 0; arm(); return; }
        if (state === 'wait') { clearTimeout(timer); state = 'early'; z.className = 'reflex-zone bad'; z.textContent = 'Trop tôt ! Touche pour reprendre'; playSound(150, 'sawtooth', 0.2); return; }
        if (state === 'early' || state === 'between') { arm(); return; }
        if (state === 'go') {
          const ms = Math.round(performance.now() - t0);
          times.push(ms); round++;
          byId('reflex-round').innerText = round;
          playSound(600, 'sine', 0.06);
          if (round >= 5) {
            const moy = Math.round(times.reduce((a, b) => a + b, 0) / times.length);
            const score = Math.max(0, Math.round(1000 - moy * 2));
            byId('reflex-score').innerText = score;
            state = 'done';
            z.className = 'reflex-zone';
            z.textContent = 'Moyenne ' + moy + ' ms · Score ' + score + ' — touche pour rejouer';
            submitScore('reflex', score);
          } else {
            state = 'between';
            z.className = 'reflex-zone';
            z.textContent = ms + ' ms — touche pour la manche suivante';
          }
        }
      };
    }

    /* ==========================================
       JEU 13 : LES ONI FARCEURS (TAUPES)
    ========================================== */
    function initTaupe() {
      const grid = byId('taupe-grid');
      const holes = [];
      let hits = 0, left = 30, running = false, current = -1, hideT = null;
      grid.innerHTML = '';
      byId('taupe-score').innerText = 0;
      byId('taupe-time').innerText = 30;
      byId('taupe-msg').innerText = 'Touche les oni dès qu\'ils apparaissent !';
      window.onkeydown = null;
      activeHandler = null;

      function show() {
        if (!running) return;
        let n;
        do { n = Math.floor(Math.random() * 9); } while (n === current);
        current = n;
        holes[n].textContent = '👺';
        hideT = setTimeout(() => {
          if (current >= 0) { holes[current].textContent = ''; current = -1; }
          simonLater(show, 150);
        }, Math.max(380, 850 - hits * 12));
        simonTimers.push(hideT);
      }
      function tick() {
        left--;
        byId('taupe-time').innerText = left;
        if (left <= 0) {
          running = false;
          holes.forEach(x => x.textContent = '');
          byId('taupe-msg').innerText = 'Temps écoulé : ' + hits + ' oni touchés !';
          submitScore('taupe', hits);
        } else simonLater(tick, 1000);
      }
      for (let i = 0; i < 9; i++) {
        const d = document.createElement('div');
        d.className = 'taupe-hole';
        d.onpointerdown = (e) => {
          e.preventDefault();
          if (!running || i !== current) return;
          hits++;
          byId('taupe-score').innerText = hits;
          d.textContent = '✨';
          playSound(500 + hits * 5, 'sine', 0.05);
          current = -1;
          clearTimeout(hideT);
          simonLater(() => { d.textContent = ''; }, 180);
          simonLater(show, 250);
        };
        holes.push(d);
        grid.appendChild(d);
      }
      taupeStart = () => {
        if (running) return;
        simonTimers.forEach(clearTimeout); simonTimers = [];
        holes.forEach(x => x.textContent = '');
        hits = 0; left = 30; running = true; current = -1;
        byId('taupe-score').innerText = 0;
        byId('taupe-time').innerText = 30;
        byId('taupe-msg').innerText = '';
        show();
        simonLater(tick, 1000);
      };
    }

    /* ==========================================
       JEU 14 : LA COURSE DU NINJA (RUNNER)
    ========================================== */
    function initRunner() {
      flushScore();
      const canvas = byId('canvas-runner');
      const ctx = canvas.getContext('2d');
      const W = canvas.width, H = canvas.height, ground = H - 30;
      let p, obs, dist, speed, state, deadAt, nextIn;
      scoreHook = () => ({ game: 'runner', score: Math.floor(dist / 10) });
      function reset() {
        p = { x: 50, y: ground - 30, w: 24, h: 30, vy: 0 };
        obs = []; dist = 0; speed = 4; state = 'ready'; deadAt = 0; nextIn = 160;
        byId('runner-score').innerText = 0;
      }
      reset();
      function jump() {
        if (state === 'dead') { if (Date.now() - deadAt > 400) reset(); return; }
        if (state === 'ready') state = 'play';
        if (p.y + p.h >= ground - 0.5) { p.vy = -10.2; playSound(520, 'sine', 0.05); }
      }
      window.onkeydown = (e) => { if ([' ', 'ArrowUp', 'z'].includes(e.key)) { e.preventDefault(); if (!e.repeat) jump(); } };
      activeHandler = (e) => { if (e.key === ' ' || e.key === 'ArrowUp') jump(); };
      canvas.style.touchAction = 'none';
      canvas.onpointerdown = (e) => { e.preventDefault(); jump(); };

      function loop() {
        if (state === 'play') {
          p.vy += 0.6; p.y += p.vy;
          if (p.y + p.h > ground) { p.y = ground - p.h; p.vy = 0; }
          dist += speed;
          speed = Math.min(9, 4 + dist / 2500);
          nextIn -= speed;
          if (nextIn <= 0) { obs.push({ x: W, w: 14 + Math.random() * 14, h: 24 + Math.random() * 26 }); nextIn = 190 + Math.random() * 180 + speed * 12; }
          obs.forEach(o => o.x -= speed);
          obs = obs.filter(o => o.x > -40);
          byId('runner-score').innerText = Math.floor(dist / 10);
          if (obs.some(o => p.x + p.w - 4 > o.x && p.x + 4 < o.x + o.w && p.y + p.h - 2 > ground - o.h)) {
            state = 'dead'; deadAt = Date.now();
            submitScore('runner', Math.floor(dist / 10));
            playSound(150, 'sawtooth', 0.25);
          }
        }
        ctx.fillStyle = '#fff7f9';
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = '#e9c9d3';
        ctx.fillRect(0, ground, W, H - ground);
        obs.forEach(o => {
          ctx.fillStyle = '#7a9a6b';
          ctx.fillRect(o.x, ground - o.h, o.w, o.h);
          ctx.fillStyle = 'rgba(255,255,255,0.4)';
          for (let y = ground - o.h + 10; y < ground; y += 14) ctx.fillRect(o.x, y, o.w, 2);
        });
        ctx.fillStyle = '#3b2230';
        ctx.fillRect(p.x, p.y, p.w, p.h);
        ctx.fillStyle = '#b8456a';
        ctx.fillRect(p.x, p.y + 4, p.w, 5);
        ctx.fillRect(p.x - 6, p.y + 5, 6, 3);
        ctx.fillStyle = '#fff';
        ctx.fillRect(p.x + 14, p.y + 12, 5, 3);
        if (state !== 'play') {
          ctx.fillStyle = '#3b2230';
          ctx.font = '700 16px "Shippori Mincho", serif';
          ctx.textAlign = 'center';
          ctx.fillText(state === 'ready' ? 'Touche pour courir et sauter' : 'Perdu · Touche pour rejouer', W / 2, 70);
        }
        currentLoop = requestAnimationFrame(loop);
      }
      currentLoop = requestAnimationFrame(loop);
    }

    /* ==========================================
       JEU 15 : TRI SAKURA (ball sort)
    ========================================== */
    function initSort() {
      const box = byId('sort-tubes');
      const msg = byId('sort-msg');
      const COLORS = ['#e58fa7', '#7a9a6b', '#d9b26a', '#8f7fc0'];
      const CAP = 4, N_TUBES = 6; // 4 couleurs + 2 vides
      let tubes = [], selected = -1, moves = 0, solved = false;
      window.onkeydown = null; activeHandler = null;

      function shuffle() {
        const pool = [];
        COLORS.forEach(c => { for (let i = 0; i < CAP; i++) pool.push(c); });
        for (let i = pool.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [pool[i], pool[j]] = [pool[j], pool[i]];
        }
        tubes = [];
        for (let t = 0; t < 4; t++) tubes.push(pool.slice(t * CAP, t * CAP + CAP));
        tubes.push([]); tubes.push([]);
        moves = 0; selected = -1; solved = false;
        byId('sort-moves').innerText = 0;
        byId('sort-score').innerText = 0;
        msg.innerText = 'Clique un tube puis un autre pour verser';
        render();
      }
      function topColor(t) { return tubes[t].length ? tubes[t][tubes[t].length - 1] : null; }
      function canPour(from, to) {
        if (from === to || !tubes[from].length || tubes[to].length >= CAP) return false;
        const c = topColor(from);
        return !tubes[to].length || topColor(to) === c;
      }
      function pour(from, to) {
        const c = topColor(from);
        let n = 0;
        while (tubes[from].length && topColor(from) === c && tubes[to].length < CAP) {
          tubes[to].push(tubes[from].pop());
          n++;
        }
        if (n) {
          moves++;
          byId('sort-moves').innerText = moves;
          playSound(480, 'sine', 0.05);
        }
        if (tubes.every(t => !t.length || (t.length === CAP && t.every(x => x === t[0])))) {
          solved = true;
          const sc = Math.max(20, 500 - moves * 8);
          byId('sort-score').innerText = sc;
          msg.innerText = 'Tri terminé ! Score ' + sc;
          submitScore('sort', sc);
          playSound(800, 'sine', 0.2);
        }
      }
      function render() {
        box.innerHTML = '';
        tubes.forEach((tube, i) => {
          const col = document.createElement('div');
          col.style.cssText = 'width:48px;height:160px;border:2px solid #e58fa7;border-radius:8px 8px 14px 14px;display:flex;flex-direction:column-reverse;align-items:center;padding:4px;background:#fff7f9;cursor:pointer;' + (selected === i ? 'outline:3px solid #b8456a;' : '');
          tube.forEach(c => {
            const drop = document.createElement('div');
            drop.style.cssText = 'width:36px;height:28px;border-radius:50% 50% 40% 40%;margin:2px 0;background:' + c;
            col.appendChild(drop);
          });
          col.onclick = () => {
            if (solved) return;
            if (selected < 0) { selected = i; render(); return; }
            if (selected === i) { selected = -1; render(); return; }
            if (canPour(selected, i)) pour(selected, i);
            else playSound(150, 'sawtooth', 0.08);
            selected = -1;
            render();
          };
          box.appendChild(col);
        });
      }
      shuffle();
    }

    /* Le serveur a déjà validé l'accès : on lance le premier jeu */
    initSnake();
    chargerClassement('snake');
  </script>
</body>
</html>
