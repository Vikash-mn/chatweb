<?php
include("connection.php");

echo "Starting database migration...\n";

// Add new columns to users table
$alter_queries = [
    "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    "ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE",
    "ALTER TABLE rooms ADD COLUMN creator VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE rooms ADD COLUMN display_photo VARCHAR(255) DEFAULT NULL"
];

foreach ($alter_queries as $query) {
    echo "Executing: $query\n";
    if (mysqli_query($conn, $query)) {
        echo "✓ Success\n";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "\n";
    }
}

// Create new tables
$create_queries = [
    "CREATE TABLE IF NOT EXISTS user_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        preference_key VARCHAR(50) NOT NULL,
        preference_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (username, preference_key)
    )"
];

foreach ($create_queries as $query) {
    echo "Creating table...\n";
    if (mysqli_query($conn, $query)) {
        echo "✓ Table created successfully\n";
    } else {
        echo "✗ Error creating table: " . mysqli_error($conn) . "\n";
    }
}

echo "Migration completed!\n";
?>