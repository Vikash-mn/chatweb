<?php
// System test script to verify all components are working
session_start();
include("connection.php");

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>System Test - Galaxy Chat</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .pass { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .fail { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warn { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
        .summary {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='test-container'>
        <h1>🧪 Galaxy Chat System Test</h1>
        <p>Testing all system components...</p>

        <div class='test-results'>";

$tests = [];
$passed = 0;
$failed = 0;
$warnings = 0;

// Test 1: Database Connection
echo "<div class='test-result info'>🔍 Testing database connection...</div>";
try {
    if ($conn && mysqli_ping($conn)) {
        echo "<div class='test-result pass'>✅ Database connection successful</div>";
        $tests[] = ['Database Connection', 'PASS'];
        $passed++;
    } else {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    echo "<div class='test-result fail'>❌ Database connection failed: " . $e->getMessage() . "</div>";
    $tests[] = ['Database Connection', 'FAIL'];
    $failed++;
}

// Test 2: Required Tables
echo "<div class='test-result info'>🔍 Checking required tables...</div>";
$required_tables = ['users', 'rooms', 'room_users', 'messages', 'typing_indicators', 'user_preferences', 'join_requests'];
foreach ($required_tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "<div class='test-result pass'>✅ Table '$table' exists</div>";
        $tests[] = ["Table: $table", 'PASS'];
        $passed++;
    } else {
        echo "<div class='test-result fail'>❌ Table '$table' missing</div>";
        $tests[] = ["Table: $table", 'FAIL'];
        $failed++;
    }
}

// Test 3: File Existence
echo "<div class='test-result info'>🔍 Checking required files...</div>";
$required_files = [
    'index.php', 'room.php', 'login.php', 'signup.php', 'logout.php',
    'welcome.php', 'connection.php', 'session_manager.php',
    'fetchmessages.php', 'postmsg.php', 'fetchonlineusers.php',
    'fetchtyping.php', 'updatetyping.php', 'updateonlinestatus.php',
    'get_room_members.php', 'save_room_settings.php', 'update_room_settings.php',
    'get_pending_requests.php', 'approve_request.php', 'deny_request.php',
    'invite_member.php', 'remove_member.php', 'make_admin.php',
    'save_preference.php', 'load_preferences.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<div class='test-result pass'>✅ File '$file' exists</div>";
        $tests[] = ["File: $file", 'PASS'];
        $passed++;
    } else {
        echo "<div class='test-result fail'>❌ File '$file' missing</div>";
        $tests[] = ["File: $file", 'FAIL'];
        $failed++;
    }
}

// Test 4: PHP Version
echo "<div class='test-result info'>🔍 Checking PHP version...</div>";
$php_version = PHP_VERSION;
if (version_compare($php_version, '7.0.0', '>=')) {
    echo "<div class='test-result pass'>✅ PHP version $php_version is compatible</div>";
    $tests[] = ['PHP Version', 'PASS'];
    $passed++;
} else {
    echo "<div class='test-result fail'>❌ PHP version $php_version is too old (requires 7.0+)</div>";
    $tests[] = ['PHP Version', 'FAIL'];
    $failed++;
}

// Test 5: Required PHP Extensions
echo "<div class='test-result info'>🔍 Checking PHP extensions...</div>";
$required_extensions = ['mysqli', 'mbstring', 'json', 'session'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='test-result pass'>✅ Extension '$ext' is loaded</div>";
        $tests[] = ["Extension: $ext", 'PASS'];
        $passed++;
    } else {
        echo "<div class='test-result fail'>❌ Extension '$ext' is not loaded</div>";
        $tests[] = ["Extension: $ext", 'FAIL'];
        $failed++;
    }
}

// Test 6: Directory Permissions
echo "<div class='test-result info'>🔍 Checking directory permissions...</div>";
$directories = ['uploads', 'uploads/avatars', 'uploads/room_photos'];
foreach ($directories as $dir) {
    if (file_exists($dir)) {
        if (is_writable($dir)) {
            echo "<div class='test-result pass'>✅ Directory '$dir' is writable</div>";
            $tests[] = ["Directory: $dir", 'PASS'];
            $passed++;
        } else {
            echo "<div class='test-result warn'>⚠️ Directory '$dir' is not writable</div>";
            $tests[] = ["Directory: $dir", 'WARN'];
            $warnings++;
        }
    } else {
        // Try to create directory
        if (mkdir($dir, 0755, true)) {
            echo "<div class='test-result pass'>✅ Created directory '$dir'</div>";
            $tests[] = ["Directory: $dir", 'PASS'];
            $passed++;
        } else {
            echo "<div class='test-result fail'>❌ Cannot create directory '$dir'</div>";
            $tests[] = ["Directory: $dir", 'FAIL'];
            $failed++;
        }
    }
}

// Test 7: Session Configuration
echo "<div class='test-result info'>🔍 Testing session configuration...</div>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<div class='test-result pass'>✅ Session is working</div>";
    $tests[] = ['Session', 'PASS'];
    $passed++;
} else {
    echo "<div class='test-result fail'>❌ Session is not working</div>";
    $tests[] = ['Session', 'FAIL'];
    $failed++;
}

// Summary
echo "</div><div class='summary'>";
echo "<h2>Test Summary</h2>";
echo "<p><strong>Total Tests:</strong> " . count($tests) . "</p>";
echo "<p><span style='color: green;'>✅ Passed: $passed</span></p>";
if ($failed > 0) {
    echo "<p><span style='color: red;'>❌ Failed: $failed</span></p>";
}
if ($warnings > 0) {
    echo "<p><span style='color: orange;'>⚠️ Warnings: $warnings</span></p>";
}

if ($failed === 0) {
    echo "<div class='test-result pass'>🎉 All critical tests passed! Your Galaxy Chat system is ready to use.</div>";
    echo "<p><a href='welcome.php' style='color: #007bff; text-decoration: none; font-weight: bold;'>→ Start Using Galaxy Chat</a></p>";
} else {
    echo "<div class='test-result fail'>⚠️ Some tests failed. Please fix the issues above before using the system.</div>";
    echo "<p><a href='db_setup.php' style='color: #007bff; text-decoration: none; font-weight: bold;'>→ Run Database Setup</a></p>";
}

echo "</div></div></body></html>";
?>