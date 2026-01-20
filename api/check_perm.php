<?php
// api/check_perm.php
header('Content-Type: text/plain');

echo "=== Permission Diagnostic ===\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Current Script: " . __FILE__ . "\n";
echo "Current Dir: " . __DIR__ . "\n";

// Check API Folder Write
$testFile = __DIR__ . '/perm_test.txt';
echo "Trying to write to API folder...\n";
if (@file_put_contents($testFile, 'OK')) {
    echo "[PASS] Write Access: YES (File created)\n";
    unlink($testFile);
} else {
    echo "[FAIL] Write Access: NO\n";
    echo "Is Dir Writable? " . (is_writable(__DIR__) ? 'YES' : 'NO') . "\n";
}

echo "\n-----------------------------\n";

// Check Root Write (for SQLite .db creation if not exists)
$rootDir = realpath(__DIR__ . '/../');
echo "Root Dir: $rootDir\n";
echo "Trying to write to Root folder...\n";

$testFileRoot = $rootDir . '/root_perm_test.txt';
if (@file_put_contents($testFileRoot, 'OK')) {
    echo "[PASS] Root Write Access: YES\n";
    unlink($testFileRoot);
} else {
    echo "[FAIL] Root Write Access: NO\n";
    echo "Is Root Dir Writable? " . (is_writable($rootDir) ? 'YES' : 'NO') . "\n";
}

echo "\n-----------------------------\n";

// Check Database File specific
$dbFile = $rootDir . '/notifications.db';
echo "Checking Database: $dbFile\n";
if (file_exists($dbFile)) {
    echo "DB File Exists: YES\n";
    echo "Is DB Writable? " . (is_writable($dbFile) ? 'YES' : 'NO') . "\n";
} else {
    echo "DB File Exists: NO (Needs Root Write Access to create)\n";
}

// Check PHP SQLite Driver
echo "\n-----------------------------\n";
echo "SQLite Extension: " . (extension_loaded('pdo_sqlite') ? 'LOADED' : 'MISSING (Critical!)') . "\n";
?>
