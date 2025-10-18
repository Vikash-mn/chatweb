<?php
// Test UI components without database
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>UI Components Test - Galaxy Chat</title>
    <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&display=swap' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: radial-gradient(ellipse at bottom, #1B2735 0%, #090A0F 100%);
            color: #ffffff;
            font-family: 'Montserrat', 'Arial', sans-serif;
            padding: 20px;
        }

        .test-container {
            background: rgba(13, 13, 39, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: #ffffff;
        }

        .test-section {
            margin: 2rem 0;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
        }

        .test-section h2 {
            color: rgba(0, 212, 255, 0.8);
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            background: rgba(20, 20, 50, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: white;
            font-size: 0.95rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: rgba(0, 212, 255, 0.5);
            background: rgba(30, 30, 70, 0.7);
        }

        .btn {
            padding: 1rem;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.8), rgba(9, 9, 121, 0.8));
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            margin: 0.5rem 0;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 212, 255, 0.2);
        }

        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        .demo-form {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class='test-container'>
        <h1>🎨 Galaxy Chat UI Test</h1>
        <p style='text-align: center; margin-bottom: 2rem; color: rgba(255,255,255,0.7);'>Testing UI components without database</p>";

echo "<div class='test-section'>
    <h2>📝 Form Components</h2>
    <div class='demo-form'>
        <form>
            <div class='form-group'>
                <label for='test-username'>Username:</label>
                <input type='text' id='test-username' placeholder='Enter username' value='testuser'>
            </div>

            <div class='form-group'>
                <label for='test-email'>Email:</label>
                <input type='email' id='test-email' placeholder='Enter email' value='test@example.com'>
            </div>

            <div class='form-group'>
                <label for='test-password'>Password:</label>
                <input type='password' id='test-password' placeholder='Enter password' value='password123'>
            </div>

            <button type='button' class='btn' onclick='alert(\"Form validation works!\")'>Test Submit</button>
        </form>
    </div>
</div>";

echo "<div class='test-section'>
    <h2>🎯 Interactive Elements</h2>
    <button class='btn' onclick='showMessage(\"success\", \"✅ Success message works!\")'>Test Success</button>
    <button class='btn' onclick='showMessage(\"error\", \"❌ Error message works!\")'>Test Error</button>
    <button class='btn' onclick='showMessage(\"info\", \"ℹ️ Info message works!\")'>Test Info</button>
</div>";

echo "<div class='test-section'>
    <h2>📱 Responsive Test</h2>
    <p>Resize your browser window to test responsive design.</p>
    <div style='background: rgba(0, 212, 255, 0.1); padding: 1rem; border-radius: 8px; margin: 1rem 0;'>
        <strong>Current viewport:</strong> <span id='viewport-size'></span>
    </div>
</div>";

echo "<div class='test-section'>
    <h2>🔧 System Information</h2>
    <ul style='color: rgba(255,255,255,0.8); line-height: 1.6;'>
        <li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>
        <li><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</li>
        <li><strong>User Agent:</strong> " . $_SERVER['HTTP_USER_AGENT'] . "</li>
        <li><strong>Request Time:</strong> " . date('Y-m-d H:i:s') . "</li>
    </ul>
</div>";

echo "<script>
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
        `;
        msgDiv.textContent = message;

        document.body.appendChild(msgDiv);

        // Remove after 3 seconds
        setTimeout(() => {
            msgDiv.remove();
        }, 3000);
    }

    function updateViewportSize() {
        document.getElementById('viewport-size').textContent =
            window.innerWidth + 'x' + window.innerHeight;
    }

    updateViewportSize();
    window.addEventListener('resize', updateViewportSize);
</script>
</body>
</html>";
?>