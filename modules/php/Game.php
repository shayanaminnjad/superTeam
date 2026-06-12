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
use Bga\Games\SuperTeam\Cards\CardFactory;
use Bga\Games\SuperTeam\Cards\Basic1;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\TeamBoard;
use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\Utility\Logger;

use Exception;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");
require_once(__DIR__ . '/StateMachineConstants.inc.php');

class Game extends \Table
{
    private ?TeamBoard $teamBoard = null;

    private CardFactory $cardFactory;

    private array $playerHands = [];
    
    public function __construct()
    {
        parent::__construct();
        
        DBAdapter::getInstance()->init(
            fn($sql) => $this->getObjectListFromDB($sql),
            fn($sql) => $this->getObjectFromDB($sql),
            fn($sql) => $this->getUniqueValueFromDB($sql),
            fn($sql) => $this->DbQuery($sql),
            fn($sql) => $this->getCollectionFromDB($sql),
            fn($name, $value) => $this->setGameStateValue($name, $value),
            fn($name) => $this->getGameStateValue($name)
        );

        NotificationAdapter::getInstance()->init(
            fn($name, $message, $args) => $this->notify->all($name, $message, $args)
        );

        Logger::init(DBAdapter::getInstance(), 'logs');

        HexLib::SetHexInfo(BoardInfo::GetHexInfo());
        BoardInfo::init();
        $this->initCardFactory();
        
        $this->teamBoard = new TeamBoard(DBAdapter::getInstance());

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
            "isAttributesAssignmentFinished" => 19,
            "lastPassResult" => 20
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

    //(sha) TODO: probably this must be a part of board? or a piece manager?
    public function getSelectablePieces(?callable $additionalPredicate): array
    {
        $board = $this->getBoard();

        $activePlayerId = \intval($this->getActivePlayerId());
        $activePlayerPieces = $board->getPlayerPiecesByOwner($activePlayerId);

        $playerResources = new PlayerResources($activePlayerId);

        $freshPieceIDs = [];
        $exhaustedSelectablePieceIds = [];

        for ($i = 0; $i < count($activePlayerPieces); $i++) {
            $currentPiece = $activePlayerPieces[$i];
            if ($currentPiece->getRole() != PlayerRole::GoalKeeper->value) {
                if ($currentPiece->isFresh() && (!$additionalPredicate || $additionalPredicate($currentPiece))) {
                    $freshPieceIDs[] = $currentPiece->getId();
                } else if (!$currentPiece->isFresh() && $playerResources->getMorale() > 0 && (!$additionalPredicate || $additionalPredicate($currentPiece))) {
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
        $currentPlayer = \intval($this->getActivePlayerId());
        $forcedTargetsJson = DBAdapter::getInstance()->getPlayerTempStr($currentPlayer, "forced_pass_targets");
        $forcedTargetsArray = json_decode($forcedTargetsJson, true);

        if (!\is_array($forcedTargetsArray)) 
            $forcedTargetsArray = [];

        $forcedPositions = [];

        foreach($forcedTargetsArray as $forcedTarget)
        {
            $forcedPositions[] = HexID::fromJson($forcedTarget)->convertToArray();
        }

        $board = $this->getBoard();

        $allPieces = $board->getPieces();

        $blockedSpaces = [];

        $currentPlayerId = \intval($this->getActivePlayerId());

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
            "blockedSpaces" => $blockedSpaces,
            "forcedPositions" => $forcedPositions
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
        $opponent_player_id = (int) $this->getPlayerAfter($current_player_id);

        // Get information about players.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score`, `resource_morale` `morale`, `goals_scored` `goals`, 
                `team_attr_pass`, `team_attr_shoot` , `team_attr_dribble`, `team_attr_tackle`, `team_attr_gk` FROM `player`"
        );

        $isAttributesPublic = \intval($this->getGameStateValue('isAttributesAssignmentFinished')) === 1;

        if (!$isAttributesPublic)
        {
            foreach ($result["players"] as &$player) 
            {
                if ($player['id'] != $current_player_id) 
                {
                    unset(
                        $player["team_attr_pass"],
                        $player["team_attr_shoot"],
                        $player["team_attr_dribble"],
                        $player["team_attr_tackle"],
                        $player["team_attr_gk"]
                );
                }
            }
            
            unset($player);
        }

        $playerHand = $this->getPlayerHand($current_player_id);
        $otherHand = $this->getPlayerHand($opponent_player_id);

        $result['other_deck_count'] = $otherHand->CountCardsInDeck();
        $result['other_discard'] = $otherHand->getDiscardedCards();
        $result['player_deck_count'] = $playerHand->CountCardsInDeck();
        $result['player_discard'] = $playerHand->getDiscardedCards();
        $result['player_hand'] = $playerHand->getCardsInHand();
        $result['player_pieces'] = $this->getObjectListFromDB("SELECT * FROM `player_pieces`");
        $result['ball'] = $this->getObjectFromDB("SELECT * FROM `ball`");
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
        $playersDeck = $this->deckFactory->createDeck('player_cards');
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

            //TODO: this must change, it should be initialized when we have selected captain board and team board.
            PlayerHand::createPlayerHand($playersDeck, $player_id, 0, 0);
            $this->getPlayerHand($player_id)->drawToFour();
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
        $this->setGameStateInitialValue("isAttributesAssignmentFinished", 0);
        $this->setGameStateInitialValue("lastPassResult", 0);
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

    public function getPlayerHand(int $playerId) : PlayerHand
    {
        for($i = 0; $i < \count($this->playerHands); $i++)
        {
            if ($this->playerHands[$i]->getOwner() === $playerId)
            {
                return $this->playerHands[$i];
            }
        }

        $playerHand = new PlayerHand($playerId, $this);
        $this->playerHands[] = $playerHand;

        return $playerHand;
    }

    public function getTeamBoard() : TeamBoard
    {
        return $this->teamBoard;
    }

    public function getBoard(): Board
    {
        $query = PlayerPiecesDBManager::getPiecesDataQuery();
        $piecesData = $this->getObjectListFromDB($query, false);
        $ballPosition = $this->getObjectFromDB("SELECT hex_column, hex_row FROM `ball`");
        $ballHex = new HexID(intval($ballPosition['hex_column']), intval($ballPosition['hex_row']));

        return new Board($piecesData, $ballHex, DBAdapter::getInstance());
    }

    //TODO: Move this function to playerResources.
    public function AddOneGoal(int $playerId): int
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

    private function initCardFactory()
    {
        $cardFactory = new CardFactory();

        $cardFactory->registerCard(Basic1::staticId(), fn() => new Basic1($this, $this->getBoard()));
        
        $this->cardFactory = $cardFactory;
    }

    public function getCardFactory() : CardFactory
    {
        return $this->cardFactory;
    }

    function debug_setMorale(int $amount) {
        $playerResource  =new PlayerResources(\intval($this->getActivePlayerId()));
        $playerResource->setMorale($amount, true);
    }
}
