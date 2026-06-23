<?php
// scratch/search_code.php
$content = file_get_contents(__DIR__ . '/../modules/school/students/index.php');
$lines = explode("\n", $content);

echo "Searching for SELECT or FROM in students/index.php:\n";
foreach ($lines as $i => $line) {
    if (stripos($line, 'SELECT') !== false || stripos($line, 'FROM') !== false || stripos($line, 'deleted_at') !== false) {
        printf("%4d: %s\n", $i + 1, trim($line));
    }
}
