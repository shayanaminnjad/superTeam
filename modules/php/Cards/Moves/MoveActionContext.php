<?php

namespace Bga\Games\SuperTeam\Cards\Moves;

use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\PlayerPiece;
use http\Exception\RuntimeException;

//(sha) consider whether caching is required at all.
class MoveActionContext
{
    private DBAdapter $dbAdapter;
    private array $piecesMoved = [];
    private CardDefensiveMoves $movesPerformed;

    private const string MOVEABLE_PIECES_KEY = 'moveable_pieces';
    private const string PIECES_MOVED_KEY = 'pieces_moved';
    private const string MOVES_PERFORMED_KEY = 'moves_performed';
    private const string CURRENT_MOVE_TYPE_KEY = 'current_move_type';
    private const string SELECTED_PIECE_DATA_KEY = 'move_selected_piece_data';

    public function __construct()
    {
        $this->dbAdapter = DBAdapter::getInstance();

        $this->loadPiecesMoved();

        $this->loadMovesPerformed();
    }

    public function getPiecesMoved() : array
    {
        return $this->piecesMoved;
    }

    public function getMovesPerformed() : CardDefensiveMoves
    {
        return $this->movesPerformed;
    }

    public function storeMovesPerformed(CardDefensiveMoves $movesPerformed) : void
    {
        $this->movesPerformed = $movesPerformed;

        $this->dbAdapter->storeGlobalString(self::MOVES_PERFORMED_KEY, $movesPerformed->toJson());
    }

    public function storeMoveablePieces(array $pieces) : void
    {
        $res = [];
        foreach ($pieces as $piece)
        {
            $res[] = $piece->getId();
        }

        $this->dbAdapter->storeGlobalString(self::MOVEABLE_PIECES_KEY, json_encode($res));
    }

    public function getMoveablePiecesIds() : array
    {
        $json = $this->dbAdapter->getGlobalString(self::MOVEABLE_PIECES_KEY);

        if (empty($json))
            return [];

        return json_decode($json, true);
    }

    public function getCurrentMoveType() : MoveTypes
    {
        $moveTypeStr = $this->dbAdapter->getGlobalString(self::CURRENT_MOVE_TYPE_KEY);

        $moveType = MoveTypes::tryFrom($moveTypeStr);

        if ($moveType == null)
            throw new RuntimeException("CurrentMoveType has not been set! it should be set before the move begins");

        return $moveType;
    }

    public function setCurrentMoveType(MoveTypes $moveTypes) : void
    {
        $this->dbAdapter->storeGlobalString(self::CURRENT_MOVE_TYPE_KEY, $moveTypes->value);
    }

    public function setSelectedPieceData(PlayerPiece $piece) : void
    {
        $movedPieceInfo = new MovedPieceInfo($piece->getId(), $piece->getPosition());

        $this->dbAdapter->storeGlobalString(self::SELECTED_PIECE_DATA_KEY, $movedPieceInfo->toJson());
    }

    public function getSelectedPieceData() : MovedPieceInfo
    {
        $movedPieceInfo = $this->dbAdapter->getGlobalString(self::SELECTED_PIECE_DATA_KEY);

        if (empty($movedPieceInfo))
            throw new RuntimeException('selected piece has not been set!');

        return MovedPieceInfo::fromJson($movedPieceInfo);
    }

    public function moveSelectedPieceDataToMovedPieces() : void
    {
        $selectedPieceData = $this->getSelectedPieceData();
        $this->piecesMoved[] = $selectedPieceData;

        $this->storeMovedPieces();

        $this->dbAdapter->storeGlobalString(self::SELECTED_PIECE_DATA_KEY, '');
    }

    private function storeMovedPieces() : void
    {
        $this->dbAdapter->storeGlobalString(self::PIECES_MOVED_KEY,
            json_encode(array_map(fn(MovedPieceInfo $p) => $p->toArray(), $this->piecesMoved)));
    }

    public function reset(): void
    {
        //TODO: do it in one query
        $this->piecesMoved = [];
        $this->movesPerformed = CardDefensiveMoves::empty();

        $this->dbAdapter->storeGlobalString(self::PIECES_MOVED_KEY, '');
        $this->dbAdapter->storeGlobalString(self::MOVES_PERFORMED_KEY, '');
        $this->dbAdapter->storeGlobalString(self::MOVEABLE_PIECES_KEY, '');
        $this->dbAdapter->storeGlobalString(self::CURRENT_MOVE_TYPE_KEY, '');
        $this->dbAdapter->storeGlobalString(self::SELECTED_PIECE_DATA_KEY, '');
    }

    private function loadPiecesMoved(): void
    {
        $piecesMovedJson = $this->dbAdapter->getGlobalString(self::PIECES_MOVED_KEY);

        if (empty($piecesMovedJson))
        {
            $this->piecesMoved = [];
            return;
        }

        $piecesMoved = json_decode($piecesMovedJson, true);

        $this->piecesMoved = array_map(
            fn(array $pieceMoved) => MovedPieceInfo::fromArray($pieceMoved),
            $piecesMoved ?? []
        );
    }

    private function loadMovesPerformed(): void
    {
        $movesPerformedJson = $this->dbAdapter->getGlobalString(self::MOVES_PERFORMED_KEY);

        $this->movesPerformed = CardDefensiveMoves::fromJson($movesPerformedJson);
    }
}