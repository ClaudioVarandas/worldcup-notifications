# FIFA World Cup 2018 Slack notifications
This is a WIP project, developed in a couple of hours in the second day of the World Cup 2018, so this is not a best example of PHP Code or best practises, my goal its to have something functional and i did jut for fun :)


I used the Laravel Zero project to write some commands to fetch World Cup matches data and announces some events to notifications channels (Slack).


## Instructions
- create a database
- cp .env.example .env
- set env vars
- composer install
- php wc2018-notifications app:install database
- php wc2018-notifications migrate:fresh
- php wc2018-notifications fetch:matches
- schedule wc2018-notifications commands in crontab

## Notifications 

- Match start / end
- Score change
- Match events (penalty, card, goal, ...)

## TODO

- more notification channels
- code refactor / organization

## Requirements

- PHP 7.1.3+
- Composer
- Sqlite

## Thanks 

Laravel Zero was created by, and is maintained by [Nuno Maduro](https://github.com/nunomaduro)

World Cup API is provided by http://worldcup.sfg.io/ WORLD CUP 2018 ...IN JSON

## Warning

This project is based on a scraper, and scraping is inherently a messy and brittle procedural process. 


## License

This project is an open-source software licensed under the [MIT license](https://github.com/laravel-zero/laravel-zero/blob/stable/LICENSE.md).
