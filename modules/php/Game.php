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

use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\Dice\GameDice;
use Bga\Games\SuperTeam\Math\HexInfo;
use Bga\Games\SuperTeam\Utility\Logger;


use BgaUserException;
use Exception;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");
require_once(__DIR__ . '/StateMachineConstants.inc.php');

class PassForwardCommand
{
    private Board $board;
    private $currentPlayer;
    private $opponentPlayer;

    public function __construct(Board $board, $currentPlayer, $oppoenentPlayer)
    {
        $this->board = $board;
        $this->currentPlayer = $currentPlayer;
        $this->opponentPlayer = $oppoenentPlayer;
    }

    public function canBePlayed(): bool
    {
        $ballHex = $this->board->getBallHex();
        $playerPiece = null;

        if ($this->board->tryGetPlayerPieceInHex($ballHex, $playerPiece)) {
            //PlayerPiece is here to supress a warning.
            if ($playerPiece && $playerPiece->getOwner() === $this->currentPlayer) {
                return count($this->board->getPlayerPiecesInForwardColumns($ballHex->getColumn(), $this->currentPlayer)) > 0;
            }
        }

        return false;
    }

    public function getCommandArgs(): array
    {
        //TODO: In general a pass can be onto any space that is not occupied by your opponent! however this card require us to pass in a certain way.

        $ballHex = $this->board->getBallHex();

        $playerPiecesInForwardColumns = $this->board->getPlayerPiecesInForwardColumns($ballHex->getColumn(), $this->currentPlayer);
        $piecesIds = [];
        for ($i = 0; $i < count($playerPiecesInForwardColumns); $i++) {
            $piecesIds[] = $playerPiecesInForwardColumns[$i]->getId();
        }

        return ["possibleTargets" => $piecesIds];
    }

    public function performAct(array $actData): void
    {
        $passTarget = $actData['target'];
        $spentMorale = $actData['spentMorale'];

        //TOOD: This should be inside validation function!
        if (intval($spentMorale) > 3) {
            throw new \BgaUserException("You cannot spend more than 3 morale on an action!");
        }

        //TODO: target can't be current ball location!

        $targetHex = new HexID($passTarget['column'], $passTarget['row']);
        $ballHex = $this->board->getBallHex();
        //TODO: This logic for pass must be encapsulated inside another class, it doesn't relate to here.
        $passDistance = HexLib::getOffsetDistance($ballHex, $targetHex);

        $opponentPieces = $this->board->getPlayerPiecesByOwner($this->opponentPlayer);
        $opponentPiecesInPath = HexLib::getHexesInPath($ballHex, $targetHex, 1, $opponentPieces);

        $powerNeeded = ceil($passDistance * 0.5) + count($opponentPiecesInPath);

        $greenRoll = GameDice::greenDie()->roll();
        $whiteDieRes = [];

        //TODO: get coach markers for pass action.
        $coachMarkerCount = 1;
        $whiteRollSum = 0;
        for ($i = 0; $i < $coachMarkerCount; $i++) {
            $currentWhiteRollRes = GameDice::whiteDie()->roll();
            $whiteRollSum += $currentWhiteRollRes;
            $whiteDieRes[] = $currentWhiteRollRes;
        }

        $totalPlayerPower = $whiteRollSum + $greenRoll + $spentMorale;

        $finalBallLocation = null;

        if ($totalPlayerPower >= $powerNeeded) {
            $finalBallLocation = $targetHex;
        } else {
            if (count($opponentPiecesInPath) > 0) {
                $nearestOpponent = null;
                $nearestOpponentDistance = 1000; //1000 is way beyond the furthest distance we can have.

                //TODO: asked amir about this, if two opponent pieces have the same distance from the ball, then how should we determine which one will get the ball?
                //right now I only find the closest one, and don't solve the above edge case.
                for ($i = 0; $i < count($opponentPiecesInPath); $i++) {
                    $currentOpponentDistance = HexLib::getOffsetDistance($ballHex, $opponentPiecesInPath[$i]);

                    if ($currentOpponentDistance < $nearestOpponentDistance) {
                        $nearestOpponent = $opponentPiecesInPath[$i];
                    }
                }

                $finalBallLocation = $nearestOpponent;
            } else {
                $hexesInLine = HexLib::getHexesInLine($ballHex, $targetHex);
                $hexInMiddle = ceil((count($hexesInLine) - 1) / 2.0);

                $finalBallLocation = $hexesInLine[$hexInMiddle];
            }
        }

        DBAdapter::getInstance()->DbQuery("UPDATE ball SET hex_column= {$finalBallLocation->getColumn()}, hex_row= {$finalBallLocation->getRow()}");

        //TODO: notify all about all these changes.
    }
}

class Game extends \Table
{
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

        HexLib::SetHexInfo(BoardInfo::GetHexInfo());
        BoardInfo::init();

        DBAdapter::getInstance()->init(
            fn($sql) => $this->getObjectListFromDB($sql),
            fn($sql) => $this->getObjectFromDB($sql),
            fn($sql) => $this->getUniqueValueFromDB($sql),
            fn($sql) => $this->DbQuery($sql),
            fn($name, $value) => $this->setGameStateValue($name, $value),
            fn($name) => $this->getGameStateValue($name)
        );

