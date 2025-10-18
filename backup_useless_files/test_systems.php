<?php
include("connection.php");
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Galaxy Chat Systems</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        h2 { margin-top: 0; color: #333; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 Galaxy Chat Systems Test</h1>
        <p>This page tests all the advanced features of your chat system.</p>";

echo "<div class='test-section info'>
    <h2>📊 System Status</h2>";

// Test database connection
if ($conn) {
    echo "<div class='test-result success'>✅ Database connection: OK</div>";
} else {
    echo "<div class='test-result error'>❌ Database connection: FAILED</div>";
}

// Test required tables
$required_tables = ['users', 'rooms', 'messages', 'room_users', 'user_preferences'];
foreach ($required_tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "<div class='test-result success'>✅ Table '$table' exists</div>";
    } else {
        echo "<div class='test-result error'>❌ Table '$table' missing</div>";
    }
}

// Test required PHP files
$required_files = [
    'admin.php',
    'fetchmessages.php',
    'updatetyping.php',
    'fetchtyping.php',
    'uploadfile.php',
    'fetchfiles.php',
    'updateonlinestatus.php',
    'fetchonlineusers.php',
    'save_preference.php',
    'load_preferences.php',
    'create_user_preferences.php',
    'update_room_settings.php',
    'update_room_creators.php',
    'add_room_columns.php',
    'debug_room_issues.php',
    'cleanup_files.php',
    'fix_file_rooms.php',
    'test_file_filtering.php',
    'test_room_settings.php'
];

echo "<h3>PHP Files Check:</h3>";
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<div class='test-result success'>✅ $file exists</div>";
    } else {
        echo "<div class='test-result error'>❌ $file missing</div>";
    }
}

echo "</div>";

echo "<div class='test-section info'>
    <h2>🚀 Quick Tests</h2>
    <p>Click these buttons to test different features:</p>
    <button onclick=\"window.open('diagnostics.php', '_blank')\">Run Full Diagnostics</button>
    <button onclick=\"window.open('index.php', '_blank')\">Open Chat</button>
    <button onclick=\"window.open('admin.php', '_blank')\">Admin Panel</button>
    <button onclick=\"testAjax('fetchmessages.php?roomname=test&last_id=0')\">Test Message Fetch</button>
    <button onclick=\"testAjax('fetchonlineusers.php?roomname=test')\">Test Online Users</button>
</div>";

echo "<div class='test-section warning'>
    <h2>🔧 Troubleshooting</h2>
    <h3>If systems aren't working:</h3>
    <ol>
        <li><strong>Run Database Migration:</strong> Visit <code>diagnostics.php</code> and use the migration tool</li>
        <li><strong>Check File Permissions:</strong> Ensure PHP files are executable (644 permissions)</li>
        <li><strong>Browser Cache:</strong> Clear browser cache and try again</li>
        <li><strong>Check Console:</strong> Open browser developer tools (F12) and check for JavaScript errors</li>
        <li><strong>Database Issues:</strong> Verify your MySQL credentials in connection.php</li>
    </ol>
</div>";

echo "<div class='test-section success'>
    <h2>✨ Expected Behavior</h2>
    <ul>
        <li><strong>Online Status:</strong> Users should appear in header and floating panel</li>
        <li><strong>File Upload:</strong> Use 📁 button to share files</li>
        <li><strong>Real-time Updates:</strong> Messages and status should update automatically</li>
    </ul>
</div>";

echo "<script>
function testAjax(url) {
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('AJAX Test Result:', data);
            alert('AJAX test completed! Check browser console for details.');
        })
        .catch(error => {
            console.error('AJAX Test Error:', error);
            alert('AJAX test failed! Check console for details.');
        });
}
</script>";

echo "    </div>
</body>
</html>";
?>