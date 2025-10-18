<?php
session_start();
include("connection.php");

// Test room settings functionality
$roomname = $_GET['roomname'] ?? 'testroom';
$username = $_SESSION['username'] ?? 'testuser';

// Simulate session for testing
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'testuser';
}

echo "<h1>Room Settings Test</h1>";
echo "<p>Testing room: <strong>$roomname</strong></p>";
echo "<p>Current user: <strong>$username</strong></p>";

// Check database structure
echo "<h2>Database Check</h2>";
$tables = mysqli_query($conn, "SHOW TABLES LIKE 'rooms'");
if ($tables && mysqli_num_rows($tables) > 0) {
    echo "<p style='color: green;'>✅ Rooms table exists</p>";

    // Check columns
    $columns = mysqli_query($conn, "SHOW COLUMNS FROM rooms");
    echo "<h3>Rooms Table Columns:</h3><ul>";
    while ($col = mysqli_fetch_assoc($columns)) {
        echo "<li>{$col['Field']} - {$col['Type']}</li>";
    }
    echo "</ul>";

    // Check if room exists
    $query = "SELECT * FROM rooms WHERE roomname = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $roomname);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $room = mysqli_fetch_assoc($result);
        echo "<h3>Room Data:</h3>";
        echo "<pre>" . print_r($room, true) . "</pre>";
    } else {
        echo "<p style='color: orange;'>⚠️ Room '$roomname' doesn't exist in database</p>";

        // Create test room
        echo "<h3>Creating Test Room...</h3>";
        $create_query = "INSERT INTO rooms (roomname, creator, password) VALUES (?, ?, '')";
        $create_stmt = mysqli_prepare($conn, $create_query);
        mysqli_stmt_bind_param($create_stmt, "ss", $roomname, $username);

        if (mysqli_stmt_execute($create_stmt)) {
            echo "<p style='color: green;'>✅ Test room created successfully</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create test room: " . mysqli_error($conn) . "</p>";
        }
    }
} else {
    echo "<p style='color: red;'>❌ Rooms table doesn't exist</p>";
}

// Test room settings permissions
echo "<h2>Permissions Test</h2>";
$query = "SELECT creator FROM rooms WHERE roomname = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $roomname);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    $room = mysqli_fetch_assoc($result);
    $isCreator = ($room['creator'] === $username);

    echo "<p>Room creator: <strong>{$room['creator']}</strong></p>";
    echo "<p>Current user: <strong>$username</strong></p>";
    echo "<p>Is creator: <strong>" . ($isCreator ? 'YES' : 'NO') . "</strong></p>";

    if ($isCreator) {
        echo "<p style='color: green;'>✅ User has permission to modify room settings</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ User does NOT have permission to modify room settings</p>";
    }
}

// Test modal HTML
echo "<h2>Modal Test</h2>";
echo "<button onclick='testModal()' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test Modal</button>";

echo "<div id='test-modal' style='display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;'>
    <div style='background: white; padding: 20px; border-radius: 10px; max-width: 500px; width: 90%;'>
        <h3>Test Modal</h3>
        <p>This is a test modal to verify modal functionality works.</p>
        <button onclick='closeTestModal()' style='padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;'>Close</button>
    </div>
</div>";

echo "<script>
function testModal() {
    document.getElementById('test-modal').style.display = 'flex';
    console.log('Modal opened');
}

function closeTestModal() {
    document.getElementById('test-modal').style.display = 'none';
    console.log('Modal closed');
}
</script>";

echo "<br><br><a href='room.php?roomname=$roomname'>Go to Room</a> | <a href='admin.php'>Go to Admin</a>";
?>