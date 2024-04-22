<?php

namespace App\Lib\YClients\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YClientsService
{
    private const URL = 'https://api.yclients.com';

    private mixed $companyId;

    private mixed $token;

    public function __construct()
    {
        $this->companyId = config('yclients.companyId');
        $this->token = config('yclients.token');
    }

    private function get($path, $data = [])
    {
        return Http::withHeaders(['Authorization' => $this->token])->get(self::URL . $path, $data);
    }

    private function post($path, $data = [])
    {
        $response = Http::withHeaders([
            'Authorization' => $this->token
        ])
            ->post(self::URL . $path, $data);

        Log::info('post response', [$response]);

        return $response;
    }

    public function getStaffs()
    {
        return Cache::remember('YClientsStaffs', 3600, function () {
            $response = $this->get('/api/v1/book_staff/' . $this->companyId);
            return collect($response->json());
        });
    }

    public function getStaffAddresses()
    {
        $staffs = $this->getStaffs();
        return $staffs->pluck('position.title')->unique()->values()->toArray();
    }

    public function getStaffsByAddress($address)
    {
        $staffs = $this->getStaffs();
        return $staffs
            ->where(function ($staff) use ($address) {
                return !empty($staff['position']['title']) && $staff['position']['title'] === $address;
            })
            ->values();
    }

    public function getStaffRecords($staffId, $date)
    {
        $response = $this->get('/api/v1/records/' . $this->companyId, [
            'staff_id' => $staffId,
            'start_date' => $date,
            'end_date' => $date,
        ]);

        return collect($response->json()['data']);
    }

    public function getServices()
    {
        return Cache::remember('YClientsServices', 3600, function () {
            $response = $this->get('/api/v1/services/' . $this->companyId, [
                'category_id' => config('yclients.serviceCategory')
            ]);
            return collect($response->json());
        });

    }

    /**
     * @param $staffId
     * @return array
     * @deprecated
     */
    public function getStaffService($staffId)
    {
        $allServices = $this->getServices();

        $result = [];
        foreach ($allServices as $service) {

            if ($service['active'] != 1) {
                continue;
            }

            if (!empty($service['staff']) && count($service['staff'])) {
                foreach ($service['staff'] as $staff) {
                    if ($staff['id'] == $staffId) {
                        $result[] = [
                            'staff_id' => $staff['id'],
                            'seance_length' => $staff['seance_length'],
                            'price_min' => $service['price_min'],
                            'price_max' => $service['price_max'],
                            'title' => $service['title'],
                            'booking_title' => $service['booking_title'],
                            'weight' => $service['weight'],
                        ];
                    }
                }
            }

        }

        return $result;
    }

    public function createStaffRecords($staff_id, $datetimeStart, $durationInMin, $chat)
    {
        $data = [
            'staff_id' => (int)$staff_id,
            'services' => [
                [
                    'id' => (int)config('yclients.residentServiceId'),
                    'amount' => (int)round($durationInMin / 30), //количество 30минуток
                    'cost' => 0, //стоимость на кол-во 30минуток, для резидентов 0
                ]
            ],
            'client' => [
                'phone' => $chat->phone,
                'name' => $chat->name,
                'email' => $chat->email,
            ],
            'save_if_busy' => true,
            'datetime' => $datetimeStart,
            'seance_length' => $durationInMin * 60 - 300, //Отправляем в секундах, вычитаем 5 мин
        ];

        $path = '/api/v1/records/' . $this->companyId;

        Log::info('create record', [
            'path' => $path,
            'data' => $data,
        ]);

        if (config('yclients.prodMode') === 1) {
            try {
                $response = $this->post($path, $data);
                if ($response->ok() || $response->created()) {
                    return $response->json();
                } else {
                    return false;
                }
            } catch (\Throwable $e) {
                report($e);
                return false;
            }
        } else {
            // if demo - do nothing
            return [];
        }
    }

    public function isStaffIsBook($staffId, $datetimeStart, $datetimeEnd)
    {
        $records = $this->getStaffRecords($staffId, Carbon::parse($datetimeStart)->format('Y-m-d'))
            ->map(function ($record) {
                $record['date_end'] = Carbon::parse($record['date'])->addSeconds($record['length'])->format('Y-m-d H:i:s');
                return $record;
            });

        $blockRecords = $records
            ->filter(function ($record) use ($datetimeStart, $datetimeEnd) {
                // Убираем из массива записи, которые не пересекаются с выбранным временем
                return !($datetimeEnd <= $record['date'] || $datetimeStart >= $record['date_end']);
            });

        return count($blockRecords) > 0;
    }

    public function getFutureBooks($chat)
    {
        if (!$chat->client_id) {
            return collect([]);
        }

        $response = $this->get('/api/v1/records/' . $this->companyId, [
            'client_id' => $chat->client_id,
            'start_date' => Carbon::now()->format('Y-m-d'),
        ]);

        return collect($response->json()['data'] ?? []);
    }

    public function getBooksByDates($chat, $dateStart, $dateEnd)
    {
        if (!$chat->client_id) {
            return collect([]);
        }

        $response = $this->get('/api/v1/records/' . $this->companyId, [
            'client_id' => $chat->client_id,
            'start_date' => $dateStart->format('Y-m-d'),
            'end_date' => $dateEnd->format('Y-m-d'),
        ]);

        return collect($response->json()['data'] ?? []);
    }
}
