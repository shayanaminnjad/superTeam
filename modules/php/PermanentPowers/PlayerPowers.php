<?php

namespace Bga\Games\SuperTeam\PermanentPowers;

use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainId;
use Bga\Games\SuperTeam\PermanentPowers\Teams\TeamId;
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\PlayerRole;

final class PlayerPowers
{
    private static array $cache = [];

    private function __construct(
        private readonly ?CaptainId $captain,
        private readonly ?TeamId $team
    ) {}

    public static function forPlayer(int $playerId): self
    {
        return self::$cache[$playerId] ??= self::load($playerId);
    }

    private static function load(int $playerId): self
    {
        $player = DBAdapter::getInstance()->getObjectFromDB(
            "SELECT captain_power, team_power FROM player WHERE player_id = $playerId");

        return new self(
            CaptainId::tryFrom((int) ($player['captain_power'] ?? 0)),
            TeamId::tryFrom((int) ($player['team_power'] ?? 0)),
        );
    }

    private function isCaptainAction(PlayerPiece $actor, CaptainId $id): bool
    {
        return $this->captain === $id && $actor->getRole() == PlayerRole::Captain->value;
    }

    /** @param HexID[] $movesSoFar */
    public function maxMovement(PlayerPiece $mover, bool $hasBall, array $movesSoFar, int $moraleResource): int
    {
        $pieceMaxDistance = 0;

        if (!$mover->isFresh())
        {
            $moveActionContext = new MoveActionContext();
            if ($moveActionContext->doExhaustedNeedMoraleForMovement())
            {
                $pieceMaxDistance = $moraleResource > 0 ? 1 : 0;
            }
            else
            {
                $pieceMaxDistance = 1;
            }
        }
        else
        {
            $pieceMaxDistance =
                $this->isCaptainAction($mover, CaptainId::MoveThreeWithoutBall) && !$hasBall ? 3 : 2;

            if ($this->team == TeamId::MoveThreeAlongSideRows && !empty($movesSoFar))
            {
                $isOnTopRow = fn(HexID $hex) => $hex->getRow() == 0;
                // odd columns have one row less
                $isOnBottomRow = fn(HexID $hex) => $hex->getRow() == ($hex->getColumn() % 2 == 0 ? 10 : 9);

                if (array_all($movesSoFar, $isOnTopRow) || array_all($movesSoFar, $isOnBottomRow))
                    $pieceMaxDistance = 3;
            }
        }

        return $pieceMaxDistance;
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
    public function extraMoraleOrMoveForSuccessfulTactic(array $involvedPieces): bool
    {
        foreach ($involvedPieces as $piece)
            if ($this->isCaptainAction($piece, CaptainId::MoraleOnTactic))
                return true;

        return false;
    }

    public function moraleForSuccessfulDribble(PlayerPiece $dribbler): int
    {
        return $this->isCaptainAction($dribbler, CaptainId::MoraleOnDribble) ? 1 : 0;
    }

    public function reRollNegativeDiceOncePerTurn() : bool
    {
        return $this->team === TeamId::RerollNegativeDice;
    }
}
