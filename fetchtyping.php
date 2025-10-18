<?php
session_start();
include("connection.php");

header('Content-Type: application/json');

$roomname = $_GET['roomname'] ?? '';

if (empty($roomname)) {
    echo json_encode(['error' => 'Room name required']);
    exit();
}

// Get typing users in the room (users who updated typing status in the last 3 seconds)
$query = "SELECT username FROM typing_indicators
          WHERE roomname = ?
          AND is_typing = TRUE
          AND last_updated > DATE_SUB(NOW(), INTERVAL 3 SECOND)";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $roomname);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$typing_users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $typing_users[] = $row['username'];
}

if (empty($typing_users)) {
    echo json_encode('');
    exit();
}

// Create typing message
if (count($typing_users) === 1) {
    $message = $typing_users[0] . " is typing...";
} elseif (count($typing_users) === 2) {
    $message = $typing_users[0] . " and " . $typing_users[1] . " are typing...";
} else {
    $message = implode(", ", array_slice($typing_users, 0, -1)) . " and " . end($typing_users) . " are typing...";
}

echo json_encode($message);
?>