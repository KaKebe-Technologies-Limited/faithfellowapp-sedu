<?php
/**
 * FaithFellows one-shot setup script.
 * Run: C:\xampp\php\php.exe setup.php
 */

$php   = 'C:\\xampp\\php\\php.exe';
$mysql = 'C:\\xampp\\mysql\\bin\\mysql.exe';
$npm   = 'C:\\Users\\SEDU\\tools\\node\\npm.cmd';
$node  = 'C:\\Users\\SEDU\\tools\\node\\node.exe';
$dir   = __DIR__;

function step(string $label): void
{
    echo "\n>> {$label}\n";
}

function run(string $cmd): bool
{
    echo "   $ {$cmd}\n";
    passthru($cmd, $code);
    if ($code !== 0) {
        echo "   !! FAILED (exit {$code})\n";
        return false;
    }
    echo "   OK\n";
    return true;
}

echo "\n==============================================\n";
echo "  FaithFellows Auto Setup\n";
echo "==============================================\n";

// 1. Create DB
step('1/6 — Creating MySQL database');
$dsn = 'mysql:host=127.0.0.1;port=3306';
try {
    $pdo = new PDO($dsn, 'root', '');
    $pdo->exec("CREATE DATABASE IF NOT EXISTS faithfellows CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "   Database 'faithfellows' ready.\n";
} catch (PDOException $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    echo "   Make sure XAMPP MySQL is running, then re-run this script.\n";
    exit(1);
}

// 2. Composer install (vendor already exists, but ensure it's fresh)
step('2/6 — Composer install');
if (!file_exists("{$dir}/vendor/autoload.php")) {
    $composer = 'C:\\Users\\SEDU\\tools\\composer.phar';
    run("\"{$php}\" \"{$composer}\" install --no-interaction --no-progress");
} else {
    echo "   vendor/ already exists — skipping.\n";
}

// 3. NPM install
step('3/6 — NPM install');
if (!is_dir("{$dir}/node_modules")) {
    run("\"{$node}\" \"{$npm}\" install --no-fund --no-audit");
} else {
    echo "   node_modules/ already exists — skipping.\n";
}

// 4. Vite build
step('4/6 — Vite build');
if (!is_dir("{$dir}/public/build")) {
    run("\"{$node}\" \"{$npm}\" run build");
} else {
    echo "   public/build/ already exists — skipping (delete it to force rebuild).\n";
}

// 5. Migrations
step('5/6 — Running migrations');
run("\"{$php}\" \"{$dir}/artisan\" migrate --force");

// 6. Seed
step('6/6 — Seeding database');
run("\"{$php}\" \"{$dir}/artisan\" db:seed --force");

// 7. Storage link & cache clear
step('Storage link + cache clear');
run("\"{$php}\" \"{$dir}/artisan\" storage:link");
run("\"{$php}\" \"{$dir}/artisan\" config:clear");
run("\"{$php}\" \"{$dir}/artisan\" cache:clear");
run("\"{$php}\" \"{$dir}/artisan\" view:clear");
run("\"{$php}\" \"{$dir}/artisan\" route:clear");

echo "\n==============================================\n";
echo "  DONE! Start the server:\n";
echo "  {$php} {$dir}\\artisan serve\n";
echo "\n";
echo "  Open: http://127.0.0.1:8000\n";
echo "  Super Admin — admin\@faithfellows.app / ChangeMe!2026\n";
echo "==============================================\n\n";
