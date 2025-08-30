#!/usr/bin/env php
<?php
/**
 * Simple Database Initialization Script
 * This script initializes the database with the complete schema
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load database configuration
$config = include __DIR__ . '/config/database.php';

echo "=== SchoolLive Database Initialization ===\n\n";

try {
    // Test database connection
    echo "1. Connecting to database...\n";
    $dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    echo "✓ Database connection successful\n\n";

    // Create database if it doesn't exist
    echo "2. Setting up database '{$config['db_name']}'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$config['db_name']}");
    $pdo->exec("USE {$config['db_name']}");
    echo "✓ Database ready\n\n";

    // Run initialization script
    echo "3. Initializing database schema...\n";
    $initFile = __DIR__ . '/database/init_schema.sql';
    
    if (file_exists($initFile)) {
        $initSQL = file_get_contents($initFile);
        
        // Execute the entire SQL file at once
        try {
            $pdo->exec($initSQL);
            echo "✓ Schema initialization completed\n\n";
        } catch (PDOException $e) {
            echo "Error executing SQL: " . $e->getMessage() . "\n";
            // Try executing statement by statement as fallback
            $statements = array_filter(array_map('trim', explode(';', $initSQL)));
            
            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^(--|#)/', trim($statement))) {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        echo "Warning: " . $e->getMessage() . "\n";
                    }
                }
            }
            echo "✓ Schema initialization completed with warnings\n\n";
        }
    } else {
        echo "❌ Initialization file not found\n";
        exit(1);
    }

    // Verify tables
    echo "4. Verifying database structure...\n";
    $tables = ['Tm_Schools', 'Tm_Roles', 'Tx_Users', 'Tx_Classes', 'Tx_Students'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            echo "  ✓ $table: $count records\n";
        } catch (PDOException $e) {
            echo "  ❌ $table: Table not found\n";
        }
    }
    
    echo "\n=== Database Initialization Complete ===\n";
    echo "Your SchoolLive database is ready!\n\n";
    echo "Login credentials:\n";
    echo "Username: superSA002\n";
    echo "Password: password123\n\n";
    echo "API Endpoints:\n";
    echo "POST /api/login - User authentication\n";
    echo "GET /api/users - Get all users (requires auth)\n";
    echo "GET /api/students - Get all students (requires auth)\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
