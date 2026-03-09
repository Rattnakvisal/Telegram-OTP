<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="auth-body p-4">
    <div class="auth-orb auth-orb-top"></div>
    <div class="auth-orb auth-orb-bottom"></div>

    <div class="max-w-4xl mx-auto mt-8 auth-card animate-pop-in">
        <div class="flex flex-wrap gap-3 items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-emerald-700 font-semibold">User</p>
                <h1 class="text-3xl font-bold text-slate-900 mt-1">My Account</h1>
                <p class="text-sm text-slate-600 mt-2">Welcome {{ auth()->user()->name }}, your account is active and secured by Telegram OTP.</p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200">Role: {{ auth()->user()->role }}</span>
        </div>

        <div class="grid gap-4 md:grid-cols-3 mt-8 stagger-group">
            <div class="stagger-item rounded-xl p-4 bg-gradient-to-br from-emerald-50 to-white border border-emerald-100">
                <p class="text-xs uppercase text-emerald-600 font-semibold">Profile</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Secure</p>
                <p class="text-sm text-slate-600 mt-1">Phone + OTP login protection enabled.</p>
            </div>
            <div class="stagger-item rounded-xl p-4 bg-gradient-to-br from-teal-50 to-white border border-teal-100">
                <p class="text-xs uppercase text-teal-600 font-semibold">Access</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Verified</p>
                <p class="text-sm text-slate-600 mt-1">View your account information safely.</p>
            </div>
            <div class="stagger-item rounded-xl p-4 bg-gradient-to-br from-green-50 to-white border border-green-100">
                <p class="text-xs uppercase text-green-600 font-semibold">Support</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Ready</p>
                <p class="text-sm text-slate-600 mt-1">Need help? Contact staff anytime.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="btn-primary">Logout</button>
        </form>
    </div>
</body>

</html>
