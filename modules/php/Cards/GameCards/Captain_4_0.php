<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_4;

class Captain_4_0 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
                \clienttranslate("Captain 1"),
                CardType::Captain,
                \clienttranslate("**Requirement:** Play when adjacent to any opponent. 
**Effect:** Push an opponent and move."))
            , $game);
    }

    public function isExecutionPossible(): bool
    {
        return false;
        $playerId = \intval($this->game->GetActivePlayerId());

        return count(BoardEvaluator::getPlayerPiecesAdjacentToOpponent($this->board, $playerId)) > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_4::staticId(), 0);
    }
    public function getId(): string
    {
        return self::staticId();
    }

    protected function getCommands(): array
    {
        //TODO: implement push first!
        // TODO: Implement getCommands() method.
    }

    protected function cleanup(): void
    {
    }
}