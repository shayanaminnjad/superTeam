<?php

namespace Bga\Games\SuperTeam;

class NotificationAdapter
{
    /** @var callable */
    private $notifyAllCallback;

    /** @var callable */
    private $notifyPlayerCallback;

    /** @var callable */
    private $playerByIdCallback;

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

    public function init(callable $notifyAll, callable $notifyPlayer, callable $playerNameById)
    {
        $this->notifyAllCallback = $notifyAll;
        $this->notifyPlayerCallback = $notifyPlayer;
        $this->playerByIdCallback = $playerNameById;
    }

    public function notifyAll(string $name, string $message, array $args)
    {
        ($this->notifyAllCallback)($name, $message, $args);
    }

    public function notifyPlayer(int $playerId, string $name, string $message, array $args)
    {
        ($this->notifyPlayerCallback)($playerId, $name, $message, $args);
    }

    public function getPlayerNameById(int $playerId) : string
    {
        return ($this->playerByIdCallback)($playerId);
    }
}
