<?php
include("connection.php");
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Room Issues</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; padding: 10px; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .btn { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Debug Room Issues</h1>
        <p>This script helps diagnose and fix room-related issues.</p>";

$currentUser = $_SESSION['username'] ?? 'Not logged in';
echo "<div class='info'>Current User: <strong>$currentUser</strong></div>";

// Check database tables
echo "<h2>Database Tables Status</h2>";
$tables = ['rooms', 'files', 'user_preferences'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $exists = mysqli_num_rows($result) > 0;
    echo "<div class='" . ($exists ? 'success' : 'error') . "'>";
    echo "Table '$table': " . ($exists ? '✅ EXISTS' : '❌ MISSING');
    echo "</div>";
}

// Check rooms table structure
echo "<h2>Rooms Table Structure</h2>";
$result = mysqli_query($conn, "DESCRIBE rooms");
if ($result) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ Cannot read rooms table structure</div>";
}

// Check rooms data
echo "<h2>Rooms Data</h2>";
$result = mysqli_query($conn, "SELECT * FROM rooms");
if ($result && mysqli_num_rows($result) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Room Name</th><th>Creator</th><th>Display Photo</th><th>Created</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        $creator = $row['creator'] ?? 'NULL';
        $photo = $row['display_photo'] ?? 'NULL';
        $isCreator = ($creator === $currentUser) ? ' (YOU)' : '';
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['roomname']}</td>";
        echo "<td>$creator$isCreator</td>";
        echo "<td>$photo</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning'>⚠️ No rooms found in database</div>";
}

// Check files data
echo "<h2>Files Data (Last 10)</h2>";
$result = mysqli_query($conn, "SELECT * FROM files ORDER BY uploaded_at DESC LIMIT 10");
if ($result && mysqli_num_rows($result) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Room</th><th>Username</th><th>Filename</th><th>Size</th><th>Uploaded</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        $size_kb = round($row['filesize'] / 1024, 1);
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['roomname']}</td>";
        echo "<td>{$row['username']}</td>";
        echo "<td>{$row['filename']}</td>";
        echo "<td>{$size_kb} KB</td>";
        echo "<td>{$row['uploaded_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='info'>ℹ️ No files found in database</div>";
}

// Quick fixes
echo "<h2>Quick Fixes</h2>";
echo "<div style='margin: 20px 0;'>";

if (isset($_POST['add_columns'])) {
    echo "<div class='info'>Running column addition...</div>";
    $alter1 = mysqli_query($conn, "ALTER TABLE rooms ADD COLUMN creator VARCHAR(50) DEFAULT NULL");
    $alter2 = mysqli_query($conn, "ALTER TABLE rooms ADD COLUMN display_photo VARCHAR(255) DEFAULT NULL");

    if ($alter1 && $alter2) {
        echo "<div class='success'>✅ Columns added successfully! <a href='?'>Refresh</a></div>";
    } else {
        echo "<div class='error'>❌ Failed to add columns</div>";
    }
}

if (isset($_POST['assign_creators'])) {
    echo "<div class='info'>Assigning room creators...</div>";
    $query = "UPDATE rooms r
              SET creator = (
                  SELECT ru.username
                  FROM room_users ru
                  WHERE ru.roomname = r.roomname
                  ORDER BY ru.last_seen ASC
                  LIMIT 1
              )
              WHERE creator IS NULL OR creator = ''";

    if (mysqli_query($conn, $query)) {
        echo "<div class='success'>✅ Room creators assigned! <a href='?'>Refresh</a></div>";
    } else {
        echo "<div class='error'>❌ Failed to assign creators: " . mysqli_error($conn) . "</div>";
    }
}

echo "</div>";

// Action buttons
echo "<h2>Available Actions</h2>";
echo "<form method='POST' style='display: inline;'>";
echo "<button type='submit' name='add_columns' class='btn'>Add Missing Columns</button>";
echo "<button type='submit' name='assign_creators' class='btn'>Assign Room Creators</button>";
echo "</form>";

echo "<br><br>";
echo "<a href='add_room_columns.php' class='btn'>Run Full Column Setup</a>";
echo "<a href='update_room_creators.php' class='btn'>Run Full Creator Setup</a>";
echo "<a href='create_user_preferences.php' class='btn'>Setup User Preferences</a>";
echo "<a href='cleanup_files.php' class='btn'>Cleanup Files</a>";
echo "<a href='fix_file_rooms.php' class='btn'>Fix File Rooms</a>";
echo "<a href='test_file_filtering.php' class='btn'>Test File Filtering</a>";
echo "<a href='test_room_settings.php' class='btn'>Test Modal</a>";
echo "<a href='index.php' class='btn'>Back to Index</a>";

echo "    </div>
</body>
</html>";
?>