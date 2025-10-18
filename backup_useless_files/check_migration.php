<?php
include("connection.php");

echo "<h1>Galaxy Chat Database Migration Check</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; } .warning { color: orange; }</style>";

// Check users table columns
echo "<h2>Users Table Columns:</h2>";
$columns_query = "SHOW COLUMNS FROM users";
$columns_result = mysqli_query($conn, $columns_query);

$required_columns = ['profile_photo', 'last_seen', 'is_online'];
$existing_columns = [];

while ($column = mysqli_fetch_assoc($columns_result)) {
    $existing_columns[] = $column['Field'];
    echo "✓ " . $column['Field'] . "<br>";
}

$missing_columns = array_diff($required_columns, $existing_columns);
if (!empty($missing_columns)) {
    echo "<p class='error'>❌ Missing columns: " . implode(', ', $missing_columns) . "</p>";
    echo "<p>Please run the database migration SQL commands provided earlier.</p>";
} else {
    echo "<p class='success'>✅ All required columns exist!</p>";
}

// Check if new tables exist
echo "<h2>New Tables:</h2>";
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

echo "<br><a href='index.php'>← Back to Chat</a>";
?>