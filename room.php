<?php
session_start();
include("connection.php");

// Check roomname parameter
if (!isset($_GET['roomname'])) {
    header("Location: index.php");
    exit();
}

$roomname = $_GET['roomname'];

// Verify room exists
$query = "SELECT 1 FROM rooms WHERE roomname = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $roomname);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) == 0) {
    header("Location: index.php?error=room_not_found");
    exit();
}

// Verify user access
$cookie_name = 'user_token_'.$roomname;
if (!isset($_COOKIE[$cookie_name])) {
    header("Location: index.php?error=access_denied");
    exit();
}

$user_token = $_COOKIE[$cookie_name];
$query = "SELECT username FROM room_users WHERE roomname = ? AND user_token = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $roomname, $user_token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: index.php?error=invalid_token");
    exit();
}

// Set username from database
$user = mysqli_fetch_assoc($result);
$_SESSION['username'] = $user['username'];


// Load user preferences from database (if table exists)
$userPreferences = [];
$tableExists = mysqli_query($conn, "SHOW TABLES LIKE 'user_preferences'");

if ($tableExists && mysqli_num_rows($tableExists) > 0) {
    $query = "SELECT preference_key, preference_value FROM user_preferences WHERE username = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $userPreferences[$row['preference_key']] = $row['preference_value'];
        }
    }
}

// Load room information (handle missing columns gracefully)
$roomInfo = ['creator' => null, 'display_photo' => null];
$isRoomCreator = false;

$tableExists = mysqli_query($conn, "SHOW TABLES LIKE 'rooms'");
if ($tableExists && mysqli_num_rows($tableExists) > 0) {
    // Check if creator column exists
    $columns = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'creator'");
    if (mysqli_num_rows($columns) > 0) {
        $query = "SELECT creator, display_photo FROM rooms WHERE roomname = ?";
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $roomname);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && mysqli_num_rows($result) > 0) {
                $roomInfo = mysqli_fetch_assoc($result);
                $isRoomCreator = ($roomInfo['creator'] === $_SESSION['username']);

                // Debug: If no creator is set, allow current user to be creator
                if (empty($roomInfo['creator'])) {
                    $isRoomCreator = true;
                    // Optionally update the database to set current user as creator
                    $update_query = "UPDATE rooms SET creator = ? WHERE roomname = ? AND (creator IS NULL OR creator = '')";
                    $update_stmt = mysqli_prepare($conn, $update_query);
                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, "ss", $_SESSION['username'], $roomname);
                        mysqli_stmt_execute($update_stmt);
                        $roomInfo['creator'] = $_SESSION['username'];
                    }
                }
            } else {
                // Room doesn't exist, allow settings for debugging
                $isRoomCreator = true;
            }
        }
    } else {
        // If creator column doesn't exist, make current user the creator for new rooms
        $isRoomCreator = true; // Allow settings for debugging
    }
} else {
    // If rooms table doesn't exist, allow settings for debugging
    $isRoomCreator = true;
}

// Set theme preference from database or session
$_SESSION['dark_mode'] = isset($userPreferences['dark_mode']) ? ($userPreferences['dark_mode'] === 'true') : ($_SESSION['dark_mode'] ?? false);

// Update online status when entering room
$query = "UPDATE users SET is_online = TRUE, last_seen = CURRENT_TIMESTAMP WHERE username = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
mysqli_stmt_execute($stmt);


// Handle new message submission with enhanced security
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }

    $msg = trim($_POST['message']);
    $username = $_SESSION['username'];

    // Validate message
    if (empty($msg)) {
        http_response_code(400);
        die('Message cannot be empty');
    }

    if (strlen($msg) > 1000) {
        http_response_code(400);
        die('Message too long');
    }

    // Basic content filtering (you can expand this)
    $filtered_msg = sanitizeInput($msg);

    $query = "INSERT INTO messages (username, msg, roomname) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sss", $username, $filtered_msg, $roomname);
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Failed to insert message: " . mysqli_error($conn));
            http_response_code(500);
            die('Failed to send message');
        }
    } else {
        error_log("Failed to prepare message statement: " . mysqli_error($conn));
        http_response_code(500);
        die('Database error');
    }
}

// Handle typing indicator with enhanced security
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['typing_status'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token validation failed');
    }

    $typing_status = $_POST['typing_status'];
    $typing = ($typing_status === 'true');

    // Validate typing status
    if (!is_bool($typing)) {
        http_response_code(400);
        die('Invalid typing status');
    }

    $query = "INSERT INTO typing_indicators (roomname, username, is_typing)
              VALUES (?, ?, ?)
              ON DUPLICATE KEY UPDATE is_typing = ?, last_updated = CURRENT_TIMESTAMP";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssii", $roomname, $_SESSION['username'], $typing, $typing);
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Failed to update typing status: " . mysqli_error($conn));
        }
    } else {
        error_log("Failed to prepare typing statement: " . mysqli_error($conn));
    }
    exit(); // Don't need to return anything for typing indicators
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Room - <?php echo htmlspecialchars($roomname); ?></title>
    <style>
/* ========== BASE STYLES ========== */
/* Reset default browser styles */
*,
*::before,
*::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Root variables for theme management */
:root {
    /* Light theme colors - improved contrast */
    --bg-color-light: #f8f9fa;
    --text-color-light: #212529;
    --message-left-bg-light: #ffffff;
    --message-right-bg-light: #007bff;
    --input-bg-light: #ffffff;
    --border-color-light: #dee2e6;
    --header-bg-light: #343a40;
    --shadow-light: 0 2px 10px rgba(0, 0, 0, 0.08);

    /* Dark theme colors - improved contrast */
    --bg-color-dark: #121212;
    --text-color-dark: #e9ecef;
    --message-left-bg-dark: #1e1e1e;
    --message-right-bg-dark: #0d6efd;
    --input-bg-dark: #2d2d2d;
    --border-color-dark: #444;
    --header-bg-dark: #1a1a1a;
    --shadow-dark: 0 2px 10px rgba(0, 0, 0, 0.3);
    
    /* Current theme variables (default to light) */
    --bg-color: var(--bg-color-light);
    --text-color: var(--text-color-light);
    --message-left-bg: var(--message-left-bg-light);
    --message-right-bg: var(--message-right-bg-light);
    --input-bg: var(--input-bg-light);
    --border-color: var(--border-color-light);
    --header-bg: var(--header-bg-light);
    --shadow: var(--shadow-light);
    
    /* New variables for consistent spacing */
    --spacing-xs: 4px;
    --spacing-sm: 8px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
    
    /* Border radius variables */
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 16px;
    --radius-xl: 24px;
    --radius-round: 50%;
    
    /* Animation timing */
    --transition-fast: 0.15s ease;
    --transition-normal: 0.3s ease;
    --transition-slow: 0.5s ease;
}


