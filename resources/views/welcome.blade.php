<x-guest-layout title="PLN Learning Hub - Future Ready">

    <div class="relative pt-24 pb-32 lg:pt-32 lg:pb-40 overflow-hidden bg-pln-primary">
        <div class="absolute inset-0">
            <img src="https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?q=80&w=2070&auto=format&fit=crop" class="w-full h-full object-cover opacity-20 animate-slow-zoom">
            <div class="absolute inset-0 bg-gradient-to-r from-pln-primary via-pln-primary/80 to-transparent"></div>
        </div>
        
        <div class="relative z-10 max-w-6xl mx-auto px-6 text-center lg:text-left">
            <span class="inline-block py-1 px-3 rounded-full bg-white/10 border border-white/20 text-pln-light text-[10px] font-bold tracking-widest mb-4 backdrop-blur-md uppercase">
                Corporate University
            </span>
            
            <h1 class="text-4xl lg:text-6xl font-extrabold text-white tracking-tight mb-4 leading-tight">
                Building Energy <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-pln-light to-white">Heroes of Tomorrow</span>
            </h1>
            
            <p class="max-w-lg text-base text-gray-200 mb-8 leading-relaxed lg:mx-0 mx-auto font-light">
                Platform pengembangan kompetensi terintegrasi. Wujudkan talenta PLN yang unggul, inovatif, dan siap menghadapi transisi energi global.
            </p>
            
            <div class="flex flex-wrap gap-3 justify-center lg:justify-start">
                <a href="{{ route('login') }}" class="px-6 py-3 bg-pln-light text-white font-bold rounded-xl shadow-lg hover:bg-white hover:text-pln-primary transition transform hover:-translate-y-0.5 text-sm">
                    Mulai Belajar
                </a>
                <a href="#about" class="px-6 py-3 border border-white text-white font-bold rounded-xl hover:bg-white/10 transition text-sm">
                    Pelajari Lebih Lanjut
                </a>
            </div>
        </div>
    </div>

    <div class="relative z-20 max-w-6xl mx-auto px-6 -mt-20 mb-16">
        <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-pln-light grid md:grid-cols-3 gap-6 text-center md:text-left">
            <div class="group p-3 hover:bg-gray-50 rounded-lg transition">
                <div class="w-10 h-10 bg-pln-light/10 text-pln-light rounded-lg flex items-center justify-center text-xl mb-3">📚</div>
                <h3 class="text-lg font-bold text-gray-900">Digital Learning</h3>
                <p class="text-gray-500 text-xs mt-1 leading-relaxed">Ribuan modul teknis & non-teknis yang dapat diakses kapan saja.</p>
            </div>
            <div class="group p-3 hover:bg-gray-50 rounded-lg transition">
                <div class="w-10 h-10 bg-pln-light/10 text-pln-light rounded-lg flex items-center justify-center text-xl mb-3">🎖️</div>
                <h3 class="text-lg font-bold text-gray-900">Sertifikasi</h3>
                <p class="text-gray-500 text-xs mt-1 leading-relaxed">Uji kompetensi terstandarisasi untuk jenjang karir yang jelas.</p>
            </div>
            <div class="group p-3 hover:bg-gray-50 rounded-lg transition">
                <div class="w-10 h-10 bg-pln-light/10 text-pln-light rounded-lg flex items-center justify-center text-xl mb-3">🤖</div>
                <h3 class="text-lg font-bold text-gray-900">AI Mentor</h3>
                <p class="text-gray-500 text-xs mt-1 leading-relaxed">Bantuan belajar personal dengan teknologi Generative AI.</p>
            </div>
        </div>
    </div>

    <section id="about" class="py-16 bg-gray-50">
        <div class="max-w-6xl mx-auto px-6 grid lg:grid-cols-2 gap-12 items-center">
            <div class="relative">
                <div class="absolute -inset-3 bg-pln-light/20 rounded-2xl transform rotate-2"></div>
                <img src="https://images.unsplash.com/photo-1573164713714-d95e436ab8d6?q=80&w=2069&auto=format&fit=crop" class="relative rounded-2xl shadow-xl transform transition hover:scale-[1.01] duration-500">
                <div class="absolute -bottom-4 -right-4 bg-white p-4 rounded-lg shadow-lg border-l-4 border-pln-primary">
                    <p class="text-2xl font-bold text-pln-primary">78+</p>
                    <p class="text-gray-500 text-xs font-medium">Tahun Menerangi Negeri</p>
                </div>
            </div>
            <div>
                <h2 class="text-pln-light font-bold tracking-wider uppercase text-xs mb-2">Tentang Learning Hub</h2>
                <h3 class="text-3xl font-bold text-gray-900 mb-4">Mencetak SDM Unggul</h3>
                <p class="text-gray-600 text-base mb-6 leading-relaxed">
                    PLN Learning Hub bukan sekadar tempat belajar, melainkan ekosistem pertumbuhan. Kami menghubungkan karyawan dengan pengetahuan terkini, mentor ahli, dan teknologi masa depan.
                </p>
                <div class="grid grid-cols-2 gap-6 mt-6">
                    <div>
                        <h4 class="text-2xl font-bold text-pln-primary">50k+</h4>
                        <p class="text-gray-500 text-sm">Talenta Aktif</p>
                    </div>
                    <div>
                        <h4 class="text-2xl font-bold text-pln-primary">1.2k</h4>
                        <p class="text-gray-500 text-sm">Modul Pembelajaran</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white border-t border-gray-100">
        <div class="max-w-6xl mx-auto px-6 text-center mb-10">
            <h2 class="text-2xl font-bold text-gray-900">Didukung Manajemen</h2>
            <p class="text-gray-500 text-sm mt-2">Komitmen pimpinan dalam pengembangan human capital PLN.</p>
        </div>
        <div class="max-w-6xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-6">
            <div class="group text-center">
                <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-2 border-gray-100 group-hover:border-pln-light transition duration-300">
                    <img src="https://ui-avatars.com/api/?name=Darmawan+Prasodjo&background=035B71&color=fff&size=200" class="w-full h-full object-cover">
                </div>
                <h3 class="mt-4 text-base font-bold text-gray-900">Darmawan Prasodjo</h3>
                <p class="text-pln-light text-xs font-medium">Direktur Utama</p>
            </div>
            <div class="group text-center">
                <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-2 border-gray-100 group-hover:border-pln-light transition duration-300">
                    <img src="https://ui-avatars.com/api/?name=Yusuf+Didi&background=035B71&color=fff&size=200" class="w-full h-full object-cover">
                </div>
                <h3 class="mt-4 text-base font-bold text-gray-900">Yusuf Didi Setiarto</h3>
                <p class="text-pln-light text-xs font-medium">Dir. Legal & HC</p>
            </div>
            <div class="group text-center">
                <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-2 border-gray-100 group-hover:border-pln-light transition duration-300">
                    <img src="https://ui-avatars.com/api/?name=Edi+Srimulyanti&background=035B71&color=fff&size=200" class="w-full h-full object-cover">
                </div>
                <h3 class="mt-4 text-base font-bold text-gray-900">Edi Srimulyanti</h3>
                <p class="text-pln-light text-xs font-medium">Dir. Retail</p>
            </div>
            <div class="group text-center">
                <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-2 border-gray-100 group-hover:border-pln-light transition duration-300">
                    <img src="https://ui-avatars.com/api/?name=Adi+Lumakso&background=035B71&color=fff&size=200" class="w-full h-full object-cover">
                </div>
                <h3 class="mt-4 text-base font-bold text-gray-900">Adi Lumakso</h3>
                <p class="text-pln-light text-xs font-medium">Dir. Pembangkitan</p>
            </div>
        </div>
    </section>

    <section class="py-16 bg-gray-900 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-1/2 h-full bg-pln-primary/20 blur-[80px]"></div>
        <div class="max-w-6xl mx-auto px-6 relative z-10 flex flex-col md:flex-row items-center gap-10">
            <div class="md:w-1/2">
                <span class="px-3 py-1 rounded-full bg-pln-light/20 text-pln-light border border-pln-light/30 text-[10px] font-bold uppercase tracking-wider">
                    Powered by Gemini AI
                </span>
                <h2 class="text-3xl font-bold text-white mt-4 mb-4">Asisten Belajar Pribadi</h2>
                <p class="text-gray-400 text-base mb-6 leading-relaxed">
                    Bingung materi teknis? Tanyakan pada AI Chatbot kami. Dapatkan ringkasan materi dan rekomendasi karir secara instan.
                </p>
                <button class="flex items-center gap-2 bg-white text-gray-900 px-5 py-2.5 rounded-full text-sm font-bold hover:bg-gray-100 transition">
                    <span>Coba Chatbot</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
                </button>
            </div>
            <div class="md:w-1/2 w-full">
                <div class="bg-gray-800 rounded-xl p-5 border border-gray-700 shadow-xl max-w-sm ml-auto">
                    <div class="space-y-3 text-xs">
                        <div class="bg-gray-700 p-3 rounded-lg rounded-tl-none text-gray-200 inline-block">
                            Halo! Ada yang bisa saya bantu tentang materi Transmisi?
                        </div>
                        <div class="bg-pln-primary p-3 rounded-lg rounded-tr-none text-white inline-block ml-auto">
                            Jelaskan fungsi Gardu Induk.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 bg-gray-50 border-t border-gray-200">
        <div class="max-w-6xl mx-auto px-6 text-center">
            <p class="text-gray-400 text-[10px] font-bold tracking-widest uppercase mb-6">Partner Institusi</p>
            <div class="flex flex-wrap justify-center gap-8 md:gap-12 opacity-60 grayscale hover:grayscale-0 transition duration-500">
                <div class="text-lg font-bold text-gray-800 flex items-center gap-2"><span class="text-pln-primary text-2xl">⚡</span> PLN Udiklat</div>
                <div class="text-lg font-bold text-gray-800 flex items-center gap-2"><span class="text-blue-600 text-2xl">🎓</span> Kemendikbud</div>
                <div class="text-lg font-bold text-gray-800 flex items-center gap-2"><span class="text-orange-500 text-2xl">☁️</span> Oracle Cloud</div>
            </div>
        </div>
    </section>

    <div class="fixed bottom-6 right-6 z-50">
        <button class="bg-pln-light hover:bg-pln-primary text-white w-12 h-12 rounded-full shadow-lg flex items-center justify-center transition hover:scale-110 group relative">
            <span class="text-2xl">💬</span>
        </button>
    </div>

</x-guest-layout>