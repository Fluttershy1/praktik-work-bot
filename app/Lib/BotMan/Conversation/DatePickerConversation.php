<?php

namespace App\Lib\BotMan\Conversation;

use App\Lib\BotMan\Module\Calendar;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Carbon\Carbon;

trait DatePickerConversation
{
    /**
     * @param string $question
     * @param \Closure $next
     * @return void
     */
    public function askDateWithKeyboard($question, $next)
    {
        /* @var Conversation $this */
        $calendar = new Calendar();
        $buttonsArray = $calendar->getCalendar(Carbon::now()->month, Carbon::now()->year);

        $questionObject = Question::create($question)
            ->fallback('Произошла ошибка')
            ->callbackId('ask_date');

        $this->ask(
            $questionObject,
            $next,
            ['reply_markup_force' => json_encode(['inline_keyboard' => $buttonsArray], true)]
        );
    }

    /**
     * @param Answer $answer
     * @return string|void
     */
    public function prepareDateAnswer(Answer $answer)
    {
        /* @var Conversation $this */
        $calendar = new Calendar();
        try {

            //Обрабатываем команды с клавиатуры
            if ($answer->isInteractiveMessageReply()) {

                $callbackRoute = explode('-', $answer->getValue());

                if ($callbackRoute[0] === 'calendar' && $callbackRoute[1] === 'month') {
                    $replyMarkup = $calendar->getCalendar((int)$callbackRoute[2], (int)$callbackRoute[3]);
                    $this->editMarkUp($answer, ['inline_keyboard' => $replyMarkup]);
                    return;
                } elseif ($callbackRoute[0] === 'calendar' && $callbackRoute[1] === 'year') {
                    $replyMarkup = $calendar->getMonthsList((int)$callbackRoute[2]);
                    $this->editMarkUp($answer, ['inline_keyboard' => $replyMarkup]);
                    return;
                } elseif ($callbackRoute[0] === 'calendar' && $callbackRoute[1] === 'months_list') {
                    $replyMarkup = $calendar->getMonthsList((int)$callbackRoute[2]);
                    $this->editMarkUp($answer, ['inline_keyboard' => $replyMarkup]);
                    return;
                } elseif ($callbackRoute[0] === 'calendar' && $callbackRoute[1] === 'years_list') {
                    $replyMarkup = $calendar->getYearsList((int)$callbackRoute[2]);
                    $this->editMarkUp($answer, ['inline_keyboard' => $replyMarkup]);
                    return;
                } elseif ($callbackRoute[0] === 'calendar' && $callbackRoute[1] === 'day') {
                    //Если пользователь выбрал дату
                    $date = Carbon::create($callbackRoute[4], $callbackRoute[3], $callbackRoute[2]);
                    $answer->setText($date->format('Y-m-d'));
                } else {
                    $this->repeat('Неизвестная команда');
                    return;
                }

            }

            $text = $answer->getText();

            //Проверяем корректность даты
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

            } catch (\Throwable $e) {
                report($e);
                $this->say('Возникла ошибка, попробуйте ещё раз');
                return;
            }

            return $date->format('Y-m-d');

        } catch (\Throwable $e) {
            report($e);
            $this->say('Возникла ошибка, попробуйте ещё раз');
            return;
        }
    }
}
