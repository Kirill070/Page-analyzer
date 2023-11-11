### Hexlet tests and linter status:
[![Actions Status](https://github.com/Kirill070/php-project-9/workflows/hexlet-check/badge.svg)](https://github.com/Kirill070/php-project-9/actions)<br>
<a href="https://codeclimate.com/github/Kirill070/php-project-9/maintainability"><img src="https://api.codeclimate.com/v1/badges/cc3bd59c8d81ce653a91/maintainability" /></a><br>
[![Page Analyzer](https://github.com/Kirill070/php-project-9/actions/workflows/my-check.yml/badge.svg)](https://github.com/Kirill070/php-project-9/actions/workflows/my-check.yml)<br>

## Описание:

[Сервис Page Analyzer](https://page-analyzer-9r7p.onrender.com) – сайт, который анализирует указанные страницы на SEO пригодность. Разработан на базе микрофреймворка Slim.

## Минимальные требования:

* Ubuntu Linux (https://ubuntu.com/)
* PHP версии 8 и выше (https://www.php.net/downloads.php)
* Composer (https://getcomposer.org/download/)
* СУБД PostgreSQL (https://www.postgresql.org/)
* Утилита Make
```sh
$ sudo apt update
$ sudo apt install make
```

## Установка:

```sh
$ git clone git@github.com:Kirill070/php-project-9.git

$ cd php-project-9

$ make install
```
Внимание! Для подключения к базе данных приложение использует переменную окружения _DATABASE_URL_.
Запросы на создание необходимых таблиц базы данных находятся в файле _database.sql_ репозитория.

## Запуск:

```sh
$ make start
```