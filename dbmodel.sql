
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

ALTER TABLE `player` ADD `resource_morale` INT NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `team_attr_pass` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `team_attr_shoot` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `team_attr_dribble` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `team_attr_tackle` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `team_attr_gk` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `captain_power` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `player` ADD `team_power` TINYINT NOT NULL DEFAULT 0;


CREATE TABLE IF NOT EXISTS `player_pieces` (
    `id` INTEGER PRIMARY KEY AUTO_INCREMENT,
    `owner_id` INT unsigned NOT NULL,
    `piece_role` SMALLINT unsigned NOT NULL,
    `is_fresh` BOOLEAN DEFAULT TRUE,
    `hex_column` INT DEFAULT -1,
    `hex_row` INT DEFAULT -1,
    `can_tackle` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `ball` (
    `hex_column` INT DEFAULT -1,
    `hex_row` INT DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `player_cards` (
  `card_id` int unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(32) NOT NULL,
  `card_type_arg` int NOT NULL,
  `card_location` varchar(32) NOT NULL,
  `card_location_arg` int NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


CREATE TABLE IF NOT EXISTS `st_globals` (
    `morale_assign_transition` TEXT,
    `selected_hex` TEXT,
    `time_track_position` INT DEFAULT 0,
    `tactic_in_play` TEXT,
    `tactic_type` SMALLINT DEFAULT -1,
    `tactic_progress` INT DEFAULT - 1,
    `tactic_commited` BOOLEAN DEFAULT FALSE,
    `selectable_pieces` TEXT,
    `rewards_used` TEXT,
    `moves_performed` TEXT,
    `current_move_type` TEXT,
    `no_morale_for_exhausted` BOOLEAN DEFAULT FALSE,
    `pieces_moved` TEXT,
    `pieces_passed` TEXT,
    `move_selected_piece_data` TEXT,
    `ball_before_tactic_location` TEXT,
    `morale_before_tactic` INT DEFAULT -1,
    `is_restart_after_goal` INT DEFAULT 0,
    `is_skip_enabled` INT DEFAULT 0,
    `morale_player_to_piece_map` TEXT,
    `forfeitable_captain_action` BOOLEAN DEFAULT FALSE,
    `was_last_action_undone` BOOLEAN DEFAULT FALSE,
    `dice_rerolled_this_turn` BOOLEAN DEFAULT FALSE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `player_temp_data` (
    `player_id` INT unsigned NOT NULL,
    `assigned_morale` INT DEFAULT 0,
    `dice_result` TEXT,
    `dice_roll_input` TEXT,
    `shootTarget` TEXT,
    `forced_pass_targets` TEXT,
    `action_return_state` TEXT,
    `last_pass_receiver_this_turn_id` INT DEFAULT -1,
    `last_pass_pos_this_turn` TEXT
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `logs` (
    `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
    `time_stamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `level` ENUM('INFO', 'WARN', 'ERROR') DEFAULT 'INFO',
    `message` TEXT DEFAULT null
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
