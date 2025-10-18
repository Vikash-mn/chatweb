<?php
session_start();
include("connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: welcome.php");
    exit();
}

// Get current user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user profile photo (with error handling for missing column)
$profile_photo = null;
$query = "SELECT profile_photo FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) > 0) {
            $user_data = mysqli_fetch_assoc($result);
            $profile_photo = $user_data['profile_photo'];
        }
    }
}


// Helper function to show alert and redirect
function showAlertAndRedirect($message) {
    echo "<script>alert('" . addslashes($message) . "'); window.location = 'index.php';</script>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_room'])) {
        $username = $_POST['username'];
        $roomname = $_POST['room'];
        $password = $_POST['password'];
        $repassword = $_POST['repassword'];

        // Validate inputs
        if (empty($username) || empty($roomname) || empty($password)) {
            showAlertAndRedirect("All fields are required.");
        }

        if ($password !== $repassword) {
            showAlertAndRedirect("Passwords do not match.");
        }

        if (strlen($roomname) < 2 || strlen($roomname) > 15) {
            showAlertAndRedirect("Room name must be between 2-15 characters.");
        }

        if (!ctype_alnum($roomname)) {
            showAlertAndRedirect("Room name can only contain letters and numbers.");
        }

        if (strlen($password) < 4) {
            showAlertAndRedirect("Password must be at least 4 characters.");
        }

        // Use prepared statements for all database operations
        $checkQuery = "SELECT roomname FROM rooms WHERE roomname = ?";
        $checkStmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "s", $roomname);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            showAlertAndRedirect("Room name already exists. Please choose a different name.");
        }
        mysqli_stmt_close($checkStmt);

        // Create room if it doesn't exist
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO rooms (roomname, password) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $roomname, $hashed_password);

        if (mysqli_stmt_execute($stmt)) {
            // Store user information
            $user_token = bin2hex(random_bytes(16));
            $query = "INSERT INTO room_users (roomname, username, user_token) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sss", $roomname, $username, $user_token);
            mysqli_stmt_execute($stmt);
            
            $_SESSION['username'] = $username;
            setcookie('user_token_'.$roomname, $user_token, time() + (30 * 24 * 60 * 60), '/');
            header("Location: room.php?roomname=" . urlencode($roomname));
            exit();
        } else {
            showAlertAndRedirect("Error creating room. Please try again.");
        }
    } elseif (isset($_POST['join_room'])) {
        $roomname = trim($_POST['room']);
        $password = $_POST['password'];

        // Basic validation
        if (empty($roomname) || empty($password)) {
            showAlertAndRedirect("Both room name and password are required.");
        }

        // Get room details
        $query = "SELECT password FROM rooms WHERE roomname = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $roomname);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 0) {
            showAlertAndRedirect("Room does not exist. Please check the room name.");
        }

        $room = mysqli_fetch_assoc($result);
        
        // Verify password
        if (!password_verify($password, $room['password'])) {
            showAlertAndRedirect("Incorrect password for this room.");
        }

        // Set user session and cookies
        $username = $_SESSION['username'] ?? 'Guest_'.bin2hex(random_bytes(4));
        $user_token = bin2hex(random_bytes(16));
        
        // Store user-room association
        $query = "INSERT INTO room_users (roomname, username, user_token) VALUES (?, ?, ?) 
                  ON DUPLICATE KEY UPDATE user_token = VALUES(user_token)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sss", $roomname, $username, $user_token);
        mysqli_stmt_execute($stmt);

        // Set session and cookie
        $_SESSION['username'] = $username;
        setcookie('user_token_'.$roomname, $user_token, time() + (86400 * 30), "/"); // 30 days
        
        // Redirect to room
        header("Location: room.php?roomname=".urlencode($roomname));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galaxy Chat Room System</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* Modern reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Elegant galaxy background with subtle animation */
body {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: 
        radial-gradient(ellipse at bottom, #1B2735 0%, #090A0F 100%),
        linear-gradient(to bottom, rgba(255,255,255,0.1) 0%, transparent 100%);
    background-blend-mode: screen;
    color: #ffffff;
    font-family: 'Montserrat', 'Arial', sans-serif;
    overflow: hidden;
    position: relative;
}

/* Animated stars background */
body::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='1' fill='white'/%3E%3C/svg%3E") repeat;
    background-size: 2px 2px;
    opacity: 0.5;
    animation: twinkle 10s infinite alternate;
}

