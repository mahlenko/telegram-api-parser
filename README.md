# API Objects Generator (Telegram)

[![wakatime](https://wakatime.com/badge/user/508ba676-28fa-4f31-8af8-1dbd84610dad/project/1b24170d-621c-498d-b3d9-50719a9cc7d0.svg)](https://wakatime.com/badge/user/508ba676-28fa-4f31-8af8-1dbd84610dad/project/1b24170d-621c-498d-b3d9-50719a9cc7d0)

**⚠️ This project is provided as is and may require significant improvements and code refactoring.**

The ability to instantly create and update objects from the [Telegram Bot Api](https://core.telegram.org/bots/api#available-types). documentation. You can implement your own object generation after the received JSON object.

## Installation

```shell
composer require mahlenko/telegram-api-parser
```

Documentation parsing and JSON generation

```shell
php console telegram:json
```

After creating JSON from the documentation, you can generate PHP files of Telegram Bot API types and methods.

```shell
php console telegram:make 
```

## .env

```dotenv
# Telegram Bot API Documentation url
TELEGRAM_DOCUMENTATION_URL=https://core.telegram.org/bots/api

# Path to JSON Documentation
FILENAME_JSON=source/telegram-api.json

# Make a Telegramm PHP library of types and methods
BUILD_PATH=build
BASE_NAMESPACE=mahlenko\TelegramBot\Objects\
```

## Dependencies

- [imangazaliev/didom](https://github.com/nette/php-generatorhttps://github.com/Imangazaliev/DiDOM)
- [nette/php-generator](https://github.com/nette/php-generator)
- [symfony/console](https://symfony.com/components/Console)
- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)