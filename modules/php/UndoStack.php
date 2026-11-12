<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Utility\Logger;

// Saves the whole game before each undoable player action, so an undo never needs to know
// which path led to the current state. Rolls, hidden draws and a new active player clear it.
class UndoStack
{
    public const bool ENABLED = true;

    private const string TABLE = 'undo_checkpoint';
    private const array SNAPSHOT_TABLES = ['player_pieces', 'ball', 'player_cards', 'st_globals', 'player_temp_data'];

    // ids from 100 are table options, they never change during a game
    private const int FIRST_OPTION_ID = 100;

    public static function push(Game $game, string $stateClass): void
    {
        if (!self::ENABLED)
            return;

        $playerId = \intval($game->getActivePlayerId());

        // a failed save must not block the action, the player only loses this undo step
        try
        {
            $db = DBAdapter::getInstance();
            $db->DbQuery("DELETE FROM `" . self::TABLE . "` WHERE `player_id` <> $playerId");

            $state = Game::escapeStringForDB($stateClass);
            $data = Game::escapeStringForDB(json_encode(self::capture($game), JSON_THROW_ON_ERROR));

            $db->DbQuery("INSERT INTO `" . self::TABLE . "` (`player_id`, `state_class`, `data`) VALUES ($playerId, '$state', '$data')");
        }
        catch (\Throwable $e)
        {
            self::logError("checkpoint not saved", $e);
        }
    }

    public static function clear(): void
    {
        if (!self::ENABLED)
            return;

        try
        {
            DBAdapter::getInstance()->DbQuery("DELETE FROM `" . self::TABLE . "`");
        }
        catch (\Throwable $e)
        {
            self::logError("stack not cleared", $e);
        }
    }

    public static function canUndo(Game $game): bool
    {
        if (!self::ENABLED)
            return false;

        try
        {
            $playerId = \intval($game->getActivePlayerId());
            $count = DBAdapter::getInstance()->getUniqueValueFromDB("SELECT COUNT(*) FROM `" . self::TABLE . "` WHERE `player_id` = $playerId");

            return \intval($count) > 0;
        }
        catch (\Throwable $e)
        {
            return false;
        }
    }

    // for states that are not a PlayerBaseState and build their client buttons by hand
    public static function getClientButtons(Game $game): array
    {
        if (!self::canUndo($game))
            return [];

        return [[
            'name' => \clienttranslate("Undo"),
            'action' => 'actUndoStep',
            'paramsToSendBack' => [],
            'buttonParams' => (new ClientButtonParams(ClientButtonColor::ALERT))->toArray(),
        ]];
    }

    // returns the state the player was in when the undone action was taken
    public static function undo(Game $game): string
    {
        $playerId = \intval($game->getActivePlayerId());
        $db = DBAdapter::getInstance();

        $checkpoint = self::ENABLED
            ? $db->getObjectFromDB("SELECT `id`, `state_class`, `data` FROM `" . self::TABLE . "` WHERE `player_id` = $playerId ORDER BY `id` DESC LIMIT 1")
            : null;

        if (empty($checkpoint) || !class_exists($checkpoint['state_class']))
            throw new \BgaUserException(\clienttranslate("There is nothing to undo"));

        self::restore($game, json_decode($checkpoint['data'], true, 512, JSON_THROW_ON_ERROR));

        $db->DbQuery("DELETE FROM `" . self::TABLE . "` WHERE `id` >= " . \intval($checkpoint['id']));

        self::notifyRestored($game, $playerId);

        return $checkpoint['state_class'];
    }

    public static function capture(Game $game): array
    {
        $db = DBAdapter::getInstance();
        $snapshot = ['tables' => [], 'morale' => [], 'labels' => [], 'stats' => []];

        foreach (self::SNAPSHOT_TABLES as $table)
            $snapshot['tables'][$table] = $db->getObjectListFromDB("SELECT * FROM `$table`");

        $snapshot['morale'] = self::getMoraleByPlayer();

        $labelIds = self::getLabelIds();
        $globals = $db->getCollectionFromDB("SELECT `global_id`, `global_value` FROM `global` WHERE `global_id` IN (" . implode(',', $labelIds) . ")");
        foreach ($labelIds as $name => $id)
        {
            if (isset($globals[$id]))
                $snapshot['labels'][$name] = \intval($globals[$id]['global_value']);
        }

        foreach (GameStats::playerStatNames() as $statName)
            $snapshot['stats'][$statName] = $game->playerStats->getAll($statName);

        return $snapshot;
    }