/* Dark mode override */
body.dark-mode {
    --bg-color: var(--bg-color-dark);
    --text-color: var(--text-color-dark);
    --message-left-bg: var(--message-left-bg-dark);
    --message-right-bg: var(--message-right-bg-dark);
    --input-bg: var(--input-bg-dark);
    --border-color: var(--border-color-dark);
    --header-bg: var(--header-bg-dark);
    --shadow: var(--shadow-dark);
}

/* ========== BODY & LAYOUT ========== */
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
    background-color: var(--bg-color);
    color: var(--text-color);
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    height: 100vh;
    overflow: hidden;
    transition: background-color var(--transition-normal), color var(--transition-normal);
    line-height: 1.5;
}


/* ========== IMPROVED HEADER STYLES ========== */
.header {
    padding: var(--spacing-sm) var(--spacing-md);
    background-color: var(--header-bg);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow);
    z-index: 100;
    position: relative;
    min-height: 60px;
    flex-wrap: wrap;
    gap: var(--spacing-sm);
}

.header-left {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    flex: 1;
    min-width: 0; /* Allows text truncation */
}

.header-right {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}
/* ========== HEADER ONLINE USERS ========== */
.header-online-users {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
    margin-left: var(--spacing-md);
}


.online-users-label {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.85);
    font-weight: 500;
    margin-right: var(--spacing-xs);
    white-space: nowrap;
}

.online-users-avatars {
    display: flex;
    align-items: center;
    margin-right: var(--spacing-xs);
}

.online-user-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--galaxy-blue), var(--galaxy-purple));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.8rem;
    border: 2px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
}

.online-user-avatar-small:hover {
    transform: scale(1.1);
    z-index: 10;
    border-color: var(--galaxy-blue);
    box-shadow: 0 4px 12px rgba(0, 212, 255, 0.3);
}

.online-user-avatar-small img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.online-users-count-header {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 8px;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
    animation: countPulse 2s infinite;
}

@keyframes countPulse {
    0%, 100% {
        box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
    }
    50% {
        box-shadow: 0 2px 12px rgba(76, 175, 80, 0.5);
    }
}

/* Online status tooltip */
.online-user-avatar-small::after {
    content: attr(data-username);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    pointer-events: none;
    z-index: 1000;
}

.online-user-avatar-small:hover::after {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(-5px);
}

/* ========== TYPING INDICATOR ========== */
.typing-indicator {
    padding: 6px 20px;
    font-size: 0.85rem;
    font-style: italic;
    color: #666;
    height: 24px;
    background-color: var(--bg-color);
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.dark-mode .typing-indicator {
    color: #aaa;
}

/* ========== CHAT CONTAINER ========== */
#chat-container {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px; /* Space between messages */
    scroll-behavior: smooth;
    background-color: var(--bg-color);
}

/* Custom scrollbar */
#chat-container::-webkit-scrollbar {
    width: 8px;
}

#chat-container::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
    border-radius: 4px;
}

#chat-container::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.2);
    border-radius: 4px;
}

.dark-mode #chat-container::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
}

/* ========== MESSAGE BUBBLES ========== */
.message {
    max-width: 75%;
    padding: 12px 16px;
    border-radius: 18px;
    position: relative;
    word-wrap: break-word;
    line-height: 1.4;
    animation: messageAppear 0.3s ease;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    transition: background-color 0.3s ease;
}

@keyframes messageAppear {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Left-aligned messages (other users) */
.message-left {
    align-self: flex-start;
    background-color: var(--message-left-bg);
    color: var(--text-color);
    border-bottom-left-radius: 4px; /* Flat edge on bottom left */
    margin-right: auto;
}

/* Right-aligned messages (current user) */
.message-right {
    align-self: flex-end;
    background-color: var(--message-right-bg);
    color: white;
    border-bottom-right-radius: 4px; /* Flat edge on bottom right */
    margin-left: auto;
}

/* Message username */
.message-username {
    font-weight: bold;
    font-size: 0.8rem;
    margin-bottom: 4px;
    display: block;
    color: inherit;
    opacity: 0.9;
}

/* Message text content */
.message-text {
    font-size: 1rem;
    white-space: pre-wrap; /* Preserve line breaks */
}

/* Message timestamp */
.message-time {
    font-size: 0.7rem;
    opacity: 0.7;
    margin-top: 4px;
    display: block;
    text-align: right;
}

/* No messages placeholder */
.no-messages {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
}

.dark-mode .no-messages {
    color: #aaa;
}

/* ========== MESSAGE INPUT AREA ========== */
.message-form {
    display: flex;
    padding: 12px;
    background-color: var(--input-bg);
    border-top: 1px solid var(--border-color);
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 5;
}

.message-input {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid var(--border-color);
    border-radius: 24px;
    font-size: 1rem;
    background-color: var(--input-bg);
    color: var(--text-color);
    outline: none;
    transition: all 0.3s ease;
}

.message-input:focus {
    border-color: var(--message-right-bg);
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
}

.dark-mode .message-input:focus {
    box-shadow: 0 0 0 2px rgba(0, 121, 107, 0.3);
}

/* Buttons */
.message-send,
.theme-toggle {
    padding: 12px 16px;
    border: none;
    border-radius: 24px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.message-send {
    background-color: var(--message-right-bg);
    color: white;
    min-width: 80px;
}

.message-send:hover {
    background-color: #45a049;
    transform: translateY(-1px);
}

.dark-mode .message-send:hover {
    background-color: #00695c;
}


.theme-toggle {
    background-color: rgba(255,255,255,0.1);
    color: white;
    padding: 8px 12px;
    font-size: 0.9rem;
}

/* Hide actual file input */
#file-upload {
    display: none;
}

/* ========== NOTIFICATION SYSTEM ========== */
.notification-container {
    position: relative;
    margin-right: 12px;
}

.notification-btn {
    background-color: rgba(255,255,255,0.1);
    color: white;
    padding: 8px 12px;
    font-size: 0.9rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.notification-btn:hover {
    background-color: rgba(255,255,255,0.2);
    transform: translateY(-1px);
}

.notification-btn.has-notifications {
    animation: notificationPulse 2s infinite;
}

@keyframes notificationPulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(255, 71, 87, 0);
    }
}

.notification-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4757;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
}

.notification-panel {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    max-height: 400px;
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    z-index: 3000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.notification-panel.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notification-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--header-bg);
    color: white;
    border-radius: 8px 8px 0 0;
}

.notification-header h4 {
    margin: 0;
    font-size: 1rem;
}

.close-notifications {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s ease;
}

.close-notifications:hover {
    background: rgba(255,255,255,0.1);
}

.notification-list {
    max-height: 300px;
    overflow-y: auto;
    padding: 8px;
}

.notification-item {
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    margin-bottom: 8px;
    background: var(--bg-color);
    transition: all 0.2s ease;
}

.notification-item:hover {
    background: rgba(0,212,255,0.05);
    border-color: rgba(0,212,255,0.3);
}

