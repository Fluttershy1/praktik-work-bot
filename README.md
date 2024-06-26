# Описание

Чат бот на основе BotMan для телеграм.

Позволяет резидентам [Практик](https://praktik.work) осуществлять подбор и бронирование переговорных комнат.

Из преимуществ относительно бронирования через сайт:

- запоминается пользователь, от имени которого ведётся бронирование, не требуется вводить их каждый раз
- скрывает переговорные которые находятся по другим адресам
- не нужно прокликивать несколько переговорных в поисках нужного слота по времени
- позволяет создать групповой чат в телеграмме и подключить туда коллег, для более оперативного и централизованного
  оформления бронирования

Пример запущенного бота https://t.me/PraktikWorkTestBot

Для начала работы с ботом в формате личных сообщений, напишите ему `/start`.

Для начала работы с ботом в формате группы, создайте телеграмм группу, добавьте в него бота `PraktikWorkTestBot` и дайте
боту права администратора в группе. Затем напишите в группу команду `/start` и пройдите авторизацию. После этого можно
добавлять в группу коллег.

# Разворачивание проекта

Требуется хостинг с поддержкой php 8.0+ и ssl сертификат.

БД на выбор `MySQL` или `SQLite

`nginx`\ `apache` настроить что бы смотрели в папку `public`

Дополнительно потребуется создать бота в [BotFather](https://t.me/BotFather)

После создания бота добавить через BotFather боту команды
`/mybots` - BOTNAME - Edit Bot - Edit Commands

```
login - Авторизоваться
stop - Отменить заполнение формы
book - Забронировать комнату
list - Показать список будущих бронирований
free - Показать занятость переговорных на дату
calc - Информация о бронированиях в этом месяце
quit - Выйти из аккаунта
info - Справка
```

И получить API токен от YClients.

Команды для первичной настройки laravel

```sh
git clone https://github.com/Fluttershy1/praktik-work-bot.git
cd praktik-work-bot
cp .env.example .env
vi .env
composer install
php artisan key:generate
php artisan migrate
```

Разовая настройка вебхука телеграмм, в ходе выполнения команды указать ссылку вида `https://domain.loc/api/botman`.
SSL обязателен.

```sh
php artisan botman:telegram:register --output
```

Сброс кеша

```sh
php artisan cache:clear
```

# Полезные ссылки

- [TODO](TODO.md)
- [BotMan](https://botman.io/2.0/welcome)
- [Telegram API](https://core.telegram.org/api)
- [YClients](https://developers.yclients.com/ru/)
