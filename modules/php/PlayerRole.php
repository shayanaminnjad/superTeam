<?php

namespace Bga\Games\SuperTeam;

enum PlayerRole: int
{
    case None = 0;
    case GoalKeeper = 1;
    case Captain = 2;
    case Regular = 3;
}
