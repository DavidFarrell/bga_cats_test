# Herding Cats Game Project - Initial Briefing

Please help me work on the Herding Cats game project. Start by reading the following documents in this specific order to understand the project:

1. **Game Design Document**: `/workspace/projects/bga_cats_test/game_design.md`
   - This contains the core game concept, rules, and mechanics

2. **Game Implementation Plan**: `/workspace/projects/bga_cats_test/game_implementation_plan.md`
   - This outlines the technical approach and development roadmap

3. **Implementation Progress**: `/workspace/projects/bga_cats_test/implementation_progress.md`
   - This tracks what has been completed so far and what remains to be done

4. **API Documentation** (read both files):
   - `/workspace/projects/bga_cats_test/api_docs/bga_documentation.md`
   - `/workspace/projects/bga_cats_test/api_docs/bga_guide.md`
   - These contain Board Game Arena platform-specific documentation and guidelines

**Art Assets Available**: The `/workspace/projects/bga_cats_test/herding_cats_art/` folder contains the following game artwork files:
- `alleycat.jpeg` - Alley cat card artwork
- `animalcontrol.jpeg` - Animal control card artwork
- `cardback.jpeg` - Card back design
- `catnip.jpeg` - Catnip card artwork
- `imposter.jpeg` - Imposter card artwork
- `kitten.jpeg` - Kitten card artwork
- `laserpointer.jpeg` - Laser pointer card artwork
- `showcat.jpeg` - Show cat card artwork
- `Thumbs.db` - Windows thumbnail cache file (can be ignored)

After reading all these documents, please provide a summary of your understanding of the project and ask me what specific aspect of the game development I'd like you to help with next.

## Development Workflow - IMPORTANT

### How We Work with BGA Files

We use a **deploy script workflow** to maintain version control while developing for BGA:

1. **BGA files mount at:** `~/BGA_mount` (OUTSIDE the git repository)
2. **We work in:** `src/` directory (INSIDE the git repository, fully version controlled)
3. **To deploy:** Run `./deploy.sh` to sync `src/` → `~/BGA_mount`

### File Structure
```
/Users/david/git/ai-sandbox/projects/bga_cats_test/
├── src/                    # ← WORK HERE (version controlled)
│   ├── herdingcats.js
│   ├── herdingcats.css
│   ├── dbmodel.sql
│   ├── modules/
│   └── img/
├── mount_bga.sh           # Mounts BGA to ~/BGA_mount
├── unmount_bga.sh         # Unmounts BGA
├── pull.sh                # Syncs BGA → src/ (initial import)
├── deploy.sh              # Syncs src/ → BGA (for testing)
└── sync_status.sh         # Shows differences

~/BGA_mount/               # ← BGA MOUNT POINT (outside repo)
└── [BGA game files]       # Don't edit directly!
```

### Key Rules for Claude

1. **ALWAYS edit files in the `src/` directory**, never in `~/BGA_mount`
2. **The `src/` directory is version controlled** - all changes are tracked in git
3. **To test changes on BGA**, we run `./deploy.sh` to sync to the mount
4. **The mount at `~/BGA_mount` is temporary** - it's only active when mounted
5. **Initial setup**: Use `./pull.sh` to import existing BGA files to `src/`

### Workflow Commands
- `./mount_bga.sh` - Mount BGA files (required before pull/deploy)
- `./pull.sh` - Import from BGA to src/ (one-time setup)
- `./deploy.sh` - Deploy src/ to BGA for testing
- `./deploy.sh --watch` - Auto-deploy on file changes
- `./sync_status.sh` - Check what's different
- `./unmount_bga.sh` - Unmount when done

This workflow ensures all game code stays in version control while still being able to test on BGA Studio.