<?php
include("connection.php");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create User Preferences Table</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🛠️ Create User Preferences Table</h1>
        <p>This script will create the user_preferences table needed for persistent settings.</p>";

$query = "CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    preference_key VARCHAR(50) NOT NULL,
    preference_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (username, preference_key)
)";

if (mysqli_query($conn, $query)) {
    echo "<div class='success'>✅ User preferences table created successfully!</div>";
    echo "<p>The table is now ready for storing user settings like theme preferences.</p>";
    echo "<p><a href='room.php?roomname=test'>Go to a test room</a> to try the persistent settings.</p>";
} else {
    echo "<div class='error'>❌ Error creating table: " . mysqli_error($conn) . "</div>";
    echo "<p>Please check your database connection and try again.</p>";
}

echo "    </div>
</body>
</html>";
?>