@keyframes twinkle {
    0% { opacity: 0.3; }
    100% { opacity: 0.8; }
}

/* Luxurious chat container */
.chat-container {
    position: relative;
    width: 90%;
    max-width: 500px;
    background: rgba(13, 13, 39, 0.85);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 
        0 10px 30px rgba(0, 0, 0, 0.5),
        0 0 0 1px rgba(255, 255, 255, 0.05),
        0 0 40px rgba(0, 212, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    z-index: 1;
    overflow: hidden;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

/* Elegant heading */
.chat-container h2 {
    margin-bottom: 2rem;
    font-size: 2rem;
    font-weight: 300;
    text-align: center;
    color: #ffffff;
    position: relative;
    letter-spacing: 1px;
}

.chat-container h2::after {
    content: "";
    display: block;
    width: 60px;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(0, 212, 255, 0.8), transparent);
    margin: 0.8rem auto 0;
}

/* Sophisticated toggle buttons */
.toggle-buttons {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 2rem;
}

.toggle-buttons button {
    flex: 1;
    padding: 0.8rem;
    background: rgba(0, 212, 255, 0.1);
    border: 1px solid rgba(0, 212, 255, 0.2);
    border-radius: 8px;
    color: rgba(0, 212, 255, 0.8);
    font-size: 0.85rem;
    font-weight: 500;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.toggle-buttons button:hover {
    background: rgba(0, 212, 255, 0.2);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 212, 255, 0.1);
}

/* Form containers */
.form-container {
    display: none;
    flex-direction: column;
    gap: 1.5rem;
}

.form-container.active {
    display: flex;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Enhanced input with floating label - Dark Theme */
.input-group {
    position: relative;
    margin-bottom: 1rem;
}

.form-container input {
    width: 100%;
    padding: 1.5rem 1rem 0.75rem;
    background: rgba(20, 20, 50, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    color: white;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    z-index: 1;
    position: relative;
}

.form-container input::placeholder {
    color: transparent;
}

.form-container label {
    position: absolute;
    top: 1rem;
    left: 1rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
    font-weight: 400;
    transition: all 0.3s ease;
    pointer-events: none;
    z-index: 2;
}

/* Floating label effect */
.form-container input:focus + label,
.form-container input:not(:placeholder-shown) + label {
    top: 0.5rem;
    left: 1rem;
    font-size: 0.75rem;
    color: rgba(0, 212, 255, 0.8);
}

/* Active state styling */
.form-container input:focus {
    outline: none;
    border-color: rgba(0, 212, 255, 0.5);
    background: rgba(30, 30, 70, 0.7);
    box-shadow: 0 0 0 2px rgba(0, 212, 255, 0.1);
    padding-top: 1.75rem;
    padding-bottom: 0.5rem;
}

/* Fix for autofill backgrounds */
.form-container input:-webkit-autofill,
.form-container input:-webkit-autofill:hover, 
.form-container input:-webkit-autofill:focus {
    -webkit-text-fill-color: white;
    -webkit-box-shadow: 0 0 0px 1000px rgba(20, 20, 50, 0.5) inset;
    transition: background-color 5000s ease-in-out 0s;
}

/* Elegant submit button */
.form-container button[type="submit"] {
    padding: 1rem;
    background: linear-gradient(135deg, rgba(0, 212, 255, 0.8), rgba(9, 9, 121, 0.8));
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 1rem;
    font-weight: 500;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 0.5rem;
    position: relative;
    overflow: hidden;
}

.form-container button[type="submit"]::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: 0.5s;
}

.form-container button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 212, 255, 0.2);
}

.form-container button[type="submit"]:hover::before {
    left: 100%;
}

/* ===== COMPREHENSIVE RESPONSIVE DESIGN ===== */

