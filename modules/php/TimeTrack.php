<?php

namespace Bga\Games\SuperTeam;
class TimeTrack
{
    static public function AdvanceTimeTrack() : int
    {
        $timeTrack = self::GetTimeTrackPosition();
        $timeTrack += 1;

        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->storeGlobalInt('time_track_position', $timeTrack);

        return $timeTrack;
    }

    static public function GetTimeTrackPosition() : int
    {
        $dbAdapter = DBAdapter::getInstance();

        return $dbAdapter->getGlobalInt('time_track_position');
    }

    static public function IsHalfTime(int $currentPosition) : bool
    {
        return $currentPosition == 3;
    }

    static public function IsGameFinished(int $currentPosition) : bool
    {
        return $currentPosition == 7;
    }
}