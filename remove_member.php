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
    echo json_encode(['error' => 'Only room creator can remove members']);
    exit();
}

// Cannot remove yourself
if ($username === $_SESSION['username']) {
    echo json_encode(['error' => 'You cannot remove yourself from the room']);
    exit();
}

// Remove user from room
$query = "DELETE FROM room_users WHERE roomname = ? AND username = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $roomname, $username);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'User removed successfully']);
} else {
    echo json_encode(['error' => 'Failed to remove user']);
}
?>