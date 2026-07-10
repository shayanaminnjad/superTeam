<?php

namespace Bga\Games\SuperTeam\Cards\Moves;

enum MoveTypes: string
{
    case BACKWARD = 'backward';
    case FORWARD = 'forward';
    case FREE = 'free';
    case FORWARD_NEAR_OPPONENT = 'forwardNearOpponent';
    case BACKWARD_NEAR_OPPONENT = 'backwardNearOpponent';
}