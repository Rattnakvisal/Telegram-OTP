<?php
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "CONTACTS\n";
$rows = App\Models\TelegramContact::query()->get(['phone_number','telegram_chat_id','telegram_user_id','last_seen_at'])->toArray();
var_export($rows);
echo "\n\nRESOLVE\n";
try {
    $chat = app(App\Services\TelegramOtpService::class)->resolveChatId('+85578841050', null);
    echo 'CHAT_ID='.$chat.PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR='.$e->getMessage().PHP_EOL;
}
