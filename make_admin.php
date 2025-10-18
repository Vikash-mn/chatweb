<?php
session_start();
include("connection.php");

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$roomname = $_POST['roomname'] ?? '';
$username = $_POST['username'] ?? '';

if (empty($roomname) || empty($username)) {
    echo json_encode(['error' => 'Room name and username required']);
    exit();
}

// Check if user is room creator
$query = "SELECT creator FROM rooms WHERE roomname = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $roomname);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['error' => 'Room not found']);
    exit();
}

$room = mysqli_fetch_assoc($result);
if ($room['creator'] !== $_SESSION['username']) {
    echo json_encode(['error' => 'Only room creator can make admins']);
    exit();
}

// Cannot make yourself admin (you're already the creator)
if ($username === $_SESSION['username']) {
    echo json_encode(['error' => 'You are already the room creator']);
    exit();
}

// Check if user is in the room
$query = "SELECT id FROM room_users WHERE roomname = ? AND username = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $roomname, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['error' => 'User is not in the room']);
    exit();
}

// For now, we'll just mark the user as admin in a separate table
// You might want to create a room_admins table or add an admin column to room_users
$query = "INSERT INTO room_admins (roomname, username, granted_by, granted_at)
          VALUES (?, ?, ?, NOW())
          ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by), granted_at = NOW()";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "sss", $roomname, $username, $_SESSION['username']);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'User made admin successfully']);
} else {
    echo json_encode(['error' => 'Failed to make user admin']);
}
?>