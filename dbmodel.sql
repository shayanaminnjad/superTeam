
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

ALTER TABLE `player` ADD `resource_morale` int(10) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `player_pieces` (
    `id` INTEGER PRIMARY KEY AUTO_INCREMENT,
    `owner_id` int (10) unsigned NOT NULL,
    `piece_role` SMALLINT unsigned NOT NULL,
    `is_fresh` BOOLEAN DEFAULT TRUE,
    `hex_column` INT DEFAULT -1,
    `hex_row` INT DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ball` (
    `hex_column` INT DEFAULT -1,
    `hex_row` INT DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `st_globals` (
    `morale_assign_transition` TEXT DEFAULT '',
    `dice_roll_transition` TEXT DEFAULT '',
    `selected_hex_column` INT DEFAULT -1,
    `selected_hex_row` INT DEFAULT -1
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `player_temp_data` (
    `player_id` int (10) unsigned NOT NULL,
    `assigned_morale` INT DEFAULT 0,
    `dice_result` TEXT DEFAULT ''
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `logs` (
    `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
    `time_stamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `level` ENUM('INFO', 'WARN', 'ERROR') DEFAULT 'INFO',
    `message` TEXT DEFAULT null
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- Example 2: add a custom field to the standard "player" table
-- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';

