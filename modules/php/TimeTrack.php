<?php

namespace Bga\Games\SuperTeam;
class TimeTrack
{
    static private int $HALF_TIME_INDEX = 3;
    static private int $FULL_TIME_INDEX = 7;

    // index is the time track position; 45' is there twice, end of the first half and start of the second
    public const MINUTE_BY_POSITION = [0, 15, 30, 45, 45, 60, 75, 90];

    static public function AdvanceTimeTrack(bool $withNotify) : int
    {
        $timeTrack = self::GetTimeTrackPosition();
        $timeTrack += 1;

        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->storeGlobalInt('time_track_position', $timeTrack);

        if ($withNotify)
        {
            NotificationAdapter::getInstance()->notifyAll(
                "timeTrackAdvance", \clienttranslate('Time advances to ${minute}\''), [
                    'currentPosition' => $timeTrack,
                    'minute' => self::GetMinute($timeTrack),
                ]
            );
        }

        return $timeTrack;
    }

    static public function GetTimeTrackPosition() : int
    {
        $dbAdapter = DBAdapter::getInstance();

        return $dbAdapter->getGlobalInt('time_track_position');
    }

    static public function GetMinute(int $position) : int
    {
        return self::MINUTE_BY_POSITION[$position];
    }

    static public function GetCurrentMinute() : int
    {
        return self::GetMinute(self::GetTimeTrackPosition());
    }

    static public function IsHalfTime(int $currentPosition) : bool
    {
        return $currentPosition == TimeTrack::$HALF_TIME_INDEX;
    }

    static public function IsGameFinished(int $currentPosition) : bool
    {
        return $currentPosition == TimeTrack::$FULL_TIME_INDEX;
    }

    static public function GetProgressToEndGame() : int
    {
        $currentProgress = self::GetTimeTrackPosition();

        if ($currentProgress > self::$HALF_TIME_INDEX) //this accounts for the duplicated 45 min
            $currentProgress -= 1;

        return ($currentProgress / (self::$FULL_TIME_INDEX - 1)) * 100;
    }
}