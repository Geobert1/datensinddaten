<?php
// Parsedown einbinden
require_once __DIR__ . '/Parsedown.php';

$dir = __DIR__ . '/mdfiles';
$files = glob($dir . '/*.md');

// Parsedown-Instanz
$Parsedown = new Parsedown();

// Funktion zum Ersetzen von image: und title: Zeilen
function preProcessMarkdown(string $markdown): string
{
    $lines = explode("\n", $markdown);
    $result = [];
    $inImageContainer = false;

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $originalLine = $line; // Für die Prüfung nachher

        // 1. Bild und Titel (unverändert)
        if (preg_match('/^image:\s*(https?:\/\/\S+)/i', $line, $matches)) {
            $url = htmlspecialchars($matches[1], ENT_QUOTES | ENT_HTML5);
            $result[] = '<div class="image-container">';
            $result[] = '<img src="' . $url . '" alt="" style="width:100%; height:auto;">';
            $inImageContainer = true;
            continue;
        }
        if (preg_match('/^title:\s*(.*)/i', $line, $matches)) {
            if ($inImageContainer) {
                $title = htmlspecialchars(trim($matches[1]), ENT_QUOTES | ENT_HTML5);
                $result[] = '<p class="image-title">' . $title . '</p>';
                $result[] = '</div>';
                $inImageContainer = false;
            }
            continue;
        }

        // 2. URL und Name (angepasst für Listen)
        if (preg_match('/^\s*-\s*url:\s*(\S+)(?:\s+name:\s*(.+))?/i', $line, $matches)) {
            $url = htmlspecialchars($matches[1], ENT_QUOTES | ENT_HTML5);
            $name = isset($matches[2]) ? htmlspecialchars(trim($matches[2]), ENT_QUOTES | ENT_HTML5) : null;

            // Wenn Name fehlt, in der nächsten Zeile suchen
            if ($name === null && isset($lines[$i + 1]) && preg_match('/^\s*name:\s*(.+)/i', $lines[$i + 1], $nameMatch)) {
                $name = htmlspecialchars(trim($nameMatch[1]), ENT_QUOTES | ENT_HTML5);
                $i++; // Nächste Zeile überspringen
            }

            // Wenn immer noch kein Name, URL als Linktext
            if ($name === null) {
                $name = $url;
            }

            $line = '<li><a href="' . $url . '">' . $name . '</a></li>';
            $result[] = $line;
            continue;
        }

        // 3. Normale Zeile
        if ($inImageContainer) {
            $result[] = '</div>';
            $inImageContainer = false;
        }

        // Zeilenumbruch anhängen, wenn die Zeile ein Wort gefolgt von ':' enthält
        if (preg_match('/\b\w+:/', $originalLine)) {
            $line .= '<br>';
        }

        $result[] = $line;
    }

    if ($inImageContainer) {
        $result[] = '</div>';
    }

    return implode("\n", $result);
}



// Alle erlaubten Dateien (nur Basename)
$allowedFiles = array_map('basename', $files);

// Ausgewählte Datei aus GET oder POST
$selectedFile = $_GET['file'] ?? $_POST['file'] ?? null;
if ($selectedFile === null || !in_array($selectedFile, $allowedFiles, true)) {
    $selectedFile = $allowedFiles[0] ?? null;
}

$filePath = $selectedFile ? $dir . '/' . $selectedFile : null;

$message = '';

// Speichern bei POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $filePath && isset($_POST['content'])) {
    $content = $_POST['content'];
    copy($filePath, $filePath . '.bak');
    if (file_put_contents($filePath, $content) !== false) {
        $message = "Datei <strong>" . htmlspecialchars($selectedFile) . "</strong> wurde gespeichert.";
    } else {
        $message = "Fehler beim Speichern der Datei!";
    }
}

// Markdown-Inhalt laden
$markdownContent = $filePath && file_exists($filePath) ? file_get_contents($filePath) : '';

// Vorverarbeitung image: und title:
$processedMarkdown = preProcessMarkdown($markdownContent);