.notification-item-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.notification-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--galaxy-blue), var(--galaxy-purple));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.8rem;
}

.notification-info h5 {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-color);
}

.notification-time {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.6);
    margin: 0;
}

.notification-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.notification-action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.notification-action-btn.approve {
    background: #28a745;
    color: white;
}

.notification-action-btn.approve:hover {
    background: #218838;
    transform: translateY(-1px);
}

.notification-action-btn.deny {
    background: #dc3545;
    color: white;
}

.notification-action-btn.deny:hover {
    background: #c82333;
    transform: translateY(-1px);
}

.no-notifications {
    text-align: center;
    color: rgba(255,255,255,0.6);
    padding: 20px;
    font-style: italic;
}

/* ========== ROOM SETTINGS ========== */
.room-settings-btn {
    background-color: rgba(255,255,255,0.1);
    color: white;
    padding: 8px 12px;
    font-size: 0.9rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-right: 8px;
}

.room-settings-btn:hover {
    background-color: rgba(255,255,255,0.2);
    transform: translateY(-1px);
}

/* ========== ROOM SETTINGS MODAL ========== */
.room-settings-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 2000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(2px);
}

.room-settings-modal.show {
    opacity: 1;
    visibility: visible;
}

.room-settings-content {
    background: var(--bg-color);
    border-radius: 12px;
    padding: 0;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    transform: scale(0.9);
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
}

.room-settings-modal.show .room-settings-content {
    transform: scale(1);
}

/* Settings Tabs */
.settings-tabs {
    display: flex;
    background: var(--header-bg);
    border-radius: 12px 12px 0 0;
    overflow: hidden;
}

.settings-tab {
    flex: 1;
    padding: 12px 16px;
    background: rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.7);
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    font-weight: 500;
}

.settings-tab.active {
    background: var(--bg-color);
    color: var(--text-color);
    border-bottom: 2px solid var(--message-right-bg);
}

.settings-tab:hover {
    background: rgba(255,255,255,0.15);
    color: white;
}

/* Settings Content */
.settings-content {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.settings-section {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border-color);
}

.settings-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.settings-section h4 {
    margin: 0 0 16px 0;
    color: var(--text-color);
    font-size: 1.1rem;
    font-weight: 600;
}

.room-settings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.room-settings-header h3 {
    margin: 0;
    color: var(--text-color);
    font-size: 1.3rem;
}

.close-settings {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-color);
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s ease;
}

.close-settings:hover {
    background: rgba(255,255,255,0.1);
}

.settings-form-group {
    margin-bottom: 20px;
}

.settings-form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: var(--text-color);
}

.settings-input {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--input-bg);
    color: var(--text-color);
    font-size: 1rem;
    transition: border-color 0.2s ease;
}

.settings-input:focus {
    outline: none;
    border-color: var(--message-right-bg);
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
}

.dark-mode .settings-input:focus {
    box-shadow: 0 0 0 2px rgba(0, 121, 107, 0.3);
}

.settings-buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 24px;
}

.settings-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.settings-btn-primary {
    background: var(--message-right-bg);
    color: white;
}

.settings-btn-primary:hover {
    background: #45a049;
    transform: translateY(-1px);
}

.dark-mode .settings-btn-primary:hover {
    background: #00695c;
}

.settings-btn-secondary {
    background: rgba(255,255,255,0.1);
    color: var(--text-color);
}

.settings-btn-secondary:hover {
    background: rgba(255,255,255,0.2);
}

.current-photo {
    margin-top: 10px;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-color);
}

.current-photo img {
    max-width: 100px;
    max-height: 100px;
    border-radius: 6px;
    object-fit: cover;
}

/* Tab Content */
.settings-tab-content {
    display: none;
}

.settings-tab-content.active {
    display: block;
}

/* Member List */
.member-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 8px;
    background: var(--bg-color);
}

.member-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.member-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--galaxy-blue), var(--galaxy-purple));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.member-details h5 {
    margin: 0;
    color: var(--text-color);
    font-size: 0.9rem;
}

.member-role {
    color: rgba(255,255,255,0.6);
    font-size: 0.8rem;
    margin: 0;
}

.member-actions {
    display: flex;
    gap: 8px;
}

.member-action-btn {
    padding: 4px 8px;
    border: none;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.member-action-btn.remove {
    background: #ff4757;
    color: white;
}

.member-action-btn.admin {
    background: var(--message-right-bg);
    color: white;
}




/* ========== COMPREHENSIVE RESPONSIVE DESIGN ========== */

/* ===== MOBILE PHONES (320px - 480px) ===== */
@media (max-width: 480px) {
    .header {
        padding: 8px 12px;
        flex-wrap: wrap;
        min-height: 50px;
    }

    .header-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
        width: 100%;
    }

    .header-right {
        width: 100%;
        justify-content: space-between;
        margin-top: 8px;
    }

    .header h2 {
        font-size: 1rem;
        max-width: 70%;
        line-height: 1.2;
    }

    .header-online-users {
        margin-left: 0;
        order: 2;
        width: 100%;
        margin-top: 4px;
    }

    .online-users-label {
        display: none;
    }

    .online-users-avatars {
        gap: -4px;
        justify-content: flex-start;
    }

    .online-user-avatar-small {
        width: 24px;
        height: 24px;
        font-size: 0.6rem;
        border-width: 1px;
    }

    .online-users-count-header {
        width: 16px;
        height: 16px;
        font-size: 0.5rem;
        margin-left: 6px;
    }

    .theme-toggle {
        font-size: 0.75rem;
        padding: 6px 8px;
        min-width: auto;
    }

    /* Chat Container */
    #chat-container {
        padding: 10px;
        gap: 8px;
    }

    .message {
        max-width: 90%;
        padding: 8px 12px;
        border-radius: 12px;
        font-size: 0.9rem;
    }

    .message-left {
        margin-right: 8px;
    }

    .message-right {
        margin-left: 8px;
    }

    .message-username {
        font-size: 0.7rem;
    }

    .message-time {
        font-size: 0.6rem;
    }

    /* Message Form */
    .message-form {
        padding: 8px;
        gap: 6px;
    }

    .message-input {
        padding: 10px 12px;
        font-size: 0.9rem;
        border-radius: 20px;
    }

    .message-send {
        padding: 10px 12px;
        font-size: 0.85rem;
        min-width: 60px;
    }




}

/* ===== SMALL TABLETS (481px - 768px) ===== */
@media (min-width: 481px) and (max-width: 768px) {
    .header {
        padding: 10px 16px;
    }

    .header h2 {
        font-size: 1.1rem;
        max-width: 65%;
    }

    .header-online-users {
        margin-left: 15px;
    }

    .online-user-avatar-small {
        width: 28px;
        height: 28px;
        font-size: 0.7rem;
    }

    .online-users-count-header {
        width: 18px;
        height: 18px;
        font-size: 0.6rem;
    }

    .theme-toggle {
        font-size: 0.8rem;
        padding: 6px 10px;
    }

    #chat-container {
        padding: 12px;
        gap: 10px;
    }

    .message {
        max-width: 85%;
        padding: 10px 14px;
    }

    .message-form {
        padding: 10px;
    }

    .message-input {
        padding: 12px 14px;
        font-size: 0.95rem;
    }

    .message-send {
        padding: 12px 14px;
        font-size: 0.9rem;
    }



}

