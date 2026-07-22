<?php

enum PassTypes: string
{
    case BACKWARD = 'backward';
    case FORWARD = 'forward';
    case CROSS = 'cross';
    case THROUGH = 'through';
    case NEAR_OPPONENT = 'nearOpponent';
}