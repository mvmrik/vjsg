<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"="width=device-width, initial-scale=1.0">
    <title>Влизане - Resource Legends</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-2xl font-bold text-center mb-6">Влизане</h1>
        
        <form action="{{ route('login.submit') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    Частен ключ
                </label>
                <input type="text" name="private_key" class="w-full px-3 py-2 border rounded focus:outline-none focus:border-blue-500" 
                       placeholder="64-символен частен ключ" maxlength="64" required>
            </div>
            
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                Влез
            </button>
        </form>
        
        <div class="text-center mt-4">
            <a href="{{ route('homepage') }}" class="text-blue-500 hover:underline">← Назад към началото</a>
        </div>
    </div>
</body>
</html>