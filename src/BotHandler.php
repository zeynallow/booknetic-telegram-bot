<?php
namespace Zeynallow\Booknetic;

use Zeynallow\Booknetic\Booknetic;
use Zeynallow\Booknetic\Telegram;
use Zeynallow\Booknetic\Database;

class BotHandler {
    private $database;
    private $booknetic;
    private $telegram;

    public function __construct(Database $database, Booknetic $booknetic, Telegram $telegram) {
        $this->database = $database;
        $this->booknetic = $booknetic;
        $this->telegram = $telegram;
    }

    public function handleRequest($input) {
        $result = json_decode($input);
        
        if ($result) {
            $action = null;
            $userId = null;
            $callbackQuery = null;

            if (isset($result->message)) {
                $action = $result->message->text;
                $userId = $result->message->from->id;
            }

            if (isset($result->callback_query)) {
                $userId = $result->callback_query->from->id;
                $callbackQuery = $result->callback_query;
            }

            # Select Booking
            $booking = $this->database->selectOne(
                "select * from bot_booking where user_id = ? and booking_id is null", [$userId]);

            if (!$booking) {
                # Insert Booking
                $this->database->insert("bot_booking", ["user_id" => $userId]);
                # Select Booking
                $booking = $this->database->selectOne(
                    "select * from bot_booking where user_id = ? and booking_id is null",
                    [$userId]);
            }

            if (!is_null($callbackQuery)) {
                $this->handleCallbackQuery($callbackQuery, $userId, $booking);
            } elseif (!is_null($action)) {
                $this->handleAction($action, $userId, $booking);
            }
        }
    }

    private function handleCallbackQuery($callbackQuery, $userId, $booking) {
        parse_str($callbackQuery->data, $callback);

        if (isset($callback["service_id"])) {
            $this->updateBookingService($callback, $booking);
        } elseif (isset($callback["time"])) {
            $this->updateBookingTime($callback, $booking);
        } elseif (isset($callback["confirm"])) {
            $this->confirmBooking($userId, $booking);
        }
    }

    private function handleAction($action, $userId, $booking) {
        if ($action == "/start") {
            $this->handleStartAction($userId);
        } elseif ($this->isValidDate($action)) {
            $this->handleDateAction($action, $userId, $booking);
        } elseif ($this->isInformation($action)) {
            $this->handleInformationAction($action, $userId, $booking);
        } else {
            $this->telegram->sendMessage($userId, "Please start again. Enter /start");
        }
    }

    private function handleStartAction($userId) {

        try{
            $services = $this->booknetic->getServices();
        }catch(\Exception $e){
            $this->telegram->sendMessage($userId, $e->getMessage());
        }

        $keyboard = $this->prepareServiceKeyboard($services);
        $this->telegram->sendMessage($userId, "Please select a service", [
            "inline_keyboard" => $keyboard,
            "one_time_keyboard" => true,
        ]);
    }

    private function handleDateAction($action, $userId, $booking) {
        $booking = $this->updateBookingDate($action, $booking);

        if (is_null($booking['service_id']) || is_null($booking['date'])) {
            $this->telegram->sendMessage($userId, "The information is not complete. Please start again. Enter /start");
            return;
        }
        
        try{
            $times = $this->booknetic->getTimes($booking["service_id"], $action);
        }catch(\Exception $e){
            $this->telegram->sendMessage($userId, $e->getMessage());
        }

        $keyboard = $this->prepareTimeKeyboard($times);

        $message = count($keyboard) > 0 ? "Choose the time" : "This time is busy. Choose another time";

        $this->telegram->sendMessage($userId, $message, [
            "inline_keyboard" => $keyboard,
            "one_time_keyboard" => true,
        ]);
    }

    private function handleInformationAction($action, $userId, $booking) {
        
        if (is_null($booking['service_id']) || is_null($booking['date']) || is_null($booking['time'])) {
            $this->telegram->sendMessage($userId, "The information is not complete. Please start again. Enter /start");
            return;
        }

        $information = explode(",", $action);
        $booking = $this->updateBookingInformation($information, $booking);

        $message = $this->prepareBookingConfirmationMessage($booking);

        $this->telegram->sendMessage($userId, $message, [
            "inline_keyboard" => [
                [
                    [
                        "text" => "Confirm Booking",
                        "callback_data" => "confirm",
                    ],
                ],
            ],
            "one_time_keyboard" => true,
        ]);
    }

