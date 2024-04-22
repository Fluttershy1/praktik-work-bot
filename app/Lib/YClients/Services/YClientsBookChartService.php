<?php

namespace App\Lib\YClients\Services;

use App\Models\Chat;
use Carbon\Carbon;
use Illuminate\Support\Str;

class YClientsBookChartService
{
    private YClientsService $yClientsService;
    /**
     * @var false|\GdImage|resource
     */
    private $image;
    private string $font;
    private int|false $fontColor;
    private int|false $backgroundColor;
    private int $x;
    private int $y;
    private int $fontSize;
    private int|false $cellEmptyColor;
    private int|false $cellBookColor;
    private int $cellSize;
    private int $cellRightMargin;
    private int $fontTitleSize;
    private int $chartStepInMinutes;
    private int $minHours;
    private int $maxHours;
    private int $fontTimeSize;
    private int|false $cellHourly;
    private int|false $fontSecondaryColor;

    public function __construct(YClientsService $yClientsService)
    {

        $this->yClientsService = $yClientsService;

        //Положение курсора
        $this->x = 0;
        $this->y = 0;

        //Шрифты
        $this->font = storage_path('fonts/arial/ARIAL.TTF');
        $this->fontTitleSize = 18;
        $this->fontSize = 12;
        $this->fontTimeSize = 8;

        //Размеры
        $this->cellSize = 16;
        $this->cellRightMargin = 4;
        $this->imagePadding = 10;

        //Прочие настройки
        $this->chartStepInMinutes = 30; //Сколько минут в одной ячейке
        $this->minHours = (int)config('yclients.workTimeMin');
        $this->maxHours = (int)config('yclients.workTimeMax');
    }

    public function init($width, $height)
    {
        $this->image = imageCreate($width, $height);

        // Цвета
        $this->fontColor = imagecolorallocate($this->image, 0, 0, 0);
        $this->fontSecondaryColor = imagecolorallocate($this->image, 128, 128, 128);
        $this->backgroundColor = imagecolorallocate($this->image, 255, 255, 255);
        $this->cellHourly = imagecolorallocate($this->image, 0, 0, 0);
        $this->cellEmptyColor = imagecolorallocate($this->image, 128, 128, 128);
        $this->cellBookColor = imagecolorallocate($this->image, 205, 92, 92);

        imagefill($this->image, 0, 0, $this->backgroundColor);
    }

    private function getStaffBooks($address, $date)
    {
        $staffs = $this->yClientsService->getStaffsByAddress($address);

        $staffBooks = [];
        foreach ($staffs as $staff) {
            $staffBooks[] = [
                'staff' => $staff,
                'books' => $this->yClientsService->getStaffRecords($staff['id'], $date)
                    ->map(function ($record) {
                        $record['date_end'] = Carbon::parse($record['date'])->addSeconds($record['length'])->format('Y-m-d H:i:s');
                        return $record;
                    })
            ];
        }

        return $staffBooks;
    }

    private function addText($text, $x = null, $y = null, $size = null, $color = null)
    {
        imagettftext(
            $this->image,
            $size ?? $this->fontSize,
            0,
            $x ?? $this->x,
            $y ?? $this->y,
            $color ?? $this->fontColor,
            $this->font,
            $text
        );
    }

    /**
     * @param Chat $chat
     * @param Carbon $date
     * @return void
     */
    public function run($chat, $date)
    {
        //Получаем список помещений и их бронирования
        $staffBooks = $this->getStaffBooks($chat->address, $date->format('Y-m-d'));

        //Вычисляем максимальную длину названия
        $maxTitleLength = collect($staffBooks)->pluck('staff.name')->map(function ($name) {
            return mb_strlen($name);
        })->max();

        //Высчитываем ширину и высоту холста
        $width = (($this->maxHours - $this->minHours) * 60 / $this->chartStepInMinutes) * ($this->cellSize + $this->cellRightMargin) + $this->imagePadding * 2;
        $height = $this->fontTitleSize * 2 + count($staffBooks) * 2 * ($this->fontSize + $this->cellSize + $this->fontTimeSize * 0.5);

        $width = max($maxTitleLength * $this->fontSize * 0.75, $width);

        //Создаём холст
        $this->init($width, $height);

        //Переводим курсор в начало холста
        $this->x = $this->imagePadding;
        $this->y = $this->imagePadding + $this->fontSize;

        //Вывод заголовка
        $this->addText('Переговорные на ' . $date->format('d.m.Y') . ':', null, null, $this->fontTitleSize);

        //Время начала и окончания рабочего дня
        $timeStart = Carbon::parse($date)->startOfDay()->setHours($this->minHours);
        $timeEnd = Carbon::parse($date)->startOfDay()->setHours($this->maxHours);

        //Выводим по очереди помещения
        foreach ($staffBooks as $staffBook) {

            //Пишем название помещения
            $this->y += $this->fontSize * 2.5;
            $this->addText(Str::ucfirst($staffBook['staff']['name']));

            $this->y += $this->fontTimeSize * 2;


            //Перебираем ячейки времени
            for (
                $time = clone $timeStart, $cellNumber = 0;
                $time->lessThan($timeEnd);
                $time->addMinutes($this->chartStepInMinutes), $cellNumber++
            ) {
                //Высчитываем отступ ячейки
                $x = $this->x + ($cellNumber) * $this->cellSize + ($cellNumber * $this->cellRightMargin);

                //Проверяем занята эта ячейка или нет
                $datetimeStart = $time->format('Y-m-d H:i:s');
                $datetimeEnd = (clone $time)->addMinutes($this->chartStepInMinutes)->format('Y-m-d H:i:s');
                $isBlock = $staffBook['books']
                        ->filter(function ($record) use ($datetimeStart, $datetimeEnd) {
                            return !($datetimeEnd <= $record['date'] || $datetimeStart >= $record['date_end']);
                        })->count() > 0;

                //Выводим ячейку
                $args = [
                    $this->image,
                    $x,
                    $this->y + $this->fontTimeSize / 2,
                    $x + $this->cellSize,
                    $this->y + $this->cellSize + $this->fontTimeSize / 2,
                    $isBlock ? $this->cellBookColor : $this->cellEmptyColor
                ];

                if ($isBlock) {
                    imagefilledrectangle(...$args);
                } else {
                    imagerectangle(...$args);
                }


                //Если нужна отбивка по часам, выводим название часа и отсечку у ячейки
                if ($time->minute === 0) {
                    $this->addText($time->format('H:i'), $x, null, $this->fontTimeSize, $this->fontSecondaryColor);

                    imagesetthickness($this->image, 2);
                    $args = [
                        $this->image,
                        $x,
                        $this->y + $this->fontTimeSize / 2,
                        $x,
                        $this->y + $this->cellSize + $this->fontTimeSize / 2,
                        $this->cellHourly
                    ];

                    imagerectangle(...$args);
                    imagesetthickness($this->image, 1);
                }


            }
            $this->y += $this->cellSize;
        }
    }

    public function render()
    {
        Header("Content-type: image/png");
        imagepng($this->image);
        imageDestroy($this->image);
    }

    public function getImagePngContent()
    {
        ob_start();
        imagepng($this->image);
        return ob_get_contents();
    }
}
