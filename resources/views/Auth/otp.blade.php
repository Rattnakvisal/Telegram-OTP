<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="auth-body flex items-center justify-center p-4">
    <div class="auth-orb auth-orb-top"></div>
    <div class="auth-orb auth-orb-bottom"></div>

    <div class="w-full max-w-md auth-card animate-pop-in">
        <h1 class="text-2xl font-semibold text-slate-900">Verify OTP</h1>
        <p class="text-sm text-slate-600 mt-1">Enter the 6-digit Telegram OTP for {{ $intentLabel }}.</p>
        <p class="text-xs text-slate-500 mt-1">Account: {{ $accountIdentifier }}. OTP expires in {{ $expiresInMinutes }} minutes.</p>

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

        <form method="POST" action="{{ route('otp.verify') }}" class="mt-6 space-y-4 stagger-group">
            @csrf
            <div class="stagger-item">
                <label class="block text-sm font-medium text-slate-700 mb-1" for="otp">OTP code</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
                    class="auth-input tracking-[0.3em]">
            </div>

            <button type="submit" class="stagger-item w-full btn-primary">Verify OTP</button>
        </form>

        <form method="POST" action="{{ route('otp.resend') }}" class="mt-3">
            @csrf
            <button type="submit"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                Resend OTP
            </button>
        </form>

        <p class="mt-6 text-sm text-center text-slate-600">
            Cancel and go to
            <a href="{{ route('login') }}" class="text-sky-700 font-medium hover:underline">Login</a>
        </p>
    </div>
</body>

</html>