    private function confirmBooking($userId, $booking) {

        try{
            $confirm_booking = $this->booknetic->confirmBooking(
                $booking["service_id"],
                $booking["date"],
                $booking["time"],
                    [
                        "first_name" => $booking["first_name"],
                        "last_name" => $booking["last_name"],
                        "phone" => $booking["phone"],
                        "email" => $booking["email"]
                    ]);
        }catch(\Exception $e){
            $this->telegram->sendMessage($userId, $e->getMessage());
        }
           
        if (!is_null($confirm_booking["booking_id"])) {
            
            $this->database->update(
                "bot_booking",
                ["booking_id" => $confirm_booking["booking_id"]],
                ["id" => (int) $booking['id']]
            );

            $this->telegram->sendMessage($userId, "Booking Confirmed. Booking ID: " . $confirm_booking["booking_id"], [
                "inline_keyboard" => [
                    [
                        [
                            "text" => "Add to Google Calendar",
                            "url" => $confirm_booking["google_calendar"],
                        ],
                    ],
                ],
                "one_time_keyboard" => true,
            ]);
        } else {
            $this->telegram->sendMessage($userId, "Please start again. Enter /start");
        }
    }

    private function updateBookingService($callback, $booking) {

        $this->database->update(
            "bot_booking",
            [
                "service_id" => $callback["service_id"],
                "title" => $callback["title"],
                "price" => $callback["price"],
            ],
            ["id" => (int) $booking['id']]
        );

        $this->telegram->sendMessage($booking['user_id'], "Enter the date. Example: 2024-04-06");
    }

    private function updateBookingTime($callback, $booking) {
        
        $this->database->update(
            "bot_booking",
            ["time" => $callback["time"]],
            ["id" => (int) $booking['id']]
        );
        
        $this->telegram->sendMessage($booking['user_id'], "Enter First name, Last name, E-mail, Phone. Example: Foo, Bar, foo@bar.com, +994551112233");
    }

    private function updateBookingDate($action, $booking) {
        
        $this->database->update(
            "bot_booking",
            ["date" => $action],
            ["id" => (int) $booking['id']]
        );

       return $this->database->selectOne(
            "select * from bot_booking where id = ?", [$booking['id']]);

    }

    private function updateBookingInformation($information, $booking) {

        $first_name = $information[0];
        $last_name = $information[1];
        $email = $information[2];
        $phone = $information[3];

        $this->database->update(
            "bot_booking",
            [
                "first_name" => trim($first_name),
                "last_name" => trim($last_name),
                "email" => trim($email),
                "phone" => trim($phone),
            ],
            ["id" => (int) $booking['id']]
        );

        return $this->database->selectOne(
            "select * from bot_booking where id = ?", [$booking['id']]);
        
    }

    private function prepareBookingConfirmationMessage($booking) {
        return "{$booking["title"]} - {$booking["price"]}
         Date: {$booking["date"]}
         Time: {$booking["time"]}
         First name: {$booking["first_name"]}
         Last name:  {$booking["last_name"]}
         E-mail: {$booking["email"]} 
         Phone: {$booking["phone"]}";
    }

    private function prepareServiceKeyboard($services) {
        $keyboard = [];

        foreach ($services as $service) {
            $keyboard[] = [
                [
                    "text" => "[" .$service["id"] ."] : " . $service["title"] . " - " . $service["price"],
                    "callback_data" => "service_id=" .$service["id"] ."&title=" .$service["title"] ."&price=" .$service["price"],
                ],
            ];
        }

        return $keyboard;
    }

    private function prepareTimeKeyboard($times) {
        $keyboard = [];

        foreach ($times as $time) {
            $keyboard[] = [
                [
                    "text" =>$time["start_time"] . " - " . $time["end_time"],
                    "callback_data" => "time=" . $time["start_time"],
                ],
            ];
        }

        return $keyboard;
    }

    private function isValidDate($date, $format = "Y-m-d"){
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }

    private function isInformation($action) {
        $information = explode(",", $action);
        return count($information) > 3;
    }


}

