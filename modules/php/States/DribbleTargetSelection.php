<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\UndoStack;
use Bga\Games\SuperTeam\UndoStepAction;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\MoraleAssignContext;
use Bga\Games\SuperTeam\RequiredPower;
use Bga\Games\SuperTeam\TeamAttribute;

class DribbleTargetSelection extends GameState
{
    use UndoStepAction;

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_DRIBBLE_TARGET_SELECTION,
            name: 'dribbleTargetSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is choosing dribble target'),
            descriptionMyTurn: clienttranslate('${you} must choose dribble target'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function getArgs(): array
    {
        $board = $this->game->getBoard();
        $adjacentOpponents = BoardEvaluator::getAdjacentOpponentPieces($board, $board->getBallHex(), \intval($this->game->getActivePlayerId()), false);

        $adjacentOpponentsAsArray = [];
        foreach ($adjacentOpponents as $opponent) {
            $adjacentOpponentsAsArray[] = $opponent->getPosition()->convertToArray();
        }

        return [
            "adjacentOpponents" => $adjacentOpponentsAsArray,
            "clientButtons" => UndoStack::getClientButtons($this->game),
        ];
    }

    #[PossibleAction]
    public function actTargetSelected(int $hexColumn, int $hexRow): string
    {
        UndoStack::push($this->game, self::class);

        $activePlayer = \intval($this->game->getActivePlayerId());
        $board = $this->game->getBoard();
        $adjacentOpponents = BoardEvaluator::getAdjacentOpponentPieces($board, $board->getBallHex(),$activePlayer, false);
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
            throw new \BgaUserException(\clienttranslate('Invalid dribble target ${hex} selected.'), 100, ['hex' => $selectedHex->toString()]);

        DBAdapter::getInstance()->storeSelectedHex($selectedHex);
        $moraleAssignContext = new MoraleAssignContext();
        $moraleAssignContext->storeMoraleStateData(DiceRoll::class, true);

        $attacker = $board->getAttacker();
        $pieceWithBall = null;
        $board->tryGetPlayerPieceInHex($board->getBallHex(), $pieceWithBall);
        $defender = $board->getDefender();
        $defendingPiece = null;
        $board->tryGetPlayerPieceInHex($selectedHex, $defendingPiece);
        $moraleAssignContext->storeMoraleAssignPieceIds([[$attacker, $pieceWithBall->getId()],[$defender, $defendingPiece->getId()]]);

        DiceRollServices::storeRollInput(\clienttranslate("Dribble"), $activePlayer, new RequiredPower(-1, []), PerformDribble::class, TeamAttribute::Dribble, TeamAttribute::Tackle, true,
            [$attacker => $pieceWithBall->getId(), $defender => $defendingPiece->getId()]);

        return ActiveNextGoMorale::class;
    }

    function zombie(int $playerId): string
    {
        $hexes = $this->getArgs()["adjacentOpponents"];

        if (empty($hexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($hexes);

        return $this->actTargetSelected($hex['hex_column'], $hex['hex_row']);
    }
}
