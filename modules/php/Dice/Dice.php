<?php

namespace Bga\Games\SuperTeam\Dice;

use Bga\Games\SuperTeam\UndoStack;

class Dice
{
    private array $faces;

    public function __construct(array $faces)
    {
        $this->faces = $faces;
    }

    public function roll() : int
    {
        UndoStack::clear();

        $faceRolled = bga_rand(0, count($this->faces) - 1);

        return $this->faces[$faceRolled];
    }

    public function getDiceFaces() : array
    {
        return $this->faces;
    }
}