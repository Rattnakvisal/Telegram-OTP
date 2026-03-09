<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="auth-body p-4">
    <div class="auth-orb auth-orb-top"></div>
    <div class="auth-orb auth-orb-bottom"></div>

    <div class="max-w-4xl mx-auto mt-8 auth-card animate-pop-in">
        <div class="flex flex-wrap gap-3 items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-red-700 font-semibold">Admin</p>
                <h1 class="text-3xl font-bold text-slate-900 mt-1">Control Center</h1>
                <p class="text-sm text-slate-600 mt-2">Welcome back, {{ auth()->user()->name }}. Manage users and platform settings.</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-200">Role: {{ auth()->user()->role }}</span>
        </div>

        <div class="grid gap-4 md:grid-cols-3 mt-8 stagger-group">
            <div class="stagger-item rounded-xl p-4 bg-gradient-to-br from-red-50 to-white border border-red-100">
                <p class="text-xs uppercase text-red-500 font-semibold">Users</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Manage</p>
                <p class="text-sm text-slate-600 mt-1">Create, update, and review accounts.</p>
            </div>
            <div class="stagger-item rounded-xl p-4 bg-gradient-to-br from-orange-50 to-white border border-orange-100">
                <p class="text-xs uppercase text-orange-500 font-semibold">Security</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Audit</p>
                <p class="text-sm text-slate-600 mt-1">Review OTP and login events quickly.</p>
            </div>
            <div class="stagger-item rounded-xl p-4 bg-gradient-to-br from-yellow-50 to-white border border-yellow-100">
                <p class="text-xs uppercase text-yellow-600 font-semibold">Reports</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Insights</p>
                <p class="text-sm text-slate-600 mt-1">Track staff and user activities.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="btn-primary">Logout</button>
        </form>
    </div>
</body>

</html>
