<?php

namespace Bga\Games\SuperTeam;
class TimeTrack
{
    public function AdvanceTimeTrack() : int
    {
        $timeTrack = $this->GetTimeTrackPosition();
        $timeTrack += 1;

        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->storeGlobalInt('time_track_position', $timeTrack);

        return $timeTrack;
    }

    public function GetTimeTrackPosition() : int
    {
        $dbAdapter = DBAdapter::getInstance();

        $dbAdapter->getGlobalInt('time_track_position');
    }
}