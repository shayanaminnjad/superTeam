
-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- SuperTeam implementation : © <Shayan Aminnezhad> <shayanaminnjad.sa@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

CREATE TABLE IF NOT EXISTS `board` (
    `hex_x` SMALLINT unsigned NOT NULL,
    `hex_y` SMALLINT unsigned NOT NULL,
    `player_id` SMALLINT DEFAULT NULL,
    `has_ball` BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (`hex_x`, `hex_y`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- Example 2: add a custom field to the standard "player" table
-- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';

