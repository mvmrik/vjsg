<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Resource Legends - Онлайн игра за събиране на ресурси</title>
    @vite('resources/css/app.css')
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .game-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .resource-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #f39c12, #e74c3c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-6xl font-bold text-white mb-4 drop-shadow-lg">
                🏰 Resource Legends
            </h1>
            <p class="text-xl text-white/90 mb-8">
                Добре дошли в света на събирането на ресурси! Създайте профил и започнете своето приключение.
            </p>
        </div>

        <!-- Main Game Card -->
        <div class="max-w-4xl mx-auto game-card rounded-2xl p-8 shadow-2xl">
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Left Side - Game Info -->
                <div class="text-white">
                    <h2 class="text-3xl font-bold mb-6">🎮 Как да играете?</h2>
                    
                    <div class="space-y-6">
                        <div class="flex items-start space-x-4">
                            <div class="resource-icon">
                                <span>🏗️</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Събирайте ресурси</h3>
                                <p class="text-white/80">Добивайте злато, дърво, камък и храна</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="resource-icon">
                                <span>⚡</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Повишавайте ниво</h3>
                                <p class="text-white/80">Събирайте опит и ставайте по-силни</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="resource-icon">
                                <span>🔑</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Уникални ключове</h3>
                                <p class="text-white/80">Всеки играч има публичен и частен ключ</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resource Display -->
                    <div class="mt-8 p-4 bg-white/10 rounded-lg">
                        <h4 class="text-lg font-semibold mb-3">Налични ресурси:</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="flex items-center"><span class="mr-2">🪙</span> Злато</div>
                            <div class="flex items-center"><span class="mr-2">🪵</span> Дърво</div>
                            <div class="flex items-center"><span class="mr-2">🪨</span> Камък</div>
                            <div class="flex items-center"><span class="mr-2">🍞</span> Храна</div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Auth Forms -->
                <div class="bg-white rounded-xl p-6 shadow-lg">
                    <!-- Navigation Tabs -->
                    <div class="flex mb-6 bg-gray-100 rounded-lg p-1">
                        <button id="loginTab" class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors bg-blue-600 text-white">
                            Влизане
                        </button>
                        <button id="registerTab" class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-blue-600">
                            Регистрация
                        </button>
                    </div>

                    <!-- Login Form -->
                    <div id="loginForm">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">Влезте в профила си</h3>
                        <form id="loginFormSubmit" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Частен ключ</label>
                                <input type="text" id="loginPrivateKey" name="private_key" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Въведете вашия 64-символен частен ключ" 
                                       maxlength="64" required>
                            </div>
                            <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                                🔓 Влизане
                            </button>
                        </form>
                    </div>

                    <!-- Register Form -->
                    <div id="registerForm" class="hidden">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">Създайте нов профил</h3>
                        <form id="registerFormSubmit" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Потребителско име</label>
                                <input type="text" id="registerUsername" name="username" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                       placeholder="Изберете уникално потребителско име" required>
                            </div>
                            <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition-colors">
                                🎯 Създай профил
                            </button>
                        </form>
                        
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                            <p class="text-sm text-yellow-800">
                                <strong>⚠️ Важно:</strong> След регистрация ще получите публичен и частен ключ. 
                                Запазете ги сигурно - частният ключ е нужен за влизане!
                            </p>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div id="messageArea" class="mt-4 hidden"></div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-white/80">
            <p>&copy; 2025 Resource Legends. Онлайн игра за събиране на ресурси.</p>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Tab switching
        document.getElementById('loginTab').addEventListener('click', function() {
            showLogin();
        });
        
        document.getElementById('registerTab').addEventListener('click', function() {
            showRegister();
        });
        
        function showLogin() {
            document.getElementById('loginForm').classList.remove('hidden');
            document.getElementById('registerForm').classList.add('hidden');
            document.getElementById('loginTab').className = 'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors bg-blue-600 text-white';
            document.getElementById('registerTab').className = 'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-blue-600';
        }
        
        function showRegister() {
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('registerForm').classList.remove('hidden');
            document.getElementById('registerTab').className = 'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors bg-green-600 text-white';
            document.getElementById('loginTab').className = 'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors text-gray-600 hover:text-green-600';
        }

        // Form submissions
        document.getElementById('loginFormSubmit').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const privateKey = document.getElementById('loginPrivateKey').value;
            
            if (privateKey.length !== 64) {
                showMessage('Частният ключ трябва да е точно 64 символа.', 'error');
                return;
            }
            
            try {
                const response = await fetch('{{ route("login.submit") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ private_key: privateKey })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (error) {
                showMessage('Възникна грешка при влизането.', 'error');
            }
        });
        
        document.getElementById('registerFormSubmit').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('registerUsername').value;
            
            if (username.length < 3) {
                showMessage('Потребителското име трябва да е поне 3 символа.', 'error');
                return;
            }
            
            try {
                const response = await fetch('{{ route("register.submit") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ username: username })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(
                        `✅ ${data.message}<br><br>
                        <strong>📱 Вашите ключове:</strong><br>
                        <strong>Публичен:</strong> ${data.user.public_key}<br>
                        <strong>Частен:</strong> ${data.user.private_key}<br><br>
                        ⚠️ Запазете частния ключ сигурно - нужен е за влизане!`, 
                        'success'
                    );
                    document.getElementById('registerFormSubmit').reset();
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (error) {
                showMessage('Възникна грешка при регистрацията.', 'error');
            }
        });
        
        function showMessage(message, type) {
            const messageArea = document.getElementById('messageArea');
            messageArea.className = `mt-4 p-3 rounded-md ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'}`;
            messageArea.innerHTML = message;
            messageArea.classList.remove('hidden');
            
            if (type === 'error') {
                setTimeout(() => {
                    messageArea.classList.add('hidden');
                }, 5000);
            }
        }
    </script>
</body>
</html>