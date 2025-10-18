<?php
include("connection.php");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test File Filtering</title>
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
        .room-section { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .room-header { background: #f8f9fa; padding: 10px; margin: -15px -15px 15px -15px; border-radius: 5px 5px 0 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 Test File Filtering by Room</h1>
        <p>This script tests if files are properly filtered by room.</p>";

$roomname = $_GET['room'] ?? '';

// Get all rooms
$rooms_query = "SELECT roomname FROM rooms ORDER BY roomname";
$rooms_result = mysqli_query($conn, $rooms_query);
$rooms = [];

if ($rooms_result) {
    while ($row = mysqli_fetch_assoc($rooms_result)) {
        $rooms[] = $row['roomname'];
    }
}

if (empty($rooms)) {
    echo "<div class='warning'>No rooms found in database. Create some rooms first.</div>";
} else {
    echo "<div class='info'>Found " . count($rooms) . " rooms: " . implode(', ', $rooms) . "</div>";

    // Test file filtering for each room
    foreach ($rooms as $test_room) {
        echo "<div class='room-section'>";
        echo "<div class='room-header'><h3>Room: $test_room</h3></div>";

        // Simulate the fetchfiles.php query
        $query = "SELECT filename, filepath, filesize, uploaded_at FROM files WHERE roomname = ? ORDER BY uploaded_at DESC";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $test_room);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $file_count = mysqli_num_rows($result);

        if ($file_count > 0) {
            echo "<div class='success'>✅ Found $file_count files in room '$test_room':</div>";
            echo "<table>";
            echo "<tr><th>Filename</th><th>Size (KB)</th><th>Uploaded</th></tr>";

            while ($file = mysqli_fetch_assoc($result)) {
                $size_kb = round($file['filesize'] / 1024, 1);
                echo "<tr>";
                echo "<td>{$file['filename']}</td>";
                echo "<td>$size_kb</td>";
                echo "<td>{$file['uploaded_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='info'>ℹ️ No files found in room '$test_room'</div>";
        }

        echo "</div>";
    }

    // Test cross-room contamination
    echo "<h2>Cross-Room Contamination Test</h2>";
    $contamination_query = "SELECT roomname, COUNT(*) as file_count FROM files GROUP BY roomname ORDER BY roomname";
    $contamination_result = mysqli_query($conn, $contamination_query);

    if ($contamination_result && mysqli_num_rows($contamination_result) > 0) {
        echo "<div class='info'>File distribution by room:</div>";
        echo "<table>";
        echo "<tr><th>Room</th><th>File Count</th><th>Status</th></tr>";

        while ($row = mysqli_fetch_assoc($contamination_result)) {
            $status = in_array($row['roomname'], $rooms) ? '✅ Room exists' : '❌ Room deleted';
            echo "<tr>";
            echo "<td>{$row['roomname']}</td>";
            echo "<td>{$row['file_count']}</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>No files found in any room.</div>";
    }
}

echo "<h2>Test Actions</h2>";
echo "<div style='margin: 20px 0;'>";

if (!empty($rooms)) {
    echo "<p><strong>Test file upload in different rooms:</strong></p>";
    foreach ($rooms as $room) {
        echo "<a href='room.php?roomname=$room' class='btn' target='_blank'>Test Room: $room</a>";
    }
    echo "<br><br>";
}

echo "</div>";

echo "<a href='fix_file_rooms.php' class='btn'>Fix File Issues</a>";
echo "<a href='debug_room_issues.php' class='btn'>Debug Room Issues</a>";
echo "<a href='index.php' class='btn'>Back to Index</a>";

echo "    </div>
</body>
</html>";
?>