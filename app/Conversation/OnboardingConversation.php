<?php

namespace App\Conversation;

use App\Services\ChatService;
use App\Services\YClientsService;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Outgoing\Question;
use Illuminate\Support\Facades\Validator;

class OnboardingConversation extends Conversation
{
    protected $name;

    protected $phone;

    protected $email;

    protected $address;

    public function askName()
    {
        $this->say('Начат процесс регистрации. Для отмены напишите `stop` в чат');
        $this->ask('Добрый день! Напишите своё имя', function (Answer $answer) {
            $this->name = $answer->getText();
            $this->say('Рады вас приветствовать, ' . $this->name);
            $this->askPhone();
        });
    }

    public function askPhone()
    {
        $this->ask('Напишите ваш номер телефона в формате `+79112223344`', function (Answer $answer) {
            $text = $answer->getText();

            $validator = Validator::make(
                ['phone' => $text],
                ['phone' => 'required|regex:/^[\+\d]+$/i']
            );

            if (!$validator->valid()) {
                $this->repeat('Пожалуйста, введите телефон корректно');
                return;
            }

            $this->phone = $text;

            $this->askEmail();
        });
    }

    public function askEmail()
    {
        $this->ask('Напишите ваш email', function (Answer $answer) {
            $text = $answer->getText();

            $validator = Validator::make(
                ['email' => $text],
                ['email' => 'required|email']
            );

            if (!$validator->valid()) {
                $this->repeat('Пожалуйста, введите корректный email');
                return;
            }

            $this->email = $text;

            $this->askAddress();
        });
    }

    public function askAddress()
    {
        $service = app(YClientsService::class);
        $addresses = $service->getStaffAddresses();

        $buttons = [];
        foreach ($addresses as $address) {
            $buttons[] = Button::create($address)->value($address);
        }

        $question = Question::create('Выберете адрес офиса')
            ->fallback('Произошла ошибка')
            ->callbackId('ask_address')
            ->addButtons($buttons);

        $this->ask($question, function (Answer $answer) {
            if ($answer->isInteractiveMessageReply()) {
                $this->address = $answer->getValue();
                $this->save();
            } else {
                $this->repeat('Выберите пожалуйста вариант из списка');
            }

        });
    }

    public function save()
    {
        /** @var ChatService $service */
        $service = app(ChatService::class);

        try {
            $chat = $service->createChat([
                'chat_id' => $this->getBot()->getMessage()->getRecipient(),
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->say('Возникла ошибка. Попробуйте позже');
            return;

        }

        $this->say('Готово!' . PHP_EOL .
            'В дальнешем все бронирования в этом чате будут регистрироваться на:' . PHP_EOL .
            $service->chatToText($chat)
        );

        $this->say("Что бы забронировать комнату напишите /book");
    }

    public function run()
    {
        $this->askName();
    }
}
