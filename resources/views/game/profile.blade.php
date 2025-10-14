<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Профил - Resource Legends</title>
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
        .resource-button {
            transition: all 0.3s ease;
            transform: scale(1);
        }
        .resource-button:hover {
            transform: scale(1.05);
        }
        .resource-button:active {
            transform: scale(0.95);
        }
        .progress-bar {
            background: linear-gradient(90deg, #4ade80, #22c55e);
            transition: width 0.5s ease;
        }
        .collecting {
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Header -->
    <nav class="game-card m-4 rounded-xl p-4 shadow-lg">
        <div class="flex flex-wrap justify-between items-center text-white">
            <div class="flex items-center space-x-4">
                <h1 class="text-2xl font-bold">🏰 Resource Legends</h1>
                <span class="text-lg">Добре дошли, {{ $user->username }}!</span>
            </div>
            <div class="flex items-center space-x-4 mt-2 md:mt-0">
                <div class="text-sm bg-white/20 px-3 py-1 rounded-full">
                    Ниво {{ $user->level }} 
                </div>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg text-sm transition-colors">
                        🚪 Изход
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 pb-8">
        <!-- Player Stats -->
        <div class="grid md:grid-cols-3 gap-4 mb-6">
            <!-- Level & Experience -->
            <div class="game-card rounded-xl p-6 text-white">
                <h3 class="text-xl font-bold mb-4 flex items-center">
                    <span class="mr-2">⚡</span> Опит и Ниво
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span>Ниво:</span>
                        <span class="font-bold text-2xl">{{ $user->level }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Опит:</span>
                        <span class="font-bold">{{ $user->experience }}/{{ $user->level * 100 }}</span>
                    </div>
                    <div class="w-full bg-white/20 rounded-full h-3">
                        <div class="progress-bar h-3 rounded-full" 
                             style="width: {{ ($user->experience / ($user->level * 100)) * 100 }}%"></div>
                    </div>
                </div>
            </div>

            <!-- Resources -->
            <div class="game-card rounded-xl p-6 text-white">
                <h3 class="text-xl font-bold mb-4 flex items-center">
                    <span class="mr-2">📦</span> Ресурси
                </h3>
                <div id="resourcesDisplay" class="grid grid-cols-2 gap-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span>🪙 Злато:</span>
                        <span class="font-bold" id="gold">{{ $user->resources['gold'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>🪵 Дърво:</span>
                        <span class="font-bold" id="wood">{{ $user->resources['wood'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>🪨 Камък:</span>
                        <span class="font-bold" id="stone">{{ $user->resources['stone'] ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>🍞 Храна:</span>
                        <span class="font-bold" id="food">{{ $user->resources['food'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Player Info -->
            <div class="game-card rounded-xl p-6 text-white">
                <h3 class="text-xl font-bold mb-4 flex items-center">
                    <span class="mr-2">🔑</span> Информация
                </h3>
                <div class="space-y-2 text-sm">
                    <div>
                        <span class="text-white/80">Публичен ключ:</span>
                        <div class="font-mono bg-white/10 p-2 rounded mt-1 break-all text-xs">
                            {{ $user->public_key }}
                        </div>
                    </div>
                    <div class="text-xs text-white/60">
                        Последна активност: {{ $user->last_active ? $user->last_active->diffForHumans() : 'Никога' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Resource Collection Area -->
        <div class="game-card rounded-xl p-8">
            <h2 class="text-3xl font-bold text-white mb-6 text-center">
                🏗️ Събиране на ресурси
            </h2>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Gold Mining -->
                <div class="bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-xl p-6 text-white shadow-lg">
                    <div class="text-center mb-4">
                        <div class="text-4xl mb-2">🪙</div>
                        <h3 class="text-xl font-bold">Злато</h3>
                        <p class="text-sm opacity-90">Добийте ценно злато</p>
                    </div>
                    <button class="resource-button w-full bg-white/20 hover:bg-white/30 py-3 px-4 rounded-lg font-semibold"
                            onclick="collectResource('gold')">
                        ⛏️ Добиване
                    </button>
                </div>

                <!-- Wood Gathering -->
                <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl p-6 text-white shadow-lg">
                    <div class="text-center mb-4">
                        <div class="text-4xl mb-2">🪵</div>
                        <h3 class="text-xl font-bold">Дърво</h3>
                        <p class="text-sm opacity-90">Секачество в гората</p>
                    </div>
                    <button class="resource-button w-full bg-white/20 hover:bg-white/30 py-3 px-4 rounded-lg font-semibold"
                            onclick="collectResource('wood')">
                        🪓 Рубене
                    </button>
                </div>

                <!-- Stone Mining -->
                <div class="bg-gradient-to-br from-gray-500 to-gray-700 rounded-xl p-6 text-white shadow-lg">
                    <div class="text-center mb-4">
                        <div class="text-4xl mb-2">🪨</div>
                        <h3 class="text-xl font-bold">Камък</h3>
                        <p class="text-sm opacity-90">Каменоломия</p>
                    </div>
                    <button class="resource-button w-full bg-white/20 hover:bg-white/30 py-3 px-4 rounded-lg font-semibold"
                            onclick="collectResource('stone')">
                        🔨 Копаене
                    </button>
                </div>

                <!-- Food Gathering -->
                <div class="bg-gradient-to-br from-orange-400 to-orange-600 rounded-xl p-6 text-white shadow-lg">
                    <div class="text-center mb-4">
                        <div class="text-4xl mb-2">🍞</div>
                        <h3 class="text-xl font-bold">Храна</h3>
                        <p class="text-sm opacity-90">Събиране на храна</p>
                    </div>
                    <button class="resource-button w-full bg-white/20 hover:bg-white/30 py-3 px-4 rounded-lg font-semibold"
                            onclick="collectResource('food')">
                        🌾 Събиране
                    </button>
                </div>
            </div>

            <!-- Collection Status -->
            <div id="collectionStatus" class="mt-6 text-center">
                <div id="statusMessage" class="hidden p-4 rounded-lg text-white font-semibold"></div>
            </div>
        </div>

        <!-- Game Instructions -->
        <div class="game-card rounded-xl p-6 mt-6 text-white">
            <h3 class="text-xl font-bold mb-4">📖 Инструкции</h3>
            <div class="grid md:grid-cols-2 gap-4 text-sm">
                <div>
                    <h4 class="font-semibold mb-2">Как да играя:</h4>
                    <ul class="space-y-1 text-white/80">
                        <li>• Натиснете бутоните за събиране на ресурси</li>
                        <li>• Всяко събиране дава опит за повишаване на ниво</li>
                        <li>• По-високо ниво = повече възможности</li>
                        <li>• Запазвайте профила си сигурен с частния ключ</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Ресурси:</h4>
                    <ul class="space-y-1 text-white/80">
                        <li>• 🪙 Злато - най-ценният ресурс</li>
                        <li>• 🪵 Дърво - за строителство</li>
                        <li>• 🪨 Камък - за укрепления</li>
                        <li>• 🍞 Храна - за оцеляване</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        let isCollecting = false;

        async function collectResource(resourceType) {
            if (isCollecting) return;
            
            isCollecting = true;
            const amount = Math.floor(Math.random() * 10) + 1; // Random 1-10
            
            // Show collecting animation
            const statusDiv = document.getElementById('collectionStatus');
            const messageDiv = document.getElementById('statusMessage');
            messageDiv.className = 'p-4 rounded-lg text-white font-semibold collecting bg-blue-500/50';
            messageDiv.textContent = `Събиране на ${resourceType}... ⏳`;
            messageDiv.classList.remove('hidden');
            
            try {
                const response = await fetch('/api/collect-resource', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        resource_type: resourceType,
                        amount: amount
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update resources display
                    updateResourcesDisplay(data.resources);
                    
                    // Update level and experience
                    updateLevelDisplay(data.level, data.experience);
                    
                    // Show success message
                    messageDiv.className = 'p-4 rounded-lg text-white font-semibold bg-green-500';
                    messageDiv.textContent = `✅ ${data.message} (+${amount * 2} опит)`;
                } else {
                    messageDiv.className = 'p-4 rounded-lg text-white font-semibold bg-red-500';
                    messageDiv.textContent = `❌ ${data.message}`;
                }
            } catch (error) {
                messageDiv.className = 'p-4 rounded-lg text-white font-semibold bg-red-500';
                messageDiv.textContent = '❌ Възникна грешка при събирането на ресурс';
            }
            
            // Hide message after 3 seconds
            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 3000);
            
            // Reset collecting state after 2 seconds
            setTimeout(() => {
                isCollecting = false;
            }, 2000);
        }

        function updateResourcesDisplay(resources) {
            document.getElementById('gold').textContent = resources.gold || 0;
            document.getElementById('wood').textContent = resources.wood || 0;
            document.getElementById('stone').textContent = resources.stone || 0;
            document.getElementById('food').textContent = resources.food || 0;
        }

        function updateLevelDisplay(level, experience) {
            // Update level display
            document.querySelector('.text-2xl').textContent = level;
            
            // Update experience
            const expDisplay = document.querySelector('.font-bold:not(.text-2xl)');
            if (expDisplay) {
                expDisplay.textContent = `${experience}/${level * 100}`;
            }
            
            // Update progress bar
            const progressBar = document.querySelector('.progress-bar');
            const percentage = (experience / (level * 100)) * 100;
            progressBar.style.width = `${percentage}%`;
        }

        // Auto-refresh user data every 30 seconds
        setInterval(async () => {
            try {
                const response = await fetch('/api/user-data');
                const data = await response.json();
                
                if (data.success) {
                    updateResourcesDisplay(data.user.resources);
                    updateLevelDisplay(data.user.level, data.user.experience);
                }
            } catch (error) {
                console.log('Auto-refresh failed');
            }
        }, 30000);
    </script>
</body>
</html>