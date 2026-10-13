<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BypassLogic;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\MoraleAssignContext;
use Bga\Games\SuperTeam\RequiredPower;
use Bga\Games\SuperTeam\TeamAttribute;

class BypassTargetSelection extends PlayerBaseState
{

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_BYPASS_TARGET_SELECTION,
            name: 'bypassTargetSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is choosing bypass target'),
            descriptionMyTurn: clienttranslate('you must choose bypass target'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $board = $this->game->getBoard();
        $activePlayer = \intval($this->game->getActivePlayerId());

        $bypassableOpponents = BypassLogic::getBypassTargets($board, $activePlayer, $board->getBallHex());
        $bypassableOpponentsAsArray = [];
        foreach ($bypassableOpponents as $opponent) {

            $bypassableOpponentsAsArray[] = $opponent->getPosition()->convertToArray();
        }

        return ["bypassableOpponents" => $bypassableOpponentsAsArray];
    }

    #[PossibleAction]
    public function actTargetSelected(int $hexColumn, int $hexRow): string
    {
        $board = $this->game->getBoard();
        $activePlayer = \intval($this->game->getActivePlayerId());
        $bypassableOpponents = BypassLogic::getBypassTargets($board, $activePlayer, $board->getBallHex());

        $selectedHex = new HexID($hexColumn, $hexRow);

        $found = false;
        foreach($bypassableOpponents as $opponent)
        {
            if ($opponent->getPosition()->equals($selectedHex))
            {
                $found = true;
                break;
            }
        }

        if (!$found)
            throw new \BgaUserException(\clienttranslate('Invalid bypass target ${hex} selected.'), 100, ['hex' => $selectedHex->toString()]);

        DBAdapter::getInstance()->storeSelectedHex($selectedHex);

        if (BypassLogic::doesBypassRequiresDiceRoll())
        {
            $moraleAssignContext = new MoraleAssignContext();
            $moraleAssignContext->storeMoraleStateData(DiceRoll::class, true);

            $attacker = $board->getAttacker();
            $pieceWithBall = null;
            $board->tryGetPlayerPieceInHex($board->getBallHex(), $pieceWithBall);
            $defender = $board->getDefender();
            $defendingPiece = null;
            $board->tryGetPlayerPieceInHex($selectedHex, $defendingPiece);
            $moraleAssignContext->storeMoraleAssignPieceIds([[$attacker, $pieceWithBall->getId()], [$defender, $defendingPiece->getId()]]);

            DiceRollServices::storeRollInput(\clienttranslate("Bypass"), $activePlayer, new RequiredPower(-1, []), BypassPerform::class, TeamAttribute::Dribble, TeamAttribute::Tackle, true,
                [$attacker => $pieceWithBall->getId(), $defender => $defendingPiece->getId()]);

            return ActiveNextGoMorale::class;
        }
        else
            return BypassPerform::class;
    }

    function zombie(int $playerId): string
    {
        $hexes = $this->getArgs()["bypassableOpponents"];

        if (empty($hexes))
            return DBAdapter::getInstance()->getPlayerActionReturnState($playerId);

        $hex = $this->getRandomZombieChoice($hexes);

        return $this->actTargetSelected($hex['hex_column'], $hex['hex_row']);
    }

}