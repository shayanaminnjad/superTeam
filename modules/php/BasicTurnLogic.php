<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\Utility\Logger;

enum ActionTypes : string
{
    case GoaliePass = 'Goalie Pass';
    case PassBall = 'PassBall';
    case Shoot = 'Shoot';
    case Dribble = 'Dribble';
    case Tackle = 'Tackle';
    case Move = 'Move';
    case Bypass = 'Bypass';
}

class BasicTurnLogic
{
    /*
     * @return ActionTypes[]
     */
    public static function getActionsDoableByAllPieces(Board $board, int $activePlayerId, int $inactivePlayerId) : array
    {
        $playerPieces = $board->getPlayerPiecesByOwner($activePlayerId);

        $playerWithBall = null;
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerWithBall))
        {
            if ($playerWithBall->getOwner() === $activePlayerId && $playerWithBall->getRole() === PlayerRole::GoalKeeper->value)
            {
                return [ActionTypes::GoaliePass];
            }
        }

        $actionsDoableSoFar = [];

        foreach ($playerPieces as $piece)
        {
            $actionsDoable = self::getActionsDoableByPiece($board, $piece, $activePlayerId, $inactivePlayerId , $actionsDoableSoFar);

            foreach($actionsDoable as $action)
                $actionsDoableSoFar[$action->value] = 1;
        }

        $res = [];
        foreach ($actionsDoableSoFar as $key => $value)
        {
            $res[] = ActionTypes::from($key);
        }

        return $res;
    }

    /*
     * @param ActionTypes[] $excludedActions
     * @return ActionTypes[]
     */
    public static function getActionsDoableByPiece(Board $board, PlayerPiece $actingPiece, int $activePlayerId, int $inactivePlayerId, array $excludedActions = []): array
    {
        $res = [];

        $playerPieceWithBall = null;
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPieceWithBall))
        {
            if ($playerPieceWithBall->getId() === $actingPiece->getId())
            {
                if (!isset($excludedActions[ActionTypes::PassBall->value]))
                {
                    $res[] = ActionTypes::PassBall;
                }

                if (!isset($excludedActions[ActionTypes::Shoot->value]))
                {
                    $res[] = ActionTypes::Shoot;
                }

                if (!isset($excludedActions[ActionTypes::Dribble->value]) && !isset($excludedActions[ActionTypes::Bypass->value]))
                {
                    $playerPowers = PlayerPowers::forPlayer($activePlayerId);

                    if ($playerPowers->bypassesOpponentOnDribble($actingPiece))
                    {
                        if (BypassLogic::getBypassTargets($board, $activePlayerId, $actingPiece->getPosition()))
                        {
                            $res[] = ActionTypes::Bypass;
                        }
                    } else
                    {
                        $adjacentOpponentPieces = BoardEvaluator::getAdjacentOpponentPieces($board, $board->getBallHex(), $activePlayerId, false);
                        if (count($adjacentOpponentPieces) > 0)
                        {
                            $res[] = ActionTypes::Dribble;
                        }
                    }
                }
            }
            else if ($playerPieceWithBall->getOwner() !== $activePlayerId)
            {
                if (!isset($excludedActions[ActionTypes::Tackle->value]))
                {
                    $piecesThatCanTackle = BoardEvaluator::getPiecesThatCanTackle($board, $activePlayerId, $inactivePlayerId);

                    if (array_any($piecesThatCanTackle, fn($v) => $v->getId() === $actingPiece->getId()))
                    {
                        $res[] = ActionTypes::Tackle;
                    }
                }
            }
        }

        if (!isset($excludedActions[ActionTypes::Move->value]) && DBAdapter::getInstance()->getGlobalBool('can_move_in_basic_turn'))
        {
            $playerResources = new PlayerResources($activePlayerId);
            if (MovePieceSelectionLogic::canPieceFreeMove($board, $actingPiece, $playerResources->getMorale()))
                $res[] = ActionTypes::Move;
        }

        return $res;
    }

    public static function isAnyActionAvailable(Board $board, int $activePlayerId, int $inactivePlayerId): bool
    {
        return count(self::getActionsDoableByAllPieces($board, $activePlayerId, $inactivePlayerId)) > 0;
    }
}