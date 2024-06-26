<?php

namespace App\Lib\BotMan\Conversation;

use App\Lib\BotMan\Service\ClearMessageService;
use App\Lib\YClients\Services\YClientsService;
use App\Services\ChatService;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use Carbon\Carbon;
use Carbon\Traits\Creator;

class BookRoomConversation extends Conversation
{
    protected $date;
    protected $time;
    protected $datetime;
    protected $duration;
    protected $staff_id;
    protected $staff_text;

    public function askDate()
    {
        $this->say('Начат процесс бронирования переговорной. Для отмены напишите /stop в чат');
        if (config('yclients.prodMode') !== 1) {
            $this->say('*Чат-бот работает в ДЕМО режиме. Фактически бронирования комнаты не произойдёт!*');
        }

        $this->askDateWithKeyboard(
            'Напишите дату когда хотите забронировать в формате `2024-01-15` или `15.01.2024` или выберите из списка',
            function (Answer $answer) {
                if ($value = $this->prepareDateAnswer($answer)) {

                    $date = Carbon::parse($value)->startOfDay();

                    try {

                        if ($date->lessThan(Carbon::now()->startOfDay())) {
                            $this->repeat('Нельзя бронировать уже прошедшие даты, попробуйте ввести дату ещё раз');
                            return;
                        }

                        if ($date->greaterThan(Carbon::now()->addWeeks(5))) {
                            $this->repeat('К сожалению нельзя забронировать комнату на дату больше чем через 5 недель, попробуйте ввести дату ещё раз');
                            return;
                        }

                    } catch (\Throwable $e) {
                        report($e);
                        $this->say('Возникла ошибка, попробуйте ещё раз');
                        return;
                    }


                    $this->date = $value;
                    $this->askTime();
                }
            }
        );
    }

    public function askTime()
    {
        $minTime = (int)config('yclients.workTimeMin');
        $maxTime = (int)config('yclients.workTimeMax');

        $buttonsArray = [];
        for ($i = $minTime; $i < $maxTime; $i++) {
            $hour = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $buttonsArray[] = [
                ['text' => $hour . ':00', 'callback_data' => $hour . ':00'],
                ['text' => $hour . ':30', 'callback_data' => $hour . ':30'],
            ];
        }
        $buttonsArray[] = [['text' => 'Отмена', 'callback_data' => 'cancel']];

        $this->ask(
            'Напишите время начала бронирования в формате `09:30` или `9` или выберите из списка',
            function (Answer $answer) {

                if ($answer->getValue() === 'cancel') {
                    ClearMessageService::deleteMessages($this->getBot());
                    return;
                }

                $time = $answer->getText();

                try {

                    /** @var Creator $date */
                    $date = null;

                    if (preg_match("/^\d+\:\d+$/", $time)) {
                        $date = Carbon::parse($this->date . ' ' . $time)->setSeconds(0);
                    } elseif (preg_match("/^\d+$/", $time)) {
                        $date = Carbon::parse($this->date)->startOfDay()->setHours($time);
                    } else {
                        $this->repeat('Не получилось распознать время, попробуйте ввести время ещё раз');
                        return;
                    }

                    if ($date->hour < config('yclients.workTimeMin') || $date->hour > config('yclients.workTimeMax')) {
                        $this->repeat('Мы работаем с ' . config('yclients.workTimeMin') . ' до ' . config('yclients.workTimeMax') . ', пожалуйста, выберете другое время');
                        return;
                    }

                    if ($date->minute != 0 && $date->minute != 30) {
                        $this->repeat('Пожалуйста, выберите время кратное 30 минутам');
                        return;
                    }

                    $this->time = $date->format('H:i');
                    $this->datetime = $date->format('Y-m-d H:i:s');

                } catch (\Throwable $e) {
                    report($e);
                    $this->say('Возникла ошибка, попробуйте ещё раз');
                    return;
                }

                $this->askDuration();
            },
            ['reply_markup_force' => json_encode(['inline_keyboard' => $buttonsArray], true)]);
    }

    public function askDuration()
    {
        $buttons = [];

        $minutesDiff = abs(Carbon::parse($this->datetime)
            ->diffInMinutes(Carbon::parse($this->date . ' 00:00:00')->setHours(config('yclients.workTimeMax'))));

        $maxMinutes = min($minutesDiff, 360);

        for ($i = 30; $i <= $maxMinutes; $i += 30) {
            $buttons[] = Button::create($i . ' минут (' . round(($i / 60), 2) . ' ч)')->value($i);
        }

        if (!count($buttons)) {
            $this->say('К сожалению нет доступных слотов');
            return;
        }

        $question = Question::create('На сколько часов Вы хотите забронировать комнату?')
            ->fallback('Произошла ошибка')
            ->callbackId('ask_duration')
            ->addButtons([
                ...$buttons,
                Button::create('Отмена')->value('cancel')
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {

                if ($answer->getValue() === 'cancel') {
                    ClearMessageService::deleteMessages($this->getBot());
                    return;
                }

                $this->duration = $answer->getValue();
                $this->askStaff();
            } else {
                $this->repeat('Выберите пожалуйста вариант из списка');
            }

        });
    }

