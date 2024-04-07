<?php

require_once __DIR__ . '/vendor/autoload.php'; 

use Zeynallow\Booknetic\Telegram;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$telegram = new Telegram;

print_r($telegram->setWebhook("https://$_SERVER[HTTP_HOST]/webhook.php"));
