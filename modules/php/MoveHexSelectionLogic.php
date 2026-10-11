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
                $availableHexes = self::getBackwardMoveHexes($board, $pieceId);
                break;
            case MoveTypes::FORWARD:
                $availableHexes = self::getForwardMoveHexes($board, $pieceId);
                break;
            case MoveTypes::FREE:
                $availableHexes = self::getFreeMoveHexes($board, $pieceId);
                break;
            case MoveTypes::FORWARD_NEAR_OPPONENT:
                $availableHexes = self::getForwardNearOpponentHexes($board, $pieceId);
                break;
            case MoveTypes::BACKWARD_NEAR_OPPONENT:
                $availableHexes = self::getBackwardNearOpponentHexes($board, $pieceId);
                break;
            default:
                throw new \BgaUserException(\clienttranslate("Invalid move type!"));
        }

        return $availableHexes;
    }

    private static function getFreeMoveHexes(Board $board, int $pieceId): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();

        return BoardSpatial::findMoveableHexesToTarget($board, $playerPiece, $selectedPieceData->getPath(),
            fn($board, $hex, $playerId) => true);
    }

    private static function getForwardMoveHexes(Board $board, int $pieceId): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        return BoardSpatial::findMoveableHexesToTarget($board, $playerPiece, $selectedPieceData->getPath(),
            fn($board, $hex, $playerId) =>
            BoardSpatial::isColumnInPieceForward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide));
    }

    private static function getBackwardMoveHexes(Board $board, int $pieceId): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        return BoardSpatial::findMoveableHexesToTarget($board, $playerPiece, $selectedPieceData->getPath(),
            fn($board, $hex, $playerId) =>
            BoardSpatial::isColumnInPieceBackward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide));
    }

    private static function getForwardNearOpponentHexes(Board $board, int $pieceId): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        return BoardSpatial::findMoveableHexesToTarget($board, $playerPiece, $selectedPieceData->getPath(),
            fn($board, $hex, $playerId) =>
            BoardSpatial::isColumnInPieceForward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide) &&
            BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)
        );
    }

    private static function getBackwardNearOpponentHexes(Board $board, int $pieceId): array
    {
        $playerPiece = $board->getPlayerPieceData($pieceId);

        $moveActionContext = new MoveActionContext();
        $selectedPieceData = $moveActionContext->getSelectedPieceData();
        $playerSide = Board::getPlayerSide($playerPiece->getOwner());

        return BoardSpatial::findMoveableHexesToTarget($board, $playerPiece, $selectedPieceData->getPath(),
            fn($board, $hex, $playerId) =>
            BoardSpatial::isColumnInPieceBackward($selectedPieceData->initialPosition->getColumn(), $hex->getColumn(), $playerSide) &&
            BoardEvaluator::hasAdjacentOpponentPieces($board, $hex, $playerId, true)
        );
    }
}
