<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\MoraleAssignContext;
use Bga\Games\SuperTeam\RequiredPower;
use Bga\Games\SuperTeam\TeamAttribute;

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

        $pieceSelectionContext = new PieceSelectionContext();
        $piecesThatCanTackleIds = $pieceSelectionContext->getSelectablePieceIds();

        $piecesThatCanTackle = [];
        foreach ($piecesThatCanTackleIds as $pieceId)
        {
            $piecesThatCanTackle[] = $board->getPiece($pieceId);
        }

        if (empty($piecesThatCanTackle))
        {
            $piecesThatCanTackle = BoardEvaluator::getPiecesThatCanTackle($board, \intval($this->game->getActivePlayerId()), $this->game->getInactivePlayerId());
        }

        $hexesWithTacklingPieces = [];
        foreach ($piecesThatCanTackle as $piece)
        {
            $hexesWithTacklingPieces[] = $piece->getPosition()->convertToArray();
        }

        return ["HexesWithTacklingPieces" => $hexesWithTacklingPieces];
    }


    #[PossibleAction]
    public function actPieceSelected(int $hexColumn, int $hexRow): string
    {
        $hexesWithTacklingPiecesAsArray = self::getArgs()["HexesWithTacklingPieces"];

        $hexes = [];
        foreach ($hexesWithTacklingPiecesAsArray as $hexAsArray)
        {
            $hexes[] = HexID::fromArray($hexAsArray);
        }

        $selectedHex = new HexID($hexColumn, $hexRow);

        if (!array_any($hexes, fn($v) => $selectedHex->equals($v)))
            throw new \BgaUserException("selected invalid piece id for tackling");

        DBAdapter::getInstance()->storeSelectedHex($selectedHex);
        $moraleAssignContext = new MoraleAssignContext();
        $moraleAssignContext->storeMoraleStateData(DiceRoll::class, true);

        $board = $this->game->getBoard();
        $attacker = $board->getAttacker();
        $defender = $board->getDefender();

        $pieceWithBall = null;
        $board->tryGetPlayerPieceInHex($board->getBallHex(), $pieceWithBall);
        $tacklingPiece = null;
        $board->tryGetPlayerPieceInHex($selectedHex, $tacklingPiece);
        $moraleAssignContext->storeMoraleAssignPieceIds([[$attacker, $pieceWithBall->getId()],[$defender, $tacklingPiece->getId()]]);

        DiceRollServices::storeRollInput(\intval($this->game->getActivePlayerId()), new RequiredPower(0, []), PerformTackle::class, TeamAttribute::Tackle, TeamAttribute::Dribble, true);

        return ActiveNextGoMorale::class;
    }

    function zombie(int $playerId): string {
        // the code to run when the player is a Zombie
        return '';
    }
}
