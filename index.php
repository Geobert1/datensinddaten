<?php
// Parsedown einbinden
require_once __DIR__ . '/Parsedown.php';

// Verzeichnis mit Markdown-Dateien
$dir = __DIR__ . '/mdfiles';
$files = glob($dir . '/*.md');

// Parsedown-Instanz
$Parsedown = new Parsedown();

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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Markdown Dateien</title>
    <style>
        body { font-family: sans-serif; margin: 1em; }
        .container { max-width: 800px; margin: auto; }
        @media (max-width: 600px) {
            .container { padding: 0 0.5em; }
        }
        h1 { font-size: 2em; }
        h2 { font-size: 1.5em; }
        ul { padding-left: 1.2em; }
        section { border:1px solid gray; border-radius: 5px; padding:5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Markdown Dateien</h1>
    <?php foreach ($files as $file): ?>
        <section style="margin-bottom:2em;">
<?php
// Markdown-Inhalt laden
$filePath=$file;
$markdownContent = $filePath && file_exists($filePath) ? file_get_contents($filePath) : '';

// Vorverarbeitung image: und title:
$processedMarkdown = preProcessMarkdown($markdownContent);

// Markdown parsen
$htmlContent = $Parsedown->text($processedMarkdown);
?>
            <h2><?= htmlspecialchars(basename($file)) ?></h2>
            <div>
                <?= $Parsedown->text(file_get_contents($file)) ?>
                <?= $htmlContent ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
</body>
</html>
