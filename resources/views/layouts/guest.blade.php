<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'PLN Learning Hub' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pln: { primary: '#035B71', light: '#00A2B9' }
                    },
                    animation: { 'slow-zoom': 'kenburns 20s infinite alternate' },
                    keyframes: {
                        kenburns: {
                            '0%': { transform: 'scale(1)' },
                            '100%': { transform: 'scale(1.1)' }
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <nav class="absolute top-0 left-0 w-full z-50 border-b border-white/10 bg-white/5 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-pln-light rounded-xl flex items-center justify-center text-white font-bold shadow-lg shadow-pln-light/20">⚡</div>
                    <span class="font-bold text-xl tracking-tight text-white drop-shadow-md">PLN Learning Hub</span>
                </div>
                <div class="hidden md:flex items-center gap-6">
                    <a href="#" class="text-sm font-medium text-gray-200 hover:text-white transition">Home</a>
                    <a href="#" class="text-sm font-medium text-gray-200 hover:text-white transition">Company Profile</a>
                    
                    <a href="{{ route('login') }}" class="px-6 py-2.5 bg-white text-pln-primary font-bold rounded-full hover:bg-gray-100 transition shadow-lg">
                        Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow">
        {{ $slot }}
    </main>

    <footer class="bg-gray-900 text-white/40 py-8 text-center text-sm border-t border-white/5">
        <p>&copy; 2026 PT PLN (Persero). Learning Hub.</p>
    </footer>
</body>
</html>