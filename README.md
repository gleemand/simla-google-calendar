# Google Calendar (MVP)

## Setup

1. Copy your `credentials.json` provided by **Google API Console** to `./config/`.
2. Copy content of `./config/config.json.dist` to `./config/config.json`.
3. Set `base_url` of your app in `./config/config.json`.
4. Copy `.env.dev` to `.env`.
5. Run:
```shell
make start
``` 
6. Make a coffee.
7. Open http://localhost:80.

## Usage

```shell
/usr/local/bin/php bin/console app:sync
``` 
- to run sync for all users. Command for **cron** that should run periodically.
```shell
/usr/local/bin/php bin/console app:reset
``` 
- to run history reset for all users. Updates sinceId of orderHistory.