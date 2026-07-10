<?php

namespace Bga\Games\SuperTeam\Cards\Moves;

use Bga\Games\SuperTeam\DBAdapter;

class DefensiveMovesContext
{
    private DBAdapter $dbAdapter;
    private array $piecesMoved = [];
    private CardDefensiveMoves $movesPerformed;

    private const PIECES_MOVED_KEY = 'pieces_moved';
    private const MOVES_PERFORMED_KEY = 'moves_performed';

    public function __construct()
    {
        $this->dbAdapter = DBAdapter::getInstance();

        $this->loadPiecesMoved();

        $this->loadMovesPerformed();
    }

    public function addPieceMoved(int $pieceId) : void
    {
        if (in_array($pieceId, $this->piecesMoved, true))
            return;

        $this->piecesMoved[] = $pieceId;

        $this->dbAdapter->storeGlobalString(self::PIECES_MOVED_KEY, json_encode($this->piecesMoved));
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

    public function reset(): void
    {
        //TODO: do it in one query
        $this->piecesMoved = [];
        $this->movesPerformed = CardDefensiveMoves::empty();

        $this->dbAdapter->storeGlobalString(self::PIECES_MOVED_KEY, '');
        $this->dbAdapter->storeGlobalString(self::MOVES_PERFORMED_KEY, '');
    }

    private function loadPiecesMoved(): void
    {
        $piecesMovedJson = $this->dbAdapter->getGlobalString(self::PIECES_MOVED_KEY);

        if (!empty($piecesMovedJson))
        {
            $this->piecesMoved = json_decode($piecesMovedJson, true) ?? [];
        }
    }

    private function loadMovesPerformed(): void
    {
        $movesPerformedJson = $this->dbAdapter->getGlobalString(self::MOVES_PERFORMED_KEY);

        $this->movesPerformed = CardDefensiveMoves::fromJson($movesPerformedJson);
    }
}