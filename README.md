# FIFA World Cup 2018 notifications
This is a WIP
<h4> <center>This is a Laravel Zero project to fetch World Cup matches data and announces some events to notifications channels (Slack), i did this just for fun.</h4>

## Instructions
- cp .env.example .env
- set env vars
- composer install
- php wc2018-notifications app:install database
- php wc2018-notifications migrate
- php wc2018-notifications migrate
- php wc2018-notifications fetch:matches
- schedule the wc2018-notifications commands in crontab

## Requirements

- PHP 7.1.3+
- Composer
- Sqlite

## Credits
Laravel Zero was created by, and is maintained by [Nuno Maduro](https://github.com/nunomaduro)

World Cup API is provided by http://worldcup.sfg.io/ WORLD CUP 2018 ...IN JSON


------

## License

This project is an open-source software licensed under the [MIT license](https://github.com/laravel-zero/laravel-zero/blob/stable/LICENSE.md).
