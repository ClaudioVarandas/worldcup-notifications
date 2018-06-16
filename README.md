# FIFA World Cup 2018 notifications
This is a WIP project, developed in a couple of hours, so this is not a best example of best practises, and i did jut for fun :)


I used the Laravel Zero project to write some commands to fetch World Cup matches data and announces some events to notifications channels (Slack).



## Instructions
- cp .env.example .env
- set env vars
- composer install
- php wc2018-notifications app:install database
- php wc2018-notifications migrate
- php wc2018-notifications migrate
- php wc2018-notifications fetch:matches
- schedule the wc2018-notifications commands in crontab

## TODO

- more notification channels
- code refactor / organization

## Requirements

- PHP 7.1.3+
- Composer
- Sqlite

## Credits
Laravel Zero was created by, and is maintained by [Nuno Maduro](https://github.com/nunomaduro)

World Cup API is provided by http://worldcup.sfg.io/ WORLD CUP 2018 ...IN JSON


## License

This project is an open-source software licensed under the [MIT license](https://github.com/laravel-zero/laravel-zero/blob/stable/LICENSE.md).
