<?php
// Dieses Skript rendert Markdown zu HTML und gibt das Ergebnis zurück

// Funktion für das Markdown-Rendering (modular)
function renderMarkdown($text, $parser = 'parsedown') {
    if ($parser === 'parsedown') {
        require_once 'Parsedown.php';
        $Parsedown = new Parsedown();
        $Parsedown->setSafeMode(true);
        return $Parsedown->text($text);
    }
    // Später: weitere Parser ergänzen
    return htmlspecialchars($text);
}

// Nur POST-Anfragen erlauben
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['markdown'])) {
    $markdown = $_POST['markdown'];
    echo renderMarkdown($markdown);
} else {
    http_response_code(400);
    echo 'Ungültige Anfrage';
}
