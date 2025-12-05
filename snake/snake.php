<?php
session_start();

// Configuration
define('COLS', 20);
define('ROWS', 20);
define('CELL_SIZE', 20); // pixels (utilisé en style)
$default_delay = 400; // ms (utilisé pour meta refresh en mode auto)

// Helpers
function init_game() {
    $_SESSION['snake'] = [
        ['x' => intdiv(COLS,2), 'y' => intdiv(ROWS,2)]
    ];
    $_SESSION['dir'] = ['x' => 1, 'y' => 0];
    $_SESSION['score'] = 0;
    $_SESSION['running'] = false;
    $_SESSION['speed'] = $GLOBALS['default_delay'];
    place_food();
}

function place_food() {
    $snake = $_SESSION['snake'] ?? [];
    while (true) {
        $p = ['x' => rand(0, COLS-1), 'y' => rand(0, ROWS-1)];
        $collision = false;
        foreach ($snake as $s) {
            if ($s['x'] === $p['x'] && $s['y'] === $p['y']) { $collision = true; break; }
        }
        if (!$collision) {
            $_SESSION['food'] = $p;
            return;
        }
    }
}

function set_dir($nx, $ny) {
    $dir = $_SESSION['dir'];
    // empêcher 180° instantané
    if ($dx = ($dir['x'] + $nx) === 0 && ($dir['y'] + $ny) === 0) {
        // si tentative d'inversion, ignorer
        return;
    }
    $_SESSION['dir'] = ['x' => $nx, 'y' => $ny];
}

function step() {
    $snake = $_SESSION['snake'];
    $dir = $_SESSION['dir'];
    $head = ['x' => ($snake[0]['x'] + $dir['x'] + COLS) % COLS,
             'y' => ($snake[0]['y'] + $dir['y'] + ROWS) % ROWS];

    // collision avec corps ?
    foreach ($snake as $s) {
        if ($s['x'] === $head['x'] && $s['y'] === $head['y']) {
            // game over
            $_SESSION['running'] = false;
            $_SESSION['msg'] = "Game Over — score : " . ($_SESSION['score'] ?? 0);
            return;
        }
    }

    array_unshift($snake, $head); // avancer

    // manger ?
    if ($head['x'] === $_SESSION['food']['x'] && $head['y'] === $_SESSION['food']['y']) {
        $_SESSION['score'] = ($_SESSION['score'] ?? 0) + 1;
        // accélérer légèrement
        $_SESSION['speed'] = max(80, ($_SESSION['speed'] ?? $GLOBALS['default_delay']) - 15);
        place_food();
    } else {
        array_pop($snake);
    }

    $_SESSION['snake'] = $snake;
}

// init si nécessaire
if (!isset($_SESSION['snake'])) {
    init_game();
}

// Process POST actions (form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $act = $_POST['action'];
        if ($act === 'start') {
            $_SESSION['running'] = true;
            $_SESSION['msg'] = "En cours";
        } elseif ($act === 'stop') {
            $_SESSION['running'] = false;
            $_SESSION['msg'] = "Arrêté";
        } elseif ($act === 'reset') {
            init_game();
            $_SESSION['msg'] = "Réinitialisé";
        } elseif ($act === 'turn') {
            // direction envoyée via name="dir" value="U/D/L/R"
            $d = $_POST['dir'] ?? '';
            if ($d === 'U') set_dir(0, -1);
            if ($d === 'D') set_dir(0, 1);
            if ($d === 'L') set_dir(-1, 0);
            if ($d === 'R') set_dir(1, 0);
        } elseif ($act === 'save') {
            // sauvegarde simple du meilleur score dans best_score.txt
            $score = $_SESSION['score'] ?? 0;
            $best = 0;
            $file = __DIR__ . '/best_score.txt';
            if (file_exists($file)) $best = (int)trim(file_get_contents($file));
            if ($score > $best) {
                file_put_contents($file, (string)$score);
                $_SESSION['msg'] = "Meilleur mis à jour : $score";
            } else {
                $_SESSION['msg'] = "Score sauvegardé (pas de nouveau meilleur)";
            }
        }
    }
    // rediriger en GET pour éviter re-soumission de formulaire si on veut
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Si jeu en cours, faire un pas à chaque chargement (ou via meta refresh auto)
if (!empty($_SESSION['running'])) {
    step();
}

// lecture meilleur score si existe
$best = 0;
$file = __DIR__ . '/best_score.txt';
if (file_exists($file)) $best = (int)trim(file_get_contents($file));

