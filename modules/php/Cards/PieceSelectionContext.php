<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\DBAdapter;

class PieceSelectionContext
{
    private const string SELECTABLE_PIECES_KEY = 'selectable_pieces';
    private DBAdapter $dbAdapter;

    public function __construct()
    {
        $this->dbAdapter = DBAdapter::getInstance();
    }

    public function storeSelectablePieces(array $pieces): void
    {
        $res = [];
        foreach ($pieces as $piece)
        {
            $res[] = $piece->getId();
        }

        $this->dbAdapter->storeGlobalString(self::SELECTABLE_PIECES_KEY, json_encode($res));
    }

    public function storeSelectablePiecesIds(array $piecesIds): void
    {
        $this->dbAdapter->storeGlobalString(self::SELECTABLE_PIECES_KEY, json_encode($piecesIds));
    }

    /**
     * @return int[]
     */
    public function getSelectablePieceIds(): array
    {
        $json = $this->dbAdapter->getGlobalString(self::SELECTABLE_PIECES_KEY);

        if (empty($json))
            return [];

        return json_decode($json, true);
    }

    public function reset(): void
    {
        $this->dbAdapter->storeGlobalString(self::SELECTABLE_PIECES_KEY, '');
    }
}