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

// Check if join_requests table exists first
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'join_requests'");
if (mysqli_num_rows($table_check) == 0) {
    echo json_encode(['error' => 'Join requests table does not exist. Please run create_join_requests_table.php first.']);
    exit();
}

// Check if user is room creator or admin
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
    echo json_encode(['error' => 'Only room creator can send invitations']);
    exit();
}

// Check if user exists
$query = "SELECT id FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['error' => 'User not found']);
    exit();
}

// Check if user is already in the room
$query = "SELECT id FROM room_users WHERE roomname = ? AND username = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $roomname, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    echo json_encode(['error' => 'User is already in the room']);
    exit();
}

// Check if there's already a pending request for this user
$query = "SELECT id FROM join_requests WHERE roomname = ? AND username = ? AND status = 'pending'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $roomname, $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    echo json_encode(['error' => 'Invitation already sent to this user']);
    exit();
}

// Create a join request instead of directly adding the user
$query = "INSERT INTO join_requests (roomname, username, requested_by, status, requested_at)
          VALUES (?, ?, ?, 'pending', NOW())";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "sss", $roomname, $username, $_SESSION['username']);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Invitation sent successfully! User will need to accept the invitation.',
        'type' => 'request'
    ]);
} else {
    echo json_encode(['error' => 'Failed to send invitation']);
}
?>