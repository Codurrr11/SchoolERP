<?php
// scratch/audit.php

$files = [];
$dir = new RecursiveDirectoryIterator(__DIR__ . '/../modules/school');
$iterator = new RecursiveIteratorIterator($dir);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = realpath($file->getPathname());
    }
}

// Add root files
$root_files = ['index.php', 'login.php', 'register.php'];
foreach ($root_files as $rf) {
    $path = realpath(__DIR__ . '/../' . $rf);
    if ($path && is_file($path)) {
        $files[] = $path;
    }
}

$tables_with_school_id = [
    'academic_sessions', 'classes', 'fee_payments', 'parents', 'sections', 
    'student_attendance', 'students', 'teacher_attendance', 'teachers', 
    'transport_routes', 'users'
];

$tables_without_school_id = [
    'parent_students', 'student_fee_items', 'student_qualifications', 'teacher_classes'
];

$all_tables = array_merge($tables_with_school_id, $tables_without_school_id);

echo "Auditing " . count($files) . " files...\n\n";

foreach ($files as $file) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    
    // Check auth_check & enforce_tenant
    $has_auth_check = stripos($content, 'auth_check') !== false;
    $has_enforce_tenant = stripos($content, 'enforce_tenant') !== false;
    
    $relative_path = str_replace(realpath(__DIR__ . '/../'), '', $file);
    
    // Scan line by line for raw sql queries or prepared statements
    $in_query = false;
    $query_buffer = '';
    $query_start_line = 0;
    
    foreach ($lines as $line_num => $line) {
        // Simple heuristic to find queries: check for prepare or query
        if (preg_match('/->(prepare|query)\s*\(\s*[\'"](.*)/i', $line, $matches) || preg_match('/\$pdo->(prepare|query)/i', $line)) {
            // Find the query block
            $line_idx = $line_num;
            $q_str = '';
            while ($line_idx < count($lines)) {
                $q_str .= $lines[$line_idx] . "\n";
                // count quotes to see if we reached the end of the SQL string
                // or check for closing parenthesis );
                if (preg_match('/\)\s*;/i', $lines[$line_idx]) || preg_match('/;\s*$/i', trim($lines[$line_idx]))) {
                    break;
                }
                $line_idx++;
                if ($line_idx - $line_num > 15) { // safety limit
                    break;
                }
            }
            
            // Analyze q_str
            $clean_q = preg_replace('/\s+/', ' ', $q_str);
            
            // Check if any of our tables is in the query
            $matched_tables = [];
            foreach ($all_tables as $tbl) {
                if (preg_match('/\b' . $tbl . '\b/i', $clean_q)) {
                    $matched_tables[] = $tbl;
                }
            }
            
            if (!empty($matched_tables)) {
                // Check if school_id or school_name or similar is in the query
                $has_school_id = preg_match('/school_id/i', $clean_q) || preg_match('/:\s*(sid|school_id)/i', $clean_q);
                // Also check if the table is a table with school_id. If yes, it MUST have school_id check.
                // If it is a table without school_id (e.g. pivot/link tables), we need to ensure they join/check appropriately.
                $missing_school_id = false;
                
                // Let's analyze if there's any table with school_id, does it have school_id check in query?
                // Wait, if it joins them or does a SELECT from a table with school_id, it should filter by school_id.
                // We'll flag it for manual review if it doesn't have "school_id" or ":school_id" or similar.
                if (!$has_school_id) {
                    $missing_school_id = true;
                }
                
                if ($missing_school_id) {
                    echo "FILE: $relative_path (Line " . ($line_num + 1) . ")\n";
                    echo "Query: " . trim($clean_q) . "\n";
                    echo "Tables involved: " . implode(', ', $matched_tables) . "\n";
                    echo "Missing school_id filter check: " . ($missing_school_id ? 'YES' : 'NO') . "\n";
                    echo "--------------------------------------------------\n";
                }
            }
        }
    }
}
