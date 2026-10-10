<?php

namespace Bga\Games\SuperTeam\Cards\Moves;
use Bga\Games\SuperTeam\HexID;

class MovedPieceInfo
{
    /** @param HexID[] $moves hexes entered in order, initial position not included */
    public function __construct(
        public int $id,
        public HexId $initialPosition,
        public bool $wasFresh,
        public array $moves = []
    )
    {}

    /** @return HexID[] */
    public function getPath(): array
    {
        return [$this->initialPosition, ...$this->moves];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'position' => $this->initialPosition->convertToArray(),
            'wasFresh' => $this->wasFresh,
            'moves' => array_map(fn(HexID $hex) => $hex->convertToArray(), $this->moves)
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            HexId::fromArray($data['position']),
            $data['wasFresh'],
            array_map(fn(array $hex) => HexID::fromArray($hex), $data['moves'] ?? [])
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        return self::fromArray(
            json_decode($json, true, flags: JSON_THROW_ON_ERROR)
        );
    }
}