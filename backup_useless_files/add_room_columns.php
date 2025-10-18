<?php
include("connection.php");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Add Room Columns</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Add Missing Room Columns</h1>
        <p>This script will add the 'creator' and 'display_photo' columns to the rooms table.</p>";

$alter_queries = [
    "ALTER TABLE rooms ADD COLUMN creator VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE rooms ADD COLUMN display_photo VARCHAR(255) DEFAULT NULL"
];

$success_count = 0;
$error_count = 0;

foreach ($alter_queries as $query) {
    echo "<p>Executing: <code>$query</code></p>";

    if (mysqli_query($conn, $query)) {
        echo "<div class='success'>✅ Column added successfully!</div>";
        $success_count++;
    } else {
        // Check if column already exists (error code 1060)
        if (mysqli_errno($conn) == 1060) {
            echo "<div class='success'>ℹ️ Column already exists, skipping...</div>";
            $success_count++;
        } else {
            echo "<div class='error'>❌ Error: " . mysqli_error($conn) . "</div>";
            $error_count++;
        }
    }
}

if ($success_count == count($alter_queries)) {
    echo "<div class='success'>🎉 All columns added successfully! Room settings are now ready.</div>";
    echo "<p><a href='update_room_creators.php'>Next: Assign room creators</a></p>";
    echo "<p><a href='room.php?roomname=test'>Test: Enter a room</a></p>";
} elseif ($error_count > 0) {
    echo "<div class='error'>❌ Some errors occurred. Please check the database manually.</div>";
}

echo "    </div>
</body>
</html>";
?>