        Logger::init(DBAdapter::getInstance(), 'logs');

        $this->initGameStateLabels([
            "game_half" => 10,
            "user_in_left_id" => 11,
            "user_in_right_id" => 12,
            "selected_piece" => 13,
            "number_of_moves" => 14,
            "required_power" => 15,
            "attacker" => 16,
            "defender" => 17,
            "moraleAssignRemaining" => 18,
        ]);

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

    //TODO: I can have a single entry to player actions, and a dispatcher dispatches each action to it's proper handler.
    public function actPassForward(): void
    {
        $activePlayerID = $this->getActivePlayerId();
        $Command = new PassForwardCommand($this->getBoard(), $activePlayerID, $this->getInactivePlayerId());

        if ($Command->canBePlayed()) {
            $this->notify->player(intval($activePlayerID), "actionArgs", "", $Command->getCommandArgs());
        } else {
            throw new Exception("Pass forward can't be played!");
        }
    }

    //(sha) TODO: probably this must be a part of board? or a piece manager?
    public function getSelectablePieces(?callable $additionalPredicate): array
    {
        $board = $this->getBoard();

        $activePlayerPieces = $board->getPlayerPiecesByOwner(intval($this->getActivePlayerId()));

        $currentPlayerMorale = $this->getCurrentPlayerMorale();

        $freshPieceIDs = [];
        $exhaustedSelectablePieceIds = [];

        for ($i = 0; $i < count($activePlayerPieces); $i++) {
            $currentPiece = $activePlayerPieces[$i];
            if ($currentPiece->getRole() != PlayerRole::GoalKeeper->value) {
                if ($currentPiece->isFresh() && (!$additionalPredicate || $additionalPredicate($currentPiece))) {
                    $freshPieceIDs[] = $currentPiece->getId();
                } else if (!$currentPiece->isFresh() && $currentPlayerMorale > 0 && (!$additionalPredicate || $additionalPredicate($currentPiece))) {
                    $exhaustedSelectablePieceIds[] = $currentPiece->getId();
                }
            }
        }

        return [
            'selectablePieces' => $freshPieceIDs,
            'selectableExhaustedPieces' => $exhaustedSelectablePieceIds
        ];
    }

