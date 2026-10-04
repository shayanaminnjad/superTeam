<?php

namespace Bga\Games\SuperTeam\PermanentPowers;

use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainId;
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\PlayerRole;

// Game logic asks this class about rule changes. It must never check captain or board ids itself.
final class PlayerPowers
{
    /** @var array<int, PlayerPowers> */
    private static array $cache = [];

    private function __construct(private readonly ?CaptainId $captain) {}

    // Cached per request: pathfinding calls this for every visited hex.
    public static function forPlayer(int $playerId): self
    {
        return self::$cache[$playerId] ??= new self(self::loadCaptain($playerId));
    }

    private static function loadCaptain(int $playerId): ?CaptainId
    {
        $captainId = DBAdapter::getInstance()->getUniqueValueFromDB("SELECT captain_power FROM player WHERE player_id = $playerId");

        return $captainId === null ? null : CaptainId::tryFrom(\intval($captainId));
    }

    // Captain powers only work when the captain piece itself does the action.
    private function isCaptainAction(PlayerPiece $actor, CaptainId $id): bool
    {
        return $this->captain === $id && $actor->getRole() == PlayerRole::Captain->value;
    }

    public function maxMovement(PlayerPiece $mover, bool $hasBall, int $base): int
    {
        if (!$hasBall && $mover->isFresh() && $this->isCaptainAction($mover, CaptainId::MoveThreeWithoutBall))
            return max($base, 3);

        return $base;
    }

    public function canBallCarrierMoveNearOpponents(PlayerPiece $mover): bool
    {
        return $this->isCaptainAction($mover, CaptainId::MoveTwoNearOpponent);
    }

    public function bypassesOpponentOnDribble(PlayerPiece $dribbler): bool
    {
        return $this->isCaptainAction($dribbler, CaptainId::BypassOnDribble);
    }

    public function ignoresPassBlocks(PlayerPiece $passer): bool
    {
        return $this->isCaptainAction($passer, CaptainId::SkipPassBlocks);
    }

    /** @param PlayerPiece[] $involvedPieces */
    public function moraleForSuccessfulTactic(array $involvedPieces): int
    {
        foreach ($involvedPieces as $piece)
            if ($this->isCaptainAction($piece, CaptainId::MoraleOnTactic))
                return 1;

        return 0;
    }

    public function moraleForSuccessfulDribble(PlayerPiece $dribbler): int
    {
        return $this->isCaptainAction($dribbler, CaptainId::MoraleOnDribble) ? 1 : 0;
    }
}
