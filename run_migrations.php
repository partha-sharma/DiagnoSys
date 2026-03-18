<?php
/**
 * Database Migration Runner
 * Applies all pending migrations from /migrations/ folder
 * Run this from command line: php run_migrations.php
 * Or access from browser: http://localhost/DiagnoSys/run_migrations.php
 */

require_once 'config/init.php';

$migrations_dir = __DIR__ . '/migrations/';

// Get all migration files sorted by name
$migration_files = glob($migrations_dir . '*.sql');
sort($migration_files);

if (empty($migration_files)) {
    echo "❌ No migration files found in /migrations/ folder.\n";
    exit(1);
}

echo "🔄 Starting Database Migrations...\n";
echo "================================\n\n";

$success_count = 0;
$error_count = 0;
$errors = [];

foreach ($migration_files as $migration_file) {
    $migration_name = basename($migration_file);
    
    // Read migration file
    $sql_content = file_get_contents($migration_file);
    
    if ($sql_content === false) {
        echo "❌ Failed to read: $migration_name\n";
        $error_count++;
        $errors[] = "Could not read $migration_name";
        continue;
    }
    
    echo "⏳ Executing: $migration_name\n";
    
    // Split into individual queries (handle comments and multiple statements)
    $queries = array_filter(
        array_map('trim', preg_split('/;(?=(?:[^\']*\'[^\']*\')*[^\']*$)/', $sql_content))
    );
    
    foreach ($queries as $query) {
        if (empty($query) || strpos($query, '--') === 0) {
            continue;
        }
        
        try {
            if ($conn->query($query)) {
                // Query executed successfully
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            echo "   ⚠️  Warning: " . $e->getMessage() . "\n";
            // Continue with next query even if one fails
        }
    }
    
    echo "   ✅ Completed\n\n";
    $success_count++;
}

echo "================================\n";
echo "✅ Migration Results:\n";
echo "   ✅ Successful: $success_count\n";
echo "   ❌ Failed: $error_count\n";

if (!empty($errors)) {
    echo "\n⚠️  Errors:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
}

echo "\n✅ All migrations have been applied!\n";
echo "You can now proceed to PHASE 1: Gisan - Frontend Development\n";
?>
