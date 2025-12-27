<?php
/* =====================================================
   Guestbook file-based – Bludit
   JSON ONLY • NO MySQL • NO extra folders
   Paginated front-end with next/back
   ===================================================== */

$entriesFile = 'bl-themes/Perfect-Bludit-Theme-master/guestbook/entries.json';
$maxLength  = 600;

/* --- bootstrap --- */
if (!file_exists($entriesFile)) {
    file_put_contents($entriesFile, '[]');
}

function esc($v) {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

/* --- submit handler --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // honeypot
    if (!empty($_POST['website'])) exit;

    $name = esc($_POST['name'] ?? '');
    $msg  = esc($_POST['msg'] ?? '');

    if ($name === '' || $msg === '' || strlen($msg) > $maxLength) exit;

    $entry = [
        'ts'   => date('d/m/Y H:i'),
        'name' => $name,
        'msg'  => $msg
    ];

    $data = json_decode(file_get_contents($entriesFile), true);
    if (!is_array($data)) $data = [];

    $data[] = $entry;

    file_put_contents(
        $entriesFile,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
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
            style="width: 75%; font-size: 1rem; padding: 6px; border-radius: 6px; box-sizing: border-box; margin-top: 3px; background-color: #111; color: #e6e6e6;"></textarea>
    </p>

    <p>
        <strong>Messaggio</strong><br>
        <textarea name="msg" rows="6"
            placeholder="Scrivi qui il tuo messaggio..."
            style="width: 75%; font-size: 1rem; padding: 6px; border-radius: 6px; box-sizing: border-box; height: 120px; margin-top: 3px; background-color: #111; color: #e6e6e6;"></textarea>
    </p>

    <input type="submit" value="Invia" style="padding: 4px 8px; font-size: 0.85rem; border-radius: 6px; cursor: pointer; margin-left: 8px; background-color: #111; color: #e6e6e6;"> 

</div>
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
<script>
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

    // Mostra/nascondi pulsanti
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

