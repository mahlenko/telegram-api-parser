# API Objects Generator (Telegram)

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**⚠️ This project is provided as is and may require significant improvements and code refactoring.**

Example of a generated PHP project: [mahlenko/telegram-bot-casts](https://github.com/mahlenko/telegram-bot-casts)

The ability to instantly create and update objects from the [Telegram Bot Api](https://core.telegram.org/bots/api#available-types). documentation. You can implement your own object generation after the received JSON object.

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
SOURCE_PATH=versions

# Make a Telegramm PHP library of types and methods
BUILD_PATH=build
BASE_NAMESPACE=TelegramBot\
```

## Dependencies

- [imangazaliev/didom](https://github.com/nette/php-generatorhttps://github.com/Imangazaliev/DiDOM)
- [nette/php-generator](https://github.com/nette/php-generator)
- [symfony/console](https://symfony.com/components/Console)
- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)