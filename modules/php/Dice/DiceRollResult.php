<?php

namespace Bga\Games\SuperTeam\Dice;

class DiceRollResult
{
    private array $result;

    public function __construct(int $moraleAssigned = 0, int $greenDie = 0, array $whiteDice = [], int $captainStars = 0)
    {
        $this->result = [
            "assignedMorale" => $moraleAssigned,
            "greenDie" => $greenDie,
            "whiteDice" => $whiteDice,
            "captainStars" => $captainStars,
        ];
    }

    public function fromJson(string $json)
    {
        $data = json_decode($json, true);

        assert(is_array($data), "Invalid json format passed!");

        $this->result = $data;
    }

    public function toJson(): string
    {
        return json_encode($this->result);
    }

    public function getTotal() : int
    {
        $whiteTotal = 0;

        $whiteDice = $this->result["whiteDice"];

        for ($i = 0; $i < \count($whiteDice) ;$i++)
            $whiteTotal += $whiteDice[$i];

        return $this->result["assignedMorale"] + $this->result["greenDie"] + $whiteTotal + ($this->result["captainStars"] ?? 0);
    }

    // dice faces only, without morale and captain stars
    public function getDiceTotal() : int
    {
        return $this->result["greenDie"] + array_sum($this->result["whiteDice"]);
    }

    public function getWhiteDice() : array
    {
        return $this->result["whiteDice"];
    }

    public function setWhiteDice(array $whiteDice) : void
    {
        $this->result["whiteDice"] = $whiteDice;
    }

    public function getData() : array
    {
        return $this->result;
    }
}
