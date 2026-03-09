<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="auth-body flex items-center justify-center p-4">
    <div class="auth-orb auth-orb-top"></div>
    <div class="auth-orb auth-orb-bottom"></div>

    <div class="w-full max-w-md auth-card animate-pop-in">
        <h1 class="text-2xl font-semibold text-slate-900">Sign in</h1>
        <p class="text-sm text-slate-600 mt-1">Login with phone/password for your admin, staff, or user account.</p>
        <p class="text-xs text-slate-500 mt-1">Use Cambodia format: 012345678 or +85512345678.</p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
                <ul class="list-disc ml-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4 stagger-group">
            @csrf
            <div class="stagger-item">
                <label class="block text-sm font-medium text-slate-700 mb-1" for="phone_number">Phone number</label>
                <input id="phone_number" name="phone_number" type="text" value="{{ old('phone_number') }}" required autofocus
                    class="auth-input" placeholder="012345678 or +85512345678">
            </div>

            <div class="stagger-item">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
                    <a href="{{ route('password.request') }}" class="text-xs text-sky-700 font-medium hover:underline">Forgot password?</a>
                </div>
                <input id="password" name="password" type="password" required class="auth-input">
            </div>

            <label class="stagger-item flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                <span>Remember me</span>
            </label>

            <button type="submit" class="stagger-item w-full btn-primary">Login</button>
        </form>

        <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-slate-200"></div>
            </div>
            <div class="relative text-center text-xs uppercase text-slate-400">
                <span class="bg-white px-2">or</span>
            </div>
        </div>

        <a href="{{ route('auth.google.redirect') }}"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
            <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#FFC107"
                    d="M43.611 20.083H42V20H24v8h11.303C33.648 32.659 29.215 36 24 36c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z" />
                <path fill="#FF3D00"
                    d="M6.306 14.691l6.571 4.819C14.655 16.108 18.961 13 24 13c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z" />
                <path fill="#4CAF50"
                    d="M24 44c5.113 0 9.806-1.963 13.333-5.156l-6.159-5.211C29.109 35.091 26.7 36 24 36c-5.194 0-9.614-3.317-11.287-7.946l-6.518 5.02C9.505 39.556 16.227 44 24 44z" />
                <path fill="#1976D2"
                    d="M43.611 20.083H42V20H24v8h11.303c-.795 2.214-2.231 4.084-4.129 5.633l.003-.002 6.159 5.211C36.9 39.156 44 34 44 24c0-1.341-.138-2.65-.389-3.917z" />
            </svg>
            Continue with Google
        </a>

        <p class="mt-6 text-sm text-center text-slate-600">
            No account yet?
            <a href="{{ route('register') }}" class="text-sky-700 font-medium hover:underline">Create one</a>
        </p>
    </div>
</body>

</html>
