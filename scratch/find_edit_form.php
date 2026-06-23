<?php
// scratch/find_edit_form.php
$content = file_get_contents(__DIR__ . '/../modules/school/students/index.php');
$lines = explode("\n", $content);

foreach ($lines as $i => $line) {
    if (strpos($line, 'editStudentModal') !== false || strpos($line, 'id="edit_student_id"') !== false) {
        printf("%4d: %s\n", $i + 1, trim($line));
        // Print 10 lines after
        for ($j = $i + 1; $j <= min(count($lines) - 1, $i + 15); $j++) {
            printf("  %4d: %s\n", $j + 1, trim($lines[$j]));
        }
        echo "----\n";
    }
}
