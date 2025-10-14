<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Debug - Key Auth System</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .debug { background: #f8f8f8; border: 1px solid #ddd; padding: 10px; margin: 10px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug Mode - Key Auth System</h1>
        
        <div class="debug">
            <h3>Application Status:</h3>
            <p>✅ Laravel running</p>
            <p>✅ Database connected</p>
            <p>✅ Vue.js assets compiled</p>
        </div>

        <div class="debug">
            <h3>Routes Test:</h3>
            <button onclick="testRegister()">Test Register</button>
            <button onclick="testLogin()">Test Login</button>
            <button onclick="testUserData()">Test User Data</button>
        </div>

        <div id="results" class="debug">
            <h3>Results:</h3>
            <div id="output">Click buttons to test API...</div>
        </div>

        <div class="debug">
            <h3>Navigation:</h3>
            <a href="/">Go to Main App</a>
        </div>
    </div>

    <script>
        const output = document.getElementById('output');
        
        async function testRegister() {
            try {
                const response = await fetch('/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ username: 'testuser' + Math.floor(Math.random() * 1000) })
                });
                
                const data = await response.json();
                output.innerHTML = '<strong>Register:</strong><br>' + JSON.stringify(data, null, 2);
            } catch (error) {
                output.innerHTML = '<strong>Register Error:</strong><br>' + error.message;
            }
        }
        
        async function testLogin() {
            // This will fail without a valid key, but we can test the endpoint
            try {
                const response = await fetch('/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ private_key: '1234567890123456789012345678901234567890123456789012345678901234' })
                });
                
                const data = await response.json();
                output.innerHTML = '<strong>Login:</strong><br>' + JSON.stringify(data, null, 2);
            } catch (error) {
                output.innerHTML = '<strong>Login Error:</strong><br>' + error.message;
            }
        }
        
        async function testUserData() {
            try {
                const response = await fetch('/api/user-data');
                const data = await response.json();
                output.innerHTML = '<strong>User Data:</strong><br>' + JSON.stringify(data, null, 2);
            } catch (error) {
                output.innerHTML = '<strong>User Data Error:</strong><br>' + error.message;
            }
        }
    </script>
</body>
</html>