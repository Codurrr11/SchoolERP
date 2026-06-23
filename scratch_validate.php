<?php
$content = file_get_contents('modules/school/students/view.php');

// Remove all PHP blocks
$html = preg_replace('/<\?php.*?\?>/s', ' ', $content);

// Match all html tags, e.g. <tag...> and </tag>
preg_match_all('/<([a-z1-6]+)\b[^>]*>|<\/([a-z1-6]+)>/is', $html, $matches, PREG_OFFSET_CAPTURE);

$open_tags = [];
// We only care about block and structural tags that can collapse layouts if unmatched
$track_tags = ['div', 'span', 'main', 'ul', 'li', 'form', 'button', 'a', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'table', 'tr', 'td', 'thead', 'tbody'];

foreach ($matches[0] as $match) {
    $tag = $match[0];
    $offset = $match[1];
    
    $line_num = substr_count(substr($html, 0, $offset), "\n") + 1;
    
    // Check if it's a self-closing tag (e.g. <input ... /> or <img ...>)
    if (preg_match('/<[a-z1-6]+\b[^>]*\/>/i', $tag) || preg_match('/<(input|img|br|hr|link|meta)\b/i', $tag)) {
        continue;
    }
    
    if (stripos($tag, '</') === 0) {
        // Closing tag
        preg_match('/<\/([a-z1-6]+)>/i', $tag, $t);
        $tag_name = strtolower($t[1]);
        
        if (!in_array($tag_name, $track_tags)) continue;
        
        if (empty($open_tags)) {
            echo "Unmatched closing </$tag_name> on line $line_num when stack was empty\n";
        } else {
            // Find the matching open tag in stack
            $matched = false;
            $temp_stack = [];
            while (!empty($open_tags)) {
                $last = array_pop($open_tags);
                if ($last['name'] === $tag_name) {
                    $matched = true;
                    break;
                } else {
                    $temp_stack[] = $last;
                }
            }
            if (!$matched) {
                echo "Mismatched closing </$tag_name> on line $line_num. Expected open tag in stack.\n";
                // Restore stack
                while (!empty($temp_stack)) {
                    $open_tags[] = array_pop($temp_stack);
                }
            } else {
                // If we skipped some open tags, they might be unclosed inline elements like <a> or <span>
                // but let's check if we popped structural elements.
                foreach ($temp_stack as $skipped) {
                    if (in_array($skipped['name'], ['div', 'main', 'form', 'ul', 'li', 'section'])) {
                        echo "Warning: closing </$tag_name> on line $line_num skipped unclosed structural <{$skipped['name']}> opened on line {$skipped['line']}\n";
                    }
                }
            }
        }
    } else {
        // Opening tag
        preg_match('/<([a-z1-6]+)\b/i', $tag, $t);
        $tag_name = strtolower($t[1]);
        
        if (!in_array($tag_name, $track_tags)) continue;
        
        preg_match('/class=["\']([^"\']+)["\']/i', $tag, $cl);
        preg_match('/id=["\']([^"\']+)["\']/i', $tag, $id);
        $class = $cl[1] ?? '';
        $id_val = $id[1] ?? '';
        
        $open_tags[] = ['line' => $line_num, 'name' => $tag_name, 'class' => $class, 'id' => $id_val, 'tag' => $tag];
    }
}

echo "Remaining open tags: " . count($open_tags) . "\n";
foreach ($open_tags as $ot) {
    echo "Unclosed <{$ot['name']}> opened on line {$ot['line']} | ID: {$ot['id']} | Class: {$ot['class']}\n";
}
