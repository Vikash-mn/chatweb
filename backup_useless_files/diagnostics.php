<?php
include("connection.php");
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Galaxy Chat Diagnostics</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        h2 { margin-top: 0; color: #333; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Galaxy Chat System Diagnostics</h1>
        <p>This tool will check if all systems are working properly.</p>";

echo "<div class='section info'>
    <h2>📊 Database Status</h2>";

// Check database connection
if ($conn) {
    echo "<p class='success'>✅ Database connection: OK</p>";
} else {
    echo "<p class='error'>❌ Database connection: FAILED</p>";
    echo "<p>Please check your database credentials in connection.php</p>";
}

// Check users table structure
echo "<h3>Users Table Columns:</h3>";
$columns_query = "SHOW COLUMNS FROM users";
$columns_result = mysqli_query($conn, $columns_query);

$existing_columns = [];
if ($columns_result) {
    while ($column = mysqli_fetch_assoc($columns_result)) {
        $existing_columns[] = $column['Field'];
        echo "✓ " . $column['Field'] . "<br>";
    }
} else {
    echo "<p class='error'>❌ Cannot read users table</p>";
}

$required_columns = ['profile_photo', 'last_seen', 'is_online'];
$missing_columns = array_diff($required_columns, $existing_columns);

if (!empty($missing_columns)) {
    echo "<p class='error'>❌ Missing columns: " . implode(', ', $missing_columns) . "</p>";
    echo "<p class='warning'>⚠️ You need to run the database migration!</p>";
} else {
    echo "<p class='success'>✅ All required columns exist</p>";
}

// Check if new tables exist
echo "<h3>Additional Tables:</h3>";
$tables_to_check = ['user_preferences'];
foreach ($tables_to_check as $table) {
    $table_query = "SHOW TABLES LIKE '$table'";
    $table_result = mysqli_query($conn, $table_query);
    if (mysqli_num_rows($table_result) > 0) {
        echo "<p class='success'>✅ $table table exists</p>";
    } else {
        echo "<p class='error'>❌ $table table missing</p>";
    }
}

echo "</div>";

echo "<div class='section info'>
    <h2>🔧 Database Migration</h2>
    <p>If you're missing tables or columns, run these SQL commands in phpMyAdmin:</p>

    <div class='code'>
ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL;<br>
ALTER TABLE users ADD COLUMN last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP;<br>
ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE;<br><br>

CREATE TABLE IF NOT EXISTS user_preferences (<br>
&nbsp;&nbsp;&nbsp;&nbsp;id INT AUTO_INCREMENT PRIMARY KEY,<br>
&nbsp;&nbsp;&nbsp;&nbsp;username VARCHAR(50) NOT NULL,<br>
&nbsp;&nbsp;&nbsp;&nbsp;preference_key VARCHAR(50) NOT NULL,<br>
&nbsp;&nbsp;&nbsp;&nbsp;preference_value TEXT,<br>
&nbsp;&nbsp;&nbsp;&nbsp;updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,<br>
&nbsp;&nbsp;&nbsp;&nbsp;UNIQUE KEY (username, preference_key)<br>
);
    </div>

    <p><strong>Alternative:</strong> Run the migrate_db.php script</p>
    <button onclick=\"window.open('migrate_db.php', '_blank')\">Run Migration Script</button>
</div>";

echo "<div class='section info'>
    <h2>🔍 File System Check</h2>";

// Check if required PHP files exist
$required_files = [
    'admin.php',
    'fetchmessages.php',
    'updatetyping.php',
    'fetchtyping.php',
    'uploadfile.php',
    'fetchfiles.php',
    'updateonlinestatus.php',
    'fetchonlineusers.php',
    'save_preference.php',
    'load_preferences.php',
    'create_user_preferences.php',
    'update_room_settings.php',
    'update_room_creators.php',
    'add_room_columns.php',
    'debug_room_issues.php',
    'cleanup_files.php',
    'fix_file_rooms.php',
    'test_file_filtering.php',
    'test_room_settings.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p class='success'>✅ $file exists</p>";
    } else {
        echo "<p class='error'>❌ $file missing</p>";
    }
}

// Check uploads directory
if (file_exists('uploads')) {
    echo "<p class='success'>✅ uploads/ directory exists</p>";
    if (is_writable('uploads')) {
        echo "<p class='success'>✅ uploads/ directory is writable</p>";
    } else {
        echo "<p class='error'>❌ uploads/ directory is not writable</p>";
    }
} else {
    echo "<p class='error'>❌ uploads/ directory missing</p>";
}

echo "</div>";

echo "<div class='section info'>
    <h2>🚀 Quick Test</h2>
    <p>Test the systems:</p>
    <button onclick=\"window.open('index.php', '_blank')\">Open Chat</button>
    <button onclick=\"window.open('check_migration.php', '_blank')\">Check Migration</button>
    <button onclick=\"window.open('admin.php', '_blank')\">Admin Panel</button>
</div>";

echo "<div class='section success'>
    <h2>📞 Support</h2>
    <p>If issues persist after running the migration:</p>
    <ul>
        <li>Check browser console for JavaScript errors</li>
        <li>Verify PHP error logs</li>
        <li>Ensure all files are uploaded to the server</li>
        <li>Check file permissions (755 for directories, 644 for files)</li>
    </ul>
</div>";

echo "    </div>
</body>
</html>";
?>