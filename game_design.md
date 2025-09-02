Digital Game Spec - working title: Herding Cats

A compact, bluff-driven card game for 2-6 players where everyone starts with the same 9-card micro-deck, draws 7, and tries to build the highest-scoring herd.

Below is a developer-ready rules and logic spec. It focuses on state, flows, and edge cases so a digital version can be implemented without guessing.

⸻

1) Components and setup
	•	Players: 2 to 6.
	•	Per-player deck composition (9 total):
	•	3 × Kitten (value 2)
	•	1 × Show Cat (value 5, or 7 if you have at least one Kitten in your herd at scoring)
	•	2 × Alley Cat (value 1)
	•	1 × Catnip (value 1)
	•	1 × Animal Control (value 0)
	•	1 × Laser Pointer (value 0)
	•	Setup per player:
	•	Shuffle your 9-card personal deck.
	•	Draw 7 to your hand. The remaining 2 are removed from the game face-down and remain unknown to everyone.
	•	Create your herd area, initially empty.
	•	Create your discard pile area, initially empty and public.
	•	Turn order: choose a starting player, proceed clockwise.

⸻

2) Zones and visibility
	•	Hand: hidden from others.
	•	Herd: a mix of face-down cards and face-up cards. Face-up herd cards are protected and cannot be targeted by opponents. Face-down herd cards can be targeted.
	•	Discard pile: public, face-up, per player. Anyone can inspect any discard pile at any time.
	•	Removed-from-game two cards per player remain unknown.

⸻

3) Core turn flow

On your turn:
	1.	Play a card face-down from your hand and declare it as any one of the six card types. The physical card you played can be any card. The declared identity is what matters for resolution and effects.
	2.	If the declared card targets an opponent, you must name exactly one opponent now:
	•	Targeted-hand effects: Alley Cat, Catnip.
	•	Targeted-herd effects: Animal Control.
	•	Non-targeting effects: Kitten, Laser Pointer, Show Cat.
	3.	Challenge window:
•	After the declaration (and target selection if any), non-active players may declare a challenge to your claim. The first player to click Challenge becomes the sole challenger for this declaration.
•	If a player challenges, proceed to Challenge resolution (section 4).
	•	If no challenge, proceed to Effect resolution (section 5).
	4.	End of turn: after all effects and discards are resolved, your turn ends. There is no drawing in this game.

⸻

4) Challenge resolution
	•	A challenge tests the truth of the declared identity of the played face-down card.

If the challenger is correct (you bluffed):
	•	Reveal the played card to all players.
	•	You discard the revealed played card to your discard pile.
	•	You also lose one additional card from your hand:
	•	The challenger selects a hidden slot from your hand without seeing identities. Reveal and discard that card to your discard pile.
	•	Your card’s effect does not happen.
	•	Your turn ends immediately.

If the challenger is wrong (you told the truth):
	•	Reveal the played card to all players only if the engine needs proof. In the digital version you can reveal to all or run a truth flag without spoiling order, but the result must be unambiguous.
	•	The challenger suffers a penalty:
	•	You (the truthful player) select a hidden slot from that challenger’s hand. Reveal and discard that card to their discard pile.
	•	Your card’s effect proceeds as if unchallenged.
	•	After resolving the effect, the played card is added to your herd face-down unless the card’s rules say otherwise.

Challenger selection (single-challenger model):
	•	Challenges are first-come, first-served. As soon as one player clicks Challenge, the game enters the challenge state and no additional challengers may be added for that declaration.

⸻

5) Effect resolution and “ineffective-against-itself” rule

If the declaration stands (no challenge or failed challenge), resolve the effect. Then, unless said otherwise, the played card enters your herd face-down as the declared identity.

5.1 Ineffective-against-itself rule

For all targeted effects, if the declared attacking identity matches the identity of the specific card selected on the defender’s side, the attack is ineffective against that target:
	•	The selected defender card is not removed or stolen.
		•	If it came from the herd, it is revealed face-up and remains in place. Face-up cards are protected in future.
		•	If it came from the hand, reveal the selected card, return it to the defender’s hand.
		•	In either case, the attacker’s played card is considered to have been thwarted and is discarded face-up.

This rule covers:
	•	Alley Cat vs Alley Cat (hand)
		•	Catnip vs Catnip (hand)
	•	Animal Control vs Animal Control (herd)

5.2 Laser Pointer interception

Laser Pointer can intercept attacks that target you:
	•	If your herd is targeted (Animal Control), you may discard a Laser Pointer from your herd instead. This cancels the attack’s removal. The selected herd card stays hidden and untouched. Discarded Laser Pointer goes face-up to your discard pile.
	•	If your hand is targeted (Alley Cat or Catnip), you may discard a Laser Pointer from your hand instead. The attacker does not see or take any card from your hand.

Bluffing a Laser Pointer: claiming to intercept with Laser Pointer when you do not have one is challengeable like any other claim.
	•	If you are challenged and you do have Laser Pointer in the stated zone, the challenger discards a blind card, and your interception stands.
	•	If you are challenged and you lied, you must discard the card you falsely presented as Laser Pointer, discard an additional blind card chosen by the challenger, and the original attack resumes as if no interception was attempted.

