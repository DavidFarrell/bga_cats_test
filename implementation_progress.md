# Herding Cats BGA Implementation Progress Tracker

## Overview
This document tracks the implementation progress of Herding Cats for Board Game Arena. Each section corresponds to a component from the implementation plan with checkboxes to mark completion.

---

## 📁 Project Structure & Configuration

### Game Metadata Files
- [ ] Create `gameinfos.inc.php` with game metadata
  - [ ] Game name, designer, artist info
  - [ ] Player count (2-6)
  - [ ] Complexity/luck/strategy ratings
  - [ ] Interface version set to 2
- [ ] Create `material.inc.php` with constants
  - [ ] Card type constants (HC_TYPE_KITTEN, etc.)
  - [ ] Target zone constants (HC_TZ_NONE, HC_TZ_HAND, HC_TZ_HERD)
  - [ ] Card definitions array ($hc_types)
  - [ ] Deck specification array ($hc_deck_spec)
- [ ] Create `states.inc.php` with state machine
  - [ ] Define all state constants
  - [ ] Configure $machinestates array
  - [ ] Set up state transitions

### Database
- [ ] Create `dbmodel.sql`
  - [ ] Card table structure
  - [ ] Pending_action table structure
- [ ] Create `stats.json`
  - [ ] Player statistics (turns, bluffs caught, etc.)
  - [ ] Table statistics

---

## 🎮 Server-Side Implementation (PHP)

### Core Game Logic (`modules/php/Game.php`)
- [ ] Create main game class extending Table
- [ ] Constructor setup
  - [ ] Initialize Deck component
  - [ ] Set up game state labels

### Game Setup
- [ ] Implement `setupNewGame()`
  - [ ] Create 9-card decks per player
  - [ ] Deal 7 cards to each player
  - [ ] Remove 2 cards from game per player
  - [ ] Initialize pending_action table
  - [ ] Set first player

### Utility Functions
- [ ] `getOtherPlayerIds()`
- [ ] `notifyHandsCount()`
- [ ] `notifyWholeStateForPlayer()`
- [ ] `pushPending()` / `pullPending()` / `clearPending()`
- [ ] `csvToIds()` / `idsToCsv()`
- [ ] `getCardName()` / `isTargetedType()` / `targetZoneForType()`
- [ ] `addToHerdFaceDownAs()`

### Game Data Functions
- [ ] `getAllDatas()`
  - [ ] Return hand counts
  - [ ] Return current player's hand
  - [ ] Return herds (face-up/face-down)
  - [ ] Return discards
  - [ ] Return art map
- [ ] `getArtMap()`

### State Arguments
- [ ] `argAwaitDeclaration()`
- [ ] `argChallengeWindow()`
- [ ] `argChallengerSelectBluffPenalty()`
- [ ] `argAttackerSelectTruthfulPenalty()`
- [ ] `argTargetSelection()`
- [ ] `argInterceptDeclare()`
- [ ] `argInterceptChallengeWindow()`
- [ ] `argGameEnd()`

### Player Actions
- [ ] `actDeclare()` - Play card and declare identity
- [ ] `actChallenge()` / `actPassChallenge()`
- [ ] `actSelectBlindFromActor()` - Bluff penalty selection
- [ ] `actSelectBlindFromChallenger()` - Truth penalty selection
- [ ] `actSelectTargetSlot()` - Target card selection
- [ ] `actDeclareIntercept()` / `actPassIntercept()`
- [ ] `actChallengeIntercept()` / `actPassChallengeIntercept()`

### State Machine Actions
- [ ] `stResolveChallenge()`
  - [ ] Test truthfulness
  - [ ] Apply penalties
  - [ ] Handle transitions
- [ ] `stResolveInterceptChallenge()`
  - [ ] Validate Laser Pointer claim
  - [ ] Apply intercept penalties
- [ ] `stRevealAndResolve()`
  - [ ] Handle Alley Cat effect
  - [ ] Handle Catnip effect
  - [ ] Handle Animal Control effect
  - [ ] Apply ineffective-against-itself rule
- [ ] `stAddPlayedCardToHerd()`
- [ ] `stEndTurn()`
  - [ ] Check end game condition
  - [ ] Move to next player

### Scoring
- [ ] `finalScoring()`
  - [ ] Calculate base card values
  - [ ] Apply Show Cat bonus (7 if has Kitten)
  - [ ] Add hand bonus (+1 per 2 cards)

### Action Bridge (`herdingcats.action.php`)
- [ ] Create action bridge class
- [ ] Map all Ajax actions to game methods

---

## 🖼️ Client-Side Implementation

### HTML/Template
- [ ] Create `herdingcats.view.php`
- [ ] Create `herdingcats_herdingcats.tpl`
  - [ ] Hand area
  - [ ] Control area (prompt + buttons)
  - [ ] Player boards with herd zones
  - [ ] Discard piles