/* ===== TABLETS & SMALL LAPTOPS (769px - 1024px) ===== */
@media (min-width: 769px) and (max-width: 1024px) {
    .header {
        padding: 12px 18px;
    }

    .header h2 {
        font-size: 1.2rem;
        max-width: 70%;
    }

    .header-online-users {
        margin-left: 20px;
    }

    .online-user-avatar-small {
        width: 30px;
        height: 30px;
        font-size: 0.75rem;
    }

    .online-users-count-header {
        width: 20px;
        height: 20px;
        font-size: 0.65rem;
    }

    .theme-toggle {
        font-size: 0.85rem;
        padding: 8px 12px;
    }

    #chat-container {
        padding: 15px;
        gap: 12px;
    }

    .message {
        max-width: 80%;
        padding: 12px 16px;
    }

    .message-form {
        padding: 12px;
    }

    .message-input {
        padding: 12px 16px;
        font-size: 1rem;
    }

    .message-send {
        padding: 12px 16px;
        font-size: 0.95rem;
    }



}

/* ===== DESKTOPS (1025px - 1440px) ===== */
@media (min-width: 1025px) and (max-width: 1440px) {
    .header {
        padding: 14px 22px;
    }

    .header h2 {
        font-size: 1.3rem;
        max-width: 75%;
    }

    .header-online-users {
        margin-left: 25px;
    }

    .online-user-avatar-small {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }

    .online-users-count-header {
        width: 22px;
        height: 22px;
        font-size: 0.7rem;
    }

    .theme-toggle {
        font-size: 0.9rem;
        padding: 8px 14px;
    }

    #chat-container {
        padding: 18px;
        gap: 14px;
    }

    .message {
        max-width: 75%;
        padding: 14px 18px;
    }

    .message-form {
        padding: 14px;
    }

    .message-input {
        padding: 14px 18px;
        font-size: 1.05rem;
    }

    .message-send {
        padding: 14px 18px;
        font-size: 1rem;
    }



}

/* ===== LARGE SCREENS (1441px+) ===== */
@media (min-width: 1441px) {
    .header {
        padding: 16px 26px;
        max-width: 1600px;
        margin: 0 auto;
    }

    .header h2 {
        font-size: 1.4rem;
        max-width: 80%;
    }

    .header-online-users {
        margin-left: 30px;
    }

    .online-user-avatar-small {
        width: 36px;
        height: 36px;
        font-size: 0.85rem;
    }

    .online-users-count-header {
        width: 24px;
        height: 24px;
        font-size: 0.75rem;
    }

    .theme-toggle {
        font-size: 0.95rem;
        padding: 10px 16px;
    }

    #chat-container {
        padding: 20px;
        gap: 16px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .message {
        max-width: 70%;
        padding: 16px 20px;
        font-size: 1.1rem;
    }

    .message-form {
        padding: 16px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .message-input {
        padding: 16px 20px;
        font-size: 1.1rem;
    }

    .message-send {
        padding: 16px 20px;
        font-size: 1.05rem;
    }



}

/* ===== ORIENTATION CHANGES ===== */
@media (max-height: 600px) and (orientation: landscape) {
    /* Short landscape screens (like mobile landscape) */
    .header {
        padding: 6px 12px;
        min-height: 40px;
    }

    .header h2 {
        font-size: 0.9rem;
    }

    .online-user-avatar-small {
        width: 20px;
        height: 20px;
        font-size: 0.5rem;
    }

    .online-users-count-header {
        width: 14px;
        height: 14px;
        font-size: 0.45rem;
    }

    .theme-toggle {
        font-size: 0.7rem;
        padding: 4px 8px;
    }

    #chat-container {
        padding: 8px;
        gap: 6px;
    }

    .message {
        padding: 6px 10px;
        font-size: 0.85rem;
    }

    .message-form {
        padding: 6px;
    }

    .message-input {
        padding: 8px 10px;
        font-size: 0.85rem;
    }

}

/* ===== HIGH DPI SCREENS ===== */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    /* Smooth scaling for high-DPI displays */
    .online-user-avatar-small,
    .online-users-count-header {
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
    }

    .message {
        font-smoothing: antialiased;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
}

/* Print styles */
@media print {
    .message-form,
    .theme-toggle,
    .typing-indicator {
        display: none !important;
    }
    
    #chat-container {
        overflow: visible;
        height: auto;
    }
}
</style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    
    <script>
