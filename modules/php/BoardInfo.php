<?php

namespace Bga\Games\SuperTeam;

class BoardInfo
{
    //TODO: How to make these contants?
    public static HexID $CENTER_POSITION;
    public static array $LEFT_PLAYER_STARTING_POSITIONS;
    public static HEXID $LEFT_PLAYER_GOALIE_POSITION;
    public static array $RIGHT_PLAYER_STARTING_POSITIONS;
    public static HEXID $RIGHT_PLAYER_GOALIE_POSITION;

    public static int $BOARD_COLUMNS;
    public static int $BOARD_EVEN_COLS_ROWS;
    public static int $BOARD_ODD_COLS_ROWS;

    public static array $GOALIE_SPECIFIC_LOCATIONS; 

    public static function init() : void
    {
        self::$CENTER_POSITION = new HexID(8,5);
        self::$LEFT_PLAYER_STARTING_POSITIONS =  [new HexID(4, 3), new HexID(4, 5), new HexID(4, 7), new HexID(6, 2), new HexID(6, 5), new HexID(6, 8)];
        self::$LEFT_PLAYER_GOALIE_POSITION = new HexID(0, 5);
        self::$RIGHT_PLAYER_STARTING_POSITIONS= [new HexID(12, 3), new HexID(12, 5), new HexID(12, 7), new HexID(10, 2), new HexID(10, 5), new HexID(10, 8)];
        self::$RIGHT_PLAYER_GOALIE_POSITION = new HexID(16, 5);
        self::$BOARD_COLUMNS = 17;
        self::$BOARD_EVEN_COLS_ROWS = 11;
        self::$BOARD_ODD_COLS_ROWS = 10;

        self::$GOALIE_SPECIFIC_LOCATIONS = [new HexID(0, 4), new HexID(0, 5), new HexID(0, 6), new HexID(16, 4), new HexID(16, 5), new HexID(16, 6)];
    }
}