    public function askStaff()
    {
        /** @var YClientsService $yClientService */
        $yClientService = app(YClientsService::class);
        /** @var ChatService $chatService */
        $chatService = app(ChatService::class);

        $chat = $chatService->getChat($this->getBot()->getMessage()->getRecipient());

        if (!$chat) {
            $this->say('Вы не авторизованы');
            return;
        }

        try {
            $staffs = $yClientService->getStaffsByAddress($chat->address);

            if ($staffs->count() === 0) {
                $this->say('К сожалению, доступных офисов по Вашему адресу нет. Попробуйте переавторизоваться командой *Выйти*');
                return;
            }

            $this->say('Подбираем подходящие варианты');

            $datetimeEnd = Carbon::parse($this->datetime)->addMinutes($this->duration)->format('Y-m-d H:i:s');
            $buttons = [];
            foreach ($staffs as $staff) {
                $isBlocked = $yClientService->isStaffIsBook($staff['id'], $this->datetime, $datetimeEnd);
                if (!$isBlocked) {
                    $buttons[] = Button::create($staff['name'])->value($staff['id']);
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $this->say('Возникла ошибка. Попробуйте забронировать позже.');
            return;
        }
        if (count($buttons) === 0) {
            $this->say('К сожалению, свободных переговорных на указанное время нет.');
            return;
        }

        $question = Question::create('Выберите переговорную')
            ->fallback('Произошла ошибка')
            ->callbackId('ask_staff')
            ->addButtons([
                ...$buttons,
                Button::create('Отмена')->value('cancel')
            ]);

        $this->ask($question, function (Answer $answer) use ($staffs) {
            if ($answer->isInteractiveMessageReply()) {

                if ($answer->getValue() === 'cancel') {
                    ClearMessageService::deleteMessages($this->getBot());
                    return;
                }

                $this->staff_id = $answer->getValue();
                $this->staff_text = $staffs->where('id', $this->staff_id)->first()['name'];
                $this->askConfirm();
            } else {
                $this->repeat('Выберите пожалуйста вариант из списка');
            }

        });
    }

    public function askConfirm()
    {
        $text = "Всё правильно выбрано?\n" .
            (config('yclients.prodMode') !== 1 ? '*ДЕМО РЕЖИМ, фактического бронирования не произойдёт*' : '') .
            "Дата: " . $this->datetime . "\n" .
            "Длительность: " . $this->duration . " *(" . round($this->duration / 60, 2) . " ч)*\n" .
            "Переговорная: _" . $this->staff_text . "_";

        $question = Question::create($text)
            ->fallback('Произошла ошибка')
            ->callbackId('ask_confirm')
            ->addButtons([
                Button::create('Забронировать')->value('yes'),
                Button::create('Отменить')->value('no'),
            ]);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                if ($answer->getValue() === 'yes') {
                    $this->save();
                } else {
                    ClearMessageService::deleteMessages($this->getBot());
                }
            }

        });
    }

    public function save()
    {

        /** @var YClientsService $yClientService */
        $yClientService = app(YClientsService::class);
        /** @var ChatService $chatService */
        $chatService = app(ChatService::class);

        $chat = $chatService->getChat($this->getBot()->getMessage()->getRecipient());

        if (!$chat) {
            $this->say('Вы не авторизованы');
            return;
        }

        try {
            $result = $yClientService->createStaffRecords($this->staff_id, $this->datetime, $this->duration, $chat);
        } catch (\Throwable $e) {
            report($e);
            $this->say('Возникла ошибка при бронировании. Попробуйте позже или оформите бронирование через сайт.');
            return;
        }

        if ($result === false) {
            $this->say('Возникла ошибка при бронировании. Попробуйте позже или оформите бронирование через сайт.');
            return;
        }

        ClearMessageService::deleteMessages($this->getBot());

        try {
            if (!$chat->cliend_id && !empty($result['client']['id'])) {

                $chat->client_id = $result['client']['id'];
                $chat->save();

                $this->say('Закреплён пользователь №' . $chat->client_id);

            }
        } catch (\Throwable $e) {
            report($e);
        }

        $this->say('Бронирование прошло успешно' . PHP_EOL .
            (config('yclients.prodMode') !== 1 ? '*ДЕМО РЕЖИМ, фактического бронирования не было*' : '') .
            implode(PHP_EOL, [
                'Время: ' . $this->datetime,
                'Длительность: ' . $this->duration . ' минут (' . round($this->duration / 60, 2) . ' ч)',
                'Переговорная: ' . $this->staff_text,
            ]));

        ClearMessageService::cleanMessages($this->bot);
    }

    public function run()
    {
        ClearMessageService::cleanMessages($this->bot);
        $this->askDate();
    }
}
