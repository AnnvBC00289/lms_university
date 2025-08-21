<?php
/**
 * Script to remove extra whitespace from PHP files
 * This script will:
 * 1. Remove empty lines at the beginning and end of files
 * 2. Remove multiple consecutive empty lines
 * 3. Ensure proper spacing around PHP tags
 */

function fixWhitespace($filePath) {
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Remove empty lines at the beginning
    $content = preg_replace('/^\s*\n+/', '', $content);
    
    // Remove empty lines at the end
    $content = preg_replace('/\n+\s*$/', "\n", $content);
    
    // Replace multiple consecutive empty lines with single empty line
    $content = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $content);
    
    // Ensure proper spacing around PHP tags
    $content = preg_replace('/\?>\s*\n\s*<\?php/', "?>\n<?php", $content);
    
    // Remove trailing whitespace from lines
    $content = preg_replace('/[ \t]+$/m', '', $content);
    
    // Only write if content changed
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "Fixed: $filePath\n";
        return true;
    }
    
    return false;
}

// Get all PHP files in the project
function getPhpFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

// Main execution
$projectDir = __DIR__;
$phpFiles = getPhpFiles($projectDir);

echo "Found " . count($phpFiles) . " PHP files\n";
echo "Starting whitespace cleanup...\n\n";

$fixedCount = 0;
foreach ($phpFiles as $file) {
    if (fixWhitespace($file)) {
        $fixedCount++;
    }
}

echo "\nCleanup completed! Fixed $fixedCount files.\n";
?>



