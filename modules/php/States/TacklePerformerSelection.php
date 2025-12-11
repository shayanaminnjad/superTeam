<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\DBAdapter;

class TacklePerformerSelection extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PERFORMER_SELECTION,
            name: 'tacklePerformerSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('opponent must choose a player to tackle'),
            descriptionMyTurn: clienttranslate('you must choose a player to tackle'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function getArgs(): array
    {
        $board = $this->game->getBoard();

        $piecesThatCanTackle = $board->getPiecesThatCanTackle(\intval($this->game->getActivePlayerId()), $this->game->getInactivePlayerId());

        $piecesThatCanTackleIds = [];
        foreach($piecesThatCanTackle as $piece)
        {
            $piecesThatCanTackleIds[] = $piece->getPosition()->convertToArray();
        }

        return ["HexesWithTacklingPieces" => $piecesThatCanTackleIds];
    }


    #[PossibleAction]
    public function actPieceSelected(int $hexColumn, int $hexRow): string
    {
        $board = $this->game->getBoard();
        $piecesThatCanTackle = $board->getPiecesThatCanTackle(\intval($this->game->getActivePlayerId()), $this->game->getInactivePlayerId());
        $selectedHex = new HexID($hexColumn, $hexRow);

        $found = false;
        foreach($piecesThatCanTackle as $piece)
        {
            if ($piece->getPosition()->equals($selectedHex))
            {
                $found = true;
                break;
            }
        }

        if (!$found)
            throw new \BgaUserException("selected invalid piece id for tackling");

        DBAdapter::getInstance()->storeSelectedHex($hexColumn ,$hexRow);
        MoraleAssignment::storeMoraleAssignData(addslashes(DiceRoll::class), true);
        DiceRoll::storeRollData(0, addslashes(PerformTackle::class), true);

        return ActiveNextGoMorale::class;
}

    function zombie(int $playerId): string {
        // the code to run when the player is a Zombie
        return '';
    }
}
