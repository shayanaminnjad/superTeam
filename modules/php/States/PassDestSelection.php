<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\BoardInfo;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\PlayerSide;
use Bga\Games\SuperTeam\TeamAttribute;
use BgaUserException;

class PassDestSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PASS_DEST_SELECTION,
            name: 'passDestinationSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is choosing hex to pass the ball'),
            descriptionMyTurn: clienttranslate('choose a hex to pass the ball'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $activePlayer = \intval($this->game->getActivePlayerId());
        $actionReturnState = DBAdapter::getInstance()->getPlayerActionReturnState($activePlayer);
        if ($actionReturnState === TacticalTurnStateManager::class)
        {
            $tacticContext  = new TacticContext();

            if (!$tacticContext->GetTacticCommited())
            {
                $this->addClientButton('Undo', [$this, 'actUndo']);
            }
        }

        //todo: do we need to support this?
        //$this->addClientButton('Cancel', [$this, 'actCancel']);
        return $this->game->getValidPassTargets(null);
    }

    #[PossibleAction]
    public function actDestSelected(int $hexColumn, int $hexRow) : string
    {
        $selectedHex = new HexID($hexColumn, $hexRow);

        $passDestArgs = $this->game->getValidPassTargets(null);

        $forcedPositions = $passDestArgs["forcedPositions"];

        if (\count($forcedPositions) > 0)
        {
            $found = false;
            foreach($forcedPositions as $pos)
            {
                if(HexID::fromArray($pos)->equals($selectedHex))
                {
                    $found = true;
                    break;
                }
            }

            if (!$found)
                throw new BgaUserException('only given positions should be selected!');
        }
        else
        {
            $blockedSpaces = $passDestArgs["blockedSpaces"];
            $blockedSpaces[] = $passDestArgs["hexWithBall"];

            foreach ($blockedSpaces as $blockedSpace)
            {
                $hex = HexID::fromArray($blockedSpace);

                if ($selectedHex->equals($hex))
                    throw new BgaUserException('blocked space selected as pass destination!');
            }

            //todo: also ensure that the dest is not outside the extent of the board.
        }

        $board = $this->game->getBoard();
        $activePlayer = intval($this->game->getActivePlayerId());
        $goalieHasBall = BoardEvaluator::doesPlayerGoalieHasBall($board, $activePlayer);

        $playerSide = $this->game->getPlayerSide($activePlayer);

        $playerStarterHexes = ($playerSide == PlayerSide::Left) ? BoardInfo::$LEFT_PLAYER_STARTING_POSITIONS : BoardInfo::$RIGHT_PLAYER_STARTING_POSITIONS;

        if ($goalieHasBall && array_any($playerStarterHexes, fn($el) => $selectedHex->equals($el)))
        {
            $board->setBallLocation($selectedHex);

            $this->notify->all('successfulPass', "successful pass", [
                "ballHex" => $selectedHex->convertToArray()]);

            return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayer);
        }
        else
        {
            $hexWithBall = HexID::fromArray($passDestArgs["hexWithBall"]);
            $dummyinlineHexes = [];
            $dummyblockingHexes = [];
            $requiredPower = $board->getRequiredPower($hexWithBall, $selectedHex, $this->game->getInactivePlayerId(), $dummyinlineHexes, $dummyblockingHexes);

            DiceRollServices::storeRollInput(\intval($this->game->getActivePlayerId()), $requiredPower, PassPerform::class, TeamAttribute::Pass, TeamAttribute::None, false);
            MoraleAssignment::storeMoraleAssignData(DiceRoll::class, false);

            $dbAdapter = DBAdapter::getInstance();
            $dbAdapter->storeSelectedHex($selectedHex);

            return BeforeMoraleAssignment::class;
        }
    }

    #[PossibleAction]
    public function actCancel(int $activePlayerId) : string
    {
        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }

    #[PossibleAction]
    public function actUndo(int $activePlayerId) : string
    {
        $activePlayer = \intval($this->game->getActivePlayerId());
        $actionReturnState = DBAdapter::getInstance()->getPlayerActionReturnState($activePlayer);
        if ($actionReturnState === TacticalTurnStateManager::class)
        {
            $tacticContext  = new TacticContext();

            if (!$tacticContext->GetTacticCommited())
            {
                CardSelection::undoLastCardPlayed($activePlayerId, $this->game);
                return BeforeCardSelectionReset::class;
            }
        }

        throw new \BgaUserException("You cannot undo this action!");
    }

    function zombie(int $playerId): string
    {
        return $this->actCancel();
    }
}
