<?php

namespace Bga\Games\SuperTeam\Dice;

class DiceLogFormatter
{
    public static function formatRow(string $playerColor, array $rollResult, bool $isAttacker, int $distance, int $blockingPieces): string
    {
        $greenDie = $rollResult["greenDie"];
        $whiteDice = $rollResult["whiteDice"];
        $assignedMorale = $rollResult["assignedMorale"];

        $totalPower = $greenDie + $assignedMorale;
        foreach ($whiteDice as $whiteDie) {
            $totalPower += $whiteDie;
        }

        if (!$isAttacker) {
            if ($distance > 0) {
                $totalPower += (int) ceil($distance / 2);
            }
            if ($blockingPieces > 0) {
                $totalPower += $blockingPieces;
            }
        }

        $html = "<div class=\"diceLogRow\">";
        $html .= "<span class=\"diceLogColorMark\" style=\"background-color:#{$playerColor}\"></span>";
        $html .= "<span class=\"diceLogIcon superteam-dice-face die-green face-{$greenDie}\"></span>";

        foreach ($whiteDice as $whiteDie) {
            $face = $whiteDie + 2;
            $html .= "<span class=\"diceLogIcon superteam-dice-face die-white face-{$face}\"></span>";
        }

        if ($assignedMorale > 0) {
            $html .= "<span class=\"diceLogIcon diceLogMorale\"></span>";
            $html .= "<span class=\"diceLogMoraleValue\">+{$assignedMorale}</span>";
        }

        $html .= "<span class=\"diceLogEquals\">=</span>";
        $html .= "<span class=\"diceLogTotal\">{$totalPower}</span>";
        $html .= "</div>";

        return $html;
    }

    public static function formatRequiredPowerRow(int $requiredPower, int $distance, int $blockingPieces): string
    {
        if ($requiredPower === 0) {
            return "";
        }

        $html = "<div class=\"diceLogRow diceLogTargetRow\">";

        if ($distance > 0) {
            $html .= "<span class=\"diceLogIcon diceLogDistance\"></span>";
        }

        if ($blockingPieces > 0) {
            $html .= "<span class=\"diceLogIcon diceLogBlocking\"></span>";
        }

        $html .= "<span class=\"diceLogEquals\">=</span>";
        $html .= "<span class=\"diceLogTotal\">{$requiredPower}</span>";
        $html .= "</div>";

        return $html;
    }
}
