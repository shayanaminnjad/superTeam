<?php

namespace Bga\Games\SuperTeam\Cards\BallPass;

enum PassTypes: string
{
    case BACKWARD = 'backward';
    case FORWARD = 'forward';
    case CROSS = 'cross';
    case THROUGH = 'through';
    case NEAR_OPPONENT = 'nearOpponent';
}