<?php
// Database setup script with improved error handling and missing table creation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// First create database if it doesn't exist
$servername = "localhost";
$username = "root";
$password = "";

try {
    $conn_temp = mysqli_connect($servername, $username, $password);
    if (!$conn_temp) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }

    $sql = "CREATE DATABASE IF NOT EXISTS chatapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!mysqli_query($conn_temp, $sql)) {
        throw new Exception("Error creating database: " . mysqli_error($conn_temp));
    }

    mysqli_close($conn_temp);

    // Now connect to the database and create tables
    include("connection.php");

    // Set charset to utf8mb4 for full Unicode support
    mysqli_set_charset($conn, "utf8mb4");

    $tables = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            profile_photo VARCHAR(255) DEFAULT NULL,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_online BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_is_online (is_online)
        )",

        'rooms' => "CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            roomname VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            creator VARCHAR(50) NOT NULL,
            display_photo VARCHAR(255) DEFAULT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_roomname (roomname),
            INDEX idx_creator (creator)
        )",

        'room_users' => "CREATE TABLE IF NOT EXISTS room_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            roomname VARCHAR(50) NOT NULL,
            username VARCHAR(50) NOT NULL,
            user_token VARCHAR(64) NOT NULL,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_room_user (roomname, username),
            INDEX idx_roomname (roomname),
            INDEX idx_username (username),
            INDEX idx_user_token (user_token)
        )",

        'messages' => "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            msg TEXT NOT NULL,
            roomname VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_roomname (roomname),
            INDEX idx_username (username),
            INDEX idx_created_at (created_at)
        )",

        'typing_indicators' => "CREATE TABLE IF NOT EXISTS typing_indicators (
            id INT AUTO_INCREMENT PRIMARY KEY,
            roomname VARCHAR(50) NOT NULL,
            username VARCHAR(50) NOT NULL,
            is_typing BOOLEAN NOT NULL DEFAULT 0,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_room_user_typing (roomname, username),
            INDEX idx_roomname (roomname),
            INDEX idx_is_typing (is_typing)
        )",

        'user_preferences' => "CREATE TABLE IF NOT EXISTS user_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            preference_key VARCHAR(50) NOT NULL,
            preference_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_preference (username, preference_key),
            INDEX idx_username (username)
        )",

        'room_admins' => "CREATE TABLE IF NOT EXISTS room_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            roomname VARCHAR(50) NOT NULL,
            username VARCHAR(50) NOT NULL,
            granted_by VARCHAR(50) NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_room_admin (roomname, username),
            INDEX idx_roomname (roomname),
            INDEX idx_username (username)
        )",

        'join_requests' => "CREATE TABLE IF NOT EXISTS join_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            roomname VARCHAR(50) NOT NULL,
            username VARCHAR(50) NOT NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
            UNIQUE KEY unique_request (roomname, username),
            INDEX idx_roomname (roomname),
            INDEX idx_username (username),
            INDEX idx_status (status)
        )"
    ];

    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    foreach ($tables as $table => $sql) {
        try {
            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Error creating table $table: " . mysqli_error($conn));
            }
            $successCount++;
            echo "✓ Table '$table' created successfully<br>";
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = $e->getMessage();
            echo "✗ Error creating table '$table': " . $e->getMessage() . "<br>";
        }
    }

    // Check for missing columns in existing tables and add them if needed
    checkAndAddMissingColumns($conn);

    echo "<br><strong>Summary:</strong><br>";
    echo "✓ Successfully created: $successCount tables<br>";
    if ($errorCount > 0) {
        echo "✗ Errors: $errorCount<br>";
        echo "<details><summary>Show Errors</summary>";
        foreach ($errors as $error) {
            echo "<p style='color: red;'>$error</p>";
        }
        echo "</details>";
    }

    echo "<br><strong>Database setup completed!</strong><br>";
    echo "<a href='index.php'>Go to Chat Application</a>";

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

function checkAndAddMissingColumns($conn) {
    echo "<br><strong>Checking for missing columns...</strong><br>";

    // Check if users table has profile_photo column
    $result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'profile_photo'");
    if (mysqli_num_rows($result) == 0) {
        mysqli_query($conn, "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL");
        echo "✓ Added profile_photo column to users table<br>";
    }

    // Check if rooms table has creator column
    $result = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'creator'");
    if (mysqli_num_rows($result) == 0) {
        mysqli_query($conn, "ALTER TABLE rooms ADD COLUMN creator VARCHAR(50) NOT NULL DEFAULT ''");
        echo "✓ Added creator column to rooms table<br>";
    }

    // Check if rooms table has display_photo column
    $result = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'display_photo'");
    if (mysqli_num_rows($result) == 0) {
        mysqli_query($conn, "ALTER TABLE rooms ADD COLUMN display_photo VARCHAR(255) DEFAULT NULL");
        echo "✓ Added display_photo column to rooms table<br>";
    }

    // Check if rooms table has description column
    $result = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'description'");
    if (mysqli_num_rows($result) == 0) {
        mysqli_query($conn, "ALTER TABLE rooms ADD COLUMN description TEXT");
        echo "✓ Added description column to rooms table<br>";
    }
}
?>