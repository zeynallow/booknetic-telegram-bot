<?php

require_once __DIR__ . "/vendor/autoload.php";

use Zeynallow\Booknetic\Database;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$database = new Database;


$sql = "CREATE TABLE IF NOT EXISTS `bot_booking` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT(20) DEFAULT NULL,
    `service_id` BIGINT(20) DEFAULT NULL,
    `date` VARCHAR(10) DEFAULT NULL,
    `time` VARCHAR(10) DEFAULT NULL,
    `first_name` VARCHAR(255) DEFAULT NULL,
    `last_name` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(255) DEFAULT NULL,
    `booking_id` VARCHAR(255) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `price` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin";


try {
    $database->exec($sql);
    echo "Table bot_booking created successfully.";
} catch (PDOException $e) {
    die("Table creation failed: " . $e->getMessage());
}