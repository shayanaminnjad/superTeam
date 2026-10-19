# Super Team for Board Game Arena

An official [Board Game Arena](https://boardgamearena.com) adaptation of **Super Team**, a two-player football board game by [Roomiz Games](https://roomizgames.com/) ([BoardGameGeek](https://boardgamegeek.com/boardgame/322049)).

## Project layout

| Path | Contents |
| --- | --- |
| `modules/php/Game.php` | Main game class and setup |
| `modules/php/States/` | One class per game state |
| `modules/php/Cards/` | Card base classes, the card driver, and `GameCards/` (every card) |
| `modules/php/PermanentPowers/` | Team, captain and coach powers, read through `PlayerPowers.php` |
| `modules/php/CardRegistrar.php` | Registers every card with the card factory |
| `modules/js/` | Client code: board, pieces, dice, cards, and one file per client state in `States/` |
| `superteam.js`, `superteam.css` | Client entry point and styles |
| `dbmodel.sql` | Database schema |
| `gameinfos.inc.php`, `gameoptions.json`, `stats.json` | BGA metadata, options and statistics |
| `img/` | Packed image atlases |
| `misc/atlas/` | Source images and `pack_atlases.py`, which builds the atlases in `img/` |

## Credits

- Game design and artwork: Roomiz Games. Super Team is their property.
- BGA implementation: Shayan Aminnezhad, developed under license from Roomiz Games.
- Built on the Board Game Arena Studio framework. See `LICENCE_BGA`.
