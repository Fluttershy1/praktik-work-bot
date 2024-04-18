<?php

namespace App\Http\Controllers;

use App\Services\BotManService;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Cache\LaravelCache;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Telegram\TelegramDriver;

class BotManController extends Controller
{
    private BotManService $botManService;

    public function __construct(BotManService $botManService)
    {
        $this->botManService = $botManService;
    }

    public function telegram()
    {
        DriverManager::loadDriver(TelegramDriver::class);

        $botman = BotManFactory::create(config('botman'), new LaravelCache());

        $botman->hears(['/start'], \Closure::fromCallable([$this->botManService, 'start']));

        $botman->hears(['Стоп', '/stop\@?.*', 'stop', 'отмена'], \Closure::fromCallable([$this->botManService, 'stop']))
            ->stopsConversation();

        $botman->hears(['Авторизация', '/login\@?.*'], \Closure::fromCallable([$this->botManService, 'login']));

        $botman->hears(['Выйти', '/quit\@?.*'], \Closure::fromCallable([$this->botManService, 'quit']));
        $botman->hears(['Информация', '/info\@?.*'], \Closure::fromCallable([$this->botManService, 'info']));

        $botman->hears(['Забронировать', '/book\@?.*'], \Closure::fromCallable([$this->botManService, 'book']));

        $botman->listen();
    }
}
