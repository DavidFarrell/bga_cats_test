# Herding Cats - Board Game Arena Implementation

A bluff-driven card game for 2-6 players where everyone starts with the same 9-card micro-deck and tries to build the highest-scoring herd while dodging Animal Control and flashy Laser Pointers.

## 🎮 Game Overview

**Platform:** Board Game Arena  
**Players:** 2-6  
**Duration:** ~15 minutes  
**Status:** 🚧 In Development - Design Complete, Implementation Starting  
**Mechanics:** Bluffing, Hand Management, Card Play  

### Quick Description
Players declare cards face-down and can lie about their identity. Opponents may challenge claims, with penalties for wrong guesses. Build your herd of cats while using special cards to disrupt opponents or protect yourself. The game ends when any player runs out of cards.

## 📚 Documentation

This repository contains comprehensive documentation for implementing Herding Cats on BGA:

- **[`game_design.md`](game_design.md)** - Complete rules specification with edge cases
- **[`game_implementation_plan.md`](game_implementation_plan.md)** - Full BGA implementation with code scaffolds
- **[`implementation_progress.md`](implementation_progress.md)** - Development checklist (~150 items)
- **[`api_docs/`](api_docs/)** - BGA platform documentation and development guides

## 🎯 Key Game Features

### Card Types (9 cards per player)
- **Kitten (3x)** - 2 points, no effect
- **Show Cat (1x)** - 5 points (7 if you have a Kitten)  
- **Alley Cat (2x)** - 1 point, forces discard from hand
- **Catnip (1x)** - 1 point, steals from hand
- **Animal Control (1x)** - 0 points, removes from herd
- **Laser Pointer (1x)** - 0 points, can intercept attacks

### Core Mechanics
- **Bluffing System** - Declare any card identity when playing face-down
- **Challenge Resolution** - Risk/reward for calling out bluffs
- **Ineffective-Against-Itself** - Cards can't affect matching types
- **Face-Up Protection** - Revealed cards become protected
- **Laser Pointer Interception** - Cancel attacks with nested challenge system

## 🛠️ Development Setup

### Prerequisites
1. BGA Studio developer account
2. SFTP client for file deployment
3. Basic knowledge of PHP, JavaScript, HTML/CSS
4. Familiarity with BGA framework

### Project Structure
```
bga_cats_test/
├── game_design.md              # Complete game rules
├── game_implementation_plan.md # Technical implementation
├── implementation_progress.md  # Development checklist
├── README.md                   # This file
├── api_docs/                   # BGA documentation
│   ├── bga_documentation.md
│   └── bga_guide.md
└── herding_cats_art/          # Game artwork
    ├── kitten.jpeg
    ├── showcat.jpeg
    ├── alleycat.jpeg
    ├── catnip.jpeg
    ├── animalcontrol.jpeg
    ├── laserpointer.jpeg      # Note: needs rename from "lasterpointer"
    └── cardback.jpeg
```

### Getting Started
1. Review the game design document for complete rules
2. Check the implementation plan for code structure
3. Use the progress tracker to work through implementation
4. Deploy files to BGA Studio following the plan

## 📊 Implementation Status

**Current Phase:** Initial Development  
**Progress:** 0/~150 tasks complete

See [`implementation_progress.md`](implementation_progress.md) for detailed checklist.

### Next Steps
1. Create BGA Studio project
2. Set up base game files (gameinfos.inc.php, etc.)
3. Implement database structure
4. Build server-side game logic
5. Create client-side UI

## 🎨 Assets

### Artwork Requirements
- Card images: 300x420px recommended (currently using various sizes)
- Located in `/herding_cats_art/`
- **Important:** Rename `lasterpointer.jpeg` to `laserpointer.jpeg` for code compatibility

### Image Files Needed
- ✅ Card fronts (6 types)
- ✅ Card back
- ⚠️ Needs renaming: lasterpointer → laserpointer

## 🏗️ Technical Architecture

### Server-Side (PHP)
- **Game.php** - Core game logic and rules engine
- **States Machine** - 13 states covering all game phases
- **Database** - Cards table + pending actions table

### Client-Side (JavaScript)
- **Stock Component** - Hand management
- **Zone Management** - Herds, discards, face-up/face-down
- **Notification System** - Real-time game updates

### Key Implementation Details
- Cards store current identity + base type for truth checking
- Pending action table tracks complex multi-step resolutions
- Face-down cards visible only to owner

## 🧪 Testing

### Key Test Scenarios
1. **Bluff Detection** - Truth vs lie with correct penalties
2. **Multi-Challenger** - Multiple simultaneous challenges
3. **Ineffective Rule** - Same card types cancel effects
4. **Laser Pointer** - Interception with nested challenges
5. **Scoring** - Show Cat bonus, hand bonus calculation
6. **Edge Cases** - Face-up protection, end game trigger

### Testing on BGA Studio
1. Create test table with varying player counts
2. Run through complete games
3. Test all card interactions
4. Verify scoring calculations
5. Check notification flow

## 🤝 Contributing

### Development Process
1. Check [`implementation_progress.md`](implementation_progress.md) for tasks
2. Follow BGA coding standards
3. Test thoroughly on BGA Studio
4. Update progress tracker when completing tasks
5. Document any issues or questions

### Code Style
- PHP: Follow BGA framework conventions
- JavaScript: Use provided Dojo framework
- CSS: Keep selectors specific to avoid conflicts
- Comments: Minimal, code should be self-documenting

## 📚 Resources

- [BGA Studio Documentation](https://en.doc.boardgamearena.com/)
- [BGA Developer Forums](https://forum.boardgamearena.com/)
- [GitHub Repository](https://github.com/DavidFarrell/bga_cats_test)

## 📜 License & Credits

### Game Design
- **Designer:** GOSH Digital
- **Rules Specification:** David Farrell

### Artwork
- **Artist:** GOSH Digital
- **Card Images:** Located in `/herding_cats_art/`

### Development
- **BGA Implementation:** In Progress
- **Repository:** https://github.com/DavidFarrell/bga_cats_test

## 🐛 Known Issues

- Image file needs renaming: `lasterpointer.jpeg` → `laserpointer.jpeg`
- Implementation not yet started on BGA Studio

## 📞 Contact

For questions about this implementation:
- Create an issue on [GitHub](https://github.com/DavidFarrell/bga_cats_test/issues)
- Contact via BGA Studio developer forums

---

*Last Updated: January 2025*