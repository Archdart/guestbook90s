<?php
/* =====================================================
   Guestbook file-based – Bludit
   JSON ONLY • NO MySQL • NO extra folders
   Paginated front-end with next/back
   + Anti-spam: rate limit, blacklist, no-link, captcha
   ===================================================== */

session_start();

$entriesFile    = 'bl-themes/Perfect-Bludit-Theme-master/guestbook/entries.json';
$rateLimitFile  = 'bl-themes/Perfect-Bludit-Theme-master/guestbook/ratelimit.json';
$maxLength      = 600;
$rateLimitSecs  = 60; // 1 messaggio ogni 60 secondi per IP

/* --- bootstrap --- */
if (!file_exists($entriesFile)) {
    file_put_contents($entriesFile, '[]');
}
if (!file_exists($rateLimitFile)) {
    file_put_contents($rateLimitFile, '{}');
}

function esc($v) {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

function getClientIp() {
    // Con Cloudflare (Tunnel o proxy) REMOTE_ADDR è l'IP di Cloudflare, non del visitatore.
    // CF-Connecting-IP contiene l'IP reale del client.
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/* --- blacklist parole/pattern spam --- */
$blacklist = [
    'viagra', 'cialis', 'casino', 'скачать', 'игры', 'porn', 'xxx',
    'bitcoin', 'crypto invest', 'loan', 'earn money', 'seo service',
    'sex video', 'buy followers', 'click here'
];

function containsBlacklisted($text, $blacklist) {
    $lower = mb_strtolower($text, 'UTF-8');
    foreach ($blacklist as $word) {
        if (mb_strpos($lower, $word) !== false) return true;
    }
    return false;
}

function containsLinkOrHtml($text) {
    // blocca tag HTML e qualunque forma di link/URL
    if (preg_match('/<[a-z\/][\s\S]*>/i', $text)) return true;
    if (preg_match('/(https?:\/\/|www\.|\.(com|net|org|ru|xyz|info|biz)\b)/i', $text)) return true;
    return false;
}

/* --- genera nuovo captcha matematico (immagine SVG, non testo nel DOM) --- */
function captchaSvg($a, $b) {
    $text = "$a + $b = ?";
    $lines = '';
    for ($i = 0; $i < 6; $i++) {
        $x1 = random_int(0, 150); $y1 = random_int(0, 50);
        $x2 = random_int(0, 150); $y2 = random_int(0, 50);
        $color = sprintf('#%02x%02x%02x', random_int(60,120), random_int(60,120), random_int(60,120));
        $lines .= "<line x1=\"$x1\" y1=\"$y1\" x2=\"$x2\" y2=\"$y2\" stroke=\"$color\" stroke-width=\"1\"/>";
    }
    $rotate = random_int(-10, 10);
    $tx = random_int(10, 25);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="50">'
         . '<rect width="150" height="50" fill="#111"/>'
         . $lines
         . '<text x="' . $tx . '" y="32" font-size="21" font-family="monospace" fill="#e6e6e6" '
         . 'transform="rotate(' . $rotate . ' 75 25)">' . htmlspecialchars($text) . '</text>'
         . '</svg>';
    return $svg;
}

function newCaptcha() {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['captcha_answer']   = $a + $b;
    $_SESSION['captcha_svg']      = base64_encode(captchaSvg($a, $b));
    $_SESSION['form_loaded_at']   = time();
    $_SESSION['js_token']         = bin2hex(random_bytes(8));
}

$error = '';
$minFillSecs = 3; // sotto questa soglia = compilazione troppo veloce per essere umana

/* --- submit handler --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // honeypot
    if (!empty($_POST['website'])) exit;

    $name     = trim($_POST['name'] ?? '');
    $msg      = trim($_POST['msg'] ?? '');
    $captcha  = trim($_POST['captcha'] ?? '');
    $jsToken  = trim($_POST['js_check'] ?? '');

    $ip = getClientIp();

    // 1) Captcha (contro i valori generati al caricamento del form, PRIMA di rigenerarli)
    $captchaOk = isset($_SESSION['captcha_answer']) && (int)$captcha === (int)$_SESSION['captcha_answer'];

    // 2) Token JS: se il campo non è stato popolato, il browser non ha eseguito lo script -> bot
    $jsOk = isset($_SESSION['js_token']) && $jsToken === $_SESSION['js_token'];

    // 3) Time-trap: submit troppo veloce dal caricamento pagina -> bot
    $elapsed = isset($_SESSION['form_loaded_at']) ? (time() - $_SESSION['form_loaded_at']) : 0;
    $tooFast = $elapsed < $minFillSecs;

    // 4) Validazione base
    $validBase = ($name !== '' && $msg !== '' && strlen($msg) <= $maxLength);

    // 5) Blacklist + link/HTML
    $isSpam = containsBlacklisted($name . ' ' . $msg, $blacklist)
           || containsLinkOrHtml($name)
           || containsLinkOrHtml($msg);

    // 6) Rate limit per IP
    $rateData = json_decode(file_get_contents($rateLimitFile), true);
    if (!is_array($rateData)) $rateData = [];
    $lastSubmit = $rateData[$ip] ?? 0;
    $rateLimited = (time() - $lastSubmit) < $rateLimitSecs;

    if (!$captchaOk) {
        $error = 'Captcha errato, riprova.';
        newCaptcha();
    } elseif (!$jsOk || $tooFast) {
        // messaggio generico: non riveliamo ai bot quale controllo hanno fallito
        $error = 'Invio non valido, riprova.';
        newCaptcha();
    } elseif (!$validBase) {
        $error = 'Compila nome e messaggio (max ' . $maxLength . ' caratteri).';
        newCaptcha();
    } elseif ($isSpam) {
        $error = 'Messaggio non consentito (link o contenuto non ammesso).';
        newCaptcha();
    } elseif ($rateLimited) {
        $error = 'Aspetta qualche secondo prima di inviare un altro messaggio.';
        newCaptcha();
    } else {
        // tutto ok -> salva
        $entry = [
            'ts'   => date('d/m/Y H:i'),
            'name' => esc($name),
            'msg'  => esc($msg)
        ];

        $data = json_decode(file_get_contents($entriesFile), true);
        if (!is_array($data)) $data = [];
        $data[] = $entry;

        file_put_contents(
            $entriesFile,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        // aggiorna rate limit
        $rateData[$ip] = time();
        file_put_contents($rateLimitFile, json_encode($rateData), LOCK_EX);

        // consuma captcha e rigenera
        newCaptcha();

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
} else {
    // GET: primo caricamento pagina -> genera captcha nuovo
    newCaptcha();
}
?>

<!-- =====================
     UI
     ===================== -->
<form method="post">
    <p>
        <strong>Nome</strong><br>
        <textarea name="name" rows="1"
            placeholder="Inserisci il tuo nickname!"
            style="width: 75%; font-size: 1rem; padding: 6px; border-radius: 6px; box-sizing: border-box; margin-top: 3px; background-color: #111; color: #e6e6e6;"><?= isset($_POST['name']) ? esc($_POST['name']) : '' ?></textarea>
    </p>

    <p>
        <strong>Messaggio</strong><br>
        <textarea name="msg" rows="6"
            placeholder="Scrivi qui il tuo messaggio..."
            style="width: 75%; font-size: 1rem; padding: 6px; border-radius: 6px; box-sizing: border-box; height: 120px; margin-top: 3px; background-color: #111; color: #e6e6e6;"><?= isset($_POST['msg']) ? esc($_POST['msg']) : '' ?></textarea>
    </p>

    <p>
        <strong>Quanto fa?</strong><br>
        <img src="data:image/svg+xml;base64,<?= $_SESSION['captcha_svg'] ?>" alt="captcha" style="display:block; margin-top:3px; border-radius:6px;">
        <input type="text" name="captcha" required autocomplete="off"
            style="width: 100px; font-size: 1rem; padding: 6px; border-radius: 6px; box-sizing: border-box; margin-top: 6px; background-color: #111; color: #e6e6e6;">
    </p>

    <?php if ($error): ?>
        <p style="color:#ff6b6b; font-size:0.85rem;"><?= esc($error) ?></p>
    <?php endif; ?>

    <input type="submit" value="Invia" style="padding: 4px 8px; font-size: 0.85rem; border-radius: 6px; cursor: pointer; margin-left: 8px; background-color: #111; color: #e6e6e6;">

    <input type="hidden" name="js_check" id="js_check" value="">
    <input type="text" name="website" style="display:none">
</form>

<hr>

<h3>Messaggi</h3>
<pre id="gb"></pre>

<div style="margin-top:5px;">
    <button id="back" style="padding:4px 8px; border-radius:6px; cursor:pointer; background:#111; color:#e6e6e6; display:none;">Messaggi più recenti</button>
    <button id="more" style="padding:4px 8px; border-radius:6px; cursor:pointer; background:#111; color:#e6e6e6; display:none;">Vedi messaggi precedenti</button>
</div>
<div style="font-size: 0.65rem; line-height: 1; margin-top: 4px; color: #999;">
    Powered by <a href="https://github.com/Archdart/guestbook90s" style="color:#999; text-decoration:underline;">Guestbook90s by Archdart</a>
</div>
<script>
// popola il token js_check: se questo script non viene eseguito (bot senza JS),
// il campo resta vuoto e il server scarta l'invio
document.getElementById('js_check').value = '<?= $_SESSION['js_token'] ?>';

const pageSize = 10;
let allData = [];
let currentPage = 0;

function renderPage() {
    const start = allData.length - (currentPage + 1) * pageSize;
    const end   = allData.length - currentPage * pageSize;
    const pageData = allData.slice(Math.max(start,0), end);

    let out = '';
    pageData.reverse().forEach(m => {
        out += `[${m.ts}] ${m.name} ha scritto:\n`;
        out += m.msg + "\n------------------------------\n";
    });

    document.getElementById('gb').textContent = out;

    document.getElementById('more').style.display = start > 0 ? 'inline-block' : 'none';
    document.getElementById('back').style.display = currentPage > 0 ? 'inline-block' : 'none';
}

document.getElementById('more').addEventListener('click', () => {
    currentPage++;
    renderPage();
});

document.getElementById('back').addEventListener('click', () => {
    if(currentPage > 0) currentPage--;
    renderPage();
});

fetch('bl-themes/Perfect-Bludit-Theme-master/guestbook/entries.json?' + Date.now())
    .then(r => r.json())
    .then(data => {
        allData = data;
        renderPage();
    })
    .catch(err => {
        document.getElementById('gb').textContent = 'Errore nel caricare i messaggi.';
    });
</script>
