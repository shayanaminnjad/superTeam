<?php

namespace Bga\Games\SuperTeam\Cards;

enum CardType : int
{
    case None = 0;
    case Basic = 1;
    case Captain = 2;
    case Coach = 3;
    case Team = 4;
    case Fatigue = 5;
}
