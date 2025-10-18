<?php
// Basic test without database connection
echo "<!DOCTYPE html>
<html>
<head>
    <title>Basic Chat System Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        h2 { margin-top: 0; color: #333; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 Basic Chat System Test</h1>
        <p>Testing basic functionality without database connection.</p>";

echo "<div class='test-section success'>
    <h2>✅ System Status</h2>
    <p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>
    <p><strong>Server Software:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>
    <p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>
    <p><strong>Current File:</strong> " . __FILE__ . "</p>
</div>";

echo "<div class='test-section info'>
    <h2>📁 File System Test</h2>";

// Test if key files exist
$key_files = [
    'index.php' => 'Main chat interface',
    'login.php' => 'User login system',
    'signup.php' => 'User registration',
    'room.php' => 'Chat room interface',
    'connection.php' => 'Database connection',
    'welcome.php' => 'Welcome page'
];

foreach ($key_files as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $file - $description</div>";
    } else {
        echo "<div class='error'>❌ $file - $description (MISSING)</div>";
    }
}

echo "</div>";

echo "<div class='test-section warning'>
    <h2>🔧 Next Steps</h2>
    <p>To fully test the system, you need to:</p>
    <ol>
        <li><strong>Fix MySQL Connection:</strong> Update connection.php with correct credentials</li>
        <li><strong>Create Database:</strong> Run db_setup.php once connection is fixed</li>
        <li><strong>Test User Registration:</strong> Visit signup.php</li>
        <li><strong>Test Chat Rooms:</strong> Use index.php to create/join rooms</li>
    </ol>
</div>";

echo "<div class='test-section info'>
    <h2>🚀 Quick Access</h2>
    <p><a href='index.php' style='color: #007bff;'>→ Main Chat Interface</a></p>
    <p><a href='welcome.php' style='color: #007bff;'>→ Welcome Page</a></p>
    <p><a href='signup.php' style='color: #007bff;'>→ User Registration</a></p>
    <p><a href='login.php' style='color: #007bff;'>→ User Login</a></p>
</div>";

echo "    </div>
</body>
</html>";
?>