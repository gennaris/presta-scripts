<?php
/**
 * This script recursively searches for PrestaShop installations in subdirectories.
 * A PrestaShop installation is identified by the presence of "config/settings.inc.php".
 * For each installation found, it clears the contents of the "var/cache" folder,
 * without deleting the "cache" folder itself.
 *
 * Usage: php clear_prestashop_cache.php
 */

/**
 * Recursively deletes all files and folders within a given directory,
 * without deleting the directory itself.
 *
 * @param string $dir The directory whose contents will be deleted.
 */
function deleteDirContents($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            // Recursively remove the subdirectory and its contents.
            deleteDirContents($fullPath);
            if (!rmdir($fullPath)) {
                echo "Warning: Could not remove directory: $fullPath\n";
            }
        } else {
            if (!unlink($fullPath)) {
                echo "Warning: Could not delete file: $fullPath\n";
            }
        }
    }
}

/**
 * Recursively searches for PrestaShop installations starting at the base directory.
 * An installation is detected by finding a file named "settings.inc.php" inside a "config" folder.
 *
 * @param string $baseDir The directory to begin searching.
 * @return array A list of installation root paths.
 */
function findPrestashopInstallations($baseDir) {
    $installations = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->getFilename() === 'settings.inc.php') {
            $pathParts = explode(DIRECTORY_SEPARATOR, $file->getPath());
            // Check if this settings file is located in a "config" directory.
            if (end($pathParts) === 'config') {
                // The installation root is one directory up from "config".
                $installRoot = dirname($file->getPath());
                $installations[] = $installRoot;
            }
        }
    }
    return array_unique($installations);
}

// Define the base directory for the search (current working directory).
$baseDir = getcwd();
echo "Scanning for PrestaShop installations in: $baseDir\n";

// Find all PrestaShop installations in subdirectories.
$installations = findPrestashopInstallations($baseDir);

if (empty($installations)) {
    echo "No PrestaShop installations found.\n";
    exit(0);
}

foreach ($installations as $installPath) {
    $cacheDir = $installPath . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache';
    if (is_dir($cacheDir)) {
        echo "Cleaning cache in: $cacheDir\n";
        deleteDirContents($cacheDir);
    } else {
        echo "No cache directory found for installation: $installPath\n";
    }
}

echo "Cache cleaning completed.\n";
?>

