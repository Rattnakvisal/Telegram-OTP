<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const OTP_SESSION_KEY = 'auth.otp.pending';
    private const OTP_EXPIRES_IN_MINUTES = 10;

    public function __construct(private readonly TelegramOtpService $telegramOtpService)
    {
    }

    public function showLogin(): View
    {
        return view('Auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ]);

        $phoneNumber = $this->normalizePhoneNumber($credentials['phone_number']);

        if (! $this->isValidPhoneNumber($phoneNumber)) {
            return back()
                ->withErrors(['phone_number' => 'Please enter a valid phone number.'])
                ->onlyInput('phone_number');
        }

        $user = User::query()->where('phone_number', $phoneNumber)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withErrors(['phone_number' => 'The provided credentials do not match our records.'])
                ->onlyInput('phone_number');
        }

        return $this->startOtpFlow(
            request: $request,
            intent: 'login',
            accountIdentifier: $phoneNumber,
            phoneNumber: $phoneNumber,
            knownChatId: $user->telegram_chat_id,
            payload: [
                'user_id' => $user->id,
                'remember' => $request->boolean('remember'),
            ],
            inputFields: ['phone_number']
        );
    }

    public function showRegister(): View
    {
        return view('Auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $phoneNumber = $this->normalizePhoneNumber($validated['phone_number']);

        if (! $this->isValidPhoneNumber($phoneNumber)) {
            return back()
                ->withErrors(['phone_number' => 'Please enter a valid phone number.'])
                ->onlyInput('name', 'email', 'phone_number');
        }

        if (User::query()->where('phone_number', $phoneNumber)->exists()) {
            return back()
                ->withErrors(['phone_number' => 'Phone number already exists. Please login instead.'])
                ->onlyInput('name', 'email', 'phone_number');
        }

        return $this->startOtpFlow(
            request: $request,
            intent: 'register',
            accountIdentifier: $phoneNumber,
            phoneNumber: $phoneNumber,
            knownChatId: null,
            payload: [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone_number' => $phoneNumber,
                'role' => 'user',
                'password' => Hash::make($validated['password']),
            ],
            inputFields: ['name', 'email', 'phone_number']
        );
    }

    public function showForgotPassword(): View
    {
        return view('Auth.forgot-password');
    }

    public function sendForgotPasswordOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        if (! $user->phone_number) {
            return back()->withErrors([
                'email' => 'This account has no phone number linked for Telegram OTP. Please contact support.',
            ]);
        }

        return $this->startOtpFlow(
            request: $request,
            intent: 'reset_password',
            accountIdentifier: $user->phone_number,
            phoneNumber: $user->phone_number,
            knownChatId: $user->telegram_chat_id,
            payload: [
                'user_id' => $user->id,
                'password' => Hash::make($validated['password']),
            ],
            inputFields: ['email']
        );
    }

    public function showOtpForm(Request $request): RedirectResponse|View
    {
        $pendingOtp = $request->session()->get(self::OTP_SESSION_KEY);

        if (! $pendingOtp) {
            return redirect()
                ->route('login')
                ->withErrors(['otp' => 'No OTP request found. Please try again.']);
        }

        return view('Auth.otp', [
            'intentLabel' => $this->intentLabel($pendingOtp['intent']),
            'accountIdentifier' => $pendingOtp['account_identifier'],
            'expiresInMinutes' => self::OTP_EXPIRES_IN_MINUTES,
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $pendingOtp = $request->session()->get(self::OTP_SESSION_KEY);

        if (! $pendingOtp) {
            return redirect()
                ->route('login')
                ->withErrors(['otp' => 'OTP session expired. Please try again.']);
        }

        if (now()->greaterThan($pendingOtp['expires_at'])) {
            $request->session()->forget(self::OTP_SESSION_KEY);

            return redirect()
                ->route($this->routeForIntent($pendingOtp['intent']))
                ->withErrors(['otp' => 'OTP expired. Please request a new code.']);
        }

        if (! Hash::check($validated['otp'], $pendingOtp['otp_hash'])) {
            return back()->withErrors(['otp' => 'Invalid OTP code.']);
        }

        $request->session()->forget(self::OTP_SESSION_KEY);

        return match ($pendingOtp['intent']) {
            'register' => $this->completeRegistration($pendingOtp),
            'login' => $this->completeLogin($request, $pendingOtp),
            'reset_password' => $this->completePasswordReset($pendingOtp),
            default => redirect()->route('login')->withErrors(['otp' => 'Unknown OTP intent.']),
        };
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $pendingOtp = $request->session()->get(self::OTP_SESSION_KEY);

        if (! $pendingOtp) {
            return redirect()
                ->route('login')
                ->withErrors(['otp' => 'No OTP request found. Please start again.']);
        }

        $otp = $this->telegramOtpService->generateOtp();

        try {
            $chatId = $this->telegramOtpService->resolveChatId(
                $pendingOtp['phone_number'] ?? null,
                $pendingOtp['chat_id'] ?? null
            );

            $this->telegramOtpService->send(
                otp: $otp,
                intentLabel: $this->intentLabel($pendingOtp['intent']),
                accountIdentifier: $pendingOtp['account_identifier'],
                expiresInMinutes: self::OTP_EXPIRES_IN_MINUTES,
                chatId: $chatId
            );

            $pendingOtp['chat_id'] = $chatId;
        } catch (\Throwable $exception) {
            Log::warning('Telegram OTP resend failed.', [
                'message' => $exception->getMessage(),
                'phone_number' => $pendingOtp['phone_number'] ?? null,
                'intent' => $pendingOtp['intent'] ?? null,
            ]);

            $phoneForLink = $pendingOtp['phone_number'] ?? $pendingOtp['account_identifier'] ?? null;
            $botLink = $this->telegramOtpService->startLinkUrl($phoneForLink);
            $botHint = $botLink
                ? "Open {$botLink}, press START, or send '/link {$pendingOtp['account_identifier']}'."
                : "Open your bot chat, press /start, then send '/link {$pendingOtp['account_identifier']}'.";

            return back()->withErrors([
                'otp' => "Failed to send OTP. {$botHint}",
            ]);
        }

        $pendingOtp['otp_hash'] = Hash::make($otp);
        $pendingOtp['expires_at'] = now()->addMinutes(self::OTP_EXPIRES_IN_MINUTES)->toIso8601String();
        $request->session()->put(self::OTP_SESSION_KEY, $pendingOtp);

        return back()->with('status', 'A new OTP has been sent to your Telegram.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function startOtpFlow(
        Request $request,
        string $intent,
        string $accountIdentifier,
        ?string $phoneNumber,
        ?string $knownChatId,
        array $payload,
        array $inputFields
    ): RedirectResponse {
        $otp = $this->telegramOtpService->generateOtp();

        try {
            $chatId = $this->telegramOtpService->resolveChatId($phoneNumber, $knownChatId);

            $this->telegramOtpService->send(
                otp: $otp,
                intentLabel: $this->intentLabel($intent),
                accountIdentifier: $accountIdentifier,
                expiresInMinutes: self::OTP_EXPIRES_IN_MINUTES,
                chatId: $chatId
            );
        } catch (\Throwable $exception) {
            Log::warning('Telegram OTP send failed.', [
                'message' => $exception->getMessage(),
                'phone_number' => $phoneNumber,
                'intent' => $intent,
            ]);

            $botLink = $this->telegramOtpService->startLinkUrl($phoneNumber ?? $accountIdentifier);
            $botHint = $botLink
                ? "Open {$botLink}, press START, or send '/link {$accountIdentifier}'."
                : "Open your bot chat, press /start, then send '/link {$accountIdentifier}'.";

            return back()
                ->withErrors([
                    'telegram' => "Unable to send OTP to Telegram for this phone number. {$botHint}",
                ])
                ->onlyInput(...$inputFields);
        }

        $request->session()->put(self::OTP_SESSION_KEY, [
            'intent' => $intent,
            'account_identifier' => $accountIdentifier,
            'phone_number' => $phoneNumber,
            'chat_id' => $chatId,
            'payload' => $payload,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(self::OTP_EXPIRES_IN_MINUTES)->toIso8601String(),
        ]);

        return redirect()
            ->route('otp.form')
            ->with('status', 'OTP sent to Telegram. Please verify to continue.');
    }

    private function completeRegistration(array $pendingOtp): RedirectResponse
    {
        if (User::query()->where('email', $pendingOtp['payload']['email'])->exists()) {
            return redirect()
                ->route('register')
                ->withErrors(['email' => 'Email already exists. Please login instead.']);
        }

        if (User::query()->where('phone_number', $pendingOtp['payload']['phone_number'])->exists()) {
            return redirect()
                ->route('register')
                ->withErrors(['phone_number' => 'Phone number already exists. Please login instead.']);
        }

        $payload = $pendingOtp['payload'];
        $payload['telegram_chat_id'] = $pendingOtp['chat_id'] ?? null;

        User::create($payload);

        return redirect()
            ->route('login')
            ->with('status', 'Registration successful. Please login.');
    }

    private function completeLogin(Request $request, array $pendingOtp): RedirectResponse
    {
        $user = User::query()->find($pendingOtp['payload']['user_id']);

        if (! $user) {
            return redirect()
                ->route('login')
                ->withErrors(['phone_number' => 'Account no longer exists.']);
        }

        if (! $user->telegram_chat_id && ! empty($pendingOtp['chat_id'])) {
            $user->forceFill(['telegram_chat_id' => $pendingOtp['chat_id']])->save();
        }

        Auth::login($user, $pendingOtp['payload']['remember'] ?? false);
        $request->session()->regenerate();

        return redirect()->route($this->roleDashboardRoute($user));
    }

    private function completePasswordReset(array $pendingOtp): RedirectResponse
    {
        $user = User::query()->find($pendingOtp['payload']['user_id']);

        if (! $user) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'Account no longer exists.']);
        }

        $updatePayload = [
            'password' => $pendingOtp['payload']['password'],
        ];

        if (! $user->telegram_chat_id && ! empty($pendingOtp['chat_id'])) {
            $updatePayload['telegram_chat_id'] = $pendingOtp['chat_id'];
        }

        $user->forceFill($updatePayload)->save();

        return redirect()
            ->route('login')
            ->with('status', 'Password reset successful. Please login with your new password.');
    }

    private function intentLabel(string $intent): string
    {
        return match ($intent) {
            'register' => 'registration',
            'login' => 'login',
            'reset_password' => 'password reset',
            default => 'authentication',
        };
    }

    private function routeForIntent(string $intent): string
    {
        return match ($intent) {
            'register' => 'register',
            'login' => 'login',
            'reset_password' => 'password.request',
            default => 'login',
        };
    }

    private function normalizePhoneNumber(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($trimmed, '+')) {
            return "+{$digits}";
        }

        if (str_starts_with($digits, '855')) {
            return "+{$digits}";
        }

        if (str_starts_with($digits, '0')) {
            return '+855'.substr($digits, 1);
        }

        return "+{$digits}";
    }

    private function isValidPhoneNumber(?string $phoneNumber): bool
    {
        if ($phoneNumber === null) {
            return false;
        }

        return (bool) preg_match('/^\+\d{8,15}$/', $phoneNumber);
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
