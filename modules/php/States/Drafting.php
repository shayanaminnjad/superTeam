<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\PlayerHandCreator;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainId;
use Bga\Games\SuperTeam\PermanentPowers\Teams\TeamId;
use BgaUserException;

class Drafting extends PlayerBaseState
{
    private const OFFERS_PER_PLAYER = 2;
    private const CARDS_PER_BOARD = 3;
    private const CAPTAIN_OFFER = 'draft_captain_offer';
    private const COACH_OFFER = 'draft_coach_offer';

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_DRAFTING,
            name: "drafting",
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            description: clienttranslate('Players must choose a captain power and a coach board'),
            descriptionMyTurn: clienttranslate('${you} must choose a captain power and a coach board'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function onEnteringState()
    {
        $this->generateOffers($this->getPlayerIds());

        $this->gamestate->setAllPlayersMultiactive();
    }

    public function selfGetArgs(): array
    {
        $private = [];
        foreach ($this->getPlayerIds() as $playerId)
        {
            $private[$playerId] = [
                'captains' => $this->getOffer($playerId, self::CAPTAIN_OFFER),
                'coaches' => $this->getOffer($playerId, self::COACH_OFFER),
            ];
        }

        // offers are hidden from the opponent
        return ['_private' => $private];
    }

    #[PossibleAction]
    public function actConfirmDraft(int $captainId, int $coachId): void
    {
        $playerId = \intval($this->game->getCurrentPlayerId());

        if (!in_array($captainId, $this->getOffer($playerId, self::CAPTAIN_OFFER), true))
            throw new BgaUserException(\clienttranslate('This captain power was not offered to you!'));

        if (!in_array($coachId, $this->getOffer($playerId, self::COACH_OFFER), true))
            throw new BgaUserException(\clienttranslate('This coach board was not offered to you!'));

        $this->applyChoice($playerId, $captainId, $coachId);
    }

    function zombie(int $playerId): void
    {
        $captainId = $this->getRandomZombieChoice($this->getOffer($playerId, self::CAPTAIN_OFFER));
        $coachId = $this->getRandomZombieChoice($this->getOffer($playerId, self::COACH_OFFER));

        $this->applyChoice($playerId, $captainId, $coachId);
    }

    private function applyChoice(int $playerId, int $captainId, int $coachId): void
    {
        $this->game->DbQuery("UPDATE `player` SET `captain_power` = $captainId, `team_power` = $coachId WHERE `player_id` = $playerId");

        $unchosenCount = \intval($this->game->getUniqueValueFromDB(
            "SELECT COUNT(*) FROM `player` WHERE `captain_power` < 0 OR `team_power` < 0"));

        if ($unchosenCount == 0)
            $this->finishDraft();

        $this->gamestate->setPlayerNonMultiactive($playerId, PlayerPieceSetup::class);
    }

    private function finishDraft(): void
    {
        $choices = $this->game->getCollectionFromDb("SELECT `player_id`, `captain_power`, `team_power` FROM `player`");

        $deck = $this->game->deckFactory->createDeck('player_cards');
        foreach ($choices as $playerId => $choice)
        {
            PlayerHandCreator::createHand($deck, \intval($playerId), \intval($choice['captain_power']), \intval($choice['team_power']));
        }

        $this->notify->all("draftRevealed", clienttranslate('Captain powers and coach boards were chosen'), [
            'players' => $choices,
        ]);

        foreach (array_keys($choices) as $playerId)
        {
            $playerHand = $this->game->getPlayerHand(\intval($playerId));

            $this->notify->player(\intval($playerId), "deckCreated", "", [
                'deck' => $playerHand->GetCardsInDeckSecret(),
                'supply' => $playerHand->getCardsInSupply(),
            ]);
        }
    }

    private function generateOffers(array $playerIds): void
    {
        $captains = $this->shuffled($this->getDraftableIds(CaptainId::cases(), fn(int $id) => $this->getCaptainCardTypes($id)));
        $coaches = $this->shuffled($this->getDraftableIds(TeamId::cases(), fn(int $id) => $this->getCoachCardTypes($id)));

        foreach ($playerIds as $index => $playerId)
        {
            $offset = $index * self::OFFERS_PER_PLAYER;

            $this->storeOffer($playerId, self::CAPTAIN_OFFER, array_slice($captains, $offset, self::OFFERS_PER_PLAYER));
            $this->storeOffer($playerId, self::COACH_OFFER, array_slice($coaches, $offset, self::OFFERS_PER_PLAYER));
        }
    }

    /** Only boards whose whole card set is implemented can be offered. */
    private function getDraftableIds(array $enumCases, callable $cardTypesOf): array
    {
        $cardFactory = $this->game->getCardFactory();

        $ids = [];
        foreach ($enumCases as $case)
        {
            $cardTypes = $cardTypesOf($case->value);

            if (array_all($cardTypes, fn(string $type) => $cardFactory->hasCard($type)))
                $ids[] = $case->value;
        }

        return $ids;
    }

    private function getCaptainCardTypes(int $captainId): array
    {
        $types = [];
        for ($i = 0; $i < self::CARDS_PER_BOARD; $i++)
            $types[] = CardTypeHelper::getCaptainCardType($captainId, $i);

        return $types;
    }

    private function getCoachCardTypes(int $coachId): array
    {
        $types = [CardTypeHelper::getCoachCardType($coachId)];
        for ($i = 0; $i < self::CARDS_PER_BOARD; $i++)
            $types[] = CardTypeHelper::getTeamCardType($coachId, $i);

        return $types;
    }

    private function shuffled(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--)
        {
            $j = bga_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }

    private function getPlayerIds(): array
    {
        return array_map('intval', array_keys($this->game->loadPlayersBasicInfos()));
    }

    private function storeOffer(int $playerId, string $column, array $ids): void
    {
        DBAdapter::getInstance()->storePlayerTempStr($playerId, $column, json_encode(array_values($ids)));
    }

    private function getOffer(int $playerId, string $column): array
    {
        $json = DBAdapter::getInstance()->getPlayerTempStr($playerId, $column);

        return $json === '' ? [] : json_decode($json, true);
    }
}
