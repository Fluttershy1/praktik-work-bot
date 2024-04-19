<?php

namespace App\Lib\BotMan\Driver;

interface EditMessageMarkupDriver
{
    public function editMarkup($chatId, $messageId, $replyMarkup);
}
