<?php

namespace App\Lib\BotMan\Middleware;

use App\Lib\BotMan\Service\ClearMessageService;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Interfaces\Middleware\Captured;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;

class ClearConservationMiddleware implements Captured
{
    /**
     * Handle a captured message.
     *
     * @param IncomingMessage $message
     * @param callable $next
     * @param BotMan $bot
     *
     * @return mixed
     */
    public function captured(IncomingMessage $message, $next, BotMan $bot)
    {
        ClearMessageService::rememberMessage($bot, $message);

        return $next($message);
    }
}