Timing:
	•	For hand-targeting effects, the attacker selects a hidden slot. Before the selected card is revealed, the defender gets a window to declare a Laser Pointer interception.
	•	For herd-targeting effects, before the selected herd card is revealed, the defender may declare a Laser Pointer interception.

Result for attacker’s played card:
	•	If an attack is neutralised by a successful Laser Pointer interception (whether the LP claim was truthful or an unchallenged/failed-challenge bluff), the attacker’s played card is discarded face-up. The action was thwarted; no herd placement occurs.

⸻

6) Card reference and effects

All cards are played by declaration. The physical card used becomes the declared identity when added to herd.
	1.	Alley Cat
	•	Value in herd: 1.
	•	Target: choose one opponent’s hand.
	•	Effect: choose a hidden slot from that hand. Reveal it.
	•	If it is Alley Cat: ineffective. Return the revealed card to hand. Your played Alley Cat is discarded face-up.
	•	Otherwise, defender discards the revealed card, and you place your Alley Cat to your herd.
	2.	Animal Control
	•	Value in herd: 0.
	•	Target: choose one opponent’s herd. You must select a face-down herd card.
	•	Effect: reveal the selected herd card.
	•	If it is Animal Control: ineffective. The revealed card stays face-up in their herd and is protected. Your played Animal Control is discarded face-up.
	•	Otherwise, discard that card from their herd. You place your Animal Control to your herd.
	3.	Catnip
	•	Value in herd: 1.
	•	Target: choose one opponent’s hand.
	•	Effect: choose a hidden slot from that hand. Reveal it.
	•	If it is Catnip: ineffective. Defender keeps it. Your played Catnip is discarded face-up.
	•	Otherwise, move the revealed card face-down into your herd. Only you may see its identity in the UI. You also place your Catnip to your herd.
	4.	Kitten
	•	Value in herd: 2.
	•	Target: none.
	•	Effect: on a successful play, place to your herd face-down.
	5.	Laser Pointer
	•	Value in herd: 0.
	•	Target: none on play.
	•	Effect on play: place to your herd face-down.
	•	Special: may be discarded from your herd to intercept herd-targeting effects against you, or from your hand to intercept hand-targeting effects against you, as per section 5.2. When a Laser Pointer interception stands (truthful or bluff that is not overturned), the attacker’s played card is discarded face-up.
	6.	Show Cat
	•	Value in herd: 5, or 7 if you have at least one Kitten in your herd at scoring.
	•	Target: none.
	•	Effect: on a successful play, place to your herd face-down.

⸻

7) General rules and edge cases
	•	Cards ineffective against themselves: covered in section 5.1. Applies only to the specific selected target card for that effect.
	•	Face-up herd cards are protected: they cannot be selected by Animal Control or Catnip. They are still counted for scoring. You may still voluntarily discard a Laser Pointer from your herd to intercept, even if that Laser Pointer is face-up.
	•	Selecting hidden cards (hand or face-down herd):
	•	The chooser selects a hidden slot. The engine reveals it as required by the effect.
•	If a Laser Pointer interception is declared and stands, the selected card is not revealed.
•	If a Laser Pointer interception stands (truthful or bluff not overturned), the attacker’s played card is discarded face-up instead of entering the herd.
	•	Played card identity:
	•	If the play succeeds past challenges, the played card’s identity becomes the declared identity when added to the herd.
	•	If ineffective-against-itself triggers (section 5.1), the attacker’s played card is considered thwarted and is discarded face-up instead of entering the herd.
	•	If the play fails due to a successful challenge, the played card is revealed and discarded as its true identity.
	•	No draws: hands only shrink. Stealing with Catnip is the only way to gain cards beyond your own plays.
	•	Public information:
	•	Discard piles are public and inspectable.
	•	Two removed-from-game cards per player remain unknown for the whole game.

⸻

8) End of game and scoring
	•	End trigger: at the end of any player’s turn, if any player has zero cards in hand, the game ends immediately and you score.
	•	Herd scoring:
	•	Sum card values in each player’s herd:
	•	Kitten 2
	•	Show Cat 5, or 7 if that player has at least one Kitten in their herd
	•	Alley Cat 1
	•	Catnip 1
	•	Animal Control 0
	•	Laser Pointer 0
	•	Hand bonus:
	•	For each player who still has cards in hand, add 1 point per 2 cards in hand, rounded up.
	•	Examples: 1 card = +1, 2 cards = +1, 3 cards = +2, 4 cards = +2, etc.
	•	Most points wins. Ties are unresolved by default. You can add tie-breakers if desired (for example, most face-down herd cards, or most total herd cards).

⸻

9) Timing windows and interaction order

