<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8">
        <h1 class="text-2xl font-semibold text-gray-900">Verify OTP</h1>
        <p class="text-sm text-gray-500 mt-1">Enter the 6-digit Telegram OTP for {{ $intentLabel }}.</p>
        <p class="text-xs text-gray-400 mt-1">Account: {{ $accountIdentifier }}. OTP expires in {{ $expiresInMinutes }} minutes.</p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 text-green-700 px-4 py-3 text-sm">
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

        <form method="POST" action="{{ route('otp.verify') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="otp">OTP code</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm tracking-[0.3em] focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <button type="submit"
                class="w-full rounded-lg bg-gray-900 text-white py-2.5 text-sm font-medium hover:bg-gray-800 transition">
                Verify OTP
            </button>
        </form>

        <form method="POST" action="{{ route('otp.resend') }}" class="mt-3">
            @csrf
            <button type="submit"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                Resend OTP
            </button>
        </form>

        <p class="mt-6 text-sm text-center text-gray-600">
            Cancel and go to
            <a href="{{ route('login') }}" class="text-blue-600 font-medium hover:underline">Login</a>
        </p>
    </div>
</body>

</html>
