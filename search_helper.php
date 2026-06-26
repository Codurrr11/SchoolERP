<?php
$file = 'assets/js/app.js';
$content = file_get_contents($file);
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (stripos($line, 'section') !== false || stripos($line, 'class') !== false || stripos($line, 'change') !== false) {
        if (strlen(trim($line)) < 120) {
            echo ($i + 1) . ": " . trim($line) . "\n";
        }
    }
}
