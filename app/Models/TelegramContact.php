<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramContact extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'phone_number',
        'telegram_chat_id',
        'telegram_user_id',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
