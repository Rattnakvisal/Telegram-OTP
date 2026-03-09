<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramOtpService
{
    public function generateOtp(int $length = 6): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    public function resolveChatId(?string $phoneNumber, ?string $knownChatId = null): string
    {
        if ($knownChatId !== null && $knownChatId !== '') {
            return $knownChatId;
        }

        if ($phoneNumber !== null && $phoneNumber !== '') {
            $chatId = $this->findChatIdByPhone($phoneNumber);

            if ($chatId !== null) {
                return $chatId;
            }
        }

        $defaultChatId = (string) config('services.telegram.chat_id');

        if ($defaultChatId !== '') {
            return $defaultChatId;
        }

        throw new RuntimeException('Telegram chat not found for this phone number.');
    }

    public function botLink(): ?string
    {
        $username = trim((string) config('services.telegram.bot_username'));

        if ($username === '') {
            return null;
        }

        $username = ltrim($username, '@');

        if ($username === '') {
            return null;
        }

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{4,31}$/', $username)) {
            return null;
        }

        return "https://t.me/{$username}";
    }

    public function send(
        string $otp,
        string $intentLabel,
        string $accountIdentifier,
        int $expiresInMinutes,
        string $chatId
    ): void {
        $message = "Your {$intentLabel} OTP is: {$otp}\n"
            . "Account: {$accountIdentifier}\n"
            . "Expires in {$expiresInMinutes} minutes.";

        $response = Http::asForm()
            ->withOptions($this->httpOptions())
            ->post($this->buildApiUrl('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Telegram API request failed.');
        }
    }

    private function findChatIdByPhone(string $phoneNumber): ?string
    {
        $normalizedPhone = $this->normalizePhone($phoneNumber);

        if ($normalizedPhone === null) {
            return null;
        }

        $response = Http::withOptions($this->httpOptions())
            ->get($this->buildApiUrl('getUpdates'), [
                'offset' => -100,
                'limit' => 100,
                'allowed_updates' => json_encode(['message', 'edited_message']),
            ]);

        if (! $response->successful()) {
            return null;
        }

        $updates = $response->json('result', []);

        if (! is_array($updates)) {
            return null;
        }

        foreach (array_reverse($updates) as $update) {
            $message = $update['message'] ?? $update['edited_message'] ?? null;

            if (! is_array($message)) {
                continue;
            }

            $chatId = $message['chat']['id'] ?? $message['from']['id'] ?? null;

            if ($chatId === null) {
                continue;
            }

            $candidatePhones = [];

            $contactPhone = $message['contact']['phone_number'] ?? null;

            if (is_string($contactPhone)) {
                $candidatePhones[] = $contactPhone;
            }

            $text = $message['text'] ?? null;
            if (is_string($text)) {
                foreach ($this->extractPhoneCandidates($text) as $candidatePhone) {
                    $candidatePhones[] = $candidatePhone;
                }
            }

            foreach ($candidatePhones as $candidatePhone) {
                if ($this->normalizePhone($candidatePhone) === $normalizedPhone) {
                    return (string) $chatId;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractPhoneCandidates(string $text): array
    {
        $matches = [];

        preg_match_all('/\+?\d[\d\s\-\(\)]{7,20}/', $text, $matches);

        $candidates = [];
        foreach ($matches[0] ?? [] as $match) {
            if (is_string($match)) {
                $candidates[] = $match;
            }
        }

        return $candidates;
    }

    private function buildApiUrl(string $method): string
    {
        $botToken = (string) config('services.telegram.bot_token');

        if ($botToken === '') {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        return "https://api.telegram.org/bot{$botToken}/{$method}";
    }

    /**
     * @return array{verify?:bool, timeout:int}
     */
    private function httpOptions(): array
    {
        $options = [
            'timeout' => 10,
        ];

        if (! $this->isTruthy(config('services.telegram.verify_ssl', true))) {
            $options['verify'] = false;
        }

        return $options;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var((string) $value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $parsed ?? false;
    }

    private function normalizePhone(string $value): ?string
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
}
