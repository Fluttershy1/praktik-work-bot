<?php

namespace App\Lib\BotMan\Service;

use App\Lib\BotMan\Driver\ClearMessageDriver;
use BotMan\BotMan\BotMan;
use Illuminate\Support\Facades\Cache;

class ClearMessageService
{
    public static function rememberMessage(BotMan $bot, $message): void
    {
        $conversationId = $message->getConversationIdentifier();

        if ($bot->getDriver() instanceof ClearMessageDriver) {
            if ($messageId = $bot->getDriver()->getMessageId($message)) {
                self::addMessageId($conversationId, $messageId);
            }
        }
    }

    public static function getCacheKey($conversationId)
    {
        return 'conservation_' . $conversationId;
    }

    public static function addMessageId($conversationId, $messageId): void
    {
        $messageIds = self::getMessagesByConservation($conversationId);
        $messageIds[] = $messageId;
        Cache::set(self::getCacheKey($conversationId), $messageIds, 3600);
    }

    public static function getMessagesByConservation($conversationId)
    {
        return Cache::get(self::getCacheKey($conversationId), []);
    }

    public static function deleteMessages(BotMan $bot)
    {
        $conversationId = $bot->getMessage()->getConversationIdentifier();
        if (empty($conversationId)) {
            return;
        }

        $messages = self::getMessagesByConservation($conversationId);
        if (empty($messages)) {
            return;
        }

        if ($bot->getDriver() instanceof ClearMessageDriver) {
            $bot->getDriver()->deleteMessagesById($bot->getMessage()->getRecipient(), $messages);
        }

        self::cleanMessages($bot);
    }

    public static function cleanMessages(BotMan $bot)
    {
        if ($conversationId = $bot->getMessage()->getConversationIdentifier()) {
            Cache::forget(self::getCacheKey($conversationId));
        }
    }
}
