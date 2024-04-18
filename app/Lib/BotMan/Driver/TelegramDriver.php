<?php

namespace App\Lib\BotMan\Driver;

use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use Illuminate\Support\Facades\Log;

class TelegramDriver extends \BotMan\Drivers\Telegram\TelegramDriver implements ClearMessageDriver
{
    public function getMessageId(IncomingMessage $incomingMessage)
    {
        return $incomingMessage->getPayload() ? $incomingMessage->getPayload()['message_id'] ?? null : null;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @return null
     */
    public function getMessageIdFromResponse($response)
    {
        try {
            if (!$response->getContent()) {
                return null;
            }
            $data = (array)json_decode($response->getContent(), true);
            return $data['result']['message_id'] ?? null;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function deleteMessagesById($chatId, $messagesIds): void
    {
        $parameters = [
            'chat_id' => $chatId,
            'message_ids' => json_encode($messagesIds),
        ];

        try {
            $response = $this->http->post($this->buildApiUrl('deleteMessages'), [], $parameters);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
