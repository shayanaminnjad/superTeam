<?php

namespace Bga\Games\SuperTeam\Utility;
use Bga\Games\SuperTeam\DBAdapter;

class Logger
{
    private static DBAdapter $logger;
    private static $logTable;

    private const WARNING = 'WARN';
    private const INFO = 'INFO';
    private const ERROR = 'ERROR';

    public static function init($logger, $logTable):void
    {
        self::$logger = $logger;
        self::$logTable = $logTable;
    }

    public static function logInfo(string $message) : void
    {
        self::log(self::INFO, $message);
    }

    public static function logWarning(string $message) : void
    {
        self::log(self::WARNING, $message);
    }

    public static function logError(string $message) : void
    {
        self::log(self::ERROR, $message);
    }

    private static function log(string $level, string $message ) : void
    {
        $logTable = self::$logTable;
        self::$logger->DbQuery("INSERT INTO $logTable (level, message) VALUES ('$level', '$message')");
    }
}