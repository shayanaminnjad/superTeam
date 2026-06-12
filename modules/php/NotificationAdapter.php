<?php

namespace Bga\Games\SuperTeam;

class NotificationAdapter
{
    /** @var callable */
    private $notifyAllCallback;

    /** @var NotificationAdapter */
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new NotificationAdapter();
        }

        return self::$instance;
    }

    private function __construct() {}

    public function init(callable $notifyAll)
    {
        $this->notifyAllCallback = $notifyAll;
    }

    public function notifyAll(string $name, string $message, array $args)
    {
        ($this->notifyAllCallback)($name, $message, $args);
    }
}
