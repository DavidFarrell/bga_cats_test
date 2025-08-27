# Board Game Arena Game Development Guide

## Introduction

Board Game Arena (BGA) Studio is an online platform for developing digital adaptations of board games. It provides a framework and toolset to handle common game functionalities (state management, player interactions, UI components, etc.) so developers can focus on implementing game rules. To develop on BGA, you should be familiar with PHP (server logic), SQL (for database), and JavaScript/HTML/CSS (client interface). You'll also need to join the BGA developer program and set up your development environment (via the BGA Studio control panel and an SFTP connection). Once your environment is ready, BGA provides a game skeleton - a set of files that structure your project. This guide is a comprehensive handbook covering all those files, the BGA APIs, and usage of BGA's specific components, complete with examples. By following this guide, you should have everything needed to implement a board game on BGA from start to finish.

## Project Structure and Key Files

A BGA game project consists of several files and directories, each serving a specific purpose:

*   **Database Model - `dbmodel.sql`**: Defines your game's database tables for persistent data (e.g. cards, board positions). Changes here require restarting development games (and migration if the game is live).
*   **Game Metadata - `gameinfos.inc.php`**: Contains static meta-information about your game (name, player count, genre, etc.) and settings like turn order or game speed. It also lists default player colors and other constants. If you modify this, a special reload in the control panel is needed.
*   **Game Options – `gameoptions.json` / `gamepreferences.json`**: Define optional game variants or user preferences (previously this was in `gameoptions.inc.php`). Options include things like alternative rules or interface toggles. Each option is identified by an ID with attributes like name, possible values, default, etc. These are presented to players when starting a table.
*   **Client Assets `img/` directory**: Contains images and media for your game's UI (cards, board, tokens, etc.). Typically, you'll use CSS sprites (single image containing multiple assets) for efficiency. After updating graphics, players may need to clear cache or reload (CTRL+F5) to see changes.
*   **Static Layout - `<gamename>.view.php` & `<gamename>_<gamename>.tpl`**: Defines the basic HTML structure of the game interface. The `.view.php` may insert dynamic elements or prepare variables, while the `.tpl` file (a Smarty template) contains the HTML with placeholders. This is where you create containers (`divs`, `spans`) that the JS code will manipulate (for example, a `<div id="myhand">` for a player's hand of cards). The layout should ensure the game board fits in common screen sizes (e.g. width ~750px for 1024px screens).
*   **Stylesheet - `<gamename>.css`**: Contains CSS rules to style your game's interface. You'll define classes and IDs to position elements (board, cards, tokens) and to theme the UI consistent with the game. For example, you might set sizes for your board div, or background positions for sprite images representing cards.

*   **Main Game Logic - `modules/php/Game.php`**: The core server-side code for your game rules (recent BGA versions use `Game.php` under a `modules/php` folder; older projects had `yourgame.game.php` in root). This PHP class (extending BGA's `Table` class) manages the game state, processes player actions, and communicates updates to players. This is one of the **most important files**, where you implement game setup, turn progression, win conditions, etc. (Detailed in the next section).
*   **Game State Machine - `states.inc.php`**: Defines the finite state machine for your game. BGA games are modeled as a state machine with states like "game setup", "player's turn", "end of game", etc., each with defined transitions. You configure this as a PHP array `$machinestates` mapping state IDs to properties (state name, type, action to call, possible player actions, transitions, etc.). This allows BGA to enforce turn structure and automate parts of game flow.
*   **Player Actions - `<gamename>.action.php`**: A server-side file acting as a bridge between client-side AJAX calls and your game logic. It defines an AJAX entry-point for each player action (e.g., "playCard", "endTurn") which calls your `Game.php` methods. In other words, when the UI triggers a JS `ajaxcall` to, say, `playCard`, the request is handled by a method in this file, which typically sanitizes input and then invokes `$this->game->playCard(...)` in your main game class. (See Handling Player Actions section for an example).
*   **Client Game Logic - `<gamename>.js`**: The main JavaScript file for the client-side interface. This is where you handle displaying game state to the user and capturing user interactions. The class (usually defined with Dojo `dojo.declare`) extends the framework's `ebg.core.gamegui` and includes methods like `setup(gamedata)` to initialize the UI, event handlers for player inputs (clicks, etc.), and notification handlers to update the UI when the server sends game state changes. This file often uses BGA's provided UI components (stock, counter, etc.) to manage visuals.
*   **Static Data - `material.inc.php`**: A PHP file for defining static game material or constants. For instance, if your game has predefined card definitions, tile properties, or initial setup configurations, you can put those in an array here and use it in your game logic. This keeps static configurations separate from logic code.
*   **Statistics - `stats.json` (formerly `stats.inc.php`)**: Configuration of game statistics tracked by BGA (both for end-of-game summary and for internal use). Here you list stats (by ID) that your game will record, such as scores, number of turns, specific actions counts, etc. At runtime, you update these stats via PHP methods (`initStat`, `setStat`, `incStat` in `Game.php`), and BGA will persist them. Stats can be per-player or global. If this file is changed after release, migrations are needed similar to the DB model.

With this structure in mind, let's dive deeper into the key parts of the implementation: the server-side game logic, the client-side interface, and the special BGA components that simplify common tasks.

## Server-Side Game Logic (PHP)

The server-side logic, primarily in `Game.php`, is responsible for enforcing rules and updating the game state on the server. It uses BGA's framework (the `Table` base class and the Game State Machine) to manage turn flow and interactions. Below are the major elements of server-side development:

### Main Game Class (`Game.php`)

This class (named after your game, e.g. `MyGame` extending `Table`) contains all core game logic. BGA calls its methods in response to player actions or game progression events. Key parts of this class include:

*   **Constructor (`_construct`)**: Initialize game variables and components. You typically don't override the constructor much beyond calling `parent::_construct`; instead, you use it to set up global variables or initialize custom modules. For example, you might define some global game option mappings here by calling `$this->initGameStateLabels($labelsMap)` (which defines custom "global" values tracked in the DB). If using the Deck module for cards, you would instantiate it here (e.g. `$this->cards = self::getNew("module.common.deck"); $this->cards->init("card");`).
*   **`setupNewGame`**: Called when a new game is started, to initialize all game elements and the database records. This method receives an array of players and is where you set up things like creating cards, placing initial pieces, and setting the first game state. Typically you will:
    *   Insert player records into your tables (if you have a custom player table) or assign initial scores.
    *   Initialize components like the Deck by creating all cards. For example, using the Deck component you can generate a full card deck in one go:
    
    ```php
    // Example: Initialize a standard 52-card deck in setupNewGame
    $cards = [];
    foreach ($this->colors as $color_id => $color_name) {
        for ($value = 2; $value <= 14; $value++) {
            $cards[] = [ 'type' => $color_id, 'type_arg' => $value, 'nbr' => 1];
        }
    }
    $this->cards->createCards($cards, 'deck'); // create all cards in 'deck' location
    $this->cards->shuffle('deck'); // shuffle the deck after creation
    ```
    
    In this snippet (from the Hearts game), all cards are created and placed in the "deck" location, then shuffled.
    *   Set up any initial tokens or pieces on the board. For example, in Reversi, the `setupNewGame` inserts 64 rows in a board table and places 4 starting tokens.
    *   Initialize game state globals or counters if needed by calling `setGameStateInitialValue($label, $value)` for any custom global variables (though using `initGameStateLabels` in the constructor as mentioned is preferred).
    *   Finally, call `$this->activeNextPlayer()` or similar to set the first player who will play, and transition to the first state of the game (often done by returning the appropriate transition from the initial state in your state machine, or simply ensuring your state machine's initial state's "transitions" leads to the first real game state).
*   **`getAllDatas`**: Provides the current game state to the client interface upon request (for a full refresh or when a player joins). It should return an associative array of all data needed to reconstruct the game on the client side. BGA automatically calls this when a player refreshes the page or a new player loads the game. Typically, you include:
    *   A 'players' entry with basic info from the `player` table (BGA supplies this snippet by default: `$result['players'] = $this->getCollectionFromDb("SELECT player_id id, player_score score ... FROM player");` to provide each player's score, etc.).
    *   The positions of all game components: e.g., contents of the board, cards in players' hands, current scores, etc. Use your DB queries or component methods to gather these. For instance, continuing the Reversi example, `getAllDatas` would include the current board state by querying the `board` table for all squares that have a token. Or for a card game, you might do: `$result['hand'] = $this->cards->getCardsInLocation('hand', $current_player_id);` to give the current player info on their hand cards.
    *   Essentially, `getAllDatas` should supply everything the client-side `setup` function needs to render the game at its current state.
*   **`getGameProgression`**: Returns a percentage (0-100) indicating how far along the game is. BGA uses this for things like ELO point calculation if someone quits early. You can base it on turns taken vs max turns, or points vs victory condition, etc. If unsure, a simple heuristic or leaving it at 0/100 at start/end is acceptable.
*   **Game-specific Utility Methods**: Any helper functions you need for your game logic, e.g. to check win conditions, calculate scores, validate moves, etc. You'll write these as needed.
*   **State Machine Hooks**: For each game state defined in `states.inc.php`, you may have corresponding methods in `Game.php`:
    *   **State entry actions (`st<StateName>`)**: If a state has an `"action"` property (like `"action" => "stMyState"`) in the state machine config, implement `stMyState()` in `Game.php`. BGA will call it automatically when that state begins. This is where you put logic that should run on entering the state - for example, deal cards at game start state, or automatically advance to next state if no player action is required.
    *   **State argument methods (`arg<StateName>`)**: If a state has an `"args"` property (e.g. `"args" => "argPlayerTurn"`), implement `argPlayerTurn()`. This should return an array of data that the client might need during that state. Commonly, you use it to pass dynamic info for the state's UI or to fill placeholders in the state's "description". For example, if the state description says `${actplayer} must choose a card`, you might supply `'actplayer' => $this->getActivePlayerName()` in the args. These args are sent to all players' clients and can be used in JS in `onEnteringState`.
    *   **State action methods (`act<Something>`)**: If you prefer, you can handle some simple transitions directly by naming methods with the `act` prefix. However, usually player-triggered transitions are handled via the action file (see below) and then using `$this->gamestate->nextState($transition)` to move to the next state. The `act*` pattern is more for automatically triggered transitions (like a timer or an immediate state change without user input).
    *   **Notifications**: In your game logic, whenever a game event occurs that the clients need to know about (like a card played, a piece moved, scores updated), you will send notifications.

Notifications are messages dispatched from server to clients in real-time. BGA provides two main functions:

*   `notifyAllPlayers($name, $loggingMsg, $data)`: sends a notification to all players.
*   `notifyPlayer($playerId, $name, $loggingMsg, $data)`: sends a notification to a single player (useful for secret information).

The `$name` is a string identifier for the notification (your JS will register a handler for this name), and `$data` is an array of info to send. `$loggingMsg` is a string for the game log (can contain placeholders and use `clienttranslate` for internationalization). Example:

```php
// In PHP, after a player plays a card:
$this->notifyAllPlayers( "cardPlayed", clienttranslate( '${player_name} plays a card'),[
    'player_id' => $playerId,
    'player_name' => $this->getPlayerNameById($playerId),
    'card_id' => $cardId,
    'card_type' => $cardType
] );
```

On the client side, you'd have an `onNotifCardPlayed` handler to update the UI (remove the card from that player's hand, etc.). For private info, e.g. dealing initial hands, use `notifyPlayer` so each player only gets their own hand:

```php
$cards = $this->cards->pickCards(13, 'deck', $player_id);
self::notifyPlayer($player_id, 'newHand', '', ['cards' => $cards]);
```

(As shown in the Deck example, each player gets a `newHand` notification with their drawn cards, and we pass an empty log message since we don't want a public log for each hand dealt.)

*   **Randomness**: BGA provides a built-in random number generator to ensure reproducibility and fairness. Use `bga_rand(min, max)` for random integers (instead of PHP's `rand()` or `mt_rand()`). For shuffling, Deck module's `shuffle()` is already designed to use BGA's RNG. Using BGA's random ensures the same sequence can be replayed or inspected for fairness.
*   **End of Game**: Typically, you detect victory or game end conditions in your logic (e.g., a score threshold or no moves left). To end the game, you update players' scores (using `$this->DbQuery` to update the player table or via `setStat` for stats) and call `$this->gamestate->nextState("endGame")` to transition to the final state. You should also set up in `stats.inc.php` any ranking stats (like score) as “result” so BGA knows how to order winners. BGA will handle ELO and post-game once you reach the end-of-game state.
*   **Zombie Players**: BGA has a concept of zombie mode for players who leave or run out of time. Implement `zombieTurn($playerId)` in your `Game.php` to decide what happens if a player is zombified on their turn. Often, you'll choose to make them perform a pass action or simply skip their turn. BGA calls this function to allow your game to proceed without the player.

The main game class can be quite large, but BGA's default code skeleton comes with extensive comments and a recommended structure. Notably, it warns that every time an AJAX action is called, your game class is re-instantiated and the requested method run, then PHP ends. This means you **cannot** store state in PHP session or global variables between calls – always persist game state to the database or use the provided structures. For example, if a player plays a card, you must record that in the DB (or Deck component) before the function ends. Similarly, do not rely on PHP object properties persisting between turns (except those stored in the database). Understanding this request-based lifecycle is crucial.

### The Game State Machine (`states.inc.php`)

BGA uses a finite state machine (FSM) to control game flow. The `states.inc.php` file defines an array `$machinestates` describing each state and its transitions. Here's what to include:

*   **State Constants**: It's common to `define()` constants for your state IDs at the top, for readability. For example:
    
    ```php
    define("STATE_GAME_SETUP", 1);
    define("STATE_PLAYER_TURN", 2);
    define("STATE_END_GAME", 99);
    ```
    
    BGA reserves state 1 for initial setup (and 99 for the end of game) by convention.
*   **Initial State (ID=1)**: Always a `manager` type state to do `gameSetup`. BGA's framework uses this to call your `setupNewGame`. The transitions from state 1 should point to the next logical state of your game (e.g., `STATE_PLAYER_TURN`). You usually don't modify the state 1 definition except the transition.
*   **State Entries**: Each state entry is an associative array with keys:
    *   `"name"` – an internal name for the state (e.g., "playerTurn").
    *   `"description"` – a translatable string shown in the UI's status bar to all players when the state is active (you can include placeholders like `${actplayer}`). For states where players don't act (type "game"), this can be empty or a general message.
    *   `"descriptionmyturn"` – an alternative string shown to the active player (with `${you}` placeholder) so you can say "You must do X" while others see "${actplayer} must do X".
    *   `"type"` – one of:
        *   `activeplayer`: only one player is expected to act (their UI will show action buttons).
        *   `multipleactiveplayer`: several players can act simultaneously (for example, selecting cards to discard at the same time).
        *   `game`: a non-player state where the system (your code) does processing (e.g., resolving an effect, or just a transit state). Players have no direct input in these; the game automatically progresses.
        *   `manager`: a special state for setup and cleanup (no UI, used for state 1 and final state).
    *   `"action"` – (optional) the name of the PHP function to call when entering this state (as mentioned, like `"stPlayerTurn"` => calls `stPlayerTurn()` in `Game.php`).
    *   `"possibleactions"` – (optional) an array of strings naming the player actions allowed in this state. This ties into the `checkAction` mechanism: on the client side and server side, calling `checkAction('actionName')` verifies that the game is currently in a state where `'actionName'` is permitted. You should list all action names that players could perform in that state (like "playCard", "endTurn"). This helps catch illegal moves (e.g., if a client tries to make a move in the wrong state).
    *   `"transitions"` – an associative array mapping transition names to state IDs. After performing an action or finishing state logic, your code will tell the FSM which transition to take by calling `$this->gamestate->nextState("transitionName")`. The FSM will then move to the state ID specified. For example, `"transitions" => [ "playCard" => STATE_GAME_TURN, "endGame" => STATE_END_GAME]` means if you call `nextState("playCard")`, the state machine goes to the state ID for game logic, whereas `nextState("endGame")` jumps to the end. Each state can have one or multiple transitions defined.
    *   `"args"` – (optional) the name of a method in `Game.php` that provides arguments for this state. If set, BGA will call that method (e.g., `argPlayerTurn()`) and send its return array to clients (available in JS `onEnteringState` and for filling `${variables}` in the description).
    *   `"updateGameProgression"` – (optional, boolean) if true, BGA will recalc the game progression percentage by calling `getGameProgression` at the start of this state. Typically set true in states representing a full turn or round.
*   **Final State**: Usually ID 99, type "manager", with name "gameEnd". No transitions out (the game ends here).

Example: A simple turn-based game might have states: `gameSetup (1) -> playerTurn (2) -> possibly a nextPlayer (3) -> back to playerTurn (2) -> -> gameEnd (99)`. The `playerTurn` state would be type "activeplayer", allowing actions like "playCard" or "endTurn", and have transitions: `"playCard" => 3` (to a state that handles the consequences, maybe type "game"), and `"endGame" => 99`. The state 3 (type "game") might automatically decide the next player then use `nextState("")` to loop back to state 2 (since `transition "" => 2` can be used as a default).

Defining the state machine clearly is crucial. It not only documents your game flow, but BGA enforces it: if you call an undefined transition or a player tries an action not allowed by `"possibleactions"`, you'll get an error. Use the state machine to prevent illegal moves (via `checkAction` in your action handling) and to guide the turn order. The BGA Framework at a Glance slides and docs on state machines are a helpful reference.

### Handling Player Actions (`<gamename>.action.php`)

The action file is essentially a controller for requests initiated by the client. It defines a class (usually `action_<GameName>`) that extends `APP_GameAction`. Each public method in this class corresponds to an AJAX action that can be called from the client. BGA will automatically map a client AJAX call like:

`this.ajaxcall("/<gamename>/<gamename>/<actionName>.html", params, this, successCallback, failCallback);`

to a PHP method named `actionName` in your action class.

In practice, you rarely write this class from scratch – the skeleton is provided. A typical method in this class does the following:

1.  **Enable AJAX Mode**: Call `self::setAjaxMode()` - this is a required call that prepares the server to respond to AJAX (it, for example, disables page output buffering).
2.  **Retrieve Parameters**: Use `self::getArg("<paramName>", <type>, <mandatory>)` to fetch inputs. For example, if the JS sends `{ card_id: 42 }`, you'd retrieve it with `$cardId = self::getArg("card_id", AT_posint, true);` (types like `AT_posint` ensure the argument is a positive integer).
3.  **Call Game Logic**: Invoke the corresponding method in your main game class. For instance, `$this->game->playCard($cardId);` where `playCard` is implemented in `Game.php`. In that method you'd handle the logic: check if the move is allowed (`self::checkAction('playCard')` to ensure state is correct), update the game state (e.g., move the card from hand to table in DB), and send out notifications to update clients.
4.  **Return Response**: Call `self::ajaxResponse();` which ends the AJAX request and sends the accumulated data (if any). Usually, your game logic methods already sent notifications and updated the database, so no further data is needed here – the client will be updated via notifications.

This file's role is described as a bridge between client JS and server PHP. It should be kept thin: no game rule logic here, just parameter passing and perhaps some logging or simple checks. By keeping logic in `Game.php`, you ensure consistency (and easier AI or replay integration, since those also call `Game.php` methods directly).

Example: If players can "end turn" without doing anything else, you might have:

```php
public function endTurn() {
    self::setAjaxMode();
    // maybe no parameters needed for ending turn
    $this->game->endTurn(); // calls the method in Game.php
    self::ajaxResponse();
}
```

And in `Game.php::endTurn()`:

```php
function endTurn() {
    self::checkAction('endTurn'); // verify state allows this action
    // (game logic to end the turn, e.g., advance game state)
    $this->gamestate->nextState("nextTurn");
}
```

Make sure every action method calls `checkAction` before making any changes, to guard against out-of-turn usage.

On the JavaScript side, you trigger these by e.g. a button or UI event calling something like:

```javascript
dojo.connect(this.btnEndTurn, 'onclick', this, () => {
    if(! this.checkAction('endTurn')) return;
    this.ajaxcall("/mygame/mygame/endTurn.html", { lock: true }, this, () => {}, () => {});
});
```

Using `lock: true` is a common parameter that prevents other actions until this one completes (to avoid race conditions).

To summarize: define one method per distinct player action. Keep it simple - fetch input, call the game logic, respond. This separation allows BGA to handle networking, security, and ensures the game logic can be reused (for AI or for calling from console during testing).

### Database Access and Models

BGA provides a database for your game. Your `dbmodel.sql` defines tables; common ones include a `player` table (automatically provided) and any game-specific tables, like a `card` table for Deck, or a `board` table for board positions. Use the provided Database API functions in `Game.php` to interact with the DB:

*   `DbQuery($sql)`: Execute an SQL query (INSERT/UPDATE/DELETE). Use this for custom updates not covered by the framework.
*   `getObjectListFromDB($sql)` / `getObjectFromDB($sql)`: Fetch results as associative arrays. Very handy for reading your tables. e.g., `self::getObjectListFromDB("SELECT x, y, player FROM board WHERE player IS NOT NULL")` to get all occupied board cells.
*   `getUniqueValueFromDB($sql)`: Returns a single value (first field of first row).
*   `getCollectionFromDB($sql, $bSingleValue=false)`: Returns a dictionary (associative array) of the results, often used for retrieving players or mapping IDs to something.

When using these, incorporate variables safely (they're typically already safe if using proper quoting or integers). For complex operations, you might still use raw SQL. The Deck module, as mentioned, spares you from writing queries for cards (it uses a `card` table behind the scenes).

**Tips**: Always design your tables with an index or primary key that makes accessing easy. For example, the Deck expects a table with columns (`card_id`, `card_type`, `card_type_arg`, `card_location`, `card_location_arg`). For a grid board, a composite primary key on coordinates (like the Reversi `board` table with primary key (`board_x`, `board_y`)) is useful. This ensures each square is unique and updatable. Use `AUTO_INCREMENT` for unique IDs if needed (not for Deck's card table, since Deck will generate IDs).

Remember that game state must persist in the DB because of the stateless nature of PHP between moves. If a value should carry over to the next turn, put it in a table or use a "global" (via `setGameStateValue`, which stores it in a small `global` table).

### Internationalization (Translations)

BGA supports multi-language translations of game interface text. Developers mark translatable strings with special functions:

*   **In PHP (server)**: wrap strings in `clienttranslate("Your string")` when sending them in notifications or setting state descriptions. Also, in `gameinfos.inc.php`, the game name and description should be in `clienttranslate()`. This tells BGA's translation system to collect these strings for translators. Example: `clienttranslate('${actplayer} must place a worker')`. Placeholders like `${actplayer}` and `${you}` are recognized and replaced by BGA (with player names or "You") automatically. Use `self::_($text)` for non-notification strings if needed (though typically `clienttranslate` is enough).
*   **In JavaScript (client)**: Use `_("Your string")` or the provided localization mechanism. Actually, the BGA framework passes translated strings to the client already for most server-generated text (like notification logs or state descriptions). For any hardcoded client-side text (tooltips, labels not set from server), you can use the `_("txt")` function which is provided by BGA's localization script. Additionally, for dynamic text with placeholders on client side, BGA often uses `format_string_recursive` to handle insertion of variables into translated strings.

All translatable texts must appear in your code wrapped in those functions so that the BGA translation interface can pick them up. For example, if you send a log: `clienttranslate('${player_name} built a house')`, translators will see "${player_name} built a house" as a string to translate (the placeholder stays as is). The system will replace it with the player's actual name when displaying.

### Game Options and Preferences

If your game has variants or optional rules (game setup options), define them in `gameoptions.json`. For example, you might allow a "short game" or "long game". Each option entry includes: an `id`, a `name` (string, translatable), a `description`, `default value`, and the `possible values` (each with a label and possibly a difference in setup). Here's a conceptual snippet:

```json
{
  "options": {
    "100": {
      "name": "Game length",
      "values": {
        "1": { "name": "Short (10 rounds)" },
        "2": { "name": "Long (20 rounds)" }
      },
      "default": 1
    }
  }
}
```

On the PHP side, you retrieve the selected option with `self::getGameStateValue( 'optionId' )` or via the `$this->gamestate->table_globals` array for newer frameworks. Actually, BGA automatically stores game options in the `global` table with IDs in the 100+ range. For instance, if option id 100 was set to 2, you could do:

```php
if ($this->getGameStateValue("game_length") == 2) {
    // ...
}
```

to adjust game logic. Preferences (options that each player can set individually, like interface preferences) are less common for game rules, but you might handle them similarly (they appear in `gamepreferences.json` and are stored with different IDs).

### Debugging and Logs

During development, you can use `error_log()` or `var_dump()` to log to the PHP error log visible in Studio. BGA Studio also provides a "Debug" panel and you can see Studio logs for your game's output. There's also a "Reload" and "Reset the game" button in the studio to test repeatedly. Use the Practical debugging and Troubleshooting guides for tips if you get stuck. Common pitfalls include forgetting to update `getAllDatas` when game state changes (so a refresh doesn't show the correct info) or not calling `checkAction` (which can lead to tricky bugs if actions come out of sequence). The BGA framework is quite verbose with errors – if something is misconfigured (like a missing state transition or a notification with wrong data), it often throws an exception with a message visible in the Studio logs.

## Client-Side Interface (JavaScript)

On the client side, you create the interactive experience: displaying the game state and handling user input. BGA's client framework uses Dojo (a JavaScript toolkit) and a base class `ebg.core.gamegui` which your game's JS class extends. The framework handles communicating with the server (sending AJAX calls and receiving notifications), so your job is to manipulate the DOM based on game state.

Key parts of the client-side code:

### Setting up the Interface (`setup` method)

When a player loads or refreshes the game, BGA sends the data from `getAllDatas()` to the client and then calls your game's `setup(gamedatas)` function with that data. In `setup`, you should construct the visual representation of the game from scratch. Typical tasks:

*   **Initialize UI components**: For instance, create player panels or boards, using the data in `gamedatas`. BGA automatically adds basic player panel HTML (with player names, score, etc.), accessible via something like `this.gamedatas.players`. You can populate additional info there (e.g., tokens or counters next to each name).
*   **Place game elements**: Loop through game state data to place cards, pieces, etc., in the UI. For example, if `gamedatas.board` contains positions of tokens, iterate and add a token element in the corresponding HTML square. Or if `gamedatas.hand` has the current player's cards, use the Stock component to display them (see Stock usage below).
*   **Create UI controls**: Set up any interactive elements, such as enabling drag-and-drop if needed (via BGA Draggable component), or connect event handlers. For instance, attach an `onclick` to each card or token that should be clickable. Use `dojo.connect` or jQuery depending on preference (BGA's default uses Dojo, but you can include others or plain JS). Just ensure to use `this` context properly.
*   **Instantiate BGA components**: If you plan to use components like `ebg.stock` or `ebg.counter`, this is usually done in `setup`. For example, for each player you might create a Counter for their score display, or a Stock for their hand of cards. (Examples forthcoming in the Components section).

A simple example in pseudocode for `setup`:

```javascript
setup: function(gamedatas) {
    // Set up player score counters
}
```

```javascript
this.scoreCounters = {};
for(let playerId in gamedatas.players) {
    let score = gamedatas.players[playerId].score;
    this.scoreCounters[playerId] = new ebg.counter();
    this.scoreCounters[playerId].create('player_score_'+playerId);
    this.scoreCounters[playerId].setValue(score);
}

// Set up player hand stock
this.playerHand = new ebg.stock();
this.playerHand.create(this, $('myhand'), CARD_WIDTH, CARD_HEIGHT);
this.playerHand.image_items_per_row = 13;
// define the card types in the stock (for all 52 cards)
for(let color=1; color<=4; color++){
    for(let value=2; value<=14; value++){
        let cardTypeId = this.getCardUniqueId(color, value);
        this.playerHand.addItemType(cardTypeId, cardTypeId, g_gamethemeurl+'img/cards.jpg', cardTypeId);
    }
}
// Add cards from gamedatas to the stock
gamedatas.hand.forEach(card => {
    this.playerHand.addToStockWithId(this.getCardUniqueId(card.type, card.type_arg), card.id);
});

// Connect event for clicking a card
dojo.connect(this.playerHand, 'onChangeSelection', this, (control, itemId) => {
    let cardId = itemId; // assuming itemId is the card.id
    if(this.checkAction('playCard')) {
        // tell the server we play this card
        this.ajaxcall("/mygame/mygame/playCard.html", { id: cardId, lock: true }, this,
            result => {},
            isError => {}
        );
    }
});

// ... similarly set up other elements (board, tokens, etc.) ...
```

In the above: we created score counters for each player (hooked to DOM elements like `player_score_[id]` which exist by default), and a Stock for the current player's hand of cards, filling it with images. Then we attached a selection handler to play a card when it's clicked. This uses `checkAction('playCard')` to verify that the state allows playing a card (which corresponds to the `'possibleactions'` in the state machine) before sending the AJAX.

Note on theme URL: `g_gamethemeurl` is a global variable BGA provides that points to your game's theme folder (where your images are stored). So `g_gamethemeurl + 'img/cards.jpg'` yields the correct URL for the image file in the game's folder.

### Updating the Interface with Notifications

BGA's server will send notifications to all clients when something happens, by calling your JS class's handler for that notification. If your notification was named "cardPlayed", you must implement `notif_cardPlayed(args)` (the naming convention is `notif_<name>` with the first letter lowercased) in your JS. The `args` parameter contains whatever data you passed in the `notifyAllPlayers` call.

For example, if the server does:

```php
notifyAllPlayers('cardPlayed', '${player_name} played a card', [
    'player_id' => $playerId,
    'card_id' => $cardId,
    // ... other data ...
]);
```

then in JS:

```javascript
notif_cardPlayed: function(notif) {
    console.log('Notif: cardPlayed', notif);
    const playerId = notif.args.player_id;
    const cardId = notif.args.card_id;
    // Remove the card from the player's hand stock (if current player or if you keep others' hands hidden)
    if(playerId == this.player_id) {
        this.playerHand.removeFromStockById(cardId);
    } else {
        // Remove card from other player's area, perhaps show a card back moving away
        this.otherPlayersHand[playerId].removeFromStockById(cardId);
    }
    // Add the card to table area on UI
    // e.g., dojo.place( this.format_block('jstpl_cardontable', {id: cardId}), 'table_zone');
    // and maybe flip it or animate its arrival.
}
```

The above pseudo-code removes the card from the appropriate hand and places it on the board. The `notif.args` contains exactly what was sent from PHP.

All notifications you send must have a JS handler, otherwise you'll get a debug error. Also, notifications often include a pre-translated log message that BGA displays in the game log (the `clienttranslate` message). In the example, `'${player_name} played a card'` would appear in the log, with `${player_name}` replaced accordingly. The log is automatic; your JS handler should focus on updating the game state visually.

Common notifications include things like `newHand` (to deal initial cards), `updateScore` (when you change a score – you'd update the counter), `pieceMoved`, etc. It's up to you to name them clearly. In complex games, you might have dozens of different notifications.

BGA ensures notifications are received in the same order by all clients in real-time, and also on replays and when a player reconnects (all notifications are part of the game history). This means your `notif_` handlers must be **idempotent** and purely visual updates (they should not alter game state beyond the UI, since the source of truth is the server DB). They should be able to run in sequence to reconstruct the game.

### Player Panels and Other UI Elements

The player panels (on the right side) list each player's name, avatar, score by default. You can customize these to show extra info (like resources, cards left, etc.). There is an example in the Counter component doc of adding elements to the player panel: the Gomoku example adds an icon and a counter for each player. Essentially, you can define a small HTML template (as a JS template string or using `format_block` with a `jstpl`) and then insert it into each player panel. BGA provides a `<div id="player_board_[id]">` for each player that you can append content to. After adding, you might store references (like `this.stone_counters[playerId] = new ebg.counter()`) and use them.

The BGA framework also often provides some helper UI, like a general game log area, and automatically disables UI for players who aren't active (the exact behavior depends on your state settings and use of `this.checkAction` in the client).

### Responsive and Mobile Considerations

Keep in mind some players will be on mobile. BGA has a "mobile" mode switch that can include an alternate layout (`YourGame.mobile.tpl` or similar, but often you can handle via CSS). Ensure that clickable areas are not too small and that your layout can reflow to narrower screens. The framework's Mobile Users guide suggests techniques for adjusting font sizes or using vertical layouts if needed. You can detect if the client is on mobile by checking `this.isMobile()` in your JS and adjust accordingly (maybe simplify animations or have different CSS). Test your game in a small browser window to see how it behaves.

### Animations

BGA recently introduced a new animation manager (`bga-animations` library) to standardize animations (like sliding, fading, etc.). You can include it and use it for smooth effects. For example, the Reversi tutorial shows using `this.animationManager.fadeIn()` to animate a disc appearing from a player's panel to the board. To leverage it, ensure to add "dojo/_base/fx" or the specific BGA animation library as a dependency and initialize a `BgaAnimationManager`. Alternatively, you can always use direct CSS transitions or `dojo.fx` for simpler needs. Animations greatly enhance user experience, but make sure to respect the game state (i.e., don't allow another action until an animation of a move finishes, or use the promise/async pattern as in the Reversi snippet).

### External Libraries (Advanced)

The BGA Studio Cookbook has tips on using modern frameworks like Vue or using TypeScript. These are advanced topics; BGA's default paradigm is plain Dojo-based JS, but some developers successfully use frameworks by building their code and including the bundle. If you're starting out, it's easier to stick to the provided structure. But be aware: the environment has Dojo 1.15 and supports ES5-ish JavaScript. You can include additional JS or CSS files if needed by listing them in your `gameinfos.inc.php` as `game_interface` entries (not commonly done, except for 3D maybe).

## Using BGA's Built-in Components

One of the strengths of BGA Studio is a collection of pre-made components for common game tasks. Using these can save a lot of time and ensure consistency. Below, we cover the most important ones (both PHP and JS components) and how to use them, with examples.

### PHP Component: Deck (managing cards)

The Deck component is a server-side module that handles a collection of items (typically cards) with minimal SQL work on your part. When you use `self::getNew("module.common.deck")`, you get a Deck object which expects a database table (with standard columns like `card_id`, `card_type`, etc.). Using Deck, you can easily perform operations such as shuffling, drawing, moving cards between locations (deck, hand, table, discard, etc.), and even auto-reshuffling the discard into deck when needed.

**Setup**: In your `Game.php` constructor, create and initialize the deck:

```php
$this->cards = self::getNew("module.common.deck");
$this->cards->init("card"); // 'card' is the table name defined in dbmodel.sql
```

This links the component to the `card` table. Make sure your `dbmodel.sql` has that table created with the required fields.

**Card properties**: Each card managed by Deck has 5 properties: `id`, `type`, `type_arg`, `location`, `location_arg`. You decide how to use `type` and `type_arg` to classify cards. For example, in a standard deck of playing cards, you might use `type` for suit and `type_arg` for rank (as Hearts does: type=Suit(1-4), type_arg=Value(2-14 for 2-Ace)). The `id` is auto-generated and unique for each card. `location` and `location_arg` indicate where the card is: e.g., `location "deck"` could mean draw pile, `"hand"` could mean in hand with `location_arg = player_id`, `"table"` for on the board, etc. You define these location strings arbitrarily.

**Creating cards**: Use `$this->cards->createCards($card_descriptions, $location)` to create all cards at game setup. We showed an example earlier for a standard 52-card set. Each element in `$card_descriptions` is an array with keys `'type'`, `'type_arg'`, and `'nbr'` (number of such cards to create). Deck will generate the specified quantity with sequential IDs. After creation, they all sit in the given `$location` (e.g., 'deck'). You can then shuffle or deal them.

**Shuffling and drawing**:
*   `shuffle($location)`: shuffles all cards in the given location. Usually, you shuffle the main deck.
*   `pickCard($from_location, $to_player_id)`: move one card from a location (like the top of deck) to a player's hand (the component understands 'hand' as a special target that requires a player id). Alternatively, `pickCards($n, $from, $to_id)` to pick multiple at once. These return the card data (which you can directly send in a notification).
*   `moveCard($card_id, $new_location, $location_arg = null)`: move a specific card to a new place. E.g., play a card from hand to table: `$this->cards->moveCard($card_id, 'table', $player_id);` (the `location_arg` could signify which player's table area if needed).
*   `moveAllCardsInLocation($from_location, $to_location, $to_arg = null)`: bulk move, e.g., gather all cards from discard back to deck.
*   `getCardsInLocation($location, $location_arg = null)`: retrieve cards currently at a location, optionally filtering by `location_arg` (like get all cards in a specific player's hand).
*   `countCardInLocation($location, $location_arg = null)`: count how many cards are at a location (optionally with arg). There's also `countCardsByLocationArgs($location)` to, say, count how many are in each player's hand in one call.

**Auto-reshuffle**: Deck supports setting a discard pile such that if `pickCard` finds the deck empty, it can automatically reshuffle the discard into deck. This is enabled via `$this->cards->autoreshuffle = true; $this->cards->setDiscard('discard', 'deck');` (for example) – after that, drawing from an empty 'deck' will move all from 'discard' to 'deck' and shuffle. Check the Deck doc for exact usage of `autoreshuffle` if you need this feature.

**Example usage in context**: In Hearts (as referenced), after dealing cards, they use `getCardsInLocation('hand', $player_id)` to send each player their hand. When a player plays a card:

```php
// Remove from hand and place on table
$this->cards->moveCard($card_id, 'cardsontable', $player_id);
// Notify others of the played card, etc.
```

And when a trick is finished:

```php
// Move all cards from table to discard
$this->cards->moveAllCardsInLocation('cardsontable', 'discard');
```

This eliminates writing manual UPDATE queries to change card locations - the component handles it and ensures the object's state is updated.

**Retrieving card info**: Deck stores card data in the DB, but often you want a card's type or other info. If you have a card definition in `material.inc.php` (like an array mapping type/type_arg to meaningful info), you can use it in combination with Deck's methods. Deck provides `getCard($id)` and it returns the assoc array of that card (`id`, `type`, `type_arg`, `location`, `location_arg`). You then map `type`/`type_arg` to your game material. Or maintain an in-memory array in PHP of cards (since the deck structure persists via DB, not PHP memory, you might reconstitute some info when needed).

In summary, the Deck component is extremely useful for any game with cards or similar items. It abstracts away the SQL and gives clean methods to use in your `Game.php`. As the documentation says, you can do most card operations "without writing a single SQL request". We have already integrated some Deck examples above with citations for reference.

### JS Component: Stock (displaying lists of items)

Stock is a versatile JavaScript component for showing a collection of items (cards, tokens, tiles) in the UI, typically in a neatly arranged grid or line. It automatically handles layout and animated reordering when items are added or removed. It's heavily used for things like hands of cards, rows of tiles, etc.

**Setup**: Include `"ebg/stock"` in your `define` dependencies. In `setup`, create a Stock object with `new ebg.stock()`, then call its `create(this, container_div, item_width, item_height)` method. The first param is the game page context (`this`), second is the DOM element or its id where the stock will display, third and fourth are the pixel width and height for each item (they should match the actual size of your item images). For example:

```javascript
this.playerHand = new ebg.stock();
this.playerHand.create(this, $('myhand'), CARD_WIDTH, CARD_HEIGHT);```

Make sure `$('myhand')` (a container div) exists in your HTML template.

**Defining item types**: Before adding any items, tell the Stock what types of items exist and what they look like. If each item has a unique image, you could add each one separately, but typically you use a sprite sheet. For a sprite, set `stock.image_items_per_row` to the number of images per row in the sprite. Then use `addItemType(typeId, weight, image, image_position)` for each category of item:
*   `typeId`: an identifier for the item type (an integer) - you can use the same encoding as your server (e.g., a unique ID combining card suit and rank).
*   `weight`: controls sort order; items with lower weight come first if the stock auto-sorts. If you want the stock to maintain the insertion order or a specific order, you might give sequential weights or use the same weight for all to disable sorting randomness.
*   `image`: URL of the image. Often `g_gamethemeurl + 'img/sprite.png'`. All types can share the same sprite image.
*   `image_position`: index of the image for this type in the sprite (counting across rows). For example, in a 13-per-row card sprite, the card with `typeId` representing "Ace of Spades" might correspond to sprite index 0, "2 of Spades" index 1, "Ace of Hearts" index 13, etc. In our earlier code, we used the card unique id itself as the `image_position` which only worked because we carefully set those ids to line up with sprite positions. Alternatively, maintain a mapping if needed.

For instance:

```javascript
this.playerHand.image_items_per_row = 13;
this.playerHand.addItemType(11, 11, g_gamethemeurl+'img/cards.jpg', 11);
```

If type 11 corresponds to a specific card image at index 11 in the sprite. In the Hearts example, they looped and called `addItemType(card_type_id, card_type_id, ..., card_type_id)` basically using the unique card id as both type and position, after setting `image_items_per_row = 13`.

**Adding items to display**: Use `stock.addToStock(typeId, from)` or `stock.addToStockWithId(typeId, itemId, from)`. The difference: `addToStock` doesn't assign a unique identity to the item (good for generic tokens). `addToStockWithId` associates a specific ID with the item. You should use this for cards or any items that you might remove individually later (by that id) or allow selection of. The `itemId` typically is the unique DB id of the card or piece. **Important**: do not mix using `withId` and `withoutId` on the same stock; choose one style.

Example:

```javascript
// Assume we have a card object with id, type, type_arg from server:
let uniqueId = this.getCardUniqueId(card.type, card.type_arg); // maps to typeId we used in addItemType
this.playerHand.addToStockWithId(uniqueId, card.id);
```

If you just had a generic token stock not caring which specific token, you could do `addToStock(tokenType)`.

The optional `from` parameter (in both `addToStock` and `addToStockWithId`) can be a DOM element. If provided, the new item will appear to slide from that element into the stock. For example, if drawing a card from a deck, you might do:

```javascript
this.playerHand.addToStockWithId(cardType, card.id, 'deck_stock');
```

where 'deck_stock' is the HTML element of the deck. This gives an animation of the card coming from the deck to the hand (Stock handles the slide animation automatically).

**Removing items**: Similarly, to remove, use `stock.removeFromStock(type)` or `stock.removeFromStockById(id)`. With `removeFromStockById`, you specify the item's id (the same id used in `addToStockWithId`). Optionally, you can give a `to` HTML element id, so it will animate moving to that element before disappearing. This is great for playing a card to the table or discarding:

```javascript
this.playerHand.removeFromStockById(card.id, 'cardsontable_div');
```

Stock will move the card image to `cardsontable_div` then remove it from the hand. If you intend to then display it on the table, it's better to clone it by using the `from` / `to` animation. Actually, a common pattern is: - Remove from hand with animation to an off-screen buffer or the final location, and simultaneously or after, create a permanent element at the final location.

**Selection and events**: Stock can handle selection of items. You can enable selection mode with `stock.setSelectionMode(mode)` where mode 1 means single selection, mode 2 means multiple selection (for something like selecting multiple cards). Then you can connect to its `onChangeSelection` event as shown earlier, to respond when a player selects an item. In that handler, `control.getSelectedItems()` gives the list of selected item objects; or you can use the passed `itemId` if single select (the example above uses the itemId directly for a single selection play). You can also toggle selection via code: `stock.selectItem(id)` and `stock.unselectAll()`.

**Stock positioning and styling**: By default, Stock places items in a single row until it runs out of space of its container, then wraps to a new row. You can adjust some of its properties: `stock.centerItems = true;` to center the line of items. - If you want to force multiple rows or a grid, you may need to adjust the container width or use multiple Stock instances. - For overlapping display (like fan of cards), Stock is not ideal; you might need custom logic or the Zone component (described next) for overlapping placements.

Stock is extremely common - just about every card game on BGA uses it for hands or decks. It handles a lot for you: sorting, animated moves, and DOM management. As noted in documentation, "the entire life cycle of the stock is managed by the component”, so you don't manually create or delete individual item elements - you rely on stock to do it when you add or remove.

### JS Component: Zone (managing free placement in a region)

Zone is a client component for when you have a board area and pieces that can move around or stack in the same space. While Stock is for neatly ordered sets, Zone is for spatial organization. It lets you define a region (like a `<div>`) where you can add items that might overlap or be positioned relative to each other within that zone.

**Setup**: Include `"ebg/zone"` in your dependencies. In JS, create a zone with `new ebg.zone()` then initialize with `zone.create(this, container_div_id, item_width, item_height)`. The container is an HTML element (e.g., an empty div on your board). The `item_width`/`height` help zone calculate positioning.

**Pattern (layout mode)**: Zone has multiple layout patterns you can choose with `zone.setPattern(pattern)`. Patterns include:
*   `'grid'`: place items in a grid within the zone (will wrap items, each item occupies `item_width` x `item_height` space).
*   `'diagonal'`: overlap items diagonally (good for stacks of pieces where each new one is offset down-right).
*   `'ellipticalfit'`, `'horizontalfit'`, `'verticalfit'`: some special arrangements, or
*   `'custom'`: you will manually specify coordinates when adding items.

If pieces can stack (like multiple tokens on the same board space), zone's diagonal pattern is useful to display them neatly offset.

**Adding items**: `zone.placeInZone(element, position, animate)` places a DOM element into the zone at a given position index. If pattern is `grid`, position 0 is top-left, 1 next to it, etc. If `diagonal`, position might just stack them with an offset. If `custom`, position corresponds to an index you define via coordinates. For example, `zone.setPattern('custom')` and then `zone.defineItem(id, x, y)` for known coordinates (there's a way to predefine coordinates for a given number of items, see docs for 'custom' usage).

Alternatively, `zone.placeOnObject(element, targetElem)` can put an element exactly where another element is (often used to move from one zone to another smoothly).

**Usage scenario**: Imagine a game like "Can't Stop" where multiple player markers can be on the same space of a track. You could have a zone for each space. In that zone, use diagonal pattern so if two markers are there, one is slightly offset. To do so: in HTML each board space `<div>` could have a child `<div class="zone" id="zone_spaceXY"></div>`. In JS, for each space you do:

```javascript
this.spaces[spaceId] = new ebg.zone();
this.spaces[spaceId].create(this, 'zone_space'+spaceId, TOKEN_WIDTH, TOKEN_HEIGHT);
this.spaces[spaceId].setPattern('diagonal');
```

Then when placing a token:

```javascript
let tokenDiv = dojo.place(this.format_block('jstpl_token', {player: playerId}), 'zone_space'+spaceId);
this.spaces[spaceId].placeInZone(tokenDiv);
```

If another token is added, `placeInZone` with no specific position will just assign the next index (so second token gets index 1 and Zone will offset it diagonally).

Zone also has methods to remove or move items out. If you call `zone.removeFromZone(element)` it will remove that DOM element from the zone (you'll likely then destroy it or move it elsewhere).

**Zone vs Stock**: If you need an ordered but not strictly grid-aligned placement (like pieces on a specific spot on a board), Zone is appropriate. If you just need a simple list, Stock is easier. You can also use multiple stocks for different board locations but that's unwieldy if many locations. Zone was basically created to handle board coordinates.

For custom patterns, you might check official docs; it's beyond this guide's scope to detail each mode. Just know Zone exists for those spatial needs (the doc gives examples: "In Can't Stop, zone is used to display pieces on the same space (diagonal mode)").

### JS Component: Counter (animated counters)

Counter is a small JS component to display a number that can increment or decrement with animation. It's perfect for score displays, resource counts, etc., especially on the player panel or within the board.

**Setup**: Include `"ebg/counter"` in your `define` (most games do by default). In JS, create with `new ebg.counter()`, then `counter.create(targetElementOrId)`. The target should be an element like a `<span>` where the number will appear. Ensure that span is empty or contains the initial number in HTML (Counter will override it anyway).

**After creation**:
*   Use `counter.setValue(x)` to set without animation.
*   Use `counter.incValue(by)` to increment (or decrement if by is negative) with an animation counting from old to new.
*   Use `counter.toValue(x)` to set to a new value with counting animation from old to new.
*   You can get current value with `counter.getValue()` if needed.

If you want to hide a counter (like show "-" when value is irrelevant), there is `counter.disable()` which makes it show “-” temporarily.

Example: Suppose you have a score counter for each player as earlier:

```javascript
this.scoreCounters[playerId] = new ebg.counter();
this.scoreCounters[playerId].create('player_score_'+playerId);
this.scoreCounters[playerId].setValue(initialScore);
```

Now in your notification when a score changes:

```javascript
notif_updateScore: function(notif) {
    let playerId = notif.args.player_id;
    let newScore = notif.args.new_score;
    this.scoreCounters[playerId].toValue(newScore);
}
```

This will animate the number from old value to new value. If you prefer an immediate change, use `setValue`. If you want an incremental update (like +5), you could use `incValue(5)` as well.

One nice thing: if you created the span with initial content "0", you might skip setting initial value manually as Counter might pick it up, but it's safer to call `setValue` with the actual starting value from `gamedatas`.

Counter is straightforward but enhances the UI for changing numbers. BGA often uses it not just for score, but also for things like deck counts, remaining turns, etc., anywhere a number is displayed.

### Other JS Components

BGA offers a few more specialized components:

*   **Draggable**: Allows making DOM elements draggable (for drag-and-drop interactions). It's undocumented in the main docs list (marked “if somebody knows please help"), but it's used in some games. Basic usage involves including `"ebg/draggable"`, then calling something like:
    
    ```javascript
    dojo.require("ebg.drag"); // old style, or via define
    this.draggable = new ebg.draggable();
    this.draggable.create(element);
    this.draggable.onDrop = function(draggedElem, targetElem) { ... };
    ```
    
    The specifics can vary. Many games implement custom dragging by handling mouse events themselves or using the HTML5 Drag and Drop API. Unless your game really needs free drag (like moving a piece anywhere), you might get by with click-selection instead.
*   **ExpandableSection**: Manages collapsible panels in the UI. If your game has a lot of info that can be hidden (e.g., a help panel or long score sheet at end), you could use this to toggle visibility. Often not needed for most games unless you add custom UI components.
*   **Wrapper**: A utility to wrap an HTML element around absolutely-positioned children. You might not need to use this directly often; it's more for advanced layout adjustments.
*   **BGA Animation Manager (`bga-animations`)**: This is relatively new. It provides a unified way to animate elements (moving, fading, sliding). Usage involves including it and then using methods like `animationManager.attach()` and `animationManager.slideToObject()` which return promises that you can await. For example, the tutorial shows usage of `fadeIn` with await to sequence an animation. This approach can simplify complex animation sequences (no need to set timeouts).
*   **bga-cards, bga-dice, bga-score-sheet**: These are higher-level components built on top of the basic ones, to assist with common needs:
    *   **bga-cards**: likely provides a standardized way to display decks and card hands with common behaviors (perhaps combining Stock and some logic).
    *   **bga-dice**: for rolling dice and showing results.
    *   **bga-score-sheet**: for games that have an end-of-game score summary table, this can animate the tallying of points.

Documentation for these is sparse in the main BGA doc (they're listed in the index but not elaborated there). If your game involves dice, you can always simply have images of dice and update them; using `bga-dice` might provide a fancier rolling animation or logic for randomizing pips. For score-sheet, if your game has multiple scoring categories, this component might help create an animated score breakdown (some games use it to show final scoring step by step).

Given their usage requires deeper digging and many games manage without them, they are optional tools. They do indicate BGA's efforts to provide more plug-and-play UI.

For initial development, focus on **Deck** (server), **Stock/Zone/Counter** (client) as needed. Those cover most needs (cards, tokens, scores). As you become advanced, explore the others for polish.

## Putting It All Together: Workflow to Implement a Game

To conclude this guide, it's helpful to outline how all these pieces come together when developing a new game on BGA:

1.  **Initial Setup**: Create a new game project in BGA Studio (using the Control Panel). This generates the skeleton files we discussed, including a default state machine (often with just states 1 and 99) and stub methods in PHP and JS.
2.  **Define Game Data Structures**: Edit `dbmodel.sql` to add any tables your game needs (cards, board, etc.). Edit `material.inc.php` to list static info (e.g., define card suits, deck composition, tile types). Update `gameinfos.inc.php` with correct game name, player counts, etc. Optionally set up `gameoptions.json` if you have variants.
3.  **Implement Server Logic**: In `Game.php`, flesh out `setupNewGame` using your tables and possibly Deck to initialize everything. Configure the state machine in `states.inc.php` to model turn order and phases of your game. Add game logic methods for each action and tie them into the state transitions. For each action:
    1.  Write a `function actionName()` in the action file that calls `$this->game->actionName(...)`.
    2.  In `Game.php`, write `actionName()` method: use `self::checkAction('actionName')`, perform rule logic (update tables, move cards, etc.), send `notifyAllPlayers` or `notifyPlayer` updates, then `gamestate->nextState(...)` to advance the FSM.
    3.  Ensure any complex calculations or rule enforcement is done here (e.g., validate that a move is legal before committing it; if not, throw an error with `throw new BgaUserException(_("You cannot do that now."))` which will show a message to the user).

Implement auxiliary methods like scoring, checking end conditions (maybe in `stEndGame` state action, calculate winners and update scores).

1.  **Implement Client Interface**: In your JS file, use the data from `gamedatas` in `setup` to create the visual representation:
    1.  Add HTML elements or use components (Stock for cards, Zone for board placements, etc.).
    2.  Attach event handlers to allow user interaction: typically click or drag events that call `this.ajaxcall` to trigger server actions.
    3.  Possibly add some client-side validation or helpers (but remember server is source of truth).

Set up your notification handlers (`notif_` functions) to update the UI for each possible change. This means for every `notifyAllPlayers` you did in PHP, there's a corresponding JS function to reflect that change (remove card, move token, update scores, etc.).

1.  **Testing Iteratively**: Use the Studio to run a game with 1 or more players (you can open multiple dummy players as described in the docs). Step through a full game flow. Use browser console and server logs for debugging. If something is off, adjust either the server or client code as needed. Common adjustments:
    1.  Fixing state transitions if the game flow gets stuck or skips.
    2.  Correcting notification data if UI isn't updating (check that the data you send in `notify` matches what JS expects).
    3.  Fine-tuning layout/CSS if things appear misaligned.
2.  **Advanced Features**: Once basic gameplay works, implement extras:
    1.  **Game Replay**: If your game benefits from a replay, ensure all random sources use BGA RNG and all state changes are through notifications. Then BGA can replay by re-sending those notifications. You might also implement `onEnteringState` functions in JS to set up UI for specific states (e.g., highlight possible moves if info is available, etc.), though that is optional.
    2.  **Undo (if enabled)**: BGA supports undo for turn-based games under certain conditions. This is complex and often not needed unless explicitly required.
    3.  **AI/Bots**: If you want computer players, you'd implement a separate PHP class for AI (the Cookbook has a section). This is an advanced topic beyond initial development.
    4.  **Mobile optimization**: Check interface on mobile size, adjust CSS or provide alternative layout if necessary.
    5.  **Performance**: For large games, ensure you're not sending too much data in notifications (e.g., don't send the entire gamestate every time; just send what changed). The framework and components are generally efficient if used as intended.
3.  **Final Checks**: Run the Pre-release checklist – ensuring translations wrapped in `clienttranslate`, no debug code left, game results properly set, no known crashes. Use the Validation tool in BGA Studio to catch common mistakes.
4.  **Submit for Review**: Once satisfied, you'd contact BGA admins or follow their process for submitting the game for review and eventual release.

Throughout this process, keep BGA's guidelines and best practices (found in the Studio Guidelines and Cookbook) in mind. For example, do not hard-code things that should be dynamic, respect the turn structure strictly, and ensure a good user experience with feedback (disable buttons when moves not allowed, show clear info on whose turn, etc.).

## Conclusion

This guide has covered the full spectrum of BGA game development: from the structure of the project and core API methods on the server, to the creation of a dynamic client interface using provided components, and the utilization of BGA's framework features like state machines and notifications. We included examples for virtually every major part – initial setup in `setupNewGame`, using Deck to manage cards without raw SQL, using Stock to display cards in the UI with smooth animations, using Counter for scores, and so on – all anchored by references to BGA's official documentation and tutorials.

By following this as a developer handbook with inline code snippets, you should be able to scaffold your game and incrementally build it out, consulting the cited sources for deeper details on each component as needed. Remember to test frequently and consider edge cases (what if a player leaves, what if a rule exception happens, etc.). BGA's active developer community (forums and Discord) can also be a resource when you run into issues.

Good luck with your game implementation, and have fun bringing your board game to life on Board Game Arena!

**Sources**: The information above is drawn from the official BGA Studio documentation and tutorials, including the BGA Studio Reference, BGA Wiki pages on specific components like Deck, Counter, Scrollmap, Stock, Zone, and the BGA Tutorial for Reversi, among other sections of the official documentation. These provide further insight and examples for each part of the development process described.