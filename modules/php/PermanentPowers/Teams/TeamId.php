<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Captains;

enum TeamId: int
{
    case MoveThreeAlongSideRows = 0;
    case RerollNegativeDice = 1;
    case GainMoraleOnFatiguePlay = 2;
    case SpendFourOnBoost = 3;
    case OpponentNeedsExtraOneOnBlock = 4;
    case DrawFiveKeepFour = 5;
}