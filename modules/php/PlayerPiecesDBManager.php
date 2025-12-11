<?php

namespace Bga\Games\SuperTeam;

class PlayerPiecesDBManager
{
    //TODO: this class doesn't do much, I should encapsulate piece logic into a single class, which resides inside board.
    static public function getPiecesDataQuery(): string
    {
        return "SELECT `id`, `hex_column`, `hex_row`, `owner_id`, `piece_role`, `is_fresh` FROM `player_pieces`";
    }

    static public function resetPiecesDataQuery(): string
    {
        $goalkeeperValue = PlayerRole::GoalKeeper->value;

        return "UPDATE `player_pieces` SET
    `hex_column` = CASE 
                       WHEN `piece_role` = $goalkeeperValue THEN `hex_column`
                       ELSE -1
                   END,
    `hex_row` = CASE 
                    WHEN `piece_role` = $goalkeeperValue THEN 5
                    ELSE -1
                END,
    `is_fresh` = TRUE";
    }
    
    static public function swapPiecesQuery(int $lhsId, int $lhsSwapToRow, $lhsSwapToColumn, int $rhsId, int $rhsSwapToRow, int $rhsSwapToColumn) : string
    {
        return "UPDATE `player_pieces` SET 
        `hex_column` = CASE 
                           WHEN `id` = $lhsId THEN $lhsSwapToColumn
                           WHEN `id` = $rhsId THEN $rhsSwapToColumn
                           ELSE `hex_column`
                       END,
        `hex_row` = CASE 
                        WHEN `id` = $lhsId THEN $lhsSwapToRow
                        WHEN `id` = $rhsId THEN $rhsSwapToRow
                        ELSE `hex_row`
                    END";
    }
}