$(document).ready(function() {
    // ========== DOM ELEMENTS ==========
    const $chatContainer = $('#chat-container');
    const $messageForm = $('.message-form');
    const $messageInput = $('.message-input');
    const $typingIndicator = $('.typing-indicator');
    const $sendButton = $('.message-send');
    const $themeToggle = $('.theme-toggle');
    
    // ========== STATE VARIABLES ==========
    let isTyping = false;
    let typingTimer;
    let lastMessageId = 0;
    const typingDelay = 2000; // 2 seconds
    const messageRefreshRate = 2000; // 2 seconds
    const typingRefreshRate = 1000; // 1 second
    let isDarkMode = <?php echo ($_SESSION['dark_mode'] ?? false) ? 'true' : 'false'; ?>;

    // ========== INITIALIZATION ==========
    initChat();

    // Test modal functionality
    console.log('Room settings modal initialized');
    console.log('Modal element:', $('#room-settings-modal').length);
    console.log('Is room creator:', <?php echo $isRoomCreator ? 'true' : 'false'; ?>);
    console.log('Room info:', <?php echo json_encode($roomInfo); ?>);

    // Test notification system
    console.log('Notification system initialized');
    console.log('Notification button:', $('.notification-btn').length);
    console.log('Notification panel:', $('#notification-panel').length);

    function initChat() {
        applyTheme();
        setupEventListeners();
        fetchInitialData();
        fetchOnlineUsers(); // Initial online users load

        // Initialize notification system for room creators
        <?php if ($isRoomCreator): ?>
        loadPendingRequests();
        <?php endif; ?>

        startIntervals();
    }

    // ========== THEME MANAGEMENT ==========
    function applyTheme() {
        if (isDarkMode) {
            $('body').addClass('dark-mode');
            $themeToggle.html('☀️ Light Mode');
        } else {
            $('body').removeClass('dark-mode');
            $themeToggle.html('🌙 Dark Mode');
        }
    }

    // ========== EVENT LISTENERS ==========
    function setupEventListeners() {
        // Message submission
        $messageForm.on('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });

        // Typing detection
        $messageInput.on('input', handleTyping);
        $messageInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });


        // Theme toggle
        $themeToggle.on('click', function(e) {
            e.preventDefault();
            toggleTheme();
        });

        // Handle page unload (user leaving)
        $(window).on('beforeunload', function() {
            // Mark user as offline
            navigator.sendBeacon('updateonlinestatus.php', new URLSearchParams({
                action: 'offline'
            }));
        });
    }

    // ========== MESSAGE FUNCTIONS ==========
    function fetchInitialData() {
        fetchMessages(true);
        fetchFiles();
    }

    function fetchMessages(initialLoad = false) {
        $.ajax({
            url: 'fetchmessages.php',
            type: 'GET',
            data: { 
                roomname: '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>',
                last_id: initialLoad ? 0 : lastMessageId
            },
            dataType: 'json',
            success: function(response) {
                if (response.error) {
                    console.error('Error fetching messages:', response.error);
                    return;
                }

                if (response.messages && response.messages.length > 0) {
                    if (initialLoad) {
                        displayAllMessages(response.messages);
                    } else {
                        displayNewMessages(response.messages);
                    }
                    lastMessageId = response.last_id;
                }

                if (initialLoad) {
                    scrollToBottom();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
            }
        });
    }

    function displayAllMessages(messages) {
        let html = '';
        
        if (messages.length === 0) {
            html = '<div class="no-messages">No messages yet. Send the first one!</div>';
        } else {
            messages.forEach(function(msg) {
                html += createMessageHtml(msg);
            });
        }

        $chatContainer.html(html);
    }

    function displayNewMessages(messages) {
        let html = '';
        messages.forEach(function(msg) {
            html += createMessageHtml(msg);
        });

        $chatContainer.append(html);
        scrollToBottomIfNear();
    }

    function createMessageHtml(msg) {
        const messageClass = msg.isCurrentUser ? 'message-right' : 'message-left';

        return `
            <div class="message ${messageClass}" data-message-id="${msg.id}">
                <div class="message-header">
                    <span class="message-username">${msg.username}</span>
                    <span class="message-time">${formatTime(msg.time)}</span>
                </div>
                <div class="message-text">${msg.message}</div>
            </div>
        `;
    }

    function sendMessage() {
        const message = $messageInput.val().trim();
    
        if (message === '') return;

        // Disable send button during request
    $sendButton.prop('disabled', true).text('Sending...');

        $.ajax({
            url: 'postmsg.php',
            type: 'POST',
            data: {
                message: message,
                room: '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>',
                csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            },
            success: function(response) {
                $messageInput.val('');
                fetchMessages();
                updateTypingStatus(false);
                isTyping = false;
            },
            error: function(xhr, status, error) {
                console.error('Message send error:', error);
                alert('Failed to send message. Please try again.');
            },
            complete: function() {
                $sendButton.prop('disabled', false).text('Send');
            }
        });
    }
    
    // ========== TYPING INDICATOR FUNCTIONS ==========
    function handleTyping() {
        if (!isTyping) {
            isTyping = true;
            updateTypingStatus(true);
        }
        
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function() {
            isTyping = false;
            updateTypingStatus(false);
        }, typingDelay);
    }

    function updateTypingStatus(typing) {
        $.post('updatetyping.php', {
            roomname: '<?php echo $roomname; ?>',
            username: '<?php echo $_SESSION['username']; ?>',
            is_typing: typing ? 1 : 0
        });
    }

    function fetchTypingStatus() {
        $.get('fetchtyping.php', { 
            roomname: '<?php echo $roomname; ?>' 
        }, function(response) {
            if (response) {
                $typingIndicator.text(response).show();
            } else {
                $typingIndicator.hide();
            }
        });
    }

    // ========== FILE UPLOAD FUNCTIONS ==========

    // ========== THEME FUNCTIONS ==========
    function toggleTheme() {
        isDarkMode = !isDarkMode;
        applyTheme();

        // Save preference to database
        $.post('save_preference.php', {
            key: 'dark_mode',
            value: isDarkMode.toString()
        });
    }

    // ========== HELPER FUNCTIONS ==========
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function scrollToBottom() {
        $chatContainer.scrollTop($chatContainer[0].scrollHeight);
    }

    function scrollToBottomIfNear() {
        const threshold = 100; // pixels from bottom
        const currentScroll = $chatContainer.scrollTop();
        const maxScroll = $chatContainer[0].scrollHeight - $chatContainer.outerHeight();
        
        if (maxScroll - currentScroll <= threshold) {
            scrollToBottom();
        }
    }

    // ========== ONLINE USERS FUNCTIONS ==========
    function fetchOnlineUsers() {
        $.get('fetchonlineusers.php', {
            roomname: '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>'
        }, function(response) {
            if (response.online_users) {
                displayOnlineUsers(response.online_users);
            }
        });
    }

    function displayOnlineUsers(users) {
        // Update header count
        $('#header-online-count').text(users.length);

        // Display in header (compact version)
        let headerHtml = '';
        const maxDisplay = 5; // Show max 5 users in header
        const displayUsers = users.slice(0, maxDisplay);

        displayUsers.forEach(function(user) {
            const avatarLetter = user.username.charAt(0).toUpperCase();
            const avatarHtml = user.profile_photo ?
                `<img src="${user.profile_photo}" alt="${user.username}">` :
                avatarLetter;

            headerHtml += `
                <div class="online-user-avatar-small" data-username="${user.username} is online">
                    ${avatarHtml}
                </div>
            `;
        });

        // Add overflow indicator if there are more users
        if (users.length > maxDisplay) {
            const remaining = users.length - maxDisplay;
            headerHtml += `
                <div class="online-user-avatar-small" style="background: rgba(0,212,255,0.8); font-size: 0.6rem;">
                    +${remaining}
                </div>
            `;
        }

        $('#header-online-users').html(headerHtml);
    }



    // ========== ROOM SETTINGS FUNCTIONS ==========
    function openRoomSettings() {
        console.log('Opening room settings modal');
        console.log('Modal element exists:', $('#room-settings-modal').length);

        $('#room-settings-modal').addClass('show');
        loadRoomMembers();
        loadRoomSettings();
        console.log('Modal should now be visible');
    }

    function closeRoomSettings() {
        console.log('Closing room settings modal');
        $('#room-settings-modal').removeClass('show');
        // Reset forms if needed
    }

    function switchSettingsTab(tabName) {
        console.log('Switching to tab:', tabName);

        // Update tab buttons
        $('.settings-tab').removeClass('active');
        $(`.settings-tab[onclick*="${tabName}"]`).addClass('active');

        // Update tab content
        $('.settings-tab-content').removeClass('active');
        $(`#${tabName}-tab`).addClass('active');

        // Load specific data for tabs
        if (tabName === 'members') {
            loadRoomMembers();
        }
    }

    function loadRoomMembers() {
        console.log('Loading room members...');
        $.get('get_room_members.php', {
            roomname: '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>'
        }, function(response) {
            if (response.members) {
                displayRoomMembers(response.members);
            }
        }).fail(function() {
            $('#members-list').html('<div style="text-align: center; color: #ff6b6b; padding: 20px;">Failed to load members</div>');
        });
    }

    function displayRoomMembers(members) {
        let html = '';
        members.forEach(function(member) {
            const isCreator = member.username === '<?php echo $roomInfo['creator']; ?>';
            const isCurrentUser = member.username === '<?php echo $_SESSION['username']; ?>';

            html += `
                <div class="member-item">
                    <div class="member-info">
                        <div class="member-avatar">
                            ${member.username.charAt(0).toUpperCase()}
                        </div>
                        <div class="member-details">
                            <h5>${member.username} ${isCurrentUser ? '(You)' : ''}</h5>
                            <p class="member-role">${isCreator ? 'Admin' : 'Member'}</p>
                        </div>
                    </div>
                    <div class="member-actions">
                        ${!isCurrentUser && <?php echo $isRoomCreator ? 'true' : 'false'; ?> ? `
                            <button class="member-action-btn remove" onclick="removeMember('${member.username}')">Remove</button>
                            ${!isCreator ? `<button class="member-action-btn admin" onclick="makeAdmin('${member.username}')">Make Admin</button>` : ''}
                        ` : ''}
                    </div>
                </div>
            `;
        });

        $('#members-list').html(html);
    }

    function loadRoomSettings() {
        console.log('Loading room settings...');
        // Load saved settings from database or localStorage
        // For now, we'll use default values
    }

    function saveAllSettings() {
        console.log('Saving all settings...');

        const settings = {
            roomname: '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>',
            name: $('#room-name').val(),
            description: $('#room-description').val(),
            password: $('#room-password').val(),
            allowImages: $('#allow-images').is(':checked'),
            autoDownload: $('#auto-download').is(':checked'),
            mediaQuality: $('#media-quality').val(),
            privateGroup: $('#private-group').is(':checked'),
            approveMembers: $('#approve-members').is(':checked'),
            allowGuest: $('#allow-guest').is(':checked'),
            notifyMessages: $('#notify-messages').is(':checked'),
            notifyMentions: $('#notify-mentions').is(':checked'),
            notifyMedia: $('#notify-media').is(':checked'),
            muteGroup: $('#mute-group').is(':checked'),
            notificationSound: $('#notification-sound').val()
        };

        // Handle file upload separately
        const displayPhoto = $('#room-display-photo')[0].files[0];
        if (displayPhoto) {
            uploadDisplayPhoto(displayPhoto);
        }

        // Save other settings
        $.post('save_room_settings.php', {
            action: 'save_all_settings',
            settings: JSON.stringify(settings)
        }, function(response) {
            if (response.success) {
                alert('Settings saved successfully!');
                closeRoomSettings();
                location.reload(); // Reload to show changes
            } else {
                alert('Error saving settings: ' + (response.error || 'Unknown error'));
            }
        }).fail(function() {
            alert('Failed to save settings. Please try again.');
        });
    }

    function uploadDisplayPhoto(file) {
        const formData = new FormData();
        formData.append('display_photo', file);
        formData.append('roomname', '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>');
        formData.append('action', 'update_display_photo');

        $.ajax({
            url: 'update_room_settings.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    console.log('Display photo uploaded successfully');
                } else {
                    console.error('Failed to upload display photo:', response.error);
                }
            },
            error: function() {
                console.error('Upload failed');
            }
        });
    }

    function inviteMember() {
        const username = $('#invite-username').val().trim();
        if (!username) {
            alert('Please enter a username');
            return;
        }

        $.post('invite_member.php', {
            roomname: '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>',
            username: username
        }, function(response) {
            if (response.success) {
                const message = response.type === 'request'
                    ? 'Join request sent successfully! The user will need to wait for admin approval.'
                    : 'Invitation sent successfully!';
                alert(message);
                $('#invite-username').val('');
                loadRoomMembers();
            } else {
                alert('Error: ' + (response.error || 'Failed to send invitation'));
            }
        }).fail(function() {
            alert('Failed to send invitation. Please try again.');
        });
    }

    function removeMember(username) {
        if (!confirm(`Are you sure you want to remove ${username} from the group?`)) {
            return;
        }

        $.post('remove_member.php', {
            roomname: '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>',
            username: username
        }, function(response) {
            if (response.success) {
                alert(`${username} has been removed from the group`);
                loadRoomMembers();
            } else {
                alert('Error: ' + (response.error || 'Failed to remove member'));
            }
        }).fail(function() {
            alert('Failed to remove member. Please try again.');
        });
    }

    function makeAdmin(username) {
        if (!confirm(`Are you sure you want to make ${username} an admin?`)) {
            return;
        }

        $.post('make_admin.php', {
            roomname: '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>',
            username: username
        }, function(response) {
            if (response.success) {
                alert(`${username} is now an admin`);
                loadRoomMembers();
            } else {
                alert('Error: ' + (response.error || 'Failed to make admin'));
            }
        }).fail(function() {
            alert('Failed to make admin. Please try again.');
        });
    }

    // Test function to verify modal works
    window.testRoomSettings = function() {
        console.log('Testing room settings modal...');
        openRoomSettings();
    };

    // Close modal when clicking outside
    $('#room-settings-modal').on('click', function(e) {
        if (e.target === this) {
            closeRoomSettings();
        }
    });

    // Close notification panel when clicking outside
    $(document).on('click', function(e) {
        const notificationPanel = $('#notification-panel');
        const notificationBtn = $('.notification-btn');

        if (!notificationPanel.is(e.target) &&
            !notificationBtn.is(e.target) &&
            notificationPanel.has(e.target).length === 0 &&
            notificationBtn.has(e.target).length === 0) {
            closeNotificationPanel();
        }
    });

    // Handle room settings form submission
    $('#room-settings-form').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('roomname', '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>');

        const newName = $('#room-name').val().trim();
        const newPassword = $('#room-password').val();
        const displayPhoto = $('#room-display-photo')[0].files[0];

        if (newName !== '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>') {
            formData.append('action', 'update_name');
            formData.append('new_name', newName);
        } else if (newPassword) {
            formData.append('action', 'update_password');
            formData.append('new_password', newPassword);
        } else if (displayPhoto) {
            formData.append('action', 'update_display_photo');
            formData.append('display_photo', displayPhoto);
        } else {
            alert('No changes detected');
            return;
        }

        // Disable submit button
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: 'update_room_settings.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;

                    if (data.success) {
                        if (data.new_name) {
                            // Redirect to new room name
                            window.location.href = `room.php?roomname=${encodeURIComponent(data.new_name)}`;
                        } else if (data.photo_path) {
                            alert('Display photo updated successfully!');
                            location.reload(); // Reload to show new photo
                        } else {
                            alert('Password updated successfully!');
                            closeRoomSettings();
                        }
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                } catch (e) {
                    alert('Error processing response');
                }
            },
            error: function(xhr, status, error) {
                alert('Error updating room settings: ' + error);
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Save Changes');
            }
        });
    });

    // ========== NOTIFICATION SYSTEM FUNCTIONS ==========
    function toggleNotificationPanel() {
        const panel = $('#notification-panel');
        const isVisible = panel.hasClass('show');

        if (isVisible) {
            closeNotificationPanel();
        } else {
            openNotificationPanel();
        }
    }

    function openNotificationPanel() {
        $('#notification-panel').addClass('show');
        loadPendingRequests();
    }

    function closeNotificationPanel() {
        $('#notification-panel').removeClass('show');
    }

    function loadPendingRequests() {
        $.ajax({
            url: 'get_pending_requests.php',
            type: 'GET',
            data: {
                roomname: '<?php echo htmlspecialchars($roomname, ENT_QUOTES); ?>'
            },
            dataType: 'json',
            success: function(response) {
                console.log('Pending requests response:', response);
                if (response.success) {
                    displayPendingRequests(response.requests || []);
                    updateNotificationCount(response.count || 0);
                } else {
                    console.error('Error loading requests:', response.error);
                    $('#notification-list').html('<div class="no-notifications">Error: ' + (response.error || 'Unknown error') + '</div>');
                    updateNotificationCount(0);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading requests:', status, error);
                $('#notification-list').html('<div class="no-notifications">Failed to load requests. Please check your connection.</div>');
                updateNotificationCount(0);
            }
        });
    }

    function displayPendingRequests(requests) {
        let html = '';

        if (requests.length === 0) {
            html = '<div class="no-notifications">No pending requests</div>';
        } else {
            requests.forEach(function(request) {
                const avatarLetter = request.username.charAt(0).toUpperCase();
                const requestTime = formatTimeForNotifications(request.requested_at);

                html += `
                    <div class="notification-item" data-request-id="${request.id}">
                        <div class="notification-item-header">
                            <div class="notification-avatar">
                                ${avatarLetter}
                            </div>
                            <div class="notification-info">
                                <h5>${request.username}</h5>
                                <p class="notification-time">${requestTime}</p>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <button class="notification-action-btn approve" onclick="approveRequest(${request.id})">
                                ✅ Approve
                            </button>
                            <button class="notification-action-btn deny" onclick="denyRequest(${request.id})">
                                ❌ Deny
                            </button>
                        </div>
                    </div>
                `;
            });
        }

        $('#notification-list').html(html);
    }

    function approveRequest(requestId) {
        console.log('Approving request:', requestId);
        $.ajax({
            url: 'approve_request.php',
            type: 'POST',
            data: {
                request_id: requestId
            },
            dataType: 'json',
            success: function(response) {
                console.log('Approve response:', response);
                if (response.success) {
                    // Remove the notification item with animation
                    $(`.notification-item[data-request-id="${requestId}"]`).fadeOut(300, function() {
                        $(this).remove();
                        loadPendingRequests(); // Reload to update count
                    });
                    loadRoomMembers(); // Refresh member list
                    showMessage('success', '✅ Request approved successfully!');
                } else {
                    alert('Error: ' + (response.error || 'Failed to approve request'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error approving request:', status, error);
                alert('Failed to approve request. Please try again.');
            }
        });
    }

    function denyRequest(requestId) {
        console.log('Denying request:', requestId);
        $.ajax({
            url: 'deny_request.php',
            type: 'POST',
            data: {
                request_id: requestId
            },
            dataType: 'json',
            success: function(response) {
                console.log('Deny response:', response);
                if (response.success) {
                    // Remove the notification item with animation
                    $(`.notification-item[data-request-id="${requestId}"]`).fadeOut(300, function() {
                        $(this).remove();
                        loadPendingRequests(); // Reload to update count
                    });
                    showMessage('error', '❌ Request denied');
                } else {
                    alert('Error: ' + (response.error || 'Failed to deny request'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error denying request:', status, error);
                alert('Failed to deny request. Please try again.');
            }
        });
    }

    function updateNotificationCount(count) {
        const $count = $('#notification-count');
        $count.text(count);

        if (count > 0) {
            $count.show();
            $('.notification-btn').addClass('has-notifications');
        } else {
            $count.hide();
            $('.notification-btn').removeClass('has-notifications');
        }
    }

    function formatTimeForNotifications(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        return `${days}d ago`;
    }

    function showMessage(type, message) {
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            info: '#17a2b8'
        };

        // Create temporary message
        const msgDiv = document.createElement('div');
        msgDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ` + colors[type] + `;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
            font-weight: 500;
        `;
        msgDiv.textContent = message;

        document.body.appendChild(msgDiv);

        // Remove after 3 seconds
        setTimeout(() => {
            msgDiv.remove();
        }, 3000);
    }

    // ========== INTERVAL MANAGEMENT ==========
    function startIntervals() {
        // Message refresh
        setInterval(fetchMessages, messageRefreshRate);

        // Typing status refresh
        setInterval(fetchTypingStatus, typingRefreshRate);

        // Notification refresh (for room creators)
        <?php if ($isRoomCreator): ?>
        setInterval(loadPendingRequests, 30000); // Every 30 seconds
        <?php endif; ?>

        // Online users refresh
        setInterval(fetchOnlineUsers, 30000); // Every 30 seconds

        // Online status ping
        setInterval(function() {
            $.post('updateonlinestatus.php', { action: 'ping' });
        }, 60000); // Every minute
    }
});
</script>


</head>
<body>
    <div class="header">
        <div class="header-left">
            <div style="display: flex; align-items: center; gap: 12px;">
                <?php if ($roomInfo['display_photo']): ?>
                <img src="<?php echo htmlspecialchars($roomInfo['display_photo']); ?>" alt="Room" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 2px solid rgba(255,255,255,0.2);">
                <?php endif; ?>
                <h2><?php echo htmlspecialchars($roomname); ?></h2>
            </div>
            <div class="header-online-users">
                <span class="online-users-label">Online:</span>
                <div class="online-users-avatars" id="header-online-users">
                    <!-- Online users will be loaded here -->
                </div>
                <div class="online-users-count-header" id="header-online-count">0</div>
            </div>
        </div>
        <div class="header-right">
            <?php if ($isRoomCreator): ?>
            <!-- Notification Bell -->
            <div class="notification-container">
                <button onclick="toggleNotificationPanel()" class="notification-btn" title="Join Requests">
                    🔔 <span id="notification-count" class="notification-count">0</span>
                </button>
                <div id="notification-panel" class="notification-panel">
                    <div class="notification-header">
                        <h4>Join Requests</h4>
                        <button onclick="closeNotificationPanel()" class="close-notifications">&times;</button>
                    </div>
                    <div id="notification-list" class="notification-list">
                        <!-- Requests will be loaded here -->
                    </div>
                </div>
            </div>
            <button onclick="openRoomSettings()" class="room-settings-btn" title="Room Settings">
                ⚙️ Settings
            </button>
            <?php endif; ?>
            <form method="POST">
                <button type="submit" name="toggle_theme" class="theme-toggle">
                    <?php echo ($_SESSION['dark_mode'] ?? false) ? '☀️ Light Mode' : '🌙 Dark Mode'; ?>
                </button>
            </form>
        </div>
    </div>
    
    <div class="typing-indicator"></div>

    <!-- Room Settings Modal -->
    <div class="room-settings-modal" id="room-settings-modal">
        <div class="room-settings-content">
            <!-- Settings Header -->
            <div class="room-settings-header" style="padding: 16px 24px; background: var(--header-bg); border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: white; font-size: 1.2rem;">Room Settings</h3>
                <button onclick="closeRoomSettings()" class="close-settings" style="background: rgba(255,255,255,0.1); border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: white; font-size: 18px;">&times;</button>
            </div>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="settings-tab active" onclick="switchSettingsTab('info')">ℹ️ Info</button>
                <button class="settings-tab" onclick="switchSettingsTab('members')">👥 Members</button>
                <button class="settings-tab" onclick="switchSettingsTab('media')">📷 Media</button>
                <button class="settings-tab" onclick="switchSettingsTab('privacy')">🔒 Privacy</button>
                <button class="settings-tab" onclick="switchSettingsTab('notifications')">🔔 Notifications</button>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <!-- Info Tab -->
                <div id="info-tab" class="settings-tab-content active">
                    <div class="settings-section">
                        <h4>📝 Group Information</h4>
                        <form id="room-info-form">
                            <div class="settings-form-group">
                                <label for="room-name">Group Name</label>
                                <input type="text" id="room-name" class="settings-input" value="<?php echo htmlspecialchars($roomname); ?>" required>
                            </div>

                            <div class="settings-form-group">
                                <label for="room-description">Group Description</label>
                                <textarea id="room-description" class="settings-input" rows="3" placeholder="Describe your group..."></textarea>
                            </div>

                            <div class="settings-form-group">
                                <label for="room-display-photo">Group Photo</label>
                                <input type="file" id="room-display-photo" class="settings-input" accept="image/*">
                                <?php if ($roomInfo['display_photo']): ?>
                                <div class="current-photo">
                                    <p><strong>Current Photo:</strong></p>
                                    <img src="<?php echo htmlspecialchars($roomInfo['display_photo']); ?>" alt="Room Display Photo" style="max-width: 100px; max-height: 100px; border-radius: 8px;">
                                </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Members Tab -->
                <div id="members-tab" class="settings-tab-content">
                    <div class="settings-section">
                        <h4>👥 Group Members</h4>
                        <div id="members-list" style="max-height: 300px; overflow-y: auto;">
                            <!-- Members will be loaded here -->
                            <div style="text-align: center; color: rgba(255,255,255,0.6); padding: 20px;">
                                Loading members...
                            </div>
                        </div>

                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color);">
                            <h5 style="margin: 0 0 12px 0; color: var(--text-color);">Invite Members</h5>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="invite-username" class="settings-input" placeholder="Enter username" style="flex: 1;">
                                <button onclick="inviteMember()" class="settings-btn settings-btn-primary" style="padding: 8px 16px;">Invite</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Media Tab -->
                <div id="media-tab" class="settings-tab-content">
                    <div class="settings-section">
                        <h4>📷 Media Settings</h4>

                        <div style="display: grid; gap: 16px;">
                            <div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="allow-images" checked>
                                    <span>Allow image sharing</span>
                                </label>
                            </div>


                            <div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="auto-download" checked>
                                    <span>Auto-download media</span>
                                </label>
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <h5 style="margin: 0 0 12px 0; color: var(--text-color);">Media Quality</h5>
                            <select class="settings-input" id="media-quality">
                                <option value="high">High Quality</option>
                                <option value="medium" selected>Medium Quality</option>
                                <option value="low">Low Quality (Save space)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Privacy Tab -->
                <div id="privacy-tab" class="settings-tab-content">
                    <div class="settings-section">
                        <h4>🔒 Privacy & Security</h4>

                        <div style="display: grid; gap: 16px;">
                            <div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="private-group" checked>
                                    <span>Private Group</span>
                                </label>
                                <small style="color: rgba(255,255,255,0.6); display: block; margin-left: 24px;">Only invited members can join</small>
                            </div>

                            <div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="approve-members">
                                    <span>Approve new members</span>
                                </label>
                                <small style="color: rgba(255,255,255,0.6); display: block; margin-left: 24px;">Admin approval required</small>
                            </div>

                            <div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="allow-guest">
                                    <span>Allow guest access</span>
                                </label>
                                <small style="color: rgba(255,255,255,0.6); display: block; margin-left: 24px;">Temporary access without invitation</small>
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <h5 style="margin: 0 0 12px 0; color: var(--text-color);">Password Protection</h5>
                            <div class="settings-form-group">
                                <label for="room-password">Group Password</label>
                                <input type="password" id="room-password" class="settings-input" placeholder="Enter new password">
                                <small style="color: rgba(255,255,255,0.6); margin-top: 4px; display: block;">Leave empty to keep current password</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div id="notifications-tab" class="settings-tab-content">
                    <div class="settings-section">
                        <h4>🔔 Notification Settings</h4>

                        <div style="display: grid; gap: 16px;">
                            <div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="notify-messages" checked>
                                    <span>Message notifications</span>
                                </label>
                            </div>

                            <div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="notify-mentions" checked>
                                    <span>Mention notifications</span>
                                </label>
                            </div>

                            <div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="notify-media">
                                    <span>Media notifications</span>
                                </label>
                            </div>

                            <div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <input type="checkbox" id="mute-group">
                                    <span>Mute group</span>
                                </label>
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <h5 style="margin: 0 0 12px 0; color: var(--text-color);">Custom Notification Sound</h5>
                            <select class="settings-input" id="notification-sound">
                                <option value="default" selected>Default</option>
                                <option value="gentle">Gentle</option>
                                <option value="urgent">Urgent</option>
                                <option value="fun">Fun</option>
                                <option value="none">No Sound</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Footer -->
            <div style="padding: 16px 24px; background: var(--bg-color); border-top: 1px solid var(--border-color); border-radius: 0 0 12px 12px;">
                <div class="settings-buttons">
                    <button type="button" onclick="closeRoomSettings()" class="settings-btn settings-btn-secondary">Cancel</button>
                    <button type="button" onclick="saveAllSettings()" class="settings-btn settings-btn-primary">Save All Changes</button>
                </div>
            </div>
        </div>
    </div>

<div class="chat-container" id="chat-container">
    <!-- Messages will appear here -->
</div>
    
<form class="message-form" method="POST" enctype="multipart/form-data">
    <input type="text" class="message-input" name="message" placeholder="Type a message">
    <button type="submit" class="message-send">Send</button>
</form>


</body>
</html>