// Markdown parsen
$htmlContent = $Parsedown->text($processedMarkdown);

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Markdown Editor mit Dateiauswahl</title>
<style>
    /* Reset & Basis */
    * {
        box-sizing: border-box;
    }
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        display: flex;
        height: 100vh;
        overflow: hidden;
    }
    /* Seitenmenü */
    #sidebar {
        background: #2c3e50;
        color: white;
        width: 280px;
        max-width: 80vw;
        overflow-y: auto;
        padding: 1em;
        flex-shrink: 0;
        position: relative;
    }
    #sidebar h2 {
        margin-top: 0;
        font-size: 1.5em;
        margin-bottom: 1em;
        border-bottom: 1px solid #34495e;
        padding-bottom: 0.5em;
    }
    #fileList {
        list-style: none;
        padding: 0;
        margin: 0 0 1em 0;
    }
    #fileList li {
        margin-bottom: 0.5em;
    }
    #fileList li a {
        color: #ecf0f1;
        text-decoration: none;
        display: block;
        padding: 0.3em 0.5em;
        border-radius: 4px;
    }
    #fileList li a.active,
    #fileList li a:hover {
        background: #2980b9;
    }
    /* Toggle Button */
    #toggleSidebar {
        position: fixed;
        top: 1em;
        left: 1em;
        background: #2980b9;
        border: none;
        color: white;
        padding: 0.5em 1em;
        font-size: 1em;
        cursor: pointer;
        border-radius: 4px;
        z-index: 1000;
    }
    /* Hauptbereich */
    #main {
        flex-grow: 1;
        padding: 1em 2em;
        overflow-y: auto;
        background: #f7f9fc;
        display: flex;
        flex-direction: column;
        height: 100vh;
    }
    #main h1 {
        margin-top: 0;
        margin-bottom: 0.5em;
    }
    /* Nachricht */
    #message {
        margin-bottom: 1em;
        padding: 0.7em 1em;
        background: #dff0d8;
        color: #3c763d;
        border-radius: 6px;
        display: none;
    }
    #message.show {
        display: block;
    }
    /* Formular */
    form {
        display: flex;
        flex-direction: column;
        height: 90vh;       /* Gesamt-Höhe für Editor + Vorschau */
        max-height: 1000px;  /* Optional max-Höhe */
        gap: 1em;
    }

    #editor, #renderedContent {
        height: 50%;        /* Jeweils 50% der Höhe vom Formular */
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #ccc;
        border-radius: 6px;
        padding: 0.5em;
        overflow: auto;
    }

    #editor {
        font-family: monospace;
        font-size: 1em;
        resize: none;
    }

    #renderedContent {
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    #saveBtn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 0.7em 1.5em;
        font-size: 1em;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.3s ease;
        align-self: center;
        width: 150px;
    }
    #saveBtn:hover {
        background: #219150;
    }
    /* Bildcontainer */
    .image-container {
        width: 300px;
        margin-bottom: 1em;
        border: 1px solid #ccc;
        border-radius: 6px;
        overflow: hidden;
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .image-container img {
        width: 100%;
        height: auto;
        display: block;
    }

    .image-title {
        font-style: italic;
        font-size: 0.9em;
        color: #555;
        padding: 0.3em 0.5em;
        text-align: center;
        border-top: 1px solid #ddd;
    }
    /* Responsive */
    @media (max-width: 700px) {
        #sidebar {
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            z-index: 999;
            transform: translateX(0);
            transition: transform 0.3s ease;
        }
        #sidebar.hidden {
            transform: translateX(-100%);
        }
        #main {
            padding: 1em 1em 1em 1em;
        }
    }
</style>
</head>
<body>

<button id="toggleSidebar" aria-label="Menü ein-/ausklappen">☰ Menü</button>

<nav id="sidebar" class="hidden" aria-label="Dateiauswahl">
    <h2>Markdown Dateien</h2>
    <ul id="fileList">
        <?php foreach ($allowedFiles as $file): ?>
            <li>
                <a href="?file=<?= urlencode($file) ?>"
                   class="<?= ($file === $selectedFile) ? 'active' : '' ?>"
                   aria-current="<?= ($file === $selectedFile) ? 'page' : 'false' ?>">
                   <?= htmlspecialchars($file) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<main id="main">
    <h1><?= htmlspecialchars($selectedFile ?? 'Keine Datei ausgewählt') ?></h1>

    <?php if ($message): ?>
        <div id="message" class="show" role="alert"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($selectedFile): ?>
    <form method="post" action="?file=<?= urlencode($selectedFile) ?>">
        <input type="hidden" name="file" value="<?= htmlspecialchars($selectedFile) ?>" />
        <textarea id="editor" name="content" aria-label="Markdown Editor"><?= htmlspecialchars($markdownContent) ?></textarea>
        <div id="renderedContent" aria-live="polite" aria-label="Vorschau"><?= $htmlContent ?></div>
        <button type="submit" id="saveBtn">Speichern</button>
    </form>
    <?php else: ?>
        <p>Keine Markdown-Datei gefunden.</p>
    <?php endif; ?>
</main>

<script>
    // Toggle Sidebar
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('hidden');
    });

    // Rudimentäre Live-Vorschau (nur Zeilenumbrüche)
    const editor = document.getElementById('editor');
    const preview = document.getElementById('renderedContent');

    editor.addEventListener('input', () => {
        let text = editor.value
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\n/g, "<br>");
        preview.innerHTML = text;
    });
</script>

</body>
</html>
