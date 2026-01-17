<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Announcement; // Pastikan Model Announcement sudah ada
use Illuminate\Support\Facades\Auth;

class EmployeeController extends Controller
{
    public function index()
    {
        // 1. DATA ASLI: Pengumuman dari Database (Project 1)
        // Mengambil 3 pengumuman terakhir
        $announcements = Announcement::latest()->take(3)->get();

        // 2. DATA DUMMY: Simulasi Progress Belajar (Nanti dari Moodle/MySQL)
        $myCourses = [
            [
                'title' => 'Basic Power Plant Operation',
                'progress' => 75,
                'status' => 'In Progress',
                'due_date' => '2 days left',
                'color' => 'bg-blue-500' // Warna bar
            ],
            [
                'title' => 'K3 Umum & Safety Induction',
                'progress' => 100,
                'status' => 'Completed',
                'due_date' => 'Done',
                'color' => 'bg-green-500'
            ],
            [
                'title' => 'Cyber Security Awareness',
                'progress' => 30,
                'status' => 'In Progress',
                'due_date' => '1 week left',
                'color' => 'bg-orange-500'
            ],
        ];

        // 3. DATA DUMMY: Event & Statistik Sertifikat
        $upcomingEvents = [
            ['title' => 'Webinar Energi Terbarukan', 'date' => '20 Feb', 'time' => '09:00 WIB'],
            ['title' => 'Ujian Sertifikasi K3', 'date' => '25 Feb', 'time' => '13:00 WIB'],
        ];

        return view('dashboard', compact('announcements', 'myCourses', 'upcomingEvents'));
    }
}