/* ===== MOBILE PHONES (320px - 480px) ===== */
@media (max-width: 480px) {
    body {
        padding: 10px;
    }

    .chat-container {
        padding: 1.2rem;
        border-radius: 12px;
        margin: 0;
        max-width: 100%;
    }

    .chat-container h2 {
        font-size: 1.3rem;
        margin-bottom: 1.2rem;
        text-align: center;
    }

    .toggle-buttons {
        flex-direction: column;
        gap: 0.4rem;
        margin-bottom: 1.5rem;
    }

    .toggle-buttons button {
        padding: 0.7rem;
        font-size: 0.8rem;
        border-radius: 6px;
    }

    .form-container {
        gap: 1.2rem;
    }

    .input-group {
        margin-bottom: 0.8rem;
    }

    .form-container input {
        padding: 1.2rem 0.8rem 0.6rem;
        font-size: 0.9rem;
    }

    .form-container label {
        top: 0.8rem;
        left: 0.8rem;
        font-size: 0.85rem;
    }

    .form-container button[type="submit"] {
        padding: 0.9rem;
        font-size: 0.9rem;
        margin-top: 0.3rem;
    }

    .user-info {
        top: 10px;
        right: 10px;
        padding: 0.6rem 1rem;
        border-radius: 40px;
    }

    .user-avatar {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
    }

    .user-name {
        font-size: 0.8rem;
        margin-left: 8px;
    }

    .logout-btn {
        padding: 0.4rem 0.8rem;
        font-size: 0.75rem;
    }
}

/* ===== SMALL TABLETS (481px - 768px) ===== */
@media (min-width: 481px) and (max-width: 768px) {
    .chat-container {
        padding: 1.5rem;
        max-width: 450px;
    }

    .chat-container h2 {
        font-size: 1.6rem;
        margin-bottom: 1.5rem;
    }

    .toggle-buttons {
        gap: 0.6rem;
        margin-bottom: 1.8rem;
    }

    .toggle-buttons button {
        padding: 0.8rem;
        font-size: 0.85rem;
    }

    .form-container input {
        padding: 1.3rem 0.9rem 0.7rem;
        font-size: 0.95rem;
    }

    .form-container label {
        top: 0.9rem;
        left: 0.9rem;
        font-size: 0.9rem;
    }

    .form-container button[type="submit"] {
        padding: 1rem;
        font-size: 0.95rem;
    }

    .user-info {
        top: 15px;
        right: 15px;
        padding: 0.7rem 1.1rem;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
        font-size: 0.9rem;
    }

    .user-name {
        font-size: 0.85rem;
    }
}

/* ===== TABLETS & SMALL LAPTOPS (769px - 1024px) ===== */
@media (min-width: 769px) and (max-width: 1024px) {
    .chat-container {
        padding: 2rem;
        max-width: 500px;
    }

    .chat-container h2 {
        font-size: 1.8rem;
        margin-bottom: 2rem;
    }

    .toggle-buttons {
        gap: 0.7rem;
        margin-bottom: 2rem;
    }

    .toggle-buttons button {
        padding: 0.9rem;
        font-size: 0.9rem;
    }

    .form-container input {
        padding: 1.4rem 1rem 0.8rem;
        font-size: 1rem;
    }

    .form-container label {
        top: 1rem;
        left: 1rem;
        font-size: 0.95rem;
    }

    .form-container button[type="submit"] {
        padding: 1.1rem;
        font-size: 1rem;
    }

    .user-info {
        top: 20px;
        right: 20px;
        padding: 0.8rem 1.2rem;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }

    .user-name {
        font-size: 0.9rem;
    }
}

/* ===== DESKTOPS (1025px - 1440px) ===== */
@media (min-width: 1025px) and (max-width: 1440px) {
    .chat-container {
        padding: 2.5rem;
        max-width: 550px;
    }

    .chat-container h2 {
        font-size: 2rem;
        margin-bottom: 2.2rem;
    }

    .toggle-buttons {
        gap: 0.8rem;
        margin-bottom: 2.2rem;
    }

    .toggle-buttons button {
        padding: 1rem;
        font-size: 0.95rem;
    }

    .form-container input {
        padding: 1.5rem 1.1rem 0.9rem;
        font-size: 1.05rem;
    }

    .form-container label {
        top: 1.1rem;
        left: 1.1rem;
        font-size: 1rem;
    }

    .form-container button[type="submit"] {
        padding: 1.2rem;
        font-size: 1.05rem;
    }

    .user-info {
        top: 20px;
        right: 20px;
        padding: 0.8rem 1.2rem;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        font-size: 1rem;
    }

    .user-name {
        font-size: 0.9rem;
    }
}

