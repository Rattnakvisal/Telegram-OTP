<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return $this->googleDriver()->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse|View
    {
        try {
            $googleUser = $this->googleDriver()->user();

            if (! $googleUser->getEmail()) {
                return redirect()
                    ->route('login')
                    ->withErrors(['google' => 'Google did not return an email address for this account.']);
            }

            $user = User::query()
                ->where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if (! $user) {
                $user = User::create([
                    'name' => $googleUser->getName() ?: Str::before($googleUser->getEmail(), '@'),
                    'email' => $googleUser->getEmail(),
                    'role' => 'user',
                    'password' => Str::password(32),
                    'google_id' => $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'name' => $googleUser->getName() ?: $user->name,
                    'role' => $user->role ?: 'user',
                    'google_id' => $user->google_id ?: $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();
            }

            Auth::login($user, true);
            request()->session()->regenerate();

            return view('Google.Callback', [
                'userName' => $user->name,
                'redirectTo' => route($this->roleDashboardRoute($user)),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Google OAuth callback failed.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['google' => 'Google login failed. Please try again.']);
        }
    }

    private function googleDriver(): AbstractProvider
    {
        $driver = Socialite::driver('google');

        if (! $this->isTruthy(config('services.google.verify_ssl', true))) {
            $driver->setHttpClient(new Client(['verify' => false]));
        }

        if ($this->isTruthy(config('services.google.stateless', true))) {
            $driver = $driver->stateless();
        }

        return $driver;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var((string) $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? false;
    }

    private function roleDashboardRoute(User $user): string
    {
        return match ($user->role) {
            'admin' => 'dashboard.admin',
            'staff' => 'dashboard.staff',
            default => 'dashboard.user',
        };
    }
}
