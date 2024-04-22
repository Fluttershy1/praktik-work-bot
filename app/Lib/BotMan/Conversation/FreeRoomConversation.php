<?php

namespace App\Lib\BotMan\Conversation;

use App\Lib\BotMan\Service\ClearMessageService;
use App\Lib\YClients\Services\YClientsBookChartService;
use App\Services\ChatService;
use BotMan\BotMan\Messages\Incoming\Answer;
use Carbon\Carbon;

class FreeRoomConversation extends Conversation
{
    protected $date;

    public function askDate()
    {
        $this->askDateWithKeyboard(
            "Уточните, на какой день Вы хотите проверить занятость переговорных? " .
            "Для отмены напишите /stop в чат.\n" .
            "Напишите дату в формате `2024-01-15` или `15.01.2024` или выберите из списка",
            function (Answer $answer) {
                if ($value = $this->prepareDateAnswer($answer)) {

                    $date = Carbon::parse($value)->startOfDay();

                    try {

                        if ($date->lessThan(Carbon::now()->startOfDay())) {
                            $this->repeat('К сожалению, нельзя уточнить занятость переговорных за уже прошедшее время');
                            return;
                        }

                        if ($date->greaterThan(Carbon::now()->addWeeks(5))) {
                            $this->repeat('К сожалению нельзя уточнить занятость переговорных на дату больше чем через 5 недель, попробуйте ввести дату ещё раз');
                            return;
                        }

                    } catch (\Throwable $e) {
                        report($e);
                        $this->say('Возникла ошибка, попробуйте ещё раз');
                        return;
                    }


                    $this->date = $value;
                    $this->renderImage();
                }
            }
        );
    }

    public function renderImage()
    {

        /** @var YClientsBookChartService $yClientBookService */
        $yClientBookService = app(YClientsBookChartService::class);
        /** @var ChatService $chatService */
        $chatService = app(ChatService::class);

        $chat = $chatService->getChat($this->getBot()->getMessage()->getRecipient());

        if (!$chat) {
            $this->say('Вы не авторизованы');
            return;
        }

        try {

            $date = Carbon::parse($this->date);

            $this->say('Подготавливаем отчёт');

            $yClientBookService->run($chat, $date);
            $imageContent = $yClientBookService->getImagePngContent();

            ClearMessageService::deleteMessages($this->getBot());

            $this->sendPhoto(
                'Доступные переговорные на ' . $date->format('d.m.Y'),
                $imageContent,
                'image/png',
                'chart.js'
            );

        } catch (\Throwable $e) {
            $this->say('Возникла ошибка с генерацией отчёта');
            report($e);
        }

        ClearMessageService::cleanMessages($this->bot);
    }

    public function run()
    {
        ClearMessageService::cleanMessages($this->bot);
        $this->askDate();
    }
}
