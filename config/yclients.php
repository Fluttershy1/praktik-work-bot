<?php

return [
    'companyId' => env('YCLIENTS_COMPANY'),
    'token' => env('YCLIENTS_TOKEN'),
    'serviceCategory' => env('YCLIENTS_SERVICE_CATEGORY'),
    'workTimeMin' => env('YCLIENTS_WORK_TIME_MIN'),
    'workTimeMax' => env('YCLIENTS_WORK_TIME_MAX'),
    'prodMode' => env('YCLIENTS_PROD_MODE') == 1 ? 1 : 0,
    'residentServiceId' => env('YCLIENTS_RESIDENT_SERVICE_ID'),
];
