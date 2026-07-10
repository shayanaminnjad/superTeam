<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;

final class MoveHexSelectionLogic
{
    public static function GetAvailableHexes(Board $board, int $pieceId, MoveTypes $moveType): array
    {
        $availableHexes = [];

        switch ($moveType)
        {
            case MoveTypes::BACKWARD:
                $availableHexes = self::extractBackwardMoveablePieces($board, $pieceId);
                break;
            case MoveTypes::FORWARD:
                $availableHexes = self::getForwardMoveHexes($board, $pieceId);
                break;
            case MoveTypes::FREE:
                $availableHexes = self::getFreeMoveHexes($board, $pieceId);
                break;
            case MoveTypes::FORWARD_NEAR_OPPONENT:
                $availableHexes = self::extractForwardNearOpponentPieces($board, $pieceId);
                break;
            case MoveTypes::BACKWARD_NEAR_OPPONENT:
                $availableHexes = self::extractBackwardNearOpponentPieces($board, $pieceId);
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

    private static function getForwardMoveHexes(Board $board, int $pieceId): array
    {
        $res = [];

        $playerPiece = $board->getPlayerPieceData($pieceId);
        $pieceHasBall = BoardEvaluator::doesPieceHasTheBall($board, $playerPiece);

        $moveableHexes = BoardSpatial::getMoveableHexes($board, $playerPiece->getPosition(), 1, $pieceHasBall, $playerPiece->getOwner());

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        foreach($moveableHexes as $hex)
        {
            if (self::isColumnInPieceForward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide))
                $res[] = $hex;
        }

        return $res;
    }

    private static function extractBackwardMoveablePieces(Board $board, int $pieceId): array
    {
        $res = [];

        $playerPiece = $board->getPlayerPieceData($pieceId);
        $pieceHasBall = BoardEvaluator::doesPieceHasTheBall($board, $playerPiece);

        $moveableHexes = BoardSpatial::getMoveableHexes($board, $playerPiece->getPosition(), 1, $pieceHasBall, $playerPiece->getOwner());

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        foreach($moveableHexes as $hex)
        {
            if (self::isColumnInPieceBackward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide))
                $res[] = $hex;
        }

        return $res;
    }

    private static function extractForwardNearOpponentPieces(Board $board, int $playerId): array
    {
        /*
        return self::extractMoveablePieces($board, $playerId,
            fn(int $pieceColumn, HexID $hex, PlayerSide $side) => self::isColumnInPieceForward($pieceColumn, $hex->getColumn(), $side)
                && BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)
        );*/

        return [];
    }

    private static function extractBackwardNearOpponentPieces(Board $board, int $playerId): array
    {
        /*
        return self::extractMoveablePieces($board, $playerId,
            fn(int $pieceColumn, HexID $hex, PlayerSide $side) => self::isColumnInPieceBackward($pieceColumn, $hex->getColumn(), $side)
                && BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)
        );*/

        return [];
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