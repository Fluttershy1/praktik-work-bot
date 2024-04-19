<?php

namespace App\Lib\BotMan\Driver;

use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class TelegramDriver extends \BotMan\Drivers\Telegram\TelegramDriver implements ClearMessageDriver, EditMessageMarkupDriver
{
    private $unhandledMessageIds = [];

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

    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $parameters = parent::buildServicePayload($message, $matchingMessage, $additionalParameters);

        if (!empty($additionalParameters['reply_markup_force'])) {
            $parameters['reply_markup'] = $additionalParameters['reply_markup_force'];
        }

        return $parameters;
    }

    public function editMarkup($chatId, $messageId, $replyMarkup)
    {
        try {
            $response = $this->http->post($this->buildApiUrl('editMessageReplyMarkup'), [], [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => $replyMarkup,
            ]);
            $this->unhandledMessageIds[] = $messageId;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function messagesHandled()
    {
        $callback = $this->payload->get('callback_query');
        $hideInlineKeyboard = $this->config->get('hideInlineKeyboard', true);

        if ($callback !== null && $hideInlineKeyboard) {
            if (in_array($callback['message']['message_id'], $this->unhandledMessageIds)) {
                return;
            }
        }

        parent::messagesHandled();
    }
}
