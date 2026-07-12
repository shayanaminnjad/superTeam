<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;

final class MoveHexSelectionLogic
{
    public static function GetAvailableHexes(Board $board, int $pieceId, MoveTypes $moveType, int $maxMovesPossible): array
    {
        $availableHexes = [];

        switch ($moveType)
        {
            case MoveTypes::BACKWARD:
                $availableHexes = self::getBackwardMoveHexes($board, $pieceId, $maxMovesPossible);
                break;
            case MoveTypes::FORWARD:
                $availableHexes = self::getForwardMoveHexes($board, $pieceId, $maxMovesPossible);
                break;
            case MoveTypes::FREE:
                $availableHexes = self::getFreeMoveHexes($board, $pieceId);
                break;
            case MoveTypes::FORWARD_NEAR_OPPONENT:
                $availableHexes = self::getForwardNearOpponentHexes($board, $pieceId, $maxMovesPossible);
                break;
            case MoveTypes::BACKWARD_NEAR_OPPONENT:
                $availableHexes = self::getBackwardNearOpponentHexes($board, $pieceId, $maxMovesPossible);
                break;
            default:
                throw new \BgaUserException("Invalid move type!");
        }

        return $availableHexes;
    }

    private static function getFreeMoveHexes(Board $board, int $pieceId): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);
        $pieceHasBall = BoardEvaluator::doesPieceHasTheBall($board, $playerPiece);

        return BoardSpatial::getMoveableHexes($board, $playerPiece->getPosition(), 1, $pieceHasBall, $playerPiece->getOwner());
    }

    private static function getForwardMoveHexes(Board $board, int $pieceId, int $movesRemaining): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);
        $pieceHasBall = BoardEvaluator::doesPieceHasTheBall($board, $playerPiece);

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        return BoardSpatial::findMoveableHexesToTarget($board, $playerPiece->getPosition() , $movesRemaining, $pieceHasBall, $playerPiece->getOwner(),
            fn($board, $hex, $playerId) =>
                MoveHexSelectionLogic::isColumnInPieceForward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide));
    }

    private static function getBackwardMoveHexes(Board $board, int $pieceId, int $movesRemaining): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);
        $pieceHasBall = BoardEvaluator::doesPieceHasTheBall($board, $playerPiece);

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        return BoardSpatial::findMoveableHexesToTarget($board, $playerPiece->getPosition() , $movesRemaining, $pieceHasBall, $playerPiece->getOwner(),
            fn($board, $hex, $playerId) =>
                MoveHexSelectionLogic::isColumnInPieceBackward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide));
    }

    private static function getForwardNearOpponentHexes(Board $board, int $pieceId, int $movesRemaining): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);
        $pieceHasBall = BoardEvaluator::doesPieceHasTheBall($board, $playerPiece);

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        return BoardSpatial::findMoveableHexesToTarget($board, $playerPiece->getPosition() , $movesRemaining, $pieceHasBall, $playerPiece->getOwner(),
            fn($board, $hex, $playerId) =>
            MoveHexSelectionLogic::isColumnInPieceForward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide) &&
            BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)
        );
    }

    private static function getBackwardNearOpponentHexes(Board $board, int $pieceId, int $movesRemaining): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);
        $pieceHasBall = BoardEvaluator::doesPieceHasTheBall($board, $playerPiece);

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        return BoardSpatial::findMoveableHexesToTarget($board, $playerPiece->getPosition() , $movesRemaining, $pieceHasBall, $playerPiece->getOwner(),
            fn($board, $hex, $playerId) =>
            MoveHexSelectionLogic::isColumnInPieceBackward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide) &&
            BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)
        );
    }

    private static function isColumnInPieceForward(int $pieceColumn, int $column, PlayerSide $playerSide): bool
    {
        return $playerSide == PlayerSide::Left && $column > $pieceColumn ||
            $playerSide == PlayerSide::Right && $column < $pieceColumn;
    }

    private static function isColumnInPieceBackward(int $pieceColumn, int $column, PlayerSide $playerSide): bool
    {
        return $playerSide == PlayerSide::Left && $column < $pieceColumn ||
            $playerSide == PlayerSide::Right && $column > $pieceColumn;
    }
}