    public static function restore(Game $game, array $snapshot): void
    {
        $db = DBAdapter::getInstance();

        foreach (self::SNAPSHOT_TABLES as $table)
        {
            if (!isset($snapshot['tables'][$table]))
                continue;

            $rows = $snapshot['tables'][$table];

            $db->DbQuery("DELETE FROM `$table`");

            if (!empty($rows))
                $db->DbQuery(self::buildInsert($table, $rows));
        }

        foreach ($snapshot['morale'] as $playerId => $morale)
            $db->DbQuery("UPDATE `player` SET `resource_morale` = " . \intval($morale) . " WHERE `player_id` = " . \intval($playerId));

        $labelIds = self::getLabelIds();
        foreach ($snapshot['labels'] as $name => $value)
        {
            if (isset($labelIds[$name]))
                $db->setGameStateValue($name, \intval($value));
        }

        foreach ($snapshot['stats'] as $statName => $valuesByPlayer)
        {
            $current = $game->playerStats->getAll($statName);

            foreach ($valuesByPlayer as $playerId => $value)
            {
                if (($current[$playerId] ?? null) != $value)
                    $game->playerStats->set($statName, $value, \intval($playerId));
            }
        }
    }

    private static function notifyRestored(Game $game, int $activePlayerId): void
    {
        $hands = $game->getPlayerHands();
        $discards = [];
        $deckCounts = [];

        foreach ($hands as $hand)
        {
            $owner = $hand->getOwner();
            $discards[$owner] = array_values($hand->getDiscardedCards());
            $deckCounts[$owner] = $hand->CountCardsInDeck();

            // sent before the public one, so a card is already in the hand when the discards sync
            $game->notify->player($owner, 'undoRestoredCards', '', [
                'hand' => array_values($hand->getCardsInHand()),
                'deck' => $hand->GetCardsInDeckSecret(),
            ]);
        }

        $db = DBAdapter::getInstance();

        $game->notify->all('undoRestored', \clienttranslate('${player_name} undoes an action'), [
            'player_id' => $activePlayerId,
            'player_name' => $game->getPlayerNameById($activePlayerId),
            'pieces' => $db->getObjectListFromDB("SELECT * FROM `player_pieces`"),
            'ball' => $db->getObjectFromDB("SELECT * FROM `ball`"),
            'morale' => self::getMoraleByPlayer(),
            'discards' => $discards,
            'deckCounts' => $deckCounts,
            'supply' => array_values($hands[0]->getCardsInSupply()),
        ]);
    }

    private static function getMoraleByPlayer(): array
    {
        $rows = DBAdapter::getInstance()->getCollectionFromDB("SELECT `player_id`, `resource_morale` FROM `player`");

        return array_map(fn(array $row) => \intval($row['resource_morale']), $rows);
    }

    private static function getLabelIds(): array
    {
        return array_filter(Game::GAME_STATE_LABELS, fn(int $id) => $id < self::FIRST_OPTION_ID);
    }

    private static function buildInsert(string $table, array $rows): string
    {
        $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';

        $values = array_map(
            fn(array $row) => '(' . implode(', ', array_map(self::toSqlValue(...), $row)) . ')',
            $rows);

        return "INSERT INTO `$table` ($columns) VALUES " . implode(', ', $values);
    }

    private static function toSqlValue(mixed $value): string
    {
        return $value === null ? 'NULL' : "'" . Game::escapeStringForDB((string)$value) . "'";
    }

    private static function logError(string $what, \Throwable $e): void
    {
        try
        {
            Logger::logError(Game::escapeStringForDB("Undo $what: " . $e->getMessage()));
        }
        catch (\Throwable)
        {
        }
    }
}