    public function getValidPassTargets(?callable $additionalCondition): array
    {
        $board = $this->getBoard();

        $allPieces = $board->getPieces();

        $blockedSpaces = [];

        $currentPlayerId = intval($this->getActivePlayerId());

        foreach ($allPieces as $piece) {
            if ($piece->getOwner() !== $currentPlayerId && $piece->getRole() !== PlayerRole::GoalKeeper->value && (!$additionalCondition || $additionalCondition($piece))) {
                $piecePos = $piece->getPosition();
                $blockedSpaces[] = $piecePos->convertToArray();
            }
        }

        $playerGoaliePos = $board->getPlayerGoalie($currentPlayerId)->getPosition();

        foreach (BoardInfo::$GOALIE_SPECIFIC_LOCATIONS as $goalieLocation) {
            if (!$goalieLocation->equals($playerGoaliePos)) {
                $blockedSpaces[] = $goalieLocation->convertToArray();
            }
        }

        $hexWithBall = $board->getBallHex();

        return [
            "hexWithBall" => $hexWithBall->convertToArray(),
            "blockedSpaces" => $blockedSpaces
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
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the to continue to run with your new version.
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
            "SELECT `player_id` `id`, `player_score` `score`, `resource_morale` `morale`, `goals_scored` `goals` FROM `player`"
        );

        $result["player_pieces"] = $this->getObjectListFromDB("SELECT * FROM `player_pieces`");
        $result["ball"] = $this->getObjectFromDB("SELECT * FROM `ball`");
        $result['user_in_right_id'] = $this->getGameStateValue('user_in_right_id');
        $result['user_in_left_id'] = $this->getGameStateValue('user_in_left_id');

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
        $default_colors = ['DD4720', '479ADA'];

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

        list($firstPlayer, $secondPlayer) = array_keys($players);
        static::DbQuery("INSERT INTO player_temp_data (player_id) VALUES ($firstPlayer), ($secondPlayer)");

        $this->reloadPlayersBasicInfos();

        // Init game statistics.
        //
        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

        // Dummy content.
        // $this->initStat("table", "table_teststat1", 0);
        // $this->initStat("player", "player_teststat1", 0);

        $this->initGameState($players);

        DBAdapter::getInstance()->initGlobals();

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
                default: {
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
        list($redPlayer, $bluePlayer) = array_keys($players);

        $this->setGameStateInitialValue('number_of_moves', 0);
        $this->setGameStateInitialValue('selected_piece', -1);
        $this->setGameStateInitialValue('required_power', -1);
        $this->setGameStateInitialValue('game_half', 1);
        $this->setGameStateInitialValue('user_in_left_id', $redPlayer);
        $this->setGameStateInitialValue('attacker', $redPlayer);
        $this->setGameStateInitialValue('user_in_right_id', $bluePlayer);
        $this->setGameStateInitialValue('defender', $bluePlayer);
        $this->setGameStateInitialValue("moraleAssignRemaining", 0);
    }

    private function initBoard(array $players): void
    {
        $centerPos = BoardInfo::$CENTER_POSITION;
        $this->DbQuery("INSERT INTO ball (hex_column, hex_row) VALUES ({$centerPos->getColumn()},{$centerPos->getRow()})");

        list($redPlayer, $bluePlayer) = array_keys($players);

        $this->initAPlayerPiece($redPlayer);
        $this->initAPlayerPiece($bluePlayer);
    }

    private function initAPlayerPiece(int $playerId): void
    {
        $playerSide = $this->getPlayerSide($playerId);
        $goaliePos = $playerSide === PlayerSide::Left ? BoardInfo::$LEFT_PLAYER_GOALIE_POSITION : BoardInfo::$RIGHT_PLAYER_GOALIE_POSITION;

        $sqlQuery = "INSERT INTO player_pieces (owner_id, piece_role, is_fresh, hex_column, hex_row) VALUES ";

        $sqlValues[] = "($playerId," . PlayerRole::GoalKeeper->value . ", 1," . $goaliePos->getColumn() . ", " . $goaliePos->getRow() . ")";
        $sqlValues[] = "($playerId," . PlayerRole::Captain->value . ", 1, -1, -1)";

        for ($i = 3; $i <= 6; $i++) {
            $sqlValues[] = "($playerId, " . PlayerRole::Regular->value . ", 1, -1, -1)";
        }

        $sqlQuery .= implode(',', $sqlValues);

        $this->DbQuery($sqlQuery);
    }

    public function getBoard(): Board
    {
        $query = PlayerPiecesDBManager::getPiecesDataQuery();
        $piecesData = $this->getObjectListFromDB($query, false);
        $ballPosition = $this->getObjectFromDB("SELECT hex_column, hex_row FROM `ball`");
        $ballHex = new HexID(intval($ballPosition['hex_column']), intval($ballPosition['hex_row']));

        return new Board($piecesData, $ballHex, DBAdapter::getInstance());
    }

    //TODO: Move this functions to player class, when implemented. 
    public function getCurrentPlayerMorale(): int
    {
        $current_player_id = $this->getActivePlayerId();

        $playerMorale = $this->getUniqueValueFromDB(
            "SELECT `resource_morale` FROM `player` WHERE `player_id` = {$current_player_id}"
        );

        $result = intval($playerMorale);

        assert($result >= 0, "morale must be always equal or greater than 0!");

        return $result;
    }

    public function setCurrentPlayerMorale(int $amount, bool $notify): void
    {
        assert($amount >= 0, "morale should always be greater or equal to zero!");

        $current_player_id = (int) $this->getActivePlayerId();

        $this->DbQuery(
            "UPDATE `player` SET `resource_morale` = {$amount} WHERE `player_id` = {$current_player_id}"
        );

        if ($notify) {
            $this->notify->all(
                "moraleChanged",
                "morale changed to {$amount}",
                [
                    "amount" => $amount,
                    "playerId" => $current_player_id
                ]
            );
        }
    }

    public function AddOneGoal(int $playerId) : int
    {
        $goalsScored = $this->getUniqueValueFromDB(
            "SELECT `goals_scored` FROM `player` WHERE `player_id` = {$playerId}"
        );

        $goalsScored = intval($goalsScored);
        $goalsScored += 1;

        $this->DbQuery(
            "UPDATE `player` SET `goals_scored` = {$goalsScored} WHERE player_id = {$playerId}"
        );

        return $goalsScored;
    }

    public function getPlayerSide(int $userId): PlayerSide
    {
        $userInLeftID = intval($this->getGameStateValue('user_in_left_id'));

        if ($userInLeftID === $userId) {
            return PlayerSide::Left;
        } else {
            assert(intval($this->getGameStateValue('user_in_right_id')) === $userId, 'Player side not found! A player must always have a side!');

            return PlayerSide::Right;
        }
    }

    public function getInactivePlayerId(): int
    {
        $userInLeftID = intval($this->getGameStateValue('user_in_left_id'));

        if ($userInLeftID == $this->getActivePlayerId()) {
            return intval($this->getGameStateValue('user_in_right_id'));
        } else {
            return $userInLeftID;
        }
    }

    public function isCurrentPlayerFirstPlayer(): bool
    {
        $firstPlayerId = $this->getUniqueValueFromDB("SELECT `player_id` FROM `player` WHERE `player_no` = 1");

        return $firstPlayerId === $this->getActivePlayerId();
    }

    //TODO: This probably must be a private function of player piece setup.
    public function isPlayerSetupComplete(int $playerId): bool
    {
        $isComplete = true;

        $playerPieces = $this->getBoard()->getPlayerPiecesByOwner($playerId);

        for ($i = 0; $i < count($playerPieces); $i++) {
            if (!$playerPieces[$i]->hasValidPosition()) {
                $isComplete = false;
                break;
            }
        }

        return $isComplete;
    }
}
