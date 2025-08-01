<?php

namespace Bga\Games\SuperTeam;

class PlayerPiecesDBManager
{
    static public function getPiecesDataQuery() : string
    {
        return "SELECT `id`, `hex_column`, `hex_row`, `owner_id`, `piece_role`, `is_fresh` FROM `player_pieces`";
    }
}