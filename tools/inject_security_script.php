<?php
/**
 * tools/inject_security_script.php
 * Injects security.js into the <head> of all public portal files.
 */

$appDir = __DIR__ . '/../app';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));

$count = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getRealPath();
        $content = file_get_contents($filePath);

        if (stripos($content, '<head>') !== false && stripos($content, 'security.js') === false) {
            // Determine relative path to assets/js/security.js
            $depth = substr_count(str_replace($appDir, '', $filePath), DIRECTORY_SEPARATOR);
            $prefix = ($depth >= 2) ? '../../assets/js/security.js' : '../assets/js/security.js';

            $securityTag = "<script src=\"{$prefix}\"></script>\n</head>";
            $newContent = preg_replace('/<\/head>/i', $securityTag, $content, 1);

            if ($newContent && $newContent !== $content) {
                file_put_contents($filePath, $newContent);
                echo "Injected security.js into: " . str_replace($appDir, '', $filePath) . "\n";
                $count++;
            }
        }
    }
}

echo "Total pages updated with security.js: $count\n";