/* ===== LARGE SCREENS (1441px+) ===== */
@media (min-width: 1441px) {
    body {
        background-size: cover;
        background-attachment: fixed;
    }

    .chat-container {
        padding: 3rem;
        max-width: 600px;
        backdrop-filter: blur(15px);
    }

    .chat-container h2 {
        font-size: 2.2rem;
        margin-bottom: 2.5rem;
    }

    .toggle-buttons {
        gap: 1rem;
        margin-bottom: 2.5rem;
    }

    .toggle-buttons button {
        padding: 1.1rem;
        font-size: 1rem;
    }

    .form-container input {
        padding: 1.6rem 1.2rem 1rem;
        font-size: 1.1rem;
    }

    .form-container label {
        top: 1.2rem;
        left: 1.2rem;
        font-size: 1.05rem;
    }

    .form-container button[type="submit"] {
        padding: 1.3rem;
        font-size: 1.1rem;
    }

    .user-info {
        top: 25px;
        right: 25px;
        padding: 0.9rem 1.3rem;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
    }

    .user-name {
        font-size: 0.95rem;
    }
}

/* ===== ORIENTATION CHANGES ===== */
@media (max-height: 600px) and (orientation: landscape) {
    /* Short landscape screens */
    .chat-container {
        padding: 1rem;
        margin: 5px auto;
    }

    .chat-container h2 {
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }

    .toggle-buttons {
        gap: 0.3rem;
        margin-bottom: 1.2rem;
    }

    .toggle-buttons button {
        padding: 0.6rem;
        font-size: 0.75rem;
    }

    .form-container {
        gap: 0.8rem;
    }

    .form-container input {
        padding: 1rem 0.7rem 0.5rem;
        font-size: 0.85rem;
    }

    .form-container label {
        top: 0.7rem;
        left: 0.7rem;
        font-size: 0.8rem;
    }

    .form-container button[type="submit"] {
        padding: 0.8rem;
        font-size: 0.85rem;
    }

    .user-info {
        top: 8px;
        right: 8px;
        padding: 0.5rem 0.8rem;
    }

    .user-avatar {
        width: 24px;
        height: 24px;
        font-size: 0.7rem;
    }

    .user-name {
        font-size: 0.7rem;
    }
}

/* ===== HIGH DPI SCREENS ===== */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .user-avatar,
    .toggle-buttons button {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }

    .chat-container h2,
    .form-container input,
    .user-name {
        font-smoothing: antialiased;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
}
/* User Info Styles */
.user-info {
  position: absolute;
  top: 20px;
  right: 20px;
  display: flex;
  align-items: center;
  gap: 1rem;
  background: rgba(13, 13, 39, 0.7);
  padding: 0.8rem 1.2rem;
  border-radius: 50px;
  backdrop-filter: blur(5px);
  border: 1px solid var(--border-light);
  z-index: 10;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--galaxy-blue), var(--galaxy-purple));
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  text-transform: uppercase;
  overflow: hidden;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-avatar-link {
  text-decoration: none;
  display: inline-block;
  margin-right: 0.5rem;
}

.user-name {
  color: var(--text-light);
  font-size: 0.9rem;
}

.logout-btn {
  background: rgba(255, 255, 255, 0.1);
  color: var(--text-light);
  border: none;
  border-radius: 50px;
  padding: 0.5rem 1rem;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.3s ease;
}

.logout-btn:hover {
  background: rgba(255, 255, 255, 0.2);
  color: var(--galaxy-blue);
}

.profile-btn {
  background: rgba(0, 212, 255, 0.1);
  color: rgba(0, 212, 255, 0.8);
  border: 1px solid rgba(0, 212, 255, 0.2);
  border-radius: 20px;
  padding: 0.4rem 0.8rem;
  text-decoration: none;
  font-size: 0.8rem;
  margin-right: 0.5rem;
  transition: all 0.3s ease;
}

