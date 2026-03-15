<?php

namespace App\Http\Controllers;

use App\Services\TelegramOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    public function __construct(private readonly TelegramOtpService $telegramOtpService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $message = $request->input('message') ?? $request->input('edited_message');

            if (! is_array($message)) {
                return response()->json(['ok' => true]);
            }

            $chatId = $message['chat']['id'] ?? $message['from']['id'] ?? null;

            if ($chatId === null) {
                return response()->json(['ok' => true]);
            }

            $chatId = (string) $chatId;
            $fromUserId = isset($message['from']['id']) ? (string) $message['from']['id'] : null;

            $contact = $message['contact'] ?? null;
            if (is_array($contact)) {
                $this->handleContactMessage($chatId, $fromUserId, $contact);

                return response()->json(['ok' => true]);
            }

            $text = trim((string) ($message['text'] ?? ''));
            if ($text === '') {
                return response()->json(['ok' => true]);
            }

        if (Str::startsWith($text, '/start')) {
            if ($this->tryHandleStartLinkPayload($chatId, $fromUserId, $text)) {
                return response()->json(['ok' => true]);
            }

            $this->sendStartPrompt($chatId);

            return response()->json(['ok' => true]);
        }

            if (Str::startsWith($text, '/link')) {
                $this->handleLinkCommand($chatId, $fromUserId, $text);

                return response()->json(['ok' => true]);
            }

            $phoneNumber = $this->telegramOtpService->normalizePhoneNumber($text);

            if ($phoneNumber !== null) {
                $this->linkPhoneToChat($chatId, $fromUserId, $phoneNumber);
            }
        } catch (\Throwable $exception) {
            Log::warning('Telegram webhook handling failed.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function sendStartPrompt(string $chatId): void
    {
        $this->telegramOtpService->sendText(
            chatId: $chatId,
            text: "Welcome.\nPlease tap the button below to share your phone number and connect this chat for OTP.",
            replyMarkup: [
                'keyboard' => [
                    [
                        [
                            'text' => 'Share phone number',
                            'request_contact' => true,
                        ],
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]
        );
    }

    private function tryHandleStartLinkPayload(string $chatId, ?string $fromUserId, string $text): bool
    {
        $parts = preg_split('/\s+/', trim($text), 2);
        $payload = $parts[1] ?? '';

        if (! is_string($payload) || $payload === '' || ! Str::startsWith($payload, 'link_')) {
            return false;
        }

        $digits = substr($payload, 5);
        $phoneNumber = $this->telegramOtpService->normalizePhoneNumber($digits);

        if ($phoneNumber === null) {
            return false;
        }

        $this->linkPhoneToChat($chatId, $fromUserId, $phoneNumber);

        return true;
    }

    private function handleLinkCommand(string $chatId, ?string $fromUserId, string $text): void
    {
        $parts = preg_split('/\s+/', $text, 2);
        $phoneInput = $parts[1] ?? null;

        $phoneNumber = $phoneInput !== null
            ? $this->telegramOtpService->normalizePhoneNumber($phoneInput)
            : null;

        if ($phoneNumber === null) {
            $this->telegramOtpService->sendText(
                chatId: $chatId,
                text: "Usage: /link <phone_number>\nExample: /link +85512345678"
            );

            return;
        }

        $this->linkPhoneToChat($chatId, $fromUserId, $phoneNumber);
    }

    /**
     * @param array<string, mixed> $contact
     */
    private function handleContactMessage(string $chatId, ?string $fromUserId, array $contact): void
    {
        $contactUserId = isset($contact['user_id']) ? (string) $contact['user_id'] : null;

        if ($contactUserId !== null && $fromUserId !== null && $contactUserId !== $fromUserId) {
            $this->telegramOtpService->sendText(
                chatId: $chatId,
                text: 'Please share your own phone number from your Telegram account.'
            );

            return;
        }

        $phoneInput = $contact['phone_number'] ?? null;

        if (! is_string($phoneInput)) {
            $this->telegramOtpService->sendText(
                chatId: $chatId,
                text: 'Unable to read phone number. Please try again.'
            );

            return;
        }

        $phoneNumber = $this->telegramOtpService->normalizePhoneNumber($phoneInput);

        if ($phoneNumber === null) {
            $this->telegramOtpService->sendText(
                chatId: $chatId,
                text: 'Invalid phone number format. Please try again.'
            );

            return;
        }

        $this->linkPhoneToChat($chatId, $fromUserId, $phoneNumber);
    }

    private function linkPhoneToChat(string $chatId, ?string $fromUserId, string $phoneNumber): void
    {
        $userExists = $this->telegramOtpService->linkPhoneToChat(
            phoneNumber: $phoneNumber,
            chatId: $chatId,
            telegramUserId: $fromUserId
        );
        $pendingOtpSent = $this->telegramOtpService->sendPendingOtpForPhoneIfExists($phoneNumber, $chatId);

        if ($userExists) {
            $message = "Connected successfully.\nPhone: {$phoneNumber}\nYou will receive OTP messages in real time in this chat.";

            if ($pendingOtpSent) {
                $message .= "\n\nA pending OTP was found and has been sent now.";
            }

            $this->telegramOtpService->sendText(
                chatId: $chatId,
                text: $message,
                replyMarkup: ['remove_keyboard' => true]
            );

            return;
        }

        $message = "Phone saved: {$phoneNumber}\nNo account found yet. Register in the app with this number, then OTP will arrive here.";

        if ($pendingOtpSent) {
            $message = "Phone saved: {$phoneNumber}\nA pending registration OTP has been sent to this chat. Return to the app and verify it.";
        }

        $this->telegramOtpService->sendText(
            chatId: $chatId,
            text: $message,
            replyMarkup: ['remove_keyboard' => true]
        );
    }
}
