// preview.js
const editor = document.getElementById('md-editor');
const preview = document.getElementById('html-preview');
const form = document.getElementById('md-form');
const status = document.getElementById('status');

// Funktion für AJAX-Preview
function updatePreview(md) {
    fetch('render.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'markdown=' + encodeURIComponent(md)
    })
    .then(response => response.text())
    .then(html => { preview.innerHTML = html; });
}

// Bei jeder Änderung im Editor Vorschau aktualisieren
editor.addEventListener('input', function() {
    updatePreview(editor.value);
});

// Formular speichern per AJAX
form.addEventListener('submit', function(e) {
    e.preventDefault();
    fetch(window.location.pathname, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'markdown=' + encodeURIComponent(editor.value)
    })
    .then(response => response.text())
    .then(msg => {
        status.textContent = msg;
        setTimeout(() => { status.textContent = ''; }, 2000);
    });
});

// Initiale Vorschau (falls Seite neu geladen)
updatePreview(editor.value);
