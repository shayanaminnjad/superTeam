<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use BgaUserException;

class ShootTargetSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_SHOOT_TARGET_SELECTION,
            name: 'shootTargetSelection',
            type: StateType::MULTIPLE_ACTIVE_PLAYER,

            // optional
            description: clienttranslate('your opponent is choosing a target tile'),
            descriptionMyTurn: clienttranslate('${you} must choose a target tile'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $board = $this->game->getBoard();
        $defender = $board->getDefender();
        $goalieCol = $board->getPlayerGoalie($defender)->getPosition()->getColumn();

        return [
            'attacker' => $board->getAttacker(),
            'defender' => $defender,
            'selectableHexes' => [
                new HexID($goalieCol, 4)->convertToArray(),
                new HexID($goalieCol, 5)->convertToArray(),
                new HexID($goalieCol, 6)->convertToArray(),
            ]
        ];
    }

    #[PossibleAction]
    public function actTargetSelected(int $hexColumn, int $hexRow): void
    {
        $selectedHex = new HexID($hexColumn, $hexRow);
        $selectableHexes = $this->getArgs()['selectableHexes'];

        $isInputCorrect = false;
        foreach ($selectableHexes as $hex)
        {
            if (HexID::fromArray($hex)->equals($selectedHex))
            {
                $isInputCorrect = true;
                break;
            }
        }

        if (!$isInputCorrect)
        {
            throw new BgaUserException(\clienttranslate('Invalid hex selected (${column}, ${row})'), 100, ['column' => $hexColumn, 'row' => $hexRow]);
        }

        $this->selectTarget(\intval($this->game->getCurrentPlayerId()), $selectedHex);
    }

    function zombie(int $playerId): void
    {
        $hex = $this->getRandomZombieChoice($this->getArgs()['selectableHexes']);

        $this->selectTarget($playerId, HexID::fromArray($hex));
    }

    private function selectTarget(int $playerId, HexID $selectedHex): void
    {
        DBAdapter::getInstance()->storePlayerTempStr($playerId, 'shootTarget', $selectedHex->toJson());

        $this->gamestate->setPlayerNonMultiactive($playerId, ShootTargetReveal::class);
    }

    public static function getPlayerShootTarget(int $playerId) : HexID
    {
        $jsonObj = DBAdapter::getInstance()->getPlayerTempStr($playerId, 'shootTarget');
        return HexID::fromJson($jsonObj);
    }
}
