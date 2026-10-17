<?php

namespace Bga\Games\SuperTeam;

enum ActionTypes : string
{
    case GoaliePass = 'Goalie Pass';
    case PassBall = 'PassBall';
    case Shoot = 'Shoot';
    case Dribble = 'Dribble';
    case Tackle = 'Tackle';
    case Move = 'Move';
    case Bypass = 'Bypass';
}
