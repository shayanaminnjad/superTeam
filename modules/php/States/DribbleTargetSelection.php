<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\RequiredPower;
use Bga\Games\SuperTeam\TeamAttribute;

class DribbleTargetSelection extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_DRIBBLE_TARGET_SELECTION,
            name: 'dribbleTargetSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('opponent chooses dribble target'),
            descriptionMyTurn: clienttranslate('you must choose dribble target'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function getArgs(): array
    {
        $board = $this->game->getBoard();
        $adjacentOpponents = $board->getAdjacentOpponentPieces($board->getBallHex(), \intval($this->game->getActivePlayerId()), false);

        $adjacentOpponentsAsArray = [];
        foreach ($adjacentOpponents as $opponent) {
            $adjacentOpponentsAsArray[] = $opponent->getPosition()->convertToArray();
        }

        return ["adjacentOpponents" => $adjacentOpponentsAsArray];
    }

    #[PossibleAction]
    public function actTargetSelected(int $hexColumn, int $hexRow): string
    {
        $activePlayer = \intval($this->game->getActivePlayerId());
        $board = $this->game->getBoard();
        $adjacentOpponents = $board->getAdjacentOpponentPieces($board->getBallHex(),$activePlayer, false);
        $selectedHex = new HexID($hexColumn, $hexRow);

        $found = false;
        foreach($adjacentOpponents as $opponent)
        {
            if ($opponent->getPosition()->equals($selectedHex))
            {
                $found = true;
                break;
            }
        }

        if (!$found)
            throw new \BgaUserException("Invalid tackle target `" . $selectedHex->toString() . "` selected.");

        DBAdapter::getInstance()->storeSelectedHex($hexColumn ,$hexRow);
        MoraleAssignment::storeMoraleAssignData(addslashes(DiceRoll::class), true);
        DiceRoll::storeRollInput($activePlayer, new RequiredPower(-1, []), PerformDribble::class, TeamAttribute::Dribble, TeamAttribute::Tackle, true);

        return ActiveNextGoMorale::class;
    }

    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie
        return '';
    }
}
