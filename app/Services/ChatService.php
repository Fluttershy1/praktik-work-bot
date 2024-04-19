<?php

namespace App\Services;

use App\Models\Chat;

class ChatService
{
    public function getChat($chatId)
    {
        return Chat::query()->where('chat_id', $chatId)->first();
    }

    public function deleteChat($chatId)
    {
        return Chat::query()->where('chat_id', $chatId)->delete();
    }

    public function createChat($data)
    {
        return Chat::create([
            'chat_id' => $data['chat_id'] ?? null,
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
    }

    public function chatToText($chat)
    {
        return 'Имя: ' . $chat->name . PHP_EOL .
            'Телефон: ' . $chat->phone . PHP_EOL .
            'Email: ' . $chat->email . PHP_EOL .
            'Адрес офиса: ' . $chat->address . PHP_EOL .
            'ID клиента: ' . ($chat->client_id ?: 'Появится при первом бронировании');
    }
}
