# Design Addendum v1.2 - Laser Pointer intercept buff

The earlier draft suggested a draw-after-intercept buff. That conflicts with the core rule "there is no drawing in this game".
The implemented and tested buff is:

**Buff - keep tempo on a truthful intercept**  
If your Laser Pointer intercept is **truthful** (that is, it survives challenges), instead of discarding the Laser Pointer you used:
- Place it face-down into **your herd** as if you had successfully played a Laser Pointer this turn.

Notes:
- This keeps tempo without introducing draws.
- Value impact is neutral (Laser is worth 0) but it preserves card economy for the defender.
- This is fully encoded in the rules helper and server logic. Set `HCRules::$BUFF_LASER_TO_HERD = false` to revert to "discard on intercept".