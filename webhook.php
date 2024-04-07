<?php

require_once __DIR__ . "/vendor/autoload.php";

use Zeynallow\Booknetic\Booknetic;
use Zeynallow\Booknetic\Telegram;
use Zeynallow\Booknetic\Database;
use Zeynallow\Booknetic\BotHandler;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$database = new Database;
$booknetic = new Booknetic;
$telegram = new Telegram;

$botHandler = new BotHandler($database, $booknetic, $telegram);
$input = file_get_contents("php://input");
$botHandler->handleRequest($input);