### CSS (`herdingcats.css`)
- [ ] Table layout styles
- [ ] Card styles (72x96px)
- [ ] Hand and herd zone styles
- [ ] Selection/highlight states
- [ ] Responsive layout

### JavaScript (`herdingcats.js`)
- [ ] Constructor and constants
- [ ] `setup()` function
  - [ ] Initialize hand stock
  - [ ] Register card types
  - [ ] Fill initial game state
  - [ ] Connect event handlers

### UI State Management
- [ ] `onEnteringState()` handlers
  - [ ] awaitDeclaration
  - [ ] challengeWindow
  - [ ] challengerSelectBluffPenalty
  - [ ] attackerSelectTruthfulPenalty
  - [ ] targetSelection
  - [ ] interceptDeclare
  - [ ] interceptChallengeWindow
- [ ] `onLeavingState()` cleanup

### UI Helper Functions
- [ ] `cardDiv()` - Create card elements
- [ ] `refreshPlayerAreas()` - Update herds/discards
- [ ] `updateHandCounts()`
- [ ] `setPrompt()` / `clearButtons()` / `addButton()`

### UI State Functions
- [ ] `enableDeclarationUI()` - Card + identity + target selection
- [ ] `enableChallengeUI()` - Challenge/Pass buttons
- [ ] `enableBlindPickFromActor()` - Penalty selection
- [ ] `enableBlindPickFromChallenger()` - Truth penalty
- [ ] `enableTargetSelection()` - Slot picking
- [ ] `enableInterceptDeclare()` - Laser Pointer selection
- [ ] `enableInterceptChallengeUI()` - Intercept challenge

### Notifications
- [ ] Setup notification subscriptions
- [ ] `notif_declared` / `notif_challengeDeclared`
- [ ] `notif_challengeResult` / `notif_challengeResultReveal`
- [ ] `notif_discardPublic`
- [ ] `notif_handCounts`
- [ ] `notif_cardAddedToHerd` / `notif_privateHerdCardIdentity`
- [ ] `notif_stolenToHerd`
- [ ] `notif_reveal` / `notif_flipFaceUp`
- [ ] `notif_ineffective`
- [ ] `notif_scoresComputed`

---

## 🎨 Assets & Resources

### Image Files
- [ ] Place card images in `img/herding_cats_art/`
  - [ ] kitten.jpeg
  - [ ] showcat.jpeg
  - [ ] alleycat.jpeg
  - [ ] catnip.jpeg
  - [ ] animalcontrol.jpeg
  - [ ] laserpointer.jpeg (⚠️ Note: not "lasterpointer")
  - [ ] cardback.jpeg

---

## 🧪 Testing Checklist

### Core Mechanics
- [ ] Basic turn flow (declare → challenge → resolve)
- [ ] All 6 card types playable
- [ ] Targeting mechanics work correctly

### Challenge System
- [ ] Single challenger flow
- [ ] Multiple challengers simultaneously
- [ ] Bluff caught → penalties applied correctly
- [ ] Truthful claim → challenger penalties applied

### Card Effects
- [ ] Alley Cat discards from hand
- [ ] Catnip steals to herd
- [ ] Animal Control removes from herd
- [ ] Kitten/Show Cat/Laser Pointer add to herd

### Special Rules
- [ ] Ineffective-against-itself rule
  - [ ] Alley Cat vs Alley Cat
  - [ ] Catnip vs Catnip
  - [ ] Animal Control vs Animal Control
- [ ] Face-up protection working
- [ ] Laser Pointer interception
  - [ ] From hand
  - [ ] From herd
  - [ ] Intercept challenges

### Scoring
- [ ] Base card values correct
- [ ] Show Cat bonus (7 with Kitten)
- [ ] Hand bonus calculation
- [ ] End game trigger (0 cards in hand)

### Edge Cases
- [ ] Can't target face-up cards
- [ ] Can't challenge own declaration
- [ ] Proper hand count updates after steals
- [ ] Hidden information maintained correctly

---

## 🚀 Deployment

### BGA Studio Setup
- [ ] Create project in BGA Studio
- [ ] Upload all files via SFTP
- [ ] Configure game options
- [ ] Set up player preferences

### Testing on BGA
- [ ×] Create test table
- [ ] Run through full game
- [ ] Test with different player counts (2-6)
- [ ] Verify all notifications work
- [ ] Check scoring calculation

### Final Steps
- [ ] Update game presentation text
- [ ] Add game help/rules
- [ ] Submit for alpha testing
- [ ] Address feedback
- [ ] Submit for beta testing
- [ ] Final polish and release

---

## 📝 Notes & Issues

### Known Issues
- 

### Questions for Design Team
- 

### Performance Optimizations Needed
- 

### Future Enhancements
- Animation improvements
- Sound effects
- Tutorial mode
- AI opponents

---

## 📊 Progress Summary

**Total Items:** ~150  
**Completed:** 0  
**In Progress:** 0  
**Blocked:** 0  

**Estimated Completion:** ____%

---

Last Updated: [Date]  
Updated By: [Name]