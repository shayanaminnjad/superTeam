<?php

namespace Bga\Games\SuperTeam\Cards\Moves;
use Bga\Games\SuperTeam\HexID;

class MovedPieceInfo
{
    public function __construct(
        public int $id,
        public HexId $initialPosition,
        public bool $wasFresh
    )
    {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'position' => $this->initialPosition->convertToArray(),
            'wasFresh' => $this->wasFresh
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            HexId::fromArray($data['position']),
            $data['wasFresh']
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