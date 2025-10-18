<?php
session_start();
include("connection.php");

header('Content-Type: application/json');
// Add proper input validation and error handling
$request_id = filter_var($_POST['request_id'] ?? '', FILTER_VALIDATE_INT);
if (!$request_id || $request_id <= 0) {
    echo json_encode(['error' => 'Invalid request ID']);
    exit();
}
if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$request_id = $_POST['request_id'] ?? '';

if (empty($request_id)) {
    echo json_encode(['error' => 'Request ID required']);
    exit();
}

// Check if table exists first
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'join_requests'");
if (mysqli_num_rows($table_check) == 0) {
    echo json_encode(['error' => 'Join requests table does not exist. Please run create_join_requests_table.php first.']);
    exit();
}

// Get the request details
$query = "SELECT jr.*, r.creator FROM join_requests jr
          JOIN rooms r ON jr.roomname = r.roomname
          WHERE jr.id = ? AND jr.status = 'pending'";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['error' => 'Request not found or already processed']);
    exit();
}

$request = mysqli_fetch_assoc($result);

// Check if current user is the room creator
if ($request['creator'] !== $_SESSION['username']) {
    echo json_encode(['error' => 'Only room creator can approve requests']);
    exit();
}

// Update request status to approved
$query = "UPDATE join_requests SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?
          WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "si", $_SESSION['username'], $request_id);

if (mysqli_stmt_execute($stmt)) {
    // Add user to the room
    $user_token = bin2hex(random_bytes(16));
    $query = "INSERT INTO room_users (roomname, username, user_token, joined_at) VALUES (?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sss", $request['roomname'], $request['username'], $user_token);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => true,
            'message' => 'Request approved and user added to room'
        ]);
    } else {
        echo json_encode(['error' => 'Failed to add user to room']);
    }
} else {
    echo json_encode(['error' => 'Failed to approve request']);
}
?>