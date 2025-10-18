<?php
include("connection.php");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create Join Requests Table</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🛠️ Create Join Requests Table</h1>
        <p>This script will create the join_requests table needed for the notification system.</p>";

$query = "CREATE TABLE IF NOT EXISTS join_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    roomname VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    requested_by VARCHAR(50) NOT NULL,
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by VARCHAR(50) NULL,
    UNIQUE KEY unique_request (roomname, username),
    KEY idx_roomname (roomname),
    KEY idx_username (username),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $query)) {
    echo "<div class='success'>✅ Join requests table created successfully!</div>";
    echo "<p>The notification system is now ready to use.</p>";
    echo "<p><a href='room.php?roomname=test'>Go to a test room</a> to try the notification system.</p>";
} else {
    echo "<div class='error'>❌ Error creating table: " . mysqli_error($conn) . "</div>";
    echo "<p>Please check your database connection and try again.</p>";
}

echo "    </div>
</body>
</html>";
?>