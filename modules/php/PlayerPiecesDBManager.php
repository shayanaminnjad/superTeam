<?php

namespace Bga\Games\SuperTeam;

class PlayerPiecesDBManager
{
    //TODO: this class doesn't do much, I should encapsulate piece logic into a single class, which resides inside board.
    static public function getPiecesDataQuery() : string
    {
        return "SELECT `id`, `hex_column`, `hex_row`, `owner_id`, `piece_role`, `is_fresh` FROM `player_pieces`";
    }
}