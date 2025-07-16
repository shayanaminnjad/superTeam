<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * SuperTeam implementation : © <Shayan Aminnezhad> <shayanaminnjad.sa@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\SuperTeam;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");


enum PlayerRole: int
{
    case None = 0;
    case GoalKeeper = 1;
    case Captain = 2;
    case Regular = 3;
}

enum PlayerSide : int
{
    case None = 0;
    case Left = 1;
    case Right = 2;
}

class HexID
{
    private int $column;
    private int $row;

    public function __construct(int $column, int $row)
    {
        $this->column = $column;
        $this->row = $row;
    }

    public function getRow() : int
    {
        return $this->row;
    }

    public function getColumn(): int
    {
        return $this->column;
    }
}

class Game extends \Table
{
    private static array $CARD_TYPES;

    //(sha) THESE are board specific logic and should be inside their own class.
    private readonly HexID $BALL_STARTING_POSITION;
    private readonly array $LEFT_PLAYER_STARTING_POSITIONS;
    private readonly HEXID $LEFT_PLAYER_GOALIE_POSITION;
    private readonly array $RIGHT_PLAYER_STARTING_POSITIONS;
    private readonly HEXID $RIGHT_PLAYER_GOALIE_POSITION;

    private readonly int $BOARD_COLUMNS;
    private readonly int $BOARD_EVEN_COLS_ROWS;
    private readonly int $BOARD_ODD_COLS_ROWS;

    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            "game_half" => 10,
            "user_in_left_id" => 11,
            "user_in_right_id" => 12,
        ]);        

        self::$CARD_TYPES = [
            1 => [
                "card_name" => clienttranslate('Troll'), // ...
            ],
            2 => [
                "card_name" => clienttranslate('Goblin'), // ...
            ],
            // ...
        ];

        $this->BALL_STARTING_POSITION = new HexID(8, 5);
        $this->LEFT_PLAYER_STARTING_POSITIONS = [new HexID(4, 3), new HexID(4, 5), new HexID(4, 7), new HexID(6, 2), new HexID(6, 5), new HexID(6, 8)];
        $this->LEFT_PLAYER_GOALIE_POSITION = new HexID(0, 5);
        $this->RIGHT_PLAYER_STARTING_POSITIONS = [new HexID(12, 3), new HexID(12, 5), new HexID(12, 7), new HexID(10, 2), new HexID(10, 5), new HexID(10, 8)];
        $this->RIGHT_PLAYER_GOALIE_POSITION = new HexID(16, 5);

        $this->BOARD_COLUMNS = 17;
        $this->BOARD_EVEN_COLS_ROWS = 11;
        $this->BOARD_ODD_COLS_ROWS = 10;

        /* example of notification decorator.
        // automatically complete notification args when needed
        $this->notify->addDecorator(function(string $message, array $args) {
            if (isset($args['player_id']) && !isset($args['player_name']) && str_contains($message, '${player_name}')) {
                $args['player_name'] = $this->getPlayerNameById($args['player_id']);
            }
        
            if (isset($args['card_id']) && !isset($args['card_name']) && str_contains($message, '${card_name}')) {
                $args['card_name'] = self::$CARD_TYPES[$args['card_id']]['card_name'];
                $args['i18n'][] = ['card_name'];
            }
            
            return $args;
        });*/
    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player plays a card, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws BgaUserException
     */
    public function actPlayCard(int $card_id): void
    {
        /*
        // Retrieve the active player ID.
        //$player_id = (int)$this->getActivePlayerId();

        // check input values
        $args = $this->argPlayerTurn();
        $playableCardsIds = $args['playableCardsIds'];
        if (!in_array($card_id, $playableCardsIds)) {
            throw new \BgaUserException('Invalid card choice');
        }

        // Add your game logic to play a card here.
        $card_name = self::$CARD_TYPES[$card_id]['card_name'];

        // Notify all players about the card played.
        $this->notify->all("cardPlayed", clienttranslate('${player_name} plays ${card_name}'), [
            "player_id" => $player_id,
            "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
            "card_name" => $card_name, // remove this line if you uncomment notification decorator
            "card_id" => $card_id,
            "i18n" => ['card_name'], // remove this line if you uncomment notification decorator
        ]);

        // at the end of the action, move to the next state
        $this->gamestate->nextState("playCard");*/
    }

    public function actPass(): void
    {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // Notify all players about the choice to pass.
        $this->notify->all("pass", clienttranslate('${player_name} passes'), [
            "player_id" => $player_id,
            "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
        ]);

        // at the end of the action, move to the next state
        $this->gamestate->nextState("pass");
    }

    public function argPlayerPieceSetup(): array
    {
        $board = $this->getBoard();
        $playerSide = $this->getPlayerSide(strval($this->getActivePlayerId()));

        $playerStartingPositions = $playerSide === PlayerSide::Left ? $this->LEFT_PLAYER_STARTING_POSITIONS : $this->RIGHT_PLAYER_STARTING_POSITIONS;

        
        $possibleLocations = [];
        for($i = 0; $i < count($playerStartingPositions); $i++)
        {
            $hexData = $board[$playerStartingPositions[$i]->getColumn()][$playerStartingPositions[$i]->getRow()];

            if ($hexData['team_id'] === null && $hexData['piece_id'] === null)
            {
                $possibleLocations[] = [$playerStartingPositions[$i]->getColumn(), $playerStartingPositions[$i]->getRow()];
            }
        }
        
        $peiceToPlaceId = PlayerRole::Captain->value;

        if (count($possibleLocations) < 6)
        {
            $peiceToPlaceId = PlayerRole::Regular->value;
        }

        return [
            "availableStartingPositions" => $possibleLocations,
            "peiceToPlace"=> $peiceToPlaceId,
        ];
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }

    /**
     * Game state action, example content.
     *
     * The action method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    public function stNextPlayer(): void {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // Give some extra time to the active player when he completed an action
        $this->giveExtraTime($player_id);
        
        $this->activeNextPlayer();

        // Go to another gamestate
        // Here, we would detect if the game is over, and in this case use "endGame" transition instead 
        $this->gamestate->nextState("nextPlayer");
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
//       if ($from_version <= 1404301345)
//       {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
//
//       if ($from_version <= 1405061421)
//       {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas(): array
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score` FROM `player`"
        );

        $result["board"] = $this->getObjectListFromDB("SELECT `hex_column`, `hex_row`, `team_id`, `piece_id`, `has_ball` FROM `board` WHERE `team_id` IS NOT NULL OR `has_ball` IS TRUE ");

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName()
    {
        return "superteam";
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        $default_colors = ['FC5414', '00A3E5'];

        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }

        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reloadPlayersBasicInfos();

        // Init game statistics.
        //
        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.
        
        // Dummy content.
        // $this->initStat("table", "table_teststat1", 0);
        // $this->initStat("player", "player_teststat1", 0);
        
        $this->initGameState($players);

        $this->initBoard($players);

        $this->activeNextPlayer();
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $state_name = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                default:
                {
                    $this->gamestate->nextState("zombiePass");
                    break;
                }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }

    private function initGameState(array $players): void
    {
        list ($orangePlayer, $bluePlayer) = array_keys($players);

        $this->setGameStateInitialValue('game_half', 1);
        $this->setGameStateInitialValue('user_in_left_id', $orangePlayer);
        $this->setGameStateInitialValue('user_in_right_id', $bluePlayer);
    }

    private function initBoard(array $players):void
    {
        $this->initPlayersPieces($players);

        $this->initHexes($players);
    }

    private function initPlayersPieces(array $players) : void
    {
        list ($orangePlayer, $bluePlayer) = array_keys($players);

        $this->initAPlayerPiece($orangePlayer);
        $this->initAPlayerPiece($bluePlayer);
    }

    private function initAPlayerPiece(int $playerId) : void
    {
        $sqlQuery = "INSERT INTO player_pieces (owner_id, piece_role, is_fresh) VALUES ";

        $sqlValues[] = "('$playerId','" . PlayerRole::GoalKeeper->value . "', 'TRUE')";
        $sqlValues[] = "('$playerId','" . PlayerRole::Captain->value . "', 'TRUE')";

        for ($i = 3; $i <= 6; $i++) 
    {
            $sqlValues[] = "('$playerId', '" . PlayerRole::Regular->value . "', 'TRUE')";
        }

        $sqlQuery .= implode(',', $sqlValues);

        $this->DbQuery($sqlQuery);
    }

    private function initHexes(array $players) : void
    {
        $sqlQuery = "INSERT INTO board (hex_column, hex_row, piece_id, has_ball) VALUES ";
        $sqlValues = [];

        list ($orangePlayer, $bluePlayer) = array_keys($players);

        $orangePlayerSide = $this->getPlayerSide(strval($orangePlayer));
        
        for ($i = 0; $i < $this->BOARD_COLUMNS; $i++)
        {
            $rows = ($i + 1) % 2 == 0 ? $this->BOARD_ODD_COLS_ROWS : $this->BOARD_EVEN_COLS_ROWS;

            for ($j = 0; $j < $rows; $j++)
            {
                $hasBall = 'FALSE';
                $pieceId = 'NULL';

                if ($i == $this->BALL_STARTING_POSITION->getColumn() && $j == $this->BALL_STARTING_POSITION->getRow())
                {
                    $hasBall = 'TRUE';
                }

                if ($i == $this->LEFT_PLAYER_GOALIE_POSITION->getColumn() && $j == $this->LEFT_PLAYER_GOALIE_POSITION->getRow())
                {
                    $teamId = $orangePlayerSide === PlayerSide::Left ? $orangePlayer : $bluePlayer;

                    $pieceId = $this->getPlayerId($teamId, PlayerRole::GoalKeeper);
                }
                else if ($i == $this->RIGHT_PLAYER_GOALIE_POSITION->getColumn() && $j == $this->RIGHT_PLAYER_GOALIE_POSITION->getRow())
                {
                    $teamId = $orangePlayerSide === PlayerSide::Right ? $orangePlayer : $bluePlayer;

                    $pieceId = $this->getPlayerId($teamId, PlayerRole::GoalKeeper);
                }

                $sqlValues[] = "('$i', '$j', '$pieceId', $hasBall)";
            }
        }

        $sqlQuery .= implode(',', $sqlValues);

        $this->DbQuery($sqlQuery);
    }

    private function getPlayerId(int $ownerId, PlayerRole $pieceRole): int
    {
        $query = "SELECT `id` FROM `player_pieces` WHERE `owner_id` = '$ownerId' AND `piece_role` = '" . $pieceRole->value . "'";

        $result = $this->getUniqueValueFromDB($query);

        if ($result === false) {
            throw new \BgaUserException("Player piece not found for owner ID: $ownerId and role: " . $pieceRole->name);
        }

        return (int)$result;
    }

    private function getPlayerSide(string $userId) : PlayerSide
    {
        $userInLeftID = $this->getGameStateValue('user_in_left_id');

        if ($userInLeftID === $userId) 
        {
            return PlayerSide::Left;
        } 
        else 
        {
            assert($this->getGameStateValue('user_in_right_id') === $userId, 'Player side not found! A player must always have a side!');

            return PlayerSide::Right;
        }
    }

    private function getBoard(): array
    {
        return $this->getDoubleKeyCollectionFromDB("SELECT `hex_column`, `hex_row`, `piece_id`, `has_ball` FROM `board`", false);
    }
}
