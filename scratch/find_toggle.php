<?php
// scratch/find_toggle.php
$content = file_get_contents(__DIR__ . '/../modules/school/students/index.php');
$lines = explode("\n", $content);

foreach ($lines as $i => $line) {
    if (strpos($line, 'toggle_status') !== false || strpos($line, 'action-toggle') !== false) {
        printf("%4d: %s\n", $i + 1, trim($line));
        // Print 5 lines before and after
        for ($j = max(0, $i - 5); $j <= min(count($lines) - 1, $i + 5); $j++) {
            if ($j !== $i) {
                printf("  %4d: %s\n", $j + 1, trim($lines[$j]));
            }
        }
        echo "----\n";
    }
}
