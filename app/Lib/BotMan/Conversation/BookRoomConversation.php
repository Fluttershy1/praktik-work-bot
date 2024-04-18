<?php

namespace App\Lib\BotMan\Conversation;

use App\Lib\BotMan\Service\ClearMessageService;
use App\Services\ChatService;
use App\Services\YClientsService;
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
        $this->ask('Напишите дату когда хотите забронировать в формате `2024-01-15` или `15.01.2024`', function (Answer $answer) {
            $text = $answer->getText();
            try {

                try {
                    $date = Carbon::parse($text);
                } catch (\Throwable $e) {
                    $this->repeat('Не получилось распознать дату, попробуйте ввести дату ещё раз');
                    return;
                }

                if (!$date) {
                    $this->repeat('Не получилось распознать дату, попробуйте ввести дату ещё раз');
                    return;
                }

                if ($date->lessThan(Carbon::now())) {
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

            $this->date = $date->format('Y-m-d');
            $this->askTime();
        });
    }

    public function askTime()
    {
        $this->ask('Напишите время начала бронирования в формате `09:30` или `9`', function (Answer $answer) {
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
        });
    }

    public function askDuration()
    {
        $buttons = [];

        $minutesDiff = abs(Carbon::parse($this->datetime)
            ->diffInMinutes(Carbon::parse($this->date . ' 00:00:00')->setHours(config('yclients.workTimeMax'))));

        $maxMinutes = min($minutesDiff, 360);

        for ($i = 30; $i <= $maxMinutes; $i += 30) {
            $buttons[] = Button::create($i . ' минут')->value($i);
        }

        if (!count($buttons)) {
            $this->say('К сожалению нет доступных слотов');
            return;
        }

        $question = Question::create('На сколько часов Вы хотите забронировать комнату?')
            ->fallback('Произошла ошибка')
            ->callbackId('ask_duration')
            ->addButtons($buttons);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
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
                    $this->say('Процесс бронирования отменён');
                    return;
                }

                $this->staff_id = $answer->getValue();
                $this->staff_text = $staffs->where('id', $this->staff_id)->first()['name'];
                $this->save();
            } else {
                $this->repeat('Выберите пожалуйста вариант из списка');
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

        if (!$result) {
            $this->say('Возникла ошибка при бронировании. Попробуйте позже или оформите бронирование через сайт.');
        }

        ClearMessageService::deleteMessages($this->getBot());

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
