<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route(match (Auth::user()?->role) {
            'admin' => 'dashboard.admin',
            'staff' => 'dashboard.staff',
            default => 'dashboard.user',
        });
    }

    return view('Auth.login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendForgotPasswordOtp'])->name('password.email');

    Route::get('/otp/verify', [AuthController::class, 'showOtpForm'])->name('otp.form');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->name('otp.verify');
    Route::post('/otp/resend', [AuthController::class, 'resendOtp'])->name('otp.resend');

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', function () {
        return redirect()->route(match (Auth::user()?->role) {
            'admin' => 'dashboard.admin',
            'staff' => 'dashboard.staff',
            default => 'dashboard.user',
        });
    })->name('dashboard');

    Route::view('/admin/dashboard', 'Dashboard.admin')
        ->middleware('role:admin')
        ->name('dashboard.admin');

    Route::view('/staff/dashboard', 'Dashboard.staff')
        ->middleware('role:staff')
        ->name('dashboard.staff');

    Route::view('/user/dashboard', 'Dashboard.user')
        ->middleware('role:user')
        ->name('dashboard.user');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
