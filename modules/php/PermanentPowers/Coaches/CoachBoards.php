<?php

namespace Bga\Games\SuperTeam\PermanentPowers\Coaches;

class CoachBoards
{
    public static function getTooltip(int $coachBoardId): string
    {
        return match ($coachBoardId) {
            0 => \clienttranslate("Move up to 3 spaces along the two side rows of the pitch."),
            1 => \clienttranslate("Once per turn, you may reroll all dice showing ✕."),
            2 => \clienttranslate("Gain 1 Morale when you play a Fatigue card."),
            3 => \clienttranslate("On boost, you may spend up to 4 Morale."),
            4 => \clienttranslate("On blocks, the opponent needs +1 extra power."),
            5 => \clienttranslate("On draw: draw 5, keep 4, and place 1 on top of the deck."),
            default => '',
        };
    }
}
