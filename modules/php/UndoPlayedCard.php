<?php

namespace Bga\Games\SuperTeam;

use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\States\CardSelection;

// For player states a captain or coach card can sit in before its first selection is made.
trait UndoPlayedCard
{
    protected function addUndoButtonIfPlayedCardUndoable(): void
    {
        if (CardSelection::isPlayedCardUndoable($this->game, \intval($this->game->getActivePlayerId())))
        {
            $this->addClientButton(\clienttranslate("Undo"), [$this, 'actUndo'], [], new ClientButtonParams(ClientButtonColor::ALERT));
        }
    }

    #[PossibleAction]
    public function actUndo(): string
    {
        return CardSelection::undoPlayedCard($this->game, \intval($this->game->getActivePlayerId()));
    }
}