For targeted effects (Alley Cat, Catnip, Animal Control):
	1.	Attacker declares identity and target.
	2.	Challenge window.
	3.	If no challenge or attacker was truthful:
	•	Attacker selects the hidden slot to target.
	•	Defender’s intercept window:
	•	If hand was targeted, defender may declare Laser Pointer from hand.
	•	If herd was targeted, defender may declare Laser Pointer from herd.
	•	Laser Pointer declaration itself can be challenged. Resolve that challenge before the attack proceeds.
	•	If no interception or interception failed:
	•	Reveal the selected card and check ineffective-against-itself.
	•	Apply effect accordingly.
		•	Attacker’s played card goes to herd face-down, unless (a) ineffective-against-itself triggered, or (b) a Laser Pointer interception stands; in those cases, discard it face-up.

For non-targeting effects (Kitten, Laser Pointer, Show Cat):
	1.	Attacker declares identity.
	2.	Challenge window.
	3.	If the play stands, place the card to herd face-down.

⸻

10) Minimal data model
	•	Game
	•	players: list of Player
	•	turn_index: int
	•	phase: Enum { AwaitDeclaration, ChallengeWindow, ResolveChallenge, TargetSelection, InterceptWindow, RevealAndResolve, EndTurn, Scoring }
	•	Player
	•	hand: ordered list of Card
	•	herd_face_down: list of Card
	•	herd_face_up: list of Card
	•	discard: list of Card
	•	removed_from_game: list of Card (size 2)
	•	Card
	•	id: unique identifier
	•	base_type: Enum { Kitten, ShowCat, AlleyCat, Catnip, AnimalControl, LaserPointer }
	•	current_identity: same enum
	•	owner_id: Player reference
	•	zone: Enum { Hand, HerdFaceDown, HerdFaceUp, Discard, Removed }
	•	PendingAction
	•	actor_id
	•	declared_identity
	•	target_player_id (nullable)
	•	target_zone: Enum { Hand, Herd }
	•	selected_slot_index (nullable)
	•	challenged_by: set
	•	intercept_declared_by_defender: bool
	•	intercept_zone: Enum { Hand, Herd } (nullable)
	•	intercept_challenged_by: set

⸻

11) UI behaviour guidelines
	•	Hidden slot selection should preserve hand order. Show back-of-card placeholders in a fixed order so the chooser selects a position, not a random card.
	•	When ineffective-against-itself triggers against a hand card, show the reveal and return animation so players understand why nothing was lost.
	•	When Laser Pointer intercepts, show a clear cancel animation and move Laser Pointer to discard. Do not reveal the originally selected target card.
	•	Provide a challenge prompt with a short timer or require all players to click Pass or Challenge to progress, depending on your desired pacing.
	•	Always log a compact event history: declarations, challenges, results, selected slots, reveals, interceptions, discards, and cards added to herd.

⸻

12) Worked micro-examples

A) Alley Cat vs defender’s Alley Cat in hand
	•	Alice declares Alley Cat on Bob. No challenge.
	•	Alice selects slot 2 from Bob’s hand. Reveal shows Alley Cat.
	•	Ineffective. Bob keeps that Alley Cat (return to hand). Alice’s played card is discarded face-up (thwarted).

B) Animal Control vs defender’s Animal Control in herd
	•	Alice declares Animal Control on Bob. Carol challenges. Reveal shows Alice truly played Animal Control. Carol discards a blind card.
		•	Alice selects a face-down herd card. Reveal shows Animal Control. Ineffective. That card flips face-up and stays protected. Alice’s played Animal Control is discarded face-up (thwarted).


C) Catnip intercepted by Laser Pointer in hand
	•	Alice declares Catnip on Bob. No challenge.
	•	Alice selects slot 4 from Bob’s hand.
	•	Bob declares Laser Pointer from hand. Dave challenges Bob’s claim. Bob reveals a Laser Pointer from hand and discards it. Dave discards a blind card.
		•	Catnip is neutralised. Alice’s played Catnip is discarded face-up (no herd placement).

D) Animal Control intercepted by Laser Pointer in herd
		•	Alice declares Animal Control on Bob. No challenge.
		•	Alice selects a face-down herd card.
		•	Bob discards a Laser Pointer from his herd to intercept. No reveal of the selected herd card.
		•	The attack is neutralised. Alice’s played Animal Control is discarded face-up (no herd placement).

⸻

13) Scoring examples
	•	Player X herd: Show Cat + 1 Kitten + 1 Alley Cat + 1 Catnip + 1 Animal Control = 7 + 2 + 1 + 1 + 0 = 11.
Hand: 3 cards left = +2. Total = 13.
	•	Player Y herd: Show Cat + 0 Kittens + 2 Kittens stolen by others, 2 Alley Cats = 5 + 1 + 1 = 7.
Hand: 0 cards = +0. Total = 7.

⸻

14) Assumptions made explicit
	•	If an attack is neutralised by a successful Laser Pointer interception, the attacker’s played card is discarded face-up (no herd placement).
	•	Challenges are first-come, first-served; only the first challenger participates in resolution.
	•	Face-up herd cards are protected from being selected by Animal Control or Catnip, but a face-up Laser Pointer in your herd may still be voluntarily discarded to intercept.
	•	End condition is checked at the end of each turn. If any player has zero cards in hand at that moment, the game ends and you score.

If you want any of these toggled, say which rule to flip and I will adjust the spec cleanly.
