<?php

namespace Bga\Games\SuperTeam\Cards\BallPass;

enum PassTypes: string
{
    case BACKWARD = 'backward';
    case FORWARD = 'forward';
    case CROSS = 'cross';
    case THROUGH = 'through';
    case THROUGH_BACKWARD = 'through_backward';
    case THROUGH_FORWARD = 'through_forward';
    case THROUGH_PREVIOUS = 'through_previous';
    case NEAR_OPPONENT = 'nearOpponent';
}