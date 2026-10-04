<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

// Values match the shirt number printed on the captain card.
enum CaptainId: int
{
    case MoveThreeWithoutBall = 1;
    case BypassOnDribble = 3;
    case MoraleOnTactic = 4;
    case MoveTwoNearOpponent = 6;
    case SkipPassBlocks = 14;
    case MoraleOnDribble = 21;
}
