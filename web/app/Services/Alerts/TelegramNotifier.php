<?php

namespace App\Services\Alerts;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TelegramNotifier
{
    public function configured(): bool
    {
        return Setting::getSecret('telegram.bot_token') !== null
            && filled(Setting::get('telegram.chat_id'));
    }

    public function send(string $text): bool
    {
        $token = Setting::getSecret('telegram.bot_token');
        $chat = (string) Setting::get('telegram.chat_id', '');
        if ($token === null || $chat === '' || $text === '') {
            return false;
        }
        if (! preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', $token)) {
            return false;
        }
        if (! preg_match('/^-?\d{5,20}$/', $chat) && ! preg_match('/^@[A-Za-z0-9_]{5,32}$/', $chat)) {
            return false;
        }
        try {
            $res = Http::timeout(8)
                ->asForm()
                ->post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                    'chat_id' => $chat,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);
            return $res->successful() && (bool) $res->json('ok');
        } catch (\Throwable $e) {
            Log::warning('telegram send failed', ['class' => $e::class]);
            return false;
        }
    }
}
