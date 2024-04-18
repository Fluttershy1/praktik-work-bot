<?php

namespace App\Lib\BotMan\Conversation;

use App\Lib\BotMan\Driver\ClearMessageDriver;
use App\Lib\BotMan\Service\ClearMessageService;

abstract class Conversation extends \BotMan\BotMan\Messages\Conversations\Conversation
{
    public function say($message, $additionalParameters = [])
    {
        $response = $this->bot->reply($message, $additionalParameters);

        $this->handleCreateMessageResponse($response);

        return $this;
    }

    public function ask($question, $next, $additionalParameters = [])
    {
        $response = $this->bot->reply($question, $additionalParameters);
        $this->bot->storeConversation($this, $next, $question, $additionalParameters);

        $this->handleCreateMessageResponse($response);

        return $this;
    }

    public function handleCreateMessageResponse($response)
    {
        try {
            if ($this->getBot()->getDriver() instanceof ClearMessageDriver) {
                if ($conversationId = $this->getBot()->getMessage()->getConversationIdentifier()) {
                    if ($messageId = $this->getBot()->getDriver()->getMessageIdFromResponse($response)) {
                        ClearMessageService::addMessageId($conversationId, $messageId);
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

}
