<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Ambil email yang sedang login biar kodingan lebih rapi
        $email = $request->user()->email;

        // --- SKENARIO 1 & 2: SUPER ADMIN & ADMIN (Masuk ke Filament) ---
        // Sesuai jadwal, Admin biasa juga masuk panel yang sama, bedanya nanti di "Policy" (hak akses menu)
        if ($email === 'superadmin@plnip.local' || $email === 'admin@plnip.local') {
            return redirect()->intended('/admin'); 
        }

        // --- SKENARIO 3: INSTRUCTOR (Masuk Dashboard Khusus) ---
        // PENTING: Route '/instructor/dashboard' belum kita buat (masuk tugas Siang nanti).
        // Jadi kalau kamu login pakai email ini sekarang, bakal 404 Not Found. Itu wajar.
        if ($email === 'instructor@plnip.local') {
            return redirect()->intended('/instructor/dashboard');
        }

        // --- SKENARIO 4: EMPLOYEE / KARYAWAN (Default) ---
        // Sisanya (termasuk employee@plnip.local) masuk ke dashboard belajar biasa
        return redirect()->intended(route('dashboard', absolute: false));
    }
    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
