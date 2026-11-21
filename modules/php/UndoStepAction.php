<?php

namespace Bga\Games\SuperTeam;

use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\States\UndoPerfromingState;

trait UndoStepAction
{
    #[PossibleAction]
    public function actUndoStep(): string
    {
        return UndoPerfromingState::class;
    }
}
