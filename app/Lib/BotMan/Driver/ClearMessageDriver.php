<?php

namespace App\Lib\BotMan\Driver;

use BotMan\BotMan\Messages\Incoming\IncomingMessage;

interface ClearMessageDriver
{
    public function getMessageId(IncomingMessage $incomingMessage);

    public function getMessageIdFromResponse($response);

    public function deleteMessagesById($chatId, $messagesIds): void;
}
