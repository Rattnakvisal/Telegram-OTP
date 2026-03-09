<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="auth-body flex items-center justify-center p-4">
    <div class="auth-orb auth-orb-top"></div>
    <div class="auth-orb auth-orb-bottom"></div>

    <div class="w-full max-w-md auth-card animate-pop-in">
        <h1 class="text-2xl font-semibold text-slate-900">Reset password</h1>
        <p class="text-sm text-slate-600 mt-1">Enter your email and new password. OTP will be sent to your linked Telegram account.</p>

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

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4 stagger-group">
            @csrf
            <div class="stagger-item">
                <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="auth-input">
            </div>

            <div class="stagger-item">
                <label class="block text-sm font-medium text-slate-700 mb-1" for="password">New password</label>
                <input id="password" name="password" type="password" required class="auth-input">
            </div>

            <div class="stagger-item">
                <label class="block text-sm font-medium text-slate-700 mb-1" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="auth-input">
            </div>

            <button type="submit" class="stagger-item w-full btn-primary">Send OTP</button>
        </form>

        <p class="mt-6 text-sm text-center text-slate-600">
            Back to
            <a href="{{ route('login') }}" class="text-sky-700 font-medium hover:underline">Login</a>
        </p>
    </div>
</body>

</html>
