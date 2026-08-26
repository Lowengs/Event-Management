<?php
/**
 * tools/obfuscate_all_js.php
 * Automated JavaScript Obfuscator and Minifier for NAAP System
 * Guarantees global scope execution for all inline HTML onclick attributes.
 */

function obfuscateJsContent(string $code): string {
    // 1. Remove multi-line comments /* ... */
    $code = preg_replace('!/\*[\s\S]*?\*/!', '', $code);
    
    // 2. Remove single-line comments // ...
    $lines = explode("\n", $code);
    $cleanedLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strpos($trimmed, '//') === 0) continue;
        $lineClean = preg_replace('~(?<!:)//.*$~', '', $line);
        if (trim($lineClean) !== '') {
            $cleanedLines[] = $lineClean;
        }
    }
    $code = implode("\n", $cleanedLines);

    // 3. Minify whitespace
    $code = preg_replace('/[ \t]+/', ' ', $code);
    $code = trim($code);

    // 4. Base64 encode and wrap with global eval decoder
    $b64 = base64_encode($code);
    $obfuscated = "/* NAAP System Protected Asset */\n(function(){var _0x1a=function(s){try{return decodeURIComponent(escape(atob(s)));}catch(e){return atob(s);}};window.eval(_0x1a('{$b64}'));})();";

    return $obfuscated;
}

$jsDir = __DIR__ . '/../assets/js';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($jsDir));

$count = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'js') {
        $filePath = $file->getRealPath();
        
        // Skip third-party vendor libraries inside lib/ or ionicons
        if (strpos($filePath, 'lib') !== false || strpos($filePath, 'ionicons') !== false) {
            continue;
        }

        $rawCode = file_get_contents($filePath);
        // Don't re-obfuscate if already encoded
        if (strpos($rawCode, 'NAAP System Protected Asset') !== false) {
            continue;
        }

        $obfuscated = obfuscateJsContent($rawCode);
        file_put_contents($filePath, $obfuscated);
        echo "Obfuscated: " . basename($filePath) . "\n";
        $count++;
    }
}

echo "Total files obfuscated: $count\n";
