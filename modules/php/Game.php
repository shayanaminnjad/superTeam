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
use Bga\Games\SuperTeam\Cards\CardFactory;
use Bga\Games\SuperTeam\Cards\PlayerHand;
use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\Utility\Logger;

use Exception;

require_once(__DIR__ . '/StateMachineConstants.inc.php');

class Game extends \Bga\GameFramework\Table
{
    private ?TeamBoard $teamBoard = null;

    private CardFactory $cardFactory;

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
            fn($name, $message, $args) => $this->notify->all($name, $message, $args),
            fn($playerId, $name, $message, $args) => $this->notify->player($playerId, $name, $message, $args),
            fn($playerId) => $this->getPlayerNameById($playerId)
        );

        Logger::init(DBAdapter::getInstance(), 'logs');

        HexLib::SetHexInfo(BoardInfo::GetHexInfo());
        BoardInfo::init();
        $this->initCardFactory();
        
        $this->teamBoard = new TeamBoard(DBAdapter::getInstance());
        $this->initGameStateLabels([
            "starter_player" => 10,
            "user_in_left_id" => 11,
            "user_in_right_id" => 12,
            "number_of_moves" => 13,
            "required_power" => 14,
            "attacker" => 15,
            "defender" => 16,
            "moraleAssignRemaining" => 17,
            "isAttributesAssignmentFinished" => 18,
            "lastPassResult" => 19,
            "cardsPlayedThisTurn" => 20,
            "playerStartedFirstHalf" => 21
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

    //(sha) TODO: probably this must be a part of board? or a piece manager? probably moveLogic!
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
        return TimeTrack::GetProgressToEndGame();
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
            "SELECT `player_id` `id`, `player_score` `score`, `resource_morale` `morale`,
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
        $result['player_deck'] = $playerHand->GetCardsInDeckSecret();
        $result['player_discard'] = $playerHand->getDiscardedCards();
        $result['player_hand'] = $playerHand->getCardsInHand();
        $result['supply'] = $playerHand->getCardsInSupply(); //todo: a number is enough.
        $result['player_pieces'] = $this->getObjectListFromDB("SELECT * FROM `player_pieces`");
        $result['ball'] = $this->getObjectFromDB("SELECT * FROM `ball`");
        $result['user_in_right_id'] = $this->getGameStateValue('user_in_right_id');
        $result['user_in_left_id'] = $this->getGameStateValue('user_in_left_id');
        $result['attacker'] = $this->getGameStateValue('attacker');
        $result['time_index'] = TimeTrack::GetTimeTrackPosition();
        $result['card_definitions'] = $this->getCardDefinitionsForClient();

        return $result;
    }

    private function getCardDefinitionsForClient() : array
    {
        $definitions = [];

        foreach ($this->cardFactory->getAllCardIds() as $cardId)
        {
            $definition = $this->cardFactory->getCard($cardId)->getDefinition();

            $definitions[$cardId] = [
                'name' => $definition->name,
                'toolTip' => $definition->toolTip,
            ];
        }

        return $definitions;
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        $playersDeck = $this->deckFactory->createDeck('player_cards');
        $default_colors = ['DD4720', '479ADA'];

        $teamBoard= 0;
        $captainBoard = 0;
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
            PlayerHand::createPlayerHand($playersDeck, $player_id, $captainBoard, $teamBoard);
            $teamBoard += 1;
            $captainBoard = 2;
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

        $starterPlayer = \intval($this->getGameStateValue('starter_player'));
        $activePlayerId = $this->activeNextPlayer();

        if ($activePlayerId != $starterPlayer)
            $this->activeNextPlayer();

        return ST_PLAYER_PIECE_SETUP;
    }

    private function initGameState(array $players): void
    {
        list($redPlayer, $bluePlayer) = array_keys($players);
        $starterPlayerIndex = bga_rand(0, 1);
        $defenderPlayerIndex = $starterPlayerIndex == 0 ? 1 : 0;

        $this->setGameStateInitialValue('number_of_moves', 0);
        $this->setGameStateInitialValue('required_power', -1);
        $this->setGameStateInitialValue('starter_player', array_keys($players)[$starterPlayerIndex]);
        $this->setGameStateInitialValue("playerStartedFirstHalf", array_keys($players)[$starterPlayerIndex]);
        $this->setGameStateInitialValue('user_in_left_id', $redPlayer);
        $this->setGameStateInitialValue('attacker', array_keys($players)[$starterPlayerIndex]);
        $this->setGameStateInitialValue('user_in_right_id', $bluePlayer);
        $this->setGameStateInitialValue('defender', array_keys($players)[$defenderPlayerIndex]);
        $this->setGameStateInitialValue("moraleAssignRemaining", 0);
        $this->setGameStateInitialValue("isAttributesAssignmentFinished", 0);
        $this->setGameStateInitialValue("lastPassResult", 0);
        $this->setGameStateInitialValue("cardsPlayedThisTurn", 0);
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
        return new PlayerHand($playerId, $this);
    }

    /**
     * @return PlayerHand[]
     */
    public function getPlayerHands(): array
    {
        $hands = [];

        $hands[] = new PlayerHand(\intval($this->getGameStateValue("user_in_left_id")), $this);
        $hands[] = new PlayerHand(\intval($this->getGameStateValue("user_in_right_id")), $this);

        return $hands;
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

    public function getStarterPlayer() : int
    {
        return \intval($this->getGameStateValue('starter_player'));
    }

    public function isCurrentPlayerFirstPlayer(): bool
    {
        return $this->getStarterPlayer() === \intval($this->getActivePlayerId());
    }

    public function swapStarterPlayer() : void
    {
        $currentStarterPlayer = $this->getStarterPlayer();
        $newStarterPlayer = \intval($this->getGameStateValue('user_in_right_id'));;

        if ($currentStarterPlayer == $newStarterPlayer)
            $newStarterPlayer = $this->getGameStateValue('user_in_left_id');

        $this->setGameStateInitialValue('starter_player', $newStarterPlayer);
    }

    public function getPlayerStartedFirstHalf() : int
    {
        return \intval($this->getGameStateValue('playerStartedFirstHalf'));
    }

    private function initCardFactory()
    {
        $this->cardFactory = new CardFactory();
        $cardRegistrar = new CardRegistrar();

        $cardRegistrar->registerCards($this->cardFactory, $this);
    }

    public function getCardFactory() : CardFactory
    {
        return $this->cardFactory;
    }

    //========================================================================================================
    //-------------------------------------- cheats ----------------------------------------------------------
    //========================================================================================================
    public function debug_setMorale(int $amount) {
        $playerResource  = new PlayerResources(\intval($this->getActivePlayerId()), $this->getActivePlayerName());
        $playerResource->setMorale($amount, true);
    }

    //---------------------------------------------------------------------------------------------------------

    public function debug_advanceTimeTracker() {
        TimeTrack::AdvanceTimeTrack(true);
    }

    //---------------------------------------------------------------------------------------------------------

    public function debug_triggerHalfTime() {
        return ST_HALF_TIME;
    }

    //---------------------------------------------------------------------------------------------------------

    public function debug_defaultPieceSetup() {
        $board = $this->getBoard();

        $activePlayerId = \intval($this->getActivePlayerId());
        if(!$board->doAllPiecesHaveValidPosition($activePlayerId))
        {
            $pieceSetupLogic = new PlayerPieceSetupLogic($board);

            $pieceSetupLogic->defaultSetup($this, $activePlayerId);

            return ST_SETUP_COMPLETE;
        }
        else
        {
            throw new \BgaUserException("This cheat method can only be called in piece setup state!");
        }
    }

    //---------------------------------------------------------------------------------------------------------

    public function debug_defaultAttributeSetup() {
        $currentPlayerId = \intval($this->getCurrentPlayerId());
        $teamBoard = $this->getTeamBoard();
        $attributesAssigned = $teamBoard->getTotalAttributesAssigned($currentPlayerId);

        if ($attributesAssigned < TeamBoard::MAX_ALLOWED_ATTRIBUTES)
        {
            for($i = $attributesAssigned; $i < TeamBoard::MAX_ALLOWED_ATTRIBUTES; $i++)
            {
                $teamBoard->tryIncreaseAttribute($currentPlayerId, TeamAttribute::from($i % TeamAttribute::Count->value), true);
            }

            $this->gamestate->setPlayerNonMultiactive($currentPlayerId, ST_TEAM_ATTRIBUTE_REVEAL);
        }
        else
        {
            throw new \BgaUserException("This cheat method can only be called in default attribute setup state!");
        }
    }

    //---------------------------------------------------------------------------------------------------------

    public function debug_pickCard(string $cardType) : void
    {
        $currentPlayerId = \intval($this->getCurrentPlayerId());
        $playerHand = $this->getPlayerHand($currentPlayerId);

        $playerHand->drawCard($cardType);

        //TODO: this notification must change, I need to also send whether the played card can be played for tactics or not.
        //that happens in the state on enter, so I need a refresh in web browser right now.

        $playerHand->notifyPlayerOfHandRefill();
    }

    //---------------------------------------------------------------------------------------------------------
    public function debug_addOneGoal() : void
    {
        $playerResources = new PlayerResources(\intval($this->getActivePlayerId()));

        $playerResources->addOneGoal($this, true);
    }

    //---------------------------------------------------------------------------------------------------------
    public function debug_endGame() : int
    {
        return ST_END_GAME;
    }

    //---------------------------------------------------------------------------------------------------------
    public function debug_movePieceTo(int $pieceId, int $row, int $column) : void
    {
        $selectedHex = new HexId($column, $row);

        $offsetExtent = HexLib::getOffsetExtent();
        if (!$offsetExtent->isInsideExtent($selectedHex))
            throw new \BgaUserException("selected hex is not inside the board!");

        $board = $this->getBoard();
        $selectedPiece = $board->getPiece($pieceId);
        if ($selectedPiece === null)
            throw new \BgaUserException("selected piece id does not exist!");

        $board->movePieceToHex($pieceId, $selectedHex);

        $this->notify->all("movedPiece", \clienttranslate('${player_name} moves a piece'), array(
            "player_name" => $this->getActivePlayerName(),
            "movedPiece" => $pieceId,
            "hex_column" => $column,
            "hex_row" => $row,
            "ballMoved" => false,
            "spentMorale"=> 0
        ));
    }

    //---------------------------------------------------------------------------------------------------------
    public function debug_moveBallTo(int $row, int $column) : void
    {
        $selectedHex = new HexId($column, $row);

        $offsetExtent = HexLib::getOffsetExtent();
        if (!$offsetExtent->isInsideExtent($selectedHex))
            throw new \BgaUserException("selected hex is not inside the board!");

        $board = $this->getBoard();
        $board->setBallLocation($selectedHex);

        $this->notify->all('successfulPass', "successful pass", [
            "ballHex" => $selectedHex->convertToArray()
        ]);
    }

    //---------------------------------------------------------------------------------------------------------

    //========================================================================================================
}
