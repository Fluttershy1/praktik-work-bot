<?php

namespace App\Lib\BotMan\Conversation;

use App\Lib\BotMan\Driver\ClearMessageDriver;
use App\Lib\BotMan\Driver\EditMessageMarkupDriver;
use App\Lib\BotMan\Service\ClearMessageService;
use BotMan\BotMan\Messages\Incoming\Answer;
use Illuminate\Support\Collection;

abstract class Conversation extends \BotMan\BotMan\Messages\Conversations\Conversation
{
    use DatePickerConversation;

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

    public function editMarkUp(Answer $answer, $replyMarkup)
    {
        if (
            $this->getBot()->getDriver() instanceof EditMessageMarkupDriver &&
            $this->getBot()->getDriver() instanceof ClearMessageDriver
        ) {
            if ($payload = $answer->getMessage()->getPayload()) {
                $this->getBot()->getDriver()->editMarkup(
                    $payload['chat']['id'],
                    $payload['message_id'],
                    json_encode($replyMarkup)
                );
            }
        }

        $this->repeatWithOldQuestion();
    }

    public function repeatWithOldQuestion()
    {
        $conversation = $this->bot->getStoredConversation();

        $question = unserialize($conversation['question']);

        $next = $conversation['next'];
        $additionalParameters = unserialize($conversation['additionalParameters']);

        if (is_string($next)) {
            $next = unserialize($next)->getClosure();
        } elseif (is_array($next)) {
            $next = Collection::make($next)->map(function ($callback) {
                if ($this->bot->getDriver()->serializesCallbacks() && !$this->bot->runsOnSocket()) {
                    $callback['callback'] = unserialize($callback['callback'])->getClosure();
                }

                return $callback;
            })->toArray();
        }

        $this->bot->storeConversation($this, $next, $question, $additionalParameters);
    }


}
