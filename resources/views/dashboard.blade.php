@php
    use App\Models\Announcement;

    $announcements = Announcement::query()
        ->orderByDesc('published_at')
        ->take(3)
        ->get();

    $kpi = [
        'enrolled' => 3,
        'completed' => 1,
        'in_progress' => 2,
    ];

    $learningProgress = [
        ['title' => 'IT Security Basics', 'progress' => 60, 'next' => 'Next: Week 3 Quiz • Due in 2 days'],
        ['title' => 'K3 Safety Training', 'progress' => 100, 'next' => 'Completed • Certificate ready to download'],
        ['title' => 'Leadership 101', 'progress' => 25, 'next' => 'Next: Module 2 • Communication Skills'],
    ];

    $recentActivity = [
        ['icon' => 'login', 'title' => 'Logged in', 'time' => 'Just now'],
        ['icon' => 'launch', 'title' => 'Launched Moodle LMS', 'time' => '5 minutes ago'],
        ['icon' => 'check', 'title' => 'Completed K3 Safety Training', 'time' => 'Yesterday 16:20'],
        ['icon' => 'submit', 'title' => 'Submitted Assignment: IT Security Final Report', 'time' => '2 days ago'],
    ];

    $quickLinks = [
        ['label' => 'Moodle LMS', 'url' => '/moodle'],
        ['label' => 'Webmail', 'url' => '#'],
        ['label' => 'Oracle EBS', 'url' => '#'],
        ['label' => 'Company Drive', 'url' => '#'],
        ['label' => 'Phone Directory', 'url' => '#'],
    ];

    $certificates = ['count' => 3, 'url' => '#'];
@endphp