.profile-btn:hover {
  background: rgba(0, 212, 255, 0.2);
  color: white;
}
.error-message {
    color: #ff6b6b;
    padding: 10px;
    margin-top: 15px;
    background: rgba(255, 0, 0, 0.1);
    border-radius: 5px;
    border-left: 3px solid #ff6b6b;
}
</style>
</head>
<body>
    <div class="user-info">
      <a href="profile.php" class="user-avatar-link">
        <div class="user-avatar">
          <?php if ($profile_photo && file_exists($profile_photo)): ?>
            <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile">
          <?php else: ?>
            <?php echo substr($_SESSION['username'], 0, 1); ?>
          <?php endif; ?>
        </div>
      </a>
      <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
      <a href="profile.php" class="profile-btn">Profile</a>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    
        <div class="chat-container">
        <h2>Galaxy Chat Rooms</h2>
        <div class="toggle-buttons">
            <button onclick="showForm('create')">Create Room</button>
            <button onclick="showForm('join')">Join Room</button>
        </div>
        
        <form id="createForm" class="form-container" method="POST" action="index.php">
            <input type="hidden" name="create_room" value="1">
            <div class="input-group">
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($username); ?>" 
                       readonly>
                <label for="username">Your Username</label>
            </div>
            <div class="input-group">
                <input type="text" id="room" name="room" placeholder="Room Name" required>
                <label for="room">Room Name</label>
            </div>
            <div class="input-group">
                <input type="password" id="password" name="password" placeholder="Password" required>
                <label for="password">Password</label>
            </div>
            <div class="input-group">
                <input type="password" id="repassword" name="repassword" placeholder="Confirm Password" required>
                <label for="repassword">Confirm Password</label>
            </div>
            <button type="submit">Create Room</button>
        </form>
        
        <form id="joinForm" class="form-container" method="POST" action="index.php">
    <input type="hidden" name="join_room" value="1">
    <div class="input-group">
        <input type="text" id="join_room" name="room" placeholder="Room Name" required>
        <label for="join_room">Room Name</label>
    </div>
    <div class="input-group">
        <input type="password" id="join_password" name="password" placeholder="Password" required>
        <label for="join_password">Password</label>
    </div>
    <button type="submit">Join Room</button>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
            <?php 
            $errors = [
                'room_not_found' => 'Room does not exist',
                'access_denied' => 'Please join the room first',
                'invalid_token' => 'Session expired, please rejoin'
            ];
            echo $errors[$_GET['error']] ?? 'Error joining room';
            ?>
        </div>
    <?php endif; ?>
</form>
        
    </div>

    <script>
        function showForm(formType) {
            document.querySelectorAll('.form-container').forEach(function(form) {
                form.classList.remove('active');
            });
            document.getElementById(formType + 'Form').classList.add('active');
        }

        // Initialize floating labels
        document.querySelectorAll('.form-container input').forEach(input => {
            // Set initial state
            if(input.value) {
                input.nextElementSibling.style.top = '0.5rem';
                input.nextElementSibling.style.fontSize = '0.75rem';
                input.nextElementSibling.style.color = 'rgba(0, 212, 255, 0.8)';
                input.style.paddingTop = '1.75rem';
                input.style.paddingBottom = '0.5rem';
            }

            // Handle focus
            input.addEventListener('focus', function() {
                this.nextElementSibling.style.top = '0.5rem';
                this.nextElementSibling.style.fontSize = '0.75rem';
                this.nextElementSibling.style.color = 'rgba(0, 212, 255, 0.8)';
                this.style.paddingTop = '1.75rem';
                this.style.paddingBottom = '0.5rem';
            });

            // Handle blur
            input.addEventListener('blur', function() {
                if(!this.value) {
                    this.nextElementSibling.style.top = '1rem';
                    this.nextElementSibling.style.fontSize = '0.95rem';
                    this.nextElementSibling.style.color = 'rgba(255, 255, 255, 0.6)';
                    this.style.paddingTop = '1.5rem';
                    this.style.paddingBottom = '0.75rem';
                }
            });

            // Handle input changes
            input.addEventListener('input', function() {
                if(this.value) {
                    this.nextElementSibling.style.color = 'rgba(0, 212, 255, 0.8)';
                } else {
                    this.nextElementSibling.style.color = 'rgba(255, 255, 255, 0.6)';
                }
            });
        });

        // Activate the first form by default
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('createForm').classList.add('active');
        });
    </script>
</body>
</html>