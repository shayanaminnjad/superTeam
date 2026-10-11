<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Dice\DiceRollResult;

class GameStats
{
    public static function initAll(Game $game): void
    {
        $names = ['dice_rolls', 'average_dice', 'cards_as_basic', 'cards_as_tactic', 'fatigues_played', 'tactics_successful', 'tactics_failed'];

        foreach (StatAction::cases() as $action)
        {
            $prefix = $action->value;
            array_push($names, "{$prefix}_attempts", "{$prefix}_successful", "{$prefix}_success_rate", "{$prefix}_failure_rate");
        }

        array_push($names, ...array_unique(array_map(self::getGoalStat(...), TimeTrack::MINUTE_BY_POSITION)));

        $game->playerStats->init($names, 0);
    }

    public static function recordAction(Game $game, int $playerId, StatAction $action, bool $success): void
    {
        $stats = $game->playerStats;
        $prefix = $action->value;

        $stats->inc("{$prefix}_attempts", 1, $playerId);
        if ($success)
            $stats->inc("{$prefix}_successful", 1, $playerId);

        $successRate = round(100 * $stats->get("{$prefix}_successful", $playerId) / $stats->get("{$prefix}_attempts", $playerId), 1);
        $stats->set("{$prefix}_success_rate", $successRate, $playerId);
        $stats->set("{$prefix}_failure_rate", 100 - $successRate, $playerId);
    }

    public static function recordDiceRoll(Game $game, int $playerId, DiceRollResult $roll): void
    {
        $stats = $game->playerStats;

        $stats->inc('dice_rolls', 1, $playerId);
        $rolls = $stats->get('dice_rolls', $playerId);
        $average = $stats->get('average_dice', $playerId);

        // running mean, so no extra stat for the sum is needed
        $stats->set('average_dice', $average + ($roll->getDiceTotal() - $average) / $rolls, $playerId);
    }

    public static function recordGoal(Game $game, int $playerId): void
    {
        $game->playerStats->inc(self::getGoalStat(TimeTrack::GetCurrentMinute()), 1, $playerId);
    }

    // a goal belongs to the 15 minutes that start at the cube's minute; at 90' only the final turn is left
    private static function getGoalStat(int $minute): string
    {
        return $minute >= 90 ? 'goals_90' : "goals_{$minute}_" . ($minute + 15);
    }

    public static function recordCardPlayed(Game $game, int $playerId, string $cardType, bool $asBasic): void
    {
        if ($cardType === CardTypeHelper::getFatigueCardType())
            $stat = 'fatigues_played';
        else
            $stat = $asBasic ? 'cards_as_basic' : 'cards_as_tactic';

        $game->playerStats->inc($stat, 1, $playerId);

        // remembered so an undo can take back the right stat
        DBAdapter::getInstance()->storeGlobalString('last_card_play_stat', $stat);
    }

    public static function undoCardPlayed(Game $game, int $playerId): void
    {
        $stat = DBAdapter::getInstance()->getGlobalString('last_card_play_stat');
        if ($stat === '')
            return;

        $game->playerStats->inc($stat, -1, $playerId);
        DBAdapter::getInstance()->storeGlobalString('last_card_play_stat', '');
    }

    public static function recordTacticResult(Game $game, int $playerId, bool $success): void
    {
        $game->playerStats->inc($success ? 'tactics_successful' : 'tactics_failed', 1, $playerId);
    }
}
