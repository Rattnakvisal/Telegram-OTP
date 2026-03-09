<?php

namespace App\Services;

use App\Models\TelegramContact;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            $chatId = $this->findLinkedChatIdByPhone($phoneNumber) ?? $this->findChatIdByPhone($phoneNumber);

            if ($chatId !== null) {
                return $chatId;
            }
        }

        if ($this->isTruthy(config('services.telegram.allow_fallback_chat_id', false))) {
            $defaultChatId = (string) config('services.telegram.chat_id');

            if ($defaultChatId !== '') {
                return $defaultChatId;
            }
        }

        throw new RuntimeException('Telegram chat not linked to this phone number. Please open bot and send /start to share contact.');
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

    public function startLinkUrl(?string $phoneNumber = null): ?string
    {
        $botLink = $this->botLink();

        if ($botLink === null) {
            return null;
        }

        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

        if ($normalizedPhone === null) {
            return $botLink;
        }

        $payloadDigits = ltrim($normalizedPhone, '+');

        if ($payloadDigits === '') {
            return $botLink;
        }

        return $botLink.'?start=link_'.$payloadDigits;
    }

    public function sendText(string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        $response = Http::asForm()
            ->withOptions($this->httpOptions())
            ->post($this->buildApiUrl('sendMessage'), $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Telegram API request failed.');
        }
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

        $this->sendText($chatId, $message);
    }

    public function linkPhoneToChat(string $phoneNumber, string $chatId, ?string $telegramUserId = null): bool
    {
        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

        if ($normalizedPhone === null) {
            throw new RuntimeException('Phone number is invalid.');
        }

        $this->storeTelegramContact($normalizedPhone, $chatId, $telegramUserId);

        return User::query()
            ->where('phone_number', $normalizedPhone)
            ->update(['telegram_chat_id' => $chatId]) > 0;
    }

    private function findChatIdByPhone(string $phoneNumber): ?string
    {
        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

        if ($normalizedPhone === null) {
            return null;
        }

        $updates = $this->fetchRecentUpdates();

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
                if ($this->normalizePhoneNumber($candidatePhone) === $normalizedPhone) {
                    $this->storeTelegramContact(
                        $normalizedPhone,
                        (string) $chatId,
                        isset($message['from']['id']) ? (string) $message['from']['id'] : null
                    );

                    return (string) $chatId;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRecentUpdates(): array
    {
        $response = Http::withOptions($this->httpOptions())
            ->get($this->buildApiUrl('getUpdates'), [
                'limit' => 100,
                'timeout' => 0,
                'allowed_updates' => json_encode(['message', 'edited_message']),
            ]);

        if ($response->successful()) {
            return $this->normalizeUpdatesPayload($response->json('result', []));
        }

        $description = (string) ($response->json('description') ?? '');
        $hasWebhookConflict = $response->status() === 409
            || str_contains(strtolower($description), 'webhook');

        if (! $hasWebhookConflict) {
            Log::warning('Telegram getUpdates failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $this->disableWebhookForPolling();

        $retry = Http::withOptions($this->httpOptions())
            ->get($this->buildApiUrl('getUpdates'), [
                'limit' => 100,
                'timeout' => 0,
                'allowed_updates' => json_encode(['message', 'edited_message']),
            ]);

        if (! $retry->successful()) {
            Log::warning('Telegram getUpdates retry failed after webhook disable.', [
                'status' => $retry->status(),
                'body' => $retry->body(),
            ]);

            return [];
        }

        return $this->normalizeUpdatesPayload($retry->json('result', []));
    }

    private function disableWebhookForPolling(): void
    {
        $response = Http::asForm()
            ->withOptions($this->httpOptions())
            ->post($this->buildApiUrl('deleteWebhook'), [
                'drop_pending_updates' => 'false',
            ]);

        if (! $response->successful()) {
            Log::warning('Telegram deleteWebhook failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    /**
     * @param mixed $payload
     * @return list<array<string, mixed>>
     */
    private function normalizeUpdatesPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $updates = [];
        foreach ($payload as $item) {
            if (is_array($item)) {
                $updates[] = $item;
            }
        }

        return $updates;
    }

    private function findLinkedChatIdByPhone(string $phoneNumber): ?string
    {
        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

        if ($normalizedPhone === null) {
            return null;
        }

        try {
            $chatId = TelegramContact::query()
                ->where('phone_number', $normalizedPhone)
                ->value('telegram_chat_id');
        } catch (\Throwable $exception) {
            Log::warning('Telegram contact lookup failed.', [
                'message' => $exception->getMessage(),
                'phone_number' => $normalizedPhone,
            ]);

            return null;
        }

        if (! is_string($chatId) || $chatId === '') {
            return null;
        }

        return $chatId;
    }

    private function storeTelegramContact(string $phoneNumber, string $chatId, ?string $telegramUserId = null): void
    {
        try {
            TelegramContact::query()->updateOrCreate(
                ['phone_number' => $phoneNumber],
                [
                    'telegram_chat_id' => $chatId,
                    'telegram_user_id' => $telegramUserId,
                    'last_seen_at' => now(),
                ]
            );
        } catch (\Throwable $exception) {
            Log::warning('Telegram contact save failed.', [
                'message' => $exception->getMessage(),
                'phone_number' => $phoneNumber,
                'chat_id' => $chatId,
            ]);
        }
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

    public function normalizePhoneNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

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
