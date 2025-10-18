<?php
include("connection.php");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Update Room Creators</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Update Room Creators</h1>
        <p>This script will set creators for existing rooms that don't have one assigned.</p>
        <p><strong>Note:</strong> Since we don't have historical data about who created each room, this script will assign the first user who joined each room as the creator.</p>";

// Check if creator column exists
$columns = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'creator'");
if (mysqli_num_rows($columns) == 0) {
    echo "<div class='error'>❌ Creator column not found. Please run add_room_columns.php first.</div>";
    exit;
}

$query = "SELECT r.roomname, r.creator, ru.username as first_user
          FROM rooms r
          LEFT JOIN (
              SELECT roomname, MIN(last_seen) as first_join, username
              FROM room_users
              GROUP BY roomname
          ) ru ON r.roomname = ru.roomname
          ORDER BY r.roomname";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo "<div class='error'>❌ Error querying rooms: " . mysqli_error($conn) . "</div>";
    exit;
}

$rooms = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rooms[] = $row;
}

echo "<h2>Current Room Status</h2>";
echo "<table>";
echo "<tr><th>Room Name</th><th>Current Creator</th><th>Proposed Creator</th><th>Status</th></tr>";

$needsUpdate = false;
foreach ($rooms as $room) {
    $status = '';
    $proposed = '';

    if (empty($room['creator'])) {
        $status = 'Needs Update';
        $proposed = $room['first_user'] ?: 'No users found';
        $needsUpdate = true;
    } else {
        $status = 'OK';
        $proposed = '-';
    }

    echo "<tr>";
    echo "<td>" . htmlspecialchars($room['roomname']) . "</td>";
    echo "<td>" . htmlspecialchars($room['creator'] ?: 'Not set') . "</td>";
    echo "<td>" . htmlspecialchars($proposed) . "</td>";
    echo "<td>" . $status . "</td>";
    echo "</tr>";
}
echo "</table>";

if (!$needsUpdate) {
    echo "<div class='success'>✅ All rooms already have creators assigned!</div>";
} else {
    echo "<h2>Update Room Creators</h2>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='update_creators' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;'>Update Room Creators</button>";
    echo "</form>";

    if (isset($_POST['update_creators'])) {
        $updated = 0;
        $errors = 0;

        foreach ($rooms as $room) {
            if (empty($room['creator']) && !empty($room['first_user'])) {
                $update_query = "UPDATE rooms SET creator = ? WHERE roomname = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "ss", $room['first_user'], $room['roomname']);

                if (mysqli_stmt_execute($stmt)) {
                    $updated++;
                } else {
                    $errors++;
                }
            }
        }

        if ($updated > 0) {
            echo "<div class='success'>✅ Successfully updated $updated room(s) with creators!</div>";
        }

        if ($errors > 0) {
            echo "<div class='error'>❌ Failed to update $errors room(s).</div>";
        }

        echo "<p><a href='" . $_SERVER['PHP_SELF'] . "'>Refresh to see updated status</a></p>";
    }
}

echo "    </div>
</body>
</html>";
?>