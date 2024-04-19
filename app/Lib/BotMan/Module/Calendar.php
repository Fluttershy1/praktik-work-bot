<?php

namespace App\Lib\BotMan\Module;

/**
 * https://github.com/miserenkov/telegram-bot-calendar/tree/master
 * Created by PhpStorm.
 * Author: Misha Serenkov
 * Email: mi.serenkov@gmail.com
 * Date: 15.10.2017 20:51
 */
class Calendar
{
    /**
     * @param int $month
     * @param int $year
     * @return array
     */
    private $month = [
        1 => 'Янв',
        2 => 'Февр',
        3 => 'Март',
        4 => 'Апр',
        5 => 'Май',
        6 => 'Июнь',
        7 => 'Июль',
        8 => 'Авг',
        9 => 'Сент',
        10 => 'Окт',
        11 => 'Нояб',
        12 => 'Дек'
    ];

    function getCalendar(int $month, int $year): array
    {
        $currentCode = 'calendar-month-' . $month . '-' . $year;

        $prevMonthCallback = 'calendar-month-';
        if ($month === 1) {
            $prevMonthCallback .= '12-' . ($year - 1);
        } else {
            $prevMonthCallback .= ($month - 1) . '-' . $year;
        }

        $nextMonthCallback = 'calendar-month-';
        if ($month === 12) {
            $nextMonthCallback .= '1-' . ($year + 1);
        } else {
            $nextMonthCallback .= ($month + 1) . '-' . $year;
        }

        $start = new \DateTime(sprintf('%d-%d-01', $year, $month));


        $calendarMap = [
            [
                ['text' => '<', 'callback_data' => $prevMonthCallback],
                ['text' => $this->month[(int)$start->format('m')] . $start->format(' Y'), 'callback_data' => 'calendar-months_list-' . $year],
                ['text' => '>', 'callback_data' => $nextMonthCallback],
            ],
            [
                ['text' => 'Пн', 'callback_data' => $currentCode],
                ['text' => 'Вт', 'callback_data' => $currentCode],
                ['text' => 'Ср', 'callback_data' => $currentCode],
                ['text' => 'Чт', 'callback_data' => $currentCode],
                ['text' => 'Пт', 'callback_data' => $currentCode],
                ['text' => 'Сб', 'callback_data' => $currentCode],
                ['text' => 'Вс', 'callback_data' => $currentCode],
            ],
        ];


        $end = clone $start;
        $end->modify('last day of this month');
        $iterEnd = clone $start;
        $iterEnd->modify('first day of next month');
        $row = 2;
        foreach (new \DatePeriod($start, new \DateInterval("P1D"), $iterEnd) as $date) {
            /** @var \DateTime $date */

            if (!isset($calendarMap[$row])) {
                $calendarMap[$row] = array_combine([1, 2, 3, 4, 5, 6, 7], [[], [], [], [], [], [], []]);
            }

            $dayIterator = (int)$date->format('N');
            if ($dayIterator != 1 && $start->format('d') === $date->format('d')) {
                for ($i = 1; $i < $dayIterator; $i++) {
                    $calendarMap[$row][$i] = ['text' => ' ', 'callback_data' => $currentCode];
                }
            }

            $calendarMap[$row][$dayIterator] = ['text' => $date->format('d'), 'callback_data' => sprintf('calendar-day-%d-%d-%d', $date->format('d'), $month, $year)];

            if ($dayIterator < 7 && $end->format('d') === $date->format('d')) {
                for ($i = $dayIterator + 1; $i <= 7; $i++) {
                    $calendarMap[$row][$i] = ['text' => ' ', 'callback_data' => $currentCode];
                }
                $calendarMap[$row] = array_values($calendarMap[$row]);
                break;
            }

            if ($dayIterator === 7) {
                $calendarMap[$row] = array_values($calendarMap[$row]);
                $row++;
            }
        }

        return $calendarMap;
    }

    function getMonthsList(int $year): array
    {
        $listMap = [
            [
                ['text' => '<', 'callback_data' => 'calendar-year-' . ($year - 1)],
                ['text' => $year, 'callback_data' => 'calendar-years_list-' . $year],
                ['text' => '>', 'callback_data' => 'calendar-year-' . ($year + 1)],
            ],
        ];

        $row = 1;

        for ($month = 1; $month <= 12; $month++) {
            $listMap[$row][] = ['text' => $this->month[(int)$month], 'callback_data' => sprintf('calendar-month-%d-%d', $month, $year)];

            if ($month === 3 || $month === 6 || $month === 9) {
                $row++;
            }
        }

        return $listMap;
    }

    function getYearsList(int $centerYear): array
    {
        $currentCode = 'calendar-years_list-' . $centerYear;
        $prevYear = $centerYear - 25;
        $nextYear = $centerYear + 25;
        $listMap = [
            [
                $prevYear <= 76 ? ['text' => ' ', 'callback_data' => $currentCode] : ['text' => '<', 'callback_data' => 'calendar-years_list-' . $prevYear],
//            ['text' => ' ', 'callback_data' => $currentCode],
                $nextYear >= 10024 ? ['text' => ' ', 'callback_data' => $currentCode] : ['text' => '>', 'callback_data' => 'calendar-years_list-' . $nextYear],
            ],
        ];

        $row = 1;
        $i = 0;

        for ($year = ($centerYear - 12); $year <= ($centerYear + 12); $year++) {
            if ($year >= 100 && $year <= 9999) {
                $listMap[$row][] = ['text' => $year, 'callback_data' => sprintf('calendar-months_list-%d', $year)];
                $i++;
            } else {
//            $listMap[$row][] = ['text' => ' ', 'callback_data' => sprintf('calendar-months_list-%d', $year)];
            }

            if ($i === 5 || $i === 10 || $i === 15 || $i === 20) {
                $row++;
            }
        }


        return $listMap;
    }
}
