<?php

namespace App\Lib\BotMan\Service;

use App\Lib\BotMan\Conversation\BookRoomConversation;
use App\Lib\BotMan\Conversation\FreeRoomConversation;
use App\Lib\BotMan\Conversation\OnboardingConversation;
use App\Lib\YClients\Services\YClientsService;
use App\Services\ChatService;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use Carbon\Carbon;

class BotManService
{
    private YClientsService $clientsService;
    private ChatService $chatService;

    public function __construct(YClientsService $clientsService, ChatService $chatService)
    {
        $this->clientsService = $clientsService;
        $this->chatService = $chatService;
    }

    public function quit(BotMan $bot)
    {

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
                        $bot->reply('Вы деавторизовали чат. Для авторизации введите команду /login');
                    } else {
                        $bot->reply('Действие отменено');
                    }
                }

            });
        } else {
            $bot->reply('Этот чат не авторизован. Что бы авторизоваться введите команду /login');
        }
    }

    public function login(BotMan $bot)
    {
        $chat = $this->chatService->getChat($bot->getMessage()->getRecipient());
        if (!$chat) {
            $bot->startConversation(new OnboardingConversation());
        } else {
            $bot->reply('Этот чат уже авторизован' . PHP_EOL . $this->chatService->chatToText($chat));
            $bot->reply('Что бы сменить параметры введите команду /quit');
        }
    }

    public function info(BotMan $bot)
    {
        $bot->reply(
            'Список доступных команд:' . PHP_EOL .
            '- /login - Авторизоваться' . PHP_EOL .
            '- /quit - Выйти из аккаунта' . PHP_EOL .
            '- /stop - Отменить заполнение форма' . PHP_EOL .
            '- /book - Забронировать комнату' . PHP_EOL .
            '- /list - Показать список будущих бронирований' . PHP_EOL .
            '- /free - Показать занятость переговорных на дату' . PHP_EOL .
            '- /calc - Информация о бронированиях в этом месяце' . PHP_EOL .
            '- /info - Показать эту справку'
        );
    }

    public function start(BotMan $bot)
    {
        $bot->reply('Добро пожаловать. Напишите /login для начала работы');
    }

    public function stop(BotMan $bot)
    {
        $bot->reply('Заполнение формы прервано');
    }

    public function book($bot)
    {
        $chat = $this->chatService->getChat($bot->getMessage()->getRecipient());
        if ($chat) {
            $bot->startConversation(new BookRoomConversation());
        } else {
            $bot->reply('Вы не авторизованы. Для авторизации введите команду /login');
        }
    }

    public function list($bot)
    {
        $chat = $this->chatService->getChat($bot->getMessage()->getRecipient());
        if ($chat) {
            if ($chat->client_id) {
                $list = $this->clientsService->getFutureBooks($chat);

                $text = [];
                $text[] = "_Показываются только бронирования из бота или сайта. полный список уточните у Менеджера._\n";
                $text[] = "Запланировано бронирований: " . (count($list));

                foreach ($list ?? [] as $book) {
                    $text[] = "\n" . Carbon::parse($book['date'])->format('d.m.Y H:i') . ' ' .
                        '*' . (($book['length'] ?? 0) / 60) . ' мин.*' . ' - ' .
                        '_' . $book['staff']['name'] . '_';
                }

                $bot->reply(implode("\n", $text));

            } else {
                $bot->reply('Мы пока не знаем ваш ID клиента. Он появится после первого бронирования комнаты.');
            }
        } else {
            $bot->reply('Вы не авторизованы. Для авторизации введите команду /login');
        }
    }

    public function free($bot)
    {
        $chat = $this->chatService->getChat($bot->getMessage()->getRecipient());
        if ($chat) {

            $bot->startConversation(new FreeRoomConversation());
        } else {
            $bot->reply('Вы не авторизованы. Для авторизации введите команду /login');
        }
    }

    public function calc($bot)
    {
        $chat = $this->chatService->getChat($bot->getMessage()->getRecipient());
        if ($chat) {
            if ($chat->client_id) {
                $list = $this->clientsService->getBooksByDates($chat, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

                $totalTime = $list->map(function ($book) {
                    return ($book['length'] ?? 0) + 300;
                })->sum();

                $text = [];
                $text[] = "_Показываются только бронирования из бота или сайта. полный список уточните у Менеджера._\n";
                $text[] = "В текущем месяце бронирований: " . (count($list));
                $text[] = "Суммарное время " . round(($totalTime / 60 / 60), 2) . ' ч.';

                foreach ($list ?? [] as $book) {
                    $text[] = "\n" . Carbon::parse($book['date'])->format('d.m.Y H:i') . ' ' .
                        '*' . (($book['length'] ?? 0) / 60) . ' мин.*' . ' - ' .
                        '_' . $book['staff']['name'] . '_';
                }

                $bot->reply(implode("\n", $text));

            } else {
                $bot->reply('Мы пока не знаем ваш ID клиента. Он появится после первого бронирования комнаты.');
            }
        } else {
            $bot->reply('Вы не авторизованы. Для авторизации введите команду /login');
        }
    }
}