<x-app-layout>
    <style>
        :root{
            --pln-dark: #035B71;
            --pln-bright: #00A2B9;
        }
    </style>

    <div class="min-h-screen bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

            {{-- Header --}}
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Employee Dashboard</h1>
                    <p class="text-slate-600 mt-1">
                        Welcome back, <span class="font-medium">{{ auth()->user()->name }}</span>.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button"
                        class="relative rounded-xl px-3 py-2 bg-white border border-slate-200 shadow-sm hover:bg-slate-50">
                        🔔
                        <span class="sr-only">Notifications</span>
                    </button>

                    <div class="rounded-xl bg-white border border-slate-200 shadow-sm px-3 py-2">
                        <div class="text-sm font-medium text-slate-900">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-slate-500">Employee</div>
                    </div>
                </div>
            </div>

            {{-- Main grid --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                {{-- Left / Main --}}
                <div class="lg:col-span-8 space-y-6">

                    {{-- LMS hero card --}}
                    <div class="rounded-2xl overflow-hidden border border-slate-200 shadow-sm bg-white">
                        <div class="p-6 text-white" style="background: var(--pln-dark);">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm/6 opacity-90">Learning Management System</div>
                                    <h2 class="text-2xl font-semibold mt-1">Moodle LMS</h2>
                                    <p class="opacity-90 mt-1">
                                        Access your courses, assignments, and learning progress in one place.
                                    </p>
                                </div>
                                <div class="hidden sm:block text-4xl">🎓</div>
                            </div>

                            <div class="mt-5">
                                <a href="/moodle"
                                   class="inline-flex items-center justify-center rounded-xl px-4 py-2 font-medium
                                          text-white transition"
                                   style="background: var(--pln-bright);">
                                    Launch Moodle →
                                </a>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 divide-x divide-slate-200">
                            <div class="p-4 text-center">
                                <div class="text-2xl font-semibold text-slate-900">{{ $kpi['enrolled'] }}</div>
                                <div class="text-xs text-slate-500">Enrolled</div>
                            </div>
                            <div class="p-4 text-center">
                                <div class="text-2xl font-semibold text-slate-900">{{ $kpi['completed'] }}</div>
                                <div class="text-xs text-slate-500">Completed</div>
                            </div>
                            <div class="p-4 text-center">
                                <div class="text-2xl font-semibold text-slate-900">{{ $kpi['in_progress'] }}</div>
                                <div class="text-xs text-slate-500">In Progress</div>
                            </div>
                        </div>
                    </div>

                    {{-- Learning progress --}}
                    <div class="rounded-2xl border border-slate-200 shadow-sm bg-white">
                        <div class="p-5 flex items-center justify-between">
                            <div class="font-semibold text-slate-900">My Learning Progress</div>
                            <a href="#" class="text-sm hover:underline" style="color: var(--pln-dark);">View all</a>
                        </div>
                        <div class="px-5 pb-5 space-y-4">
                            @foreach ($learningProgress as $item)
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="font-medium text-slate-900">{{ $item['title'] }}</div>
                                        <div class="text-sm text-slate-600">{{ $item['progress'] }}%</div>
                                    </div>
                                    <div class="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-2 rounded-full" style="width: {{ $item['progress'] }}%; background: var(--pln-bright);"></div>
                                    </div>
                                    <div class="mt-2 text-sm text-slate-600">{{ $item['next'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Recent activity --}}
                    <div class="rounded-2xl border border-slate-200 shadow-sm bg-white">
                        <div class="p-5 flex items-center justify-between">
                            <div class="font-semibold text-slate-900">Recent Activity</div>
                            <a href="#" class="text-sm hover:underline" style="color: var(--pln-dark);">View all activity</a>
                        </div>
                        <div class="px-5 pb-5 space-y-3">
                            @foreach ($recentActivity as $act)
                                <div class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                                    <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center">
                                        @switch($act['icon'])
                                            @case('login') 🔑 @break
                                            @case('launch') 🚀 @break
                                            @case('check') ✅ @break
                                            @case('submit') 📄 @break
                                            @default 📝
                                        @endswitch
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium text-slate-900">{{ $act['title'] }}</div>
                                        <div class="text-sm text-slate-500">{{ $act['time'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Latest announcements --}}
                    <div class="rounded-2xl border border-slate-200 shadow-sm bg-white">
                        <div class="p-5 flex items-center justify-between">
                            <div class="font-semibold text-slate-900">Latest Announcements</div>
                            <a href="#" class="text-sm hover:underline" style="color: var(--pln-dark);">View all</a>
                        </div>
                        <div class="px-5 pb-5 space-y-3">
                            @forelse ($announcements as $a)
                                <div class="rounded-xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                                    <div class="font-medium text-slate-900">{{ $a->title }}</div>
                                    <div class="text-sm text-slate-600 mt-1">
                                        {{ \Illuminate\Support\Str::limit($a->content, 140) }}
                                    </div>
                                    <div class="text-xs text-slate-500 mt-2">
                                        {{ optional($a->published_at)->format('d M Y') }}
                                    </div>
                                </div>
                            @empty
                                <div class="text-sm text-slate-600">No announcements yet.</div>
                            @endforelse
                        </div>
                    </div>

                </div>

                {{-- Right sidebar --}}
                <div class="lg:col-span-4 space-y-6">

                    {{-- Quick Links --}}
                    <div class="rounded-2xl border border-slate-200 shadow-sm bg-white">
                        <div class="p-5 font-semibold text-slate-900">Quick Links</div>
                        <div class="px-5 pb-5 space-y-2">
                            @foreach ($quickLinks as $link)
                                <a href="{{ $link['url'] }}"
                                   class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 hover:bg-slate-50 transition">
                                    <span class="text-slate-900">{{ $link['label'] }}</span>
                                    <span class="text-slate-400">→</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Certificates --}}
                    <div class="rounded-2xl border border-slate-200 shadow-sm bg-white overflow-hidden">
                        <div class="p-5 font-semibold text-slate-900">My Certificates</div>
                        <div class="px-5 pb-5">
                            <div class="rounded-2xl p-6 bg-gradient-to-br from-amber-100 to-amber-200 border border-amber-200">
                                <div class="flex items-center justify-between">
                                    <div class="text-4xl">🎓</div>
                                    <div class="text-right">
                                        <div class="text-3xl font-semibold text-slate-900">{{ $certificates['count'] }}</div>
                                        <div class="text-sm text-slate-700">Certificates earned</div>
                                    </div>
                                </div>
                                <a href="{{ $certificates['url'] }}"
                                   class="mt-4 inline-flex items-center justify-center w-full rounded-xl px-4 py-2 font-medium text-white transition"
                                   style="background: var(--pln-dark);">
                                    View all
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- AI Assistant (Floating) --}}
        <div x-data="{ open: false }">
            <button
                @click="open = true"
                class="fixed bottom-6 right-6 rounded-2xl px-4 py-3 shadow-lg text-white font-medium transition"
                style="background: var(--pln-dark);">
                AI Assistant
            </button>

            <div x-show="open" x-transition.opacity
                 class="fixed inset-0 bg-black/40"
                 @click="open = false"
                 style="display:none"></div>

            <div x-show="open" x-transition
                 class="fixed top-0 right-0 h-full w-full sm:w-[420px] bg-white shadow-2xl border-l border-slate-200"
                 style="display:none">
                <div class="p-5 flex items-center justify-between border-b border-slate-200">
                    <div>
                        <div class="font-semibold text-slate-900">AI Assistant</div>
                        <div class="text-sm text-slate-600">Help for LMS, learning, and portal.</div>
                    </div>
                    <button @click="open = false"
                            class="rounded-xl px-3 py-2 border border-slate-200 hover:bg-slate-50">
                        ✕
                    </button>
                </div>

                <div class="p-5 space-y-3">
                    <div class="text-sm text-slate-600">Quick prompts:</div>
                    <div class="flex flex-wrap gap-2">
                        <button class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-sm">Summarize my week</button>
                        <button class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-sm">Recommend courses</button>
                        <button class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-sm">How to access Moodle?</button>
                    </div>

                    <div class="mt-4 rounded-2xl border border-slate-200 p-4 text-sm text-slate-600">
                        (UI ready) Backend chat logic nanti kita sambung ke endpoint internal / AI service.
                    </div>
                </div>

                <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-200 bg-white">
                    <div class="flex gap-2">
                        <input type="text"
                               placeholder="Type a message..."
                               class="flex-1 rounded-xl border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2"
                               style="--tw-ring-color: var(--pln-bright);">
                        <button class="rounded-xl px-4 py-2 font-medium text-white transition"
                                style="background: var(--pln-dark);">
                            Send
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
