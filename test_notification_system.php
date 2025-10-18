<?php
session_start();
include("connection.php");

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Notification System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        h2 { margin-top: 0; color: #333; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 Notification System Test</h1>
        <p>This page tests all components of the notification system.</p>";

echo "<div class='test-section info'>
    <h2>📊 Database Check</h2>";

// Check if join_requests table exists
$table_query = "SHOW TABLES LIKE 'join_requests'";
$table_result = mysqli_query($conn, $table_query);
if (mysqli_num_rows($table_result) > 0) {
    echo "<div class='test-result success'>✅ join_requests table exists</div>";
} else {
    echo "<div class='test-result error'>❌ join_requests table missing</div>";
    echo "<p><a href='create_join_requests_table.php' class='btn'>Create Table</a></p>";
}

// Check table structure
$structure_query = "DESCRIBE join_requests";
$structure_result = mysqli_query($conn, $structure_query);
if ($structure_result) {
    echo "<h3>Table Structure:</h3><table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = mysqli_fetch_assoc($structure_result)) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='test-result error'>❌ Cannot read table structure</div>";
}

echo "</div>";

echo "<div class='test-section info'>
    <h2>🔧 File System Check</h2>";

// Check if required PHP files exist
$required_files = [
    'invite_member.php',
    'approve_request.php',
    'deny_request.php',
    'get_pending_requests.php'
];

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
    <p>Test the notification system:</p>
    <button onclick=\"window.open('room.php?roomname=testroom', '_blank')\" class='btn'>Test Room (as Creator)</button>
    <button onclick=\"window.open('index.php', '_blank')\" class='btn'>Main Chat</button>
    <button onclick=\"testInvite()\" class='btn'>Test Invite Function</button>
</div>";

echo "<div class='test-section warning'>
    <h2>🔧 Manual Testing Steps</h2>
    <ol>
        <li><strong>Create a test room:</strong> Visit room.php?roomname=testroom</li>
        <li><strong>Open room settings:</strong> Click ⚙️ Settings button</li>
        <li><strong>Go to Members tab:</strong> Click 👥 Members</li>
        <li><strong>Send invitation:</strong> Enter a username and click Invite</li>
        <li><strong>Check notifications:</strong> Look for 🔔 bell icon with red badge</li>
        <li><strong>Click notification bell:</strong> Should show pending requests</li>
        <li><strong>Approve/Deny:</strong> Use ✅ or ❌ buttons</li>
    </ol>
</div>";

echo "<script>
function testInvite() {
    // Test the invite function
    fetch('invite_member.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'roomname=testroom&username=testuser'
    })
    .then(response => response.json())
    .then(data => {
        console.log('Invite test result:', data);
        alert('Invite test completed! Check browser console for details.');
    })
    .catch(error => {
        console.error('Invite test error:', error);
        alert('Invite test failed! Check console for details.');
    });
}
</script>";

echo "<div class='test-section success'>
    <h2>✨ Expected Behavior</h2>
    <ul>
        <li><strong>Database:</strong> join_requests table should exist with proper structure</li>
        <li><strong>Files:</strong> All notification PHP files should exist</li>
        <li><strong>UI:</strong> Notification bell should appear for room creators</li>
        <li><strong>Functionality:</strong> Invitations should create requests, not direct access</li>
        <li><strong>Notifications:</strong> Bell should show count and panel should display requests</li>
        <li><strong>Actions:</strong> Approve/Deny buttons should work properly</li>
    </ul>
</div>";

echo "    </div>
</body>
</html>";
?>