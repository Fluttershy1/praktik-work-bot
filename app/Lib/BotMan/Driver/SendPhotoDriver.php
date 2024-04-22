<?php

namespace App\Lib\BotMan\Driver;

interface SendPhotoDriver
{
    public function sendPhoto($chatId, $message, $fileContent, $mime, $fileName);
}
