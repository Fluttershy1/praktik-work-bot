<?php

namespace App\Http\Controllers;

use App\Conversation\BookRoomConversation;
use App\Conversation\OnboardingConversation;
use App\Services\ChatService;
use App\Services\YClientsService;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Cache\LaravelCache;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;

class BotManController extends Controller
{
    private YClientsService $clientsService;
    private ChatService $chatService;

    public function __construct(YClientsService $clientsService, ChatService $chatService)
    {
        $this->clientsService = $clientsService;
        $this->chatService = $chatService;
    }

    public function telegram()
    {
        DriverManager::loadDriver(\BotMan\Drivers\Telegram\TelegramDriver::class);

        $botman = BotManFactory::create(config('botman'), new LaravelCache());


        $botman->hears('stop', function (BotMan $bot) {
            $bot->reply('Заполнение формы прервано');
        })->stopsConversation();

        $botman->hears('стоп', function (BotMan $bot) {
            $bot->reply('Заполнение формы прервано');
        })->stopsConversation();

        $botman->hears('/start', function (BotMan $bot) {
            $bot->reply('Добро пожаловать. Напишите `Авторизация` для начала работы');
        });

        $botman->hears('Авторизация', function ($bot) {
            $chat = $this->chatService->getChat($bot->getMessage()->getRecipient());
            if (!$chat) {
                $bot->startConversation(new OnboardingConversation());
            } else {
                $bot->reply('Этот чат уже авторизован' . PHP_EOL . $this->chatService->chatToText($chat));
                $bot->reply('Что бы сменить параметры введите команду `Выйти`');
            }
        });

        $botman->hears('Выйти', function (BotMan $bot) {

            $chatService = $this->chatService;

            $chat = $chatService->getChat($bot->getMessage()->getRecipient());
            if ($chat) {
                $question = Question::create('Вы точно хотите выйти?')
                    ->fallback('Произошла ошибка')
                    ->callbackId('ask_quit')
                    ->addButtons([
                        Button::create('Да')->value('yes'),
                        Button::create('Нет')->value('no'),
                    ]);

                $bot->ask($question, function (Answer $answer) use ($bot, $chatService) {
                    if ($answer->isInteractiveMessageReply()) {
                        if ($answer->getValue() === 'yes') {
                            $chatService->deleteChat($bot->getMessage()->getRecipient());
                            $bot->reply('Вы деавторизовали чат. Для авторизации введите команду `Авторизация`');
                        } else {
                            $bot->reply('Действие отменено');
                        }
                    }

                });
            } else {
                $bot->reply('Этот чат не авторизован. Что бы авторизоваться введите команду `Авторизация`');
            }
        });

        $botman->hears('Забронировать', function ($bot) {
            $chat = $this->chatService->getChat($bot->getMessage()->getRecipient());
            if ($chat) {
                $bot->startConversation(new BookRoomConversation());
            } else {
                $bot->reply('Вы не авторизованы. Для авторизации введите команду `Авторизация`');
            }
        });

        $botman->hears('test', function (BotMan $bot) {

            $addresses = $this->clientsService->getStaffAddresses();

            $buttons = [];
            foreach ($addresses as $address) {
                $buttons[] = Button::create($address)->value($address);
            }

            $question = Question::create('Выберете адрес')
                ->fallback('Произошла ошибка')
                ->callbackId('ask_address')
                ->addButtons($buttons);

            $bot->ask($question, function (Answer $answer) use ($bot) {
                if ($answer->isInteractiveMessageReply()) {
                    $selectedValue = $answer->getValue();
                    // $selectedText = $answer->getText();
                    $bot->reply('Выбран адрес ' . $selectedValue);
                } else {
                    $bot->reply('Выберите пожалуйста вариант из списка');
                }

            });
        });

        $botman->listen();
    }
}
