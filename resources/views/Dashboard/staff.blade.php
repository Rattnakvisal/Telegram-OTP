<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="auth-body p-4">
    <div class="auth-orb auth-orb-top"></div>
    <div class="auth-orb auth-orb-bottom"></div>

    <div class="max-w-4xl mx-auto mt-8 auth-card animate-pop-in">
        <div class="flex flex-wrap gap-3 items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-blue-700 font-semibold">Staff</p>
                <h1 class="text-3xl font-bold text-slate-900 mt-1">Operations Desk</h1>
                <p class="text-sm text-slate-600 mt-2">Hello {{ auth()->user()->name }}, handle daily tasks and monitor requests.</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-200">Role: {{ auth()->user()->role }}</span>
        </div>

        <div class="grid gap-4 md:grid-cols-3 mt-8 stagger-group">
            <div class="stagger-item rounded-xl p-4 bg-gradient-to-br from-blue-50 to-white border border-blue-100">
                <p class="text-xs uppercase text-blue-500 font-semibold">Queue</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Tickets</p>
                <p class="text-sm text-slate-600 mt-1">Keep pending requests moving.</p>
            </div>
            <div class="stagger-item rounded-xl p-4 bg-gradient-to-br from-cyan-50 to-white border border-cyan-100">
                <p class="text-xs uppercase text-cyan-600 font-semibold">Members</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Support</p>
                <p class="text-sm text-slate-600 mt-1">Assist user account issues fast.</p>
            </div>
            <div class="stagger-item rounded-xl p-4 bg-gradient-to-br from-sky-50 to-white border border-sky-100">
                <p class="text-xs uppercase text-sky-600 font-semibold">Status</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Live</p>
                <p class="text-sm text-slate-600 mt-1">Watch service and OTP health.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="btn-primary">Logout</button>
        </form>
    </div>
</body>

</html>
