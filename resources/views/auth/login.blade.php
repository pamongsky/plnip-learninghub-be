<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - PLN Learning Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pln: { primary: '#035B71', light: '#00A2B9' }
                    }
                }
            }
        }
    </script>
    <style> @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap'); body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="h-screen w-full overflow-hidden flex items-center justify-center bg-gray-900 relative">

    <div class="fixed inset-0 z-0">
        <img src="https://www.ge.com/news/sites/default/files/Egemer%201.jpg" 
             class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-gray-900/60"></div> </div>

    <div class="relative z-10 w-full max-w-3xl bg-white rounded-2xl shadow-2xl flex overflow-hidden m-4 animate-fade-in-up">
        
        <div class="w-1/2 bg-pln-primary hidden md:flex flex-col justify-center p-10 relative text-white">
            <div class="absolute inset-0 z-0">
                <img src="https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?q=80&w=2070&auto=format&fit=crop" class="w-full h-full object-cover opacity-20 mix-blend-overlay">
            </div>
            <div class="relative z-10">
                <div class="w-12 h-12 bg-pln-light rounded-xl flex items-center justify-center text-2xl mb-6 shadow-lg">⚡</div>
                
                <h2 class="text-3xl font-bold mb-4 leading-tight">Welcome to <br>PLN Learning Hub</h2>
                
                <p class="text-gray-200 text-sm leading-relaxed opacity-90">
                    Akses materi pembelajaran, sertifikasi kompetensi, dan pengembangan karir insan PLN.
                </p>
                
                <div class="mt-8 flex items-center gap-4 text-xs text-pln-light font-semibold">
                    <span>© 2026 Corporate</span>
                </div>
            </div>
        </div>

        <div class="w-full md:w-1/2 p-8 md:p-12 flex flex-col justify-center bg-white">
             <div class="mb-6"> <h3 class="text-2xl font-bold text-gray-900 mb-1">Sign In</h3>
                <p class="text-sm text-gray-500">Masukkan akun korporat Anda.</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="space-y-4"> @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5 ml-1">Email / NIP</label>
                    <input type="email" name="email" required autofocus
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-pln-light focus:ring-4 focus:ring-pln-light/10 outline-none transition bg-gray-50 hover:bg-white text-sm"
                        placeholder="nama@pln.co.id">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5 ml-1">Password</label>
                    <input type="password" name="password" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-pln-light focus:ring-4 focus:ring-pln-light/10 outline-none transition bg-gray-50 hover:bg-white text-sm"
                        placeholder="••••••••">
                </div>
                
                <button type="submit" class="w-full py-3 mt-2 bg-pln-primary hover:bg-pln-light text-white font-bold rounded-lg shadow-lg shadow-pln-primary/30 transition transform hover:-translate-y-1 text-sm">
                    MASUK SEKARANG
                </button>
            </form>
        </div>
    </div>

</body>
</html>