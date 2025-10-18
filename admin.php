<?php
session_start();
include("db_setup.php");

// Define admin password hash (should be in config file in production)
define('ADMIN_PASSWORD_HASH', password_hash('2676', PASSWORD_DEFAULT));

$active_tab = $_GET['tab'] ?? 'files';
$error = '';
$success = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header("Location: admin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['admin_login']) || isset($_POST['view_password'])) {
        $admin_password = $_POST['admin_password'] ?? $_POST['view_password'] ?? '';
        if (password_verify($admin_password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin_logged_in'] = true;
            header("Location: admin.php");
            exit();
        } else {
            $error = 'Incorrect admin password';
        }
    } elseif (isset($_POST['delete_room']) && isset($_SESSION['admin_logged_in'])) {
        $roomname = $_POST['roomname'];
        // Delete room and related data
        $queries = [
            "DELETE FROM messages WHERE roomname = ?",
            "DELETE FROM room_users WHERE roomname = ?",
            "DELETE FROM typing_indicators WHERE roomname = ?",
            "DELETE FROM files WHERE roomname = ?",
            "DELETE FROM rooms WHERE roomname = ?"
        ];
        foreach ($queries as $query) {
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "s", $roomname);
            mysqli_stmt_execute($stmt);
        }
        $success = 'Room deleted successfully';
    } elseif (isset($_POST['delete_user']) && isset($_SESSION['admin_logged_in'])) {
        $user_id = (int)$_POST['user_id'];
        // Delete user
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $success = 'User deleted successfully';
    } elseif (isset($_POST['delete_file']) && isset($_SESSION['admin_logged_in'])) {
        $file_id = (int)$_POST['file_id'];

        // Get file info first
        $stmt = mysqli_prepare($conn, "SELECT filepath FROM files WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $file_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $file = mysqli_fetch_assoc($result);

        if ($file) {
            // Delete physical file
            if (file_exists($file['filepath'])) {
                unlink($file['filepath']);
            }

            // Delete from database
            $stmt = mysqli_prepare($conn, "DELETE FROM files WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $file_id);
            mysqli_stmt_execute($stmt);
            $success = 'File deleted successfully';
        } else {
            $error = 'File not found';
        }
    } elseif (isset($_POST['create_room']) && isset($_SESSION['admin_logged_in'])) {
        $roomname = trim($_POST['new_room_name']);
        $password = $_POST['new_room_password'] ?? '';

        if (empty($roomname)) {
            $error = 'Room name is required';
        } else {
            // Check if room already exists
            $stmt = mysqli_prepare($conn, "SELECT id FROM rooms WHERE roomname = ?");
            mysqli_stmt_bind_param($stmt, "s", $roomname);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {
                $error = 'Room already exists';
            } else {
                // Create the room
                $stmt = mysqli_prepare($conn, "INSERT INTO rooms (roomname, creator, password) VALUES (?, 'Admin', ?)");
                mysqli_stmt_bind_param($stmt, "ss", $roomname, $password);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Room created successfully';
                } else {
                    $error = 'Failed to create room: ' . mysqli_error($conn);
                }
            }
        }
    } elseif (isset($_POST['reset_room']) && isset($_SESSION['admin_logged_in'])) {
        $roomname = $_POST['roomname'];

        // Delete all messages and files for this room (keep room and users)
        $queries = [
            "DELETE FROM messages WHERE roomname = ?",
            "DELETE FROM files WHERE roomname = ?",
            "DELETE FROM typing_indicators WHERE roomname = ?"
        ];

        $success_count = 0;
        foreach ($queries as $query) {
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "s", $roomname);
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
            }
        }

        if ($success_count == count($queries)) {
            $success = 'Room reset successfully (messages and files cleared)';
        } else {
            $error = 'Room reset partially completed';
        }
    } elseif (isset($_POST['join_room_admin']) && isset($_SESSION['admin_logged_in'])) {
        $roomname = $_POST['roomname'];

        // Check if room exists
        $query = "SELECT id FROM rooms WHERE roomname = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $roomname);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            // Set admin as current user
            $_SESSION['user_id'] = 0; // Special ID for admin
            $_SESSION['username'] = 'Admin';

            // Generate token and join room
            $user_token = bin2hex(random_bytes(16));
            $query = "INSERT INTO room_users (roomname, username, user_token) VALUES (?, 'Admin', ?)
                     ON DUPLICATE KEY UPDATE user_token = VALUES(user_token), last_seen = CURRENT_TIMESTAMP";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ss", $roomname, $user_token);
            mysqli_stmt_execute($stmt);

            // Set cookie and redirect
            setcookie('user_token_' . $roomname, $user_token, time() + (86400 * 30), "/");
            header("Location: room.php?roomname=" . urlencode($roomname));
            exit();
        } else {
            $error = 'Room not found';
        }
    }
}

if (!isset($_SESSION['admin_logged_in'])) {
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - Galaxy Chat</title>
        <style>
            body {
                background: radial-gradient(ellipse at bottom, #1B2735 0%, #090A0F 100%);
                color: white;
                font-family: 'Arial', sans-serif;
                height: 100vh;
                margin: 0;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .admin-container {
                background: rgba(20, 20, 50, 0.7);
                padding: 2.5rem;
                border-radius: 15px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                backdrop-filter: blur(10px);
                width: 100%;
                max-width: 400px;
                text-align: center;
            }
            h1 { color: #00d4ff; margin-bottom: 1.5rem; }
            .error { color: #ff6b6b; margin-bottom: 1rem; }
            input {
                width: 100%;
                padding: 1rem;
                background: rgba(20, 20, 50, 0.5);
                border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 8px;
                color: white;
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }
            button {
                width: 100%;
                padding: 1rem;
                background: linear-gradient(90deg, #00d4ff, #0077ff);
                border: none;
                border-radius: 8px;
                color: white;
                font-size: 1rem;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            button:hover { transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="admin-container">
            <h1>Admin Portal</h1>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="admin_password" placeholder="Admin Password" required>
                <button type="submit" name="admin_login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Get data based on active tab
$data = [];
if ($active_tab == 'rooms') {
    $query = "SELECT r.roomname, COUNT(ru.username) as user_count, COUNT(m.id) as message_count
              FROM rooms r
              LEFT JOIN room_users ru ON r.roomname = ru.roomname
              LEFT JOIN messages m ON r.roomname = m.roomname
              GROUP BY r.roomname
              ORDER BY r.roomname ASC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
} elseif ($active_tab == 'users') {
    $query = "SELECT u.id, u.username, u.email, u.created_at, COUNT(ru.roomname) as room_count
              FROM users u
              LEFT JOIN room_users ru ON u.username = ru.username
              GROUP BY u.id, u.username, u.email, u.created_at
              ORDER BY u.created_at DESC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
} elseif ($active_tab == 'messages') {
    $query = "SELECT m.username, m.msg, m.roomname, m.created_at
              FROM messages m
              ORDER BY m.created_at DESC LIMIT 100";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
} elseif ($active_tab == 'stats') {
    $stats = [];
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result ? mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM rooms");
    $stats['total_rooms'] = $result ? mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM messages");
    $stats['total_messages'] = $result ? mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM files");
    $stats['total_files'] = $result ? mysqli_fetch_assoc($result)['count'] : 0;

    $result = mysqli_query($conn, "SELECT COUNT(DISTINCT username) as count FROM room_users");
    $stats['active_users'] = $result ? mysqli_fetch_assoc($result)['count'] : 0;

    $data = $stats;
} elseif ($active_tab == 'files') {
    $query = "SELECT f.id, f.filename, f.filepath, f.filesize, f.username, f.roomname, f.uploaded_at
              FROM files f
              ORDER BY f.uploaded_at DESC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
} elseif ($active_tab == 'file-tools') {
    // Data for file tools - will be handled in the HTML section
    $data = [];
} elseif ($active_tab == 'room-control') {
    // Get all rooms with detailed information
    // First check if created_at column exists
    $result = mysqli_query($conn, "SHOW COLUMNS FROM rooms LIKE 'created_at'");
    $has_created_at = mysqli_num_rows($result) > 0;

    if ($has_created_at) {
        $query = "SELECT r.roomname, r.creator, r.display_photo, r.created_at,
                         COUNT(DISTINCT ru.username) as user_count,
                         COUNT(DISTINCT m.id) as message_count,
                         COUNT(DISTINCT f.id) as file_count
                  FROM rooms r
                  LEFT JOIN room_users ru ON r.roomname = ru.roomname
                  LEFT JOIN messages m ON r.roomname = m.roomname
                  LEFT JOIN files f ON r.roomname = f.roomname
                  GROUP BY r.roomname, r.creator, r.display_photo, r.created_at
                  ORDER BY r.created_at DESC";
    } else {
        $query = "SELECT r.roomname, r.creator, r.display_photo,
                         COUNT(DISTINCT ru.username) as user_count,
                         COUNT(DISTINCT m.id) as message_count,
                         COUNT(DISTINCT f.id) as file_count
                  FROM rooms r
                  LEFT JOIN room_users ru ON r.roomname = ru.roomname
                  LEFT JOIN messages m ON r.roomname = m.roomname
                  LEFT JOIN files f ON r.roomname = f.roomname
                  GROUP BY r.roomname, r.creator, r.display_photo
                  ORDER BY r.roomname ASC";
    }

    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Add created_at if it doesn't exist in the result
            if (!$has_created_at) {
                $row['created_at'] = null;
            }
            $data[] = $row;
        }
    }
} elseif ($active_tab == 'system-tools') {
    // Data for system tools - will be handled in the HTML section
    $data = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - Galaxy Chat</title>
    <style>
        body {
            background: radial-gradient(ellipse at bottom, #1B2735 0%, #090A0F 100%);
            color: white;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
        }
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(20, 20, 50, 0.7);
            border-radius: 15px;
            padding: 2rem;
            backdrop-filter: blur(10px);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 1rem;
        }
        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .tab {
            padding: 0.8rem 1.5rem;
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid rgba(0, 212, 255, 0.2);
            border-radius: 8px;
            color: rgba(0, 212, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .tab.active, .tab:hover {
            background: rgba(0, 212, 255, 0.2);
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th {
            background: rgba(0, 212, 255, 0.1);
            color: #00d4ff;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .stat-card {
            background: rgba(0, 212, 255, 0.1);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #00d4ff;
        }
        .delete-btn {
            background: #ff4757;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .delete-btn:hover { background: #ff3742; }
        .join-btn:hover { background: #45a049 !important; }
        .logout-btn {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .file-link {
            color: #00d4ff;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .file-link:hover {
            color: #4fc3f7;
            text-decoration: underline;
        }
        .file-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .file-stat-card {
            background: rgba(0, 212, 255, 0.1);
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
        }
        .file-stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #00d4ff;
        }
        .file-stat-label {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.8);
        }
        .file-stat-size {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            margin-top: 0.5rem;
        }
        /* ===== COMPREHENSIVE RESPONSIVE DESIGN ===== */

        /* ===== MOBILE PHONES (320px - 480px) ===== */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .admin-container {
                padding: 1rem;
                border-radius: 10px;
                margin: 0;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding-bottom: 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .header-actions {
                justify-content: center;
                flex-wrap: wrap;
            }

            .tabs {
                flex-direction: column;
                gap: 0.5rem;
            }

            .tab {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
                text-align: center;
            }

            table {
                font-size: 0.8rem;
            }

            th, td {
                padding: 0.6rem 0.4rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .delete-btn {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
            }

            .logout-btn {
                padding: 0.6rem 1rem;
                font-size: 0.8rem;
            }
        }

        /* ===== SMALL TABLETS (481px - 768px) ===== */
        @media (min-width: 481px) and (max-width: 768px) {
            .admin-container {
                padding: 1.5rem;
                max-width: 100%;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .tabs {
                gap: 0.7rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            .tab {
                padding: 0.7rem 1.2rem;
                font-size: 0.9rem;
                flex: 1;
                min-width: 120px;
                text-align: center;
            }

            th, td {
                padding: 0.8rem 0.6rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.2rem;
            }

            .stat-number {
                font-size: 1.8rem;
            }
        }

        /* ===== TABLETS & SMALL LAPTOPS (769px - 1024px) ===== */
        @media (min-width: 769px) and (max-width: 1024px) {
            .admin-container {
                padding: 2rem;
                max-width: 900px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .tabs {
                gap: 0.8rem;
            }

            .tab {
                padding: 0.8rem 1.4rem;
                font-size: 0.95rem;
            }

            th, td {
                padding: 1rem 0.8rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.2rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        /* ===== DESKTOPS (1025px - 1440px) ===== */
        @media (min-width: 1025px) and (max-width: 1440px) {
            .admin-container {
                padding: 2.5rem;
                max-width: 1100px;
            }

            .header h1 {
                font-size: 2.2rem;
            }

            .tabs {
                gap: 1rem;
            }

            .tab {
                padding: 0.9rem 1.6rem;
                font-size: 1rem;
            }

            th, td {
                padding: 1.1rem 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }

            .stat-card {
                padding: 1.8rem;
            }

            .stat-number {
                font-size: 2.2rem;
            }
        }

        /* ===== LARGE SCREENS (1441px+) ===== */
        @media (min-width: 1441px) {
            body {
                background-attachment: fixed;
            }

            .admin-container {
                padding: 3rem;
                max-width: 1300px;
                backdrop-filter: blur(15px);
            }

            .header h1 {
                font-size: 2.5rem;
            }

            .tabs {
                gap: 1.2rem;
            }

            .tab {
                padding: 1rem 1.8rem;
                font-size: 1.05rem;
            }

            th, td {
                padding: 1.2rem 1.2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 2rem;
            }

            .stat-card {
                padding: 2rem;
            }

            .stat-number {
                font-size: 2.5rem;
            }

            .delete-btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }

            .logout-btn {
                padding: 0.9rem 1.5rem;
                font-size: 0.95rem;
            }
        }

        /* ===== ORIENTATION CHANGES ===== */
        @media (max-height: 600px) and (orientation: landscape) {
            .admin-container {
                padding: 1rem;
                margin: 5px auto;
            }

            .header {
                padding-bottom: 0.8rem;
            }

            .header h1 {
                font-size: 1.6rem;
            }

            .tabs {
                gap: 0.4rem;
            }

            .tab {
                padding: 0.5rem 0.8rem;
                font-size: 0.8rem;
            }

            table {
                font-size: 0.75rem;
            }

            th, td {
                padding: 0.5rem 0.3rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.6rem;
            }

            .stat-card {
                padding: 0.8rem;
            }

            .stat-number {
                font-size: 1.2rem;
            }
        }

        /* ===== HIGH DPI SCREENS ===== */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .admin-container,
            table,
            .stat-card {
                image-rendering: -webkit-optimize-contrast;
                image-rendering: crisp-edges;
            }

            .header h1,
            th, td,
            .stat-number {
                font-smoothing: antialiased;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
        }

        /* ===== PRINT STYLES ===== */
        @media print {
            .tabs,
            .delete-btn,
            .logout-btn {
                display: none !important;
            }

            .admin-container {
                box-shadow: none;
                border: 1px solid #000;
            }

            table {
                border-collapse: collapse;
            }

            th, td {
                border: 1px solid #000;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="header">
            <h1>Galaxy Chat Admin Portal</h1>
            <div class="header-actions">
                <a href="index.php" class="logout-btn">Back to Chat</a>
                <a href="admin.php?logout=1" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="tabs">
            <a href="admin.php?tab=files" class="tab <?php echo $active_tab == 'files' ? 'active' : ''; ?>">Files</a>
            <a href="admin.php?tab=rooms" class="tab <?php echo $active_tab == 'rooms' ? 'active' : ''; ?>">Rooms</a>
            <a href="admin.php?tab=users" class="tab <?php echo $active_tab == 'users' ? 'active' : ''; ?>">Users</a>
            <a href="admin.php?tab=messages" class="tab <?php echo $active_tab == 'messages' ? 'active' : ''; ?>">Messages</a>
            <a href="admin.php?tab=file-tools" class="tab <?php echo $active_tab == 'file-tools' ? 'active' : ''; ?>">File Tools</a>
            <a href="admin.php?tab=room-control" class="tab <?php echo $active_tab == 'room-control' ? 'active' : ''; ?>">Room Control</a>
            <a href="admin.php?tab=system-tools" class="tab <?php echo $active_tab == 'system-tools' ? 'active' : ''; ?>">System Tools</a>
            <a href="admin.php?tab=stats" class="tab <?php echo $active_tab == 'stats' ? 'active' : ''; ?>">Statistics</a>
        </div>

        <?php if ($success): ?>
            <div style="background: rgba(0, 255, 0, 0.1); color: #4CAF50; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid rgba(0, 255, 0, 0.2);">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($active_tab == 'rooms'): ?>
            <h2>Room Management</h2>
            <p style="color: rgba(255,255,255,0.7); margin-bottom: 1rem; font-size: 0.9rem;">View all chat rooms, monitor activity, and manage room access.</p>
            <table>
                <thead>
                    <tr>
                        <th>Room Name</th>
                        <th>Created</th>
                        <th>Users</th>
                        <th>Messages</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $room): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($room['roomname']); ?></td>
                            <td>N/A</td>
                            <td><?php echo $room['user_count']; ?></td>
                            <td><?php echo $room['message_count']; ?></td>
                            <td>
                                <form method="POST" style="display: inline; margin-right: 5px;">
                                    <input type="hidden" name="roomname" value="<?php echo htmlspecialchars($room['roomname']); ?>">
                                    <button type="submit" name="join_room_admin" class="join-btn" style="background: #4CAF50; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Join Room</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this room?')">
                                    <input type="hidden" name="roomname" value="<?php echo htmlspecialchars($room['roomname']); ?>">
                                    <button type="submit" name="delete_room" class="delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($active_tab == 'users'): ?>
            <h2>User Management</h2>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Joined</th>
                        <th>Rooms</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                            <td><?php echo $user['room_count']; ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="delete-btn">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($active_tab == 'messages'): ?>
            <h2>Recent Messages</h2>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Room</th>
                        <th>Message</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $message): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($message['username']); ?></td>
                            <td><?php echo htmlspecialchars($message['roomname']); ?></td>
                            <td><?php echo htmlspecialchars(substr($message['msg'], 0, 50)) . (strlen($message['msg']) > 50 ? '...' : ''); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ($active_tab == 'stats'): ?>
            <h2>System Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $data['total_users']; ?></div>
                    <div>Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $data['total_rooms']; ?></div>
                    <div>Total Rooms</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $data['total_messages']; ?></div>
                    <div>Total Messages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $data['total_files']; ?></div>
                    <div>Total Files</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $data['active_users']; ?></div>
                    <div>Active Users</div>
                </div>
            </div>

        <?php elseif ($active_tab == 'files'): ?>
            <h2>File Management</h2>
            <p style="color: rgba(255,255,255,0.7); margin-bottom: 1rem; font-size: 0.9rem;">View and manage all files uploaded across all rooms.</p>

            <?php if (count($data) > 0): ?>
            <div style="margin-bottom: 1rem; color: rgba(255,255,255,0.8);">
                <strong>Total Files:</strong> <?php echo count($data); ?> |
                <strong>Total Size:</strong> <?php
                    $total_size = array_sum(array_column($data, 'filesize'));
                    echo round($total_size / 1024 / 1024, 2) . ' MB';
                ?>
            </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Room</th>
                        <th>Uploaded By</th>
                        <th>Size</th>
                        <th>Upload Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($data) > 0): ?>
                        <?php foreach ($data as $file): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo htmlspecialchars($file['filepath']); ?>" target="_blank" class="file-link">
                                        <?php echo htmlspecialchars($file['filename']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($file['roomname']); ?></td>
                                <td><?php echo htmlspecialchars($file['username']); ?></td>
                                <td><?php echo round($file['filesize'] / 1024, 1); ?> KB</td>
                                <td><?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this file?')">
                                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                        <button type="submit" name="delete_file" class="delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: rgba(255,255,255,0.5);">
                                No files found in the system.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (count($data) > 0): ?>
            <div style="margin-top: 2rem; padding: 1rem; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <h3 style="margin-top: 0; color: #00d4ff;">File Statistics by Room</h3>
                <?php
                $room_stats = [];
                foreach ($data as $file) {
                    $room = $file['roomname'];
                    if (!isset($room_stats[$room])) {
                        $room_stats[$room] = ['count' => 0, 'size' => 0];
                    }
                    $room_stats[$room]['count']++;
                    $room_stats[$room]['size'] += $file['filesize'];
                }
                ?>
                <div class="file-stats">
                    <?php foreach ($room_stats as $room => $stats): ?>
                        <div class="file-stat-card">
                            <div class="file-stat-number"><?php echo $stats['count']; ?></div>
                            <div class="file-stat-label">Files in <?php echo htmlspecialchars($room); ?></div>
                            <div class="file-stat-size">
                                <?php echo round($stats['size'] / 1024 / 1024, 2); ?> MB
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ($active_tab == 'file-tools'): ?>
            <h2>File Management Tools</h2>
            <p style="color: rgba(255,255,255,0.7); margin-bottom: 2rem; font-size: 0.9rem;">Advanced file management and diagnostic tools for the entire system.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div style="background: rgba(0, 212, 255, 0.1); padding: 1.5rem; border-radius: 8px;">
                    <h3 style="margin-top: 0; color: #00d4ff;">🔍 Orphaned Files Check</h3>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 1rem;">Find and remove files that don't belong to existing rooms.</p>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="check_orphaned" class="btn" style="background: #4CAF50; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Check Orphaned Files</button>
                    </form>
                </div>

                <div style="background: rgba(0, 212, 255, 0.1); padding: 1.5rem; border-radius: 8px;">
                    <h3 style="margin-top: 0; color: #00d4ff;">📊 Room Distribution</h3>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 1rem;">View file distribution across all rooms.</p>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="check_room_distribution" class="btn" style="background: #2196F3; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Check Distribution</button>
                    </form>
                </div>

                <div style="background: rgba(255, 193, 7, 0.1); padding: 1.5rem; border-radius: 8px;">
                    <h3 style="margin-top: 0; color: #FFC107;">⚠️ Clear All Files</h3>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 1rem;">Delete ALL files from the database (files remain on disk).</p>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="clear_all_files" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete ALL files?')" style="background: #ff4757; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Clear ALL Files</button>
                    </form>
                </div>
            </div>

            <?php
            if (isset($_POST['check_orphaned'])) {
                echo "<h3>Orphaned Files Check Results</h3>";

                $query = "SELECT f.id, f.filename, f.roomname, f.filepath, f.username
                          FROM files f
                          LEFT JOIN rooms r ON f.roomname = r.roomname
                          WHERE r.roomname IS NULL
                          ORDER BY f.uploaded_at DESC";

                $result = mysqli_query($conn, $query);
                $orphaned = [];

                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $orphaned[] = $row;
                    }
                }

                if (count($orphaned) > 0) {
                    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;'>";
                    echo "<strong>Found " . count($orphaned) . " orphaned files</strong> (files not associated with existing rooms):";
                    echo "</div>";

                    echo "<table>";
                    echo "<tr><th>ID</th><th>Filename</th><th>Room</th><th>Username</th><th>Action</th></tr>";

                    foreach ($orphaned as $file) {
                        echo "<tr>";
                        echo "<td>{$file['id']}</td>";
                        echo "<td>{$file['filename']}</td>";
                        echo "<td><span style='color: #dc3545;'>{$file['roomname']} (INVALID)</span></td>";
                        echo "<td>{$file['username']}</td>";
                        echo "<td><span style='color: #dc3545;'>Will be deleted</span></td>";
                        echo "</tr>";
                    }
                    echo "</table>";

                    echo "<br><form method='POST' style='display: inline;'>";
                    echo "<button type='submit' name='delete_orphaned' class='btn btn-danger' onclick='return confirm(\"Delete " . count($orphaned) . " orphaned files?\")'>Delete Orphaned Files</button>";
                    echo "</form>";
                } else {
                    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 1rem; border-radius: 8px;'>✅ No orphaned files found.</div>";
                }
            }

            if (isset($_POST['delete_orphaned'])) {
                $delete_query = "DELETE f FROM files f
                                LEFT JOIN rooms r ON f.roomname = r.roomname
                                WHERE r.roomname IS NULL";

                if (mysqli_query($conn, $delete_query)) {
                    $affected = mysqli_affected_rows($conn);
                    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 1rem; border-radius: 8px; margin-top: 1rem;'>✅ Deleted $affected orphaned files from database.</div>";
                } else {
                    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 1rem; border-radius: 8px; margin-top: 1rem;'>❌ Failed to delete orphaned files: " . mysqli_error($conn) . "</div>";
                }
            }

            if (isset($_POST['check_room_distribution'])) {
                echo "<h3>File Distribution by Room</h3>";

                $query = "SELECT f.roomname, COUNT(*) as file_count, r.roomname as room_exists
                          FROM files f
                          LEFT JOIN rooms r ON f.roomname = r.roomname
                          GROUP BY f.roomname
                          ORDER BY file_count DESC";

                $result = mysqli_query($conn, $query);

                if ($result && mysqli_num_rows($result) > 0) {
                    echo "<table>";
                    echo "<tr><th>Room Name</th><th>File Count</th><th>Room Status</th></tr>";

                    while ($row = mysqli_fetch_assoc($result)) {
                        $status = $row['room_exists'] ? '<span style="color: #28a745;">✅ Room exists</span>' : '<span style="color: #dc3545;">❌ Room deleted</span>';
                        echo "<tr>";
                        echo "<td>{$row['roomname']}</td>";
                        echo "<td>{$row['file_count']}</td>";
                        echo "<td>$status</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 1rem; border-radius: 8px;'>No files found in database.</div>";
                }
            }

            if (isset($_POST['clear_all_files'])) {
                echo "<h3>⚠️ Clearing ALL Files</h3>";
                echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;'>This will delete ALL files from the database. Physical files on disk will remain.</div>";

                $delete_query = "DELETE FROM files";
                if (mysqli_query($conn, $delete_query)) {
                    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 1rem; border-radius: 8px; margin-top: 1rem;'>✅ Cleared all files from database (" . mysqli_affected_rows($conn) . " files deleted).</div>";
                } else {
                    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 1rem; border-radius: 8px; margin-top: 1rem;'>❌ Failed to clear files: " . mysqli_error($conn) . "</div>";
                }
            }
            ?>

        <?php elseif ($active_tab == 'room-control'): ?>
            <h2>Complete Room Control</h2>
            <p style="color: rgba(255,255,255,0.7); margin-bottom: 2rem; font-size: 0.9rem;">Full administrative control over all rooms in the system.</p>

            <div style="margin-bottom: 2rem;">
                <h3>Create New Room</h3>
                <form method="POST" style="display: flex; gap: 1rem; align-items: center; margin-top: 1rem;">
                    <input type="text" name="new_room_name" placeholder="Room Name" required style="padding: 0.5rem; border-radius: 4px; border: 1px solid rgba(255,255,255,0.2); background: rgba(20,20,50,0.5); color: white;">
                    <input type="password" name="new_room_password" placeholder="Room Password (optional)" style="padding: 0.5rem; border-radius: 4px; border: 1px solid rgba(255,255,255,0.2); background: rgba(20,20,50,0.5); color: white;">
                    <button type="submit" name="create_room" class="btn" style="background: #4CAF50; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Create Room</button>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Room Name</th>
                        <th>Creator</th>
                        <th>Created</th>
                        <th>Users</th>
                        <th>Messages</th>
                        <th>Files</th>
                        <th>Display Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($data) > 0): ?>
                        <?php foreach ($data as $room): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($room['roomname']); ?></td>
                                <td><?php echo htmlspecialchars($room['creator'] ?? 'N/A'); ?></td>
                                <td><?php echo ($room['created_at'] && $room['created_at'] != '0000-00-00 00:00:00') ? date('Y-m-d H:i', strtotime($room['created_at'])) : 'N/A'; ?></td>
                                <td><?php echo $room['user_count']; ?></td>
                                <td><?php echo $room['message_count']; ?></td>
                                <td><?php echo $room['file_count']; ?></td>
                                <td>
                                    <?php if ($room['display_photo']): ?>
                                        <img src="<?php echo htmlspecialchars($room['display_photo']); ?>" alt="Room Photo" style="width: 40px; height: 40px; border-radius: 4px; object-fit: cover;">
                                    <?php else: ?>
                                        <span style="color: rgba(255,255,255,0.5);">No photo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline; margin-right: 5px;">
                                        <input type="hidden" name="roomname" value="<?php echo htmlspecialchars($room['roomname']); ?>">
                                        <button type="submit" name="join_room_admin" class="join-btn" style="background: #4CAF50; color: white; border: none; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Join</button>
                                    </form>
                                    <form method="POST" style="display: inline; margin-right: 5px;">
                                        <input type="hidden" name="roomname" value="<?php echo htmlspecialchars($room['roomname']); ?>">
                                        <button type="submit" name="reset_room" class="btn" style="background: #ff9800; color: white; border: none; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;" onclick="return confirm('Reset all messages and files in this room?')">Reset</button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this room?')">
                                        <input type="hidden" name="roomname" value="<?php echo htmlspecialchars($room['roomname']); ?>">
                                        <button type="submit" name="delete_room" class="delete-btn" style="font-size: 0.8rem;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: rgba(255,255,255,0.5);">
                                No rooms found in the system.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($active_tab == 'system-tools'): ?>
            <h2>System Diagnostic Tools</h2>
            <p style="color: rgba(255,255,255,0.7); margin-bottom: 2rem; font-size: 0.9rem;">Advanced diagnostic and troubleshooting tools for system maintenance.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div style="background: rgba(0, 212, 255, 0.1); padding: 2rem; border-radius: 12px; text-align: center;">
                    <h3 style="margin-top: 0; color: #00d4ff; font-size: 1.3rem;">🔍 Database Structure Check</h3>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; font-size: 0.95rem;">Check database table structure and fix missing columns.</p>
                    <a href="check_db_structure.php" target="_blank" style="background: linear-gradient(90deg, #00d4ff, #0077ff); color: white; padding: 0.8rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-block; transition: all 0.3s ease;">
                        🔍 Check Database Structure
                    </a>
                    <br><small style="color: rgba(255,255,255,0.6); margin-top: 0.5rem; display: block;">Opens in new tab</small>
                </div>

                <div style="background: rgba(255, 193, 7, 0.1); padding: 2rem; border-radius: 12px; text-align: center;">
                    <h3 style="margin-top: 0; color: #FFC107; font-size: 1.3rem;">🏠 Room Issues Debug</h3>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; font-size: 0.95rem;">Debug room-related issues and inconsistencies.</p>
                    <a href="debug_room_issues.php" target="_blank" style="background: linear-gradient(90deg, #FFC107, #FF8F00); color: white; padding: 0.8rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-block; transition: all 0.3s ease;">
                        🏠 Debug Room Issues
                    </a>
                    <br><small style="color: rgba(255,255,255,0.6); margin-top: 0.5rem; display: block;">Opens in new tab</small>
                </div>

                <div style="background: rgba(76, 175, 80, 0.1); padding: 2rem; border-radius: 12px; text-align: center;">
                    <h3 style="margin-top: 0; color: #4CAF50; font-size: 1.3rem;">📊 System Health Check</h3>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; font-size: 0.95rem;">Comprehensive system health and performance check.</p>
                    <a href="diagnostics.php" target="_blank" style="background: linear-gradient(90deg, #4CAF50, #2E7D32); color: white; padding: 0.8rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-block; transition: all 0.3s ease;">
                        📊 System Diagnostics
                    </a>
                    <br><small style="color: rgba(255,255,255,0.6); margin-top: 0.5rem; display: block;">Opens in new tab</small>
                </div>

                <div style="background: rgba(244, 67, 54, 0.1); padding: 2rem; border-radius: 12px; text-align: center;">
                    <h3 style="margin-top: 0; color: #F44336; font-size: 1.3rem;">🧹 Database Cleanup</h3>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; font-size: 0.95rem;">Clean up orphaned records and optimize database.</p>
                    <a href="cleanup_files.php" target="_blank" style="background: linear-gradient(90deg, #F44336, #D32F2F); color: white; padding: 0.8rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-block; transition: all 0.3s ease;">
                        🧹 Database Cleanup
                    </a>
                    <br><small style="color: rgba(255,255,255,0.6); margin-top: 0.5rem; display: block;">Opens in new tab</small>
                </div>
            </div>

            <div style="background: rgba(255,255,255,0.05); padding: 2rem; border-radius: 12px; margin-top: 2rem;">
                <h3 style="margin-top: 0; color: #00d4ff;">🚀 Quick System Actions</h3>
                <p style="color: rgba(255,255,255,0.7); margin-bottom: 1.5rem;">Common maintenance tasks and system operations.</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <a href="db_setup.php" target="_blank" style="background: rgba(0, 212, 255, 0.1); padding: 1rem; border-radius: 8px; text-decoration: none; color: #00d4ff; display: block; text-align: center; transition: all 0.3s ease;">
                        🗄️ Database Setup<br><small style="color: rgba(255,255,255,0.6);">Reinitialize database</small>
                    </a>

                    <a href="migrate_db.php" target="_blank" style="background: rgba(255, 193, 7, 0.1); padding: 1rem; border-radius: 8px; text-decoration: none; color: #FFC107; display: block; text-align: center; transition: all 0.3s ease;">
                        🔄 Database Migration<br><small style="color: rgba(255,255,255,0.6);">Apply schema updates</small>
                    </a>

                    <a href="test_systems.php" target="_blank" style="background: rgba(76, 175, 80, 0.1); padding: 1rem; border-radius: 8px; text-decoration: none; color: #4CAF50; display: block; text-align: center; transition: all 0.3s ease;">
                        ✅ System Tests<br><small style="color: rgba(255,255,255,0.6);">Run system checks</small>
                    </a>

                    <a href="fix_file_rooms.php" target="_blank" style="background: rgba(244, 67, 54, 0.1); padding: 1rem; border-radius: 8px; text-decoration: none; color: #F44336; display: block; text-align: center; transition: all 0.3s ease;">
                        🔧 Fix File Rooms<br><small style="color: rgba(255,255,255,0.6);">Repair file associations</small>
                    </a>
                </div>
            </div>

            <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 8px; margin-top: 2rem; border-left: 4px solid #00d4ff;">
                <h4 style="margin-top: 0; color: #00d4ff;">💡 Pro Tips</h4>
                <ul style="color: rgba(255,255,255,0.8); margin: 0; padding-left: 1.5rem;">
                    <li>Run <strong>Database Structure Check</strong> first if you encounter errors</li>
                    <li>Use <strong>Room Issues Debug</strong> to troubleshoot room-related problems</li>
                    <li><strong>System Diagnostics</strong> provides comprehensive health reports</li>
                    <li><strong>Database Cleanup</strong> should be run periodically for maintenance</li>
                    <li>All tools open in new tabs to preserve your admin session</li>
                </ul>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>