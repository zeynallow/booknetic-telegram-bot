<?php

namespace Zeynallow\Booknetic;

use Zeynallow\Booknetic\Exceptions\TelegramException;

class Telegram {
    private $telegramBotToken;

    public function __construct()
    {
        $this->telegramBotToken = $_ENV["TELEGRAM_BOT_TOKEN"];
    }

    public function setWebhook($webhook)
    {
        $response = $this->apiRequest('setWebhook', ['url' => $webhook]);
        return $response ? 'Success: '. $webhook : 'URL is wrong...';
    }

    public function sendMessage($userId, $text, $keyboard = null)
    {
        $replyMarkup = !is_null($keyboard) ? ['reply_markup' => json_encode($keyboard)] : [];

        $this->apiRequest('sendMessage', [
            'chat_id' => $userId,
            'text' => $text
        ] + $replyMarkup);
    }

    private function apiRequest($method, $parameters = [])
    {
        $url = 'https://api.telegram.org/bot' . $this->telegramBotToken . '/' . $method;
        
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POSTFIELDS => http_build_query($parameters)
        ]);

        $response = curl_exec($handle);

        if ($response === false) {
            throw new TelegramException("cURL error: " . curl_error($handle));
        }

        curl_close($handle);

        $response = json_decode($response, true);

        if (!$response || $response['ok'] !== true) {
            throw new TelegramException("Telegram API error: " . json_encode($response));
        }

        return $response['result'];
    }
}
