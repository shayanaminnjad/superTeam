<?php

namespace Bga\Games\SuperTeam;

enum StatAction: string
{
    case Pass = 'pass';
    case Shoot = 'shoot';
    case Dribble = 'dribble';
    case Tackle = 'tackle';}
