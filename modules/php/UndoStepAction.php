<?php

namespace Bga\Games\SuperTeam;

use Bga\GameFramework\States\PossibleAction;

trait UndoStepAction
{
    #[PossibleAction]
    public function actUndoStep(): string
    {
        return UndoStack::undo($this->game);
    }
}
