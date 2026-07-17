<?php

namespace Bga\Games\SuperTeam\Cards\Moves;

class CardDefensiveMoves
{
    private array $moves;

    public function __construct(
        int $free,
        int $forward,
        int $backward,
        int $forwardNearOpponent,
        int $backwardNearOpponent)
    {
        $this->moves = [
                MoveTypes::FREE->value => $free,
                MoveTypes::FORWARD->value => $forward,
                MoveTypes::BACKWARD->value => $backward,
                MoveTypes::FORWARD_NEAR_OPPONENT->value => $forwardNearOpponent,
                MoveTypes::BACKWARD_NEAR_OPPONENT->value => $backwardNearOpponent
            ];
    }

    public function toJson() : string
    {
        return json_encode($this->toArray());
    }

    public function toArray() : array
    {
        return $this->moves;
    }

    public function getMoves(MoveTypes $id) : int
    {
        return $this->moves[$id->value] ?? 0;
    }

    public function increaseMoveByOne(MoveTypes $id) : void
    {
        $this->moves[$id->value] = $this->moves[$id->value] + 1;
    }

    public function getTotalMoves() : int
    {
        $total = 0;

        foreach ($this->moves as $value)
        {
            $total += $value;
        }

        return $total;
    }

    public function isEmpty() : bool
    {
        return array_all($this->moves, fn($value) => $value <= 0);
    }

    public function diff(CardDefensiveMoves $rhs) : CardDefensiveMoves
    {
        return new CardDefensiveMoves(
            $this->getMoves(MoveTypes::FREE) -
            $rhs->getMoves(MoveTypes::FREE),

            $this->getMoves(MoveTypes::FORWARD) -
            $rhs->getMoves(MoveTypes::FORWARD),

            $this->getMoves(MoveTypes::BACKWARD) -
            $rhs->getMoves(MoveTypes::BACKWARD),

            $this->getMoves(MoveTypes::FORWARD_NEAR_OPPONENT) -
            $rhs->getMoves(MoveTypes::FORWARD_NEAR_OPPONENT),

            $this->getMoves(MoveTypes::BACKWARD_NEAR_OPPONENT) -
            $rhs->getMoves(MoveTypes::BACKWARD_NEAR_OPPONENT)
        );
    }

    public static function fromJson(string $jsonInput) : CardDefensiveMoves
    {
        if (empty($jsonInput))
            return self::empty();

        return CardDefensiveMoves::fromArray(json_decode($jsonInput, true));
    }

    public static function fromArray(array $input) : CardDefensiveMoves
    {
        return new CardDefensiveMoves($input['free'], $input['forward'], $input['backward'], $input['forwardNearOpponent'], $input['backwardNearOpponent']);
    }

    public static function empty() : CardDefensiveMoves
    {
        return new CardDefensiveMoves(0,0,0,0, 0);
    }
}