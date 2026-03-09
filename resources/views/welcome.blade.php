<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-100 p-4">
    <div class="max-w-2xl mx-auto mt-10 bg-white rounded-2xl shadow-xl p-8">
        <h1 class="text-2xl font-semibold text-gray-900">Welcome back, {{ auth()->user()->name }}!</h1>
        <p class="text-gray-600 mt-2">You are signed in.</p>

        <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 space-y-2">
            <p><span class="font-medium">Name:</span> {{ auth()->user()->name }}</p>
            <p><span class="font-medium">Email:</span> {{ auth()->user()->email }}</p>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit"
                class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800 transition">
                Logout
            </button>
        </form>
    </div>
</body>

</html>
