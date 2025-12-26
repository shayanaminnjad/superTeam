<?php

namespace Bga\Games\SuperTeam;

use ErrorException;

class TeamBoard
{
    public const MAX_ALLOWED_ATTRIBUTES = 6;
    public const MAX_PER_ATTRIBUTE_VALUE = 2;

    private $attributesRaw;

    private $attributes = [];

    public function __construct(DBAdapter $adapter)
    {
        $this->attributesRaw = $adapter->getCollectionFromDB(
            "SELECT 
            `player_id`,
            `team_attr_pass`, 
            `team_attr_shoot`,
            `team_attr_dribble`,
            `team_attr_tackle`,
            `team_attr_gk` FROM `player`"
        );

        foreach ($this->attributesRaw as $row) 
        {
            $playerId = (int)$row['player_id'];

            $this->attributes[$playerId] = [
                TeamAttribute::Pass->value    => (int)$row['team_attr_pass'],
                TeamAttribute::Shoot->value   => (int)$row['team_attr_shoot'],
                TeamAttribute::Dribble->value => (int)$row['team_attr_dribble'],
                TeamAttribute::Tackle->value  => (int)$row['team_attr_tackle'],
                TeamAttribute::GK->value      => (int)$row['team_attr_gk'],
            ];
        }
    }

    public function getAttribute(int $player_id, TeamAttribute $attribute): int
    {
        return $this->attributes[$player_id][$attribute->value];
    }

    public function getTotalAttributesAssigned(int $player_id): int
    {
        $playerAttributes = $this->attributes[$player_id];
        $assignedAttributes = 0;

        foreach ($playerAttributes as $attr) 
        {
            $assignedAttributes += $attr;
        }

        return $assignedAttributes;
    }

    public function resetAttributes(int $player_id)
    {
        $playerAttributes = $this->attributes[$player_id];

        $setQuery = '';
        for ($i = 0; $i < TeamAttribute::Count->value; $i++) 
        {
            if ($i != 0)
                $setQuery .= ',';

            $playerAttributes[$i] = 0;
            $attrName = self::attributeToString(TeamAttribute::from($i));

            $setQuery .= "`$attrName` = 0";
        }

        DBAdapter::getInstance()->DbQuery("UPDATE player SET $setQuery WHERE `player_id` = $player_id");
    }

    public function tryIncreaseAttribute(int $player_id, TeamAttribute $attribute): bool
    {
        if (!isset($this->attributes[$player_id][$attribute->value])) 
            throw new \LogicException("Invalid player or attribute");

        $attrValue = $this->attributes[$player_id][$attribute->value];
        if ($attrValue == self::MAX_PER_ATTRIBUTE_VALUE)
            return false;

        $attrValue += 1;
        
        $attrName = $this->attributeToString($attribute);
        DBAdapter::getInstance()->DbQuery("UPDATE player SET `$attrName` = $attrValue WHERE `player_id` = $player_id");

        $this->attributes[$player_id][$attribute->value] = $attrValue;

        return true;
    }

    public function isValidAttributeId(int $id): bool
    {
        return $id >= 0 && $id < TeamAttribute::Count->value;
    }

    public function getAttributesAsCollection()
    {
        return $this->attributesRaw;
    }

    public static function attributeToString(TeamAttribute $attribute): string
    {
        switch ($attribute) 
        {
            case TeamAttribute::Pass:
                return 'team_attr_pass';
            case TeamAttribute::Tackle:
                return 'team_attr_tackle';
            case TeamAttribute::Shoot:
                return 'team_attr_shoot';
            case TeamAttribute::Dribble:
                return 'team_attr_dribble';
            case TeamAttribute::GK:
                return 'team_attr_gk';
            default:
                throw new ErrorException("invalid attribute passed!");
        }
    }
}

enum TeamAttribute: int
{
    case Pass = 0;
    case Shoot = 1;
    case Dribble = 2;
    case Tackle = 3;
    case GK = 4;
    case Count = 5;
    case None = 6;
}