// HTML rendering
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Snake</title>
<style>
    :root{--bg:#0b1220;--cell:#051020;--snake:#17a34a;--snake-head:#065f3a;--food:#e11d48;--panel:#0f1720}
    body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:linear-gradient(#051022,#07162a);color:#e6eef6;display:flex;align-items:flex-start;justify-content:center;padding:18px}
    .container{display:flex;gap:18px;align-items:flex-start}
    .panel{background:var(--panel);padding:12px;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.6)}
    .grid{display:inline-block;border:4px solid rgba(255,255,255,0.04);background:linear-gradient(180deg,#000,#041025)}
    .row{display:flex}
    .cell{width:<?php echo CELL_SIZE; ?>px;height:<?php echo CELL_SIZE; ?>px;box-sizing:border-box;border:1px solid rgba(255,255,255,0.02)}
    .empty{background:transparent}
    .snake{background:var(--snake)}
    .snake-head{background:var(--snake-head)}
    .food{background:var(--food)}
    .controls{display:flex;flex-direction:column;gap:8px}
    .controls form{display:inline-block}
    button{padding:8px 10px;border-radius:8px;border:0;background:#0ea5a4;color:#022;cursor:pointer}
    .small{font-size:13px;color:#9fb0c4}
    .msg{font-size:14px;color:#cfe9d9}
</style>
<?php
// si en mode auto, on peut ajouter meta refresh (aucun JS)
if (!empty($_SESSION['running'])) {
    $delay = ($_SESSION['speed'] ?? $default_delay) / 1000; // en secondes
    $meta_seconds = max(1, (int)round($delay));
    echo "<meta http-equiv=\"refresh\" content=\"{$meta_seconds};url=" . htmlspecialchars($_SERVER['PHP_SELF']) . "\">";
}
?>
</head>
<body>
<div class="container">
  <div class="panel">
    <div style="margin-bottom:8px;display:flex;gap:8px;align-items:center">
      <strong>Snake - Hidden Game</strong>
      <div class="small">Score: <?php echo ($_SESSION['score'] ?? 0); ?> — Meilleur: <?php echo $best; ?></div>
    </div>

    <!-- grille -->
    <div class="grid">
      <?php
        // construire tableau 2D, marquer snake et food
        $grid = array_fill(0, ROWS, array_fill(0, COLS, 'empty'));
        foreach ($_SESSION['snake'] as $i => $s) {
            if ($i === 0) $grid[$s['y']][$s['x']] = 'snake-head';
            else $grid[$s['y']][$s['x']] = 'snake';
        }
        $f = $_SESSION['food'];
        $grid[$f['y']][$f['x']] = 'food';

        for ($y = 0; $y < ROWS; $y++) {
            echo '<div class="row">';
            for ($x = 0; $x < COLS; $x++) {
                $cls = $grid[$y][$x];
                echo "<div class=\"cell {$cls}\"></div>";
            }
            echo '</div>';
        }
      ?>
    </div>
  </div>

  <div class="panel controls">
    <div class="msg"><?php echo $_SESSION['msg'] ?? ''; ?></div>
    <div class="small">Contrôle - utilisez les boutons ci-dessous :</div>

    <!-- Forms for actions -->
    <form method="post" style="display:inline-block">
      <input type="hidden" name="action" value="start">
      <button type="submit">Démarrer</button>
    </form>

    <form method="post" style="display:inline-block">
      <input type="hidden" name="action" value="stop">
      <button type="submit">Arrêter</button>
    </form>

    <form method="post" style="display:inline-block">
      <input type="hidden" name="action" value="reset">
      <button type="submit">Réinitialiser</button>
    </form>

    <form method="post" style="display:inline-block">
      <input type="hidden" name="action" value="save">
      <button type="submit">Sauvegarder score</button>
    </form>

    <div style="height:6px"></div>

    <!-- Direction buttons : chaque click envoie une requête qui change la direction ; le jeu avancera au prochain rechargement -->
    <form method="post" style="display:inline-block">
      <input type="hidden" name="action" value="turn">
      <input type="hidden" name="dir" value="U">
      <button type="submit">↑ Haut</button>
    </form>

    <div style="display:flex;gap:6px;margin-top:6px">
      <form method="post">
        <input type="hidden" name="action" value="turn">
        <input type="hidden" name="dir" value="L">
        <button type="submit">← Gauche</button>
      </form>

      <form method="post">
        <input type="hidden" name="action" value="turn">
        <input type="hidden" name="dir" value="R">
        <button type="submit">Droite →</button>
      </form>
    </div>

    <form method="post" style="display:inline-block;margin-top:6px">
      <input type="hidden" name="action" value="turn">
      <input type="hidden" name="dir" value="D">
      <button type="submit">↓ Bas</button>
    </form>

    <div class="small" style="margin-top:8px">
      Remarques :
      <ul>
        <li>Bonsoir</li>
        <li>Utilisez les bouttons pour bouger</li>
      </ul>
    </div>
  </div>
</div>
</body>
</html>
