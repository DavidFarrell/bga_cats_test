# Herding Cats Art Assets

This directory contains the artwork for the Herding Cats game cards.

## Required Files

- `cardback.jpeg` - Card back design (72x96px recommended)
- `kitten.png` - Kitten card artwork 
- `showcat.png` - Show Cat card artwork
- `alleycat.png` - Alley Cat card artwork  
- `catnip.png` - Catnip card artwork
- `animalcontrol.png` - Animal Control card artwork
- `laserpointer.png` - Laser Pointer card artwork

## Specifications

- **Card Dimensions**: 72x96 pixels (3:4 aspect ratio)
- **File Formats**: PNG preferred for cards, JPEG acceptable for card back
- **Color Modes**: RGB color space
- **Resolution**: 72-150 DPI for web display
- **File Size**: Keep under 500KB per image for optimal loading

## Fallback Colors

The CSS includes fallback background colors for each card type when images are not available:

- Kitten: Light pink (#ffb3ba)
- Show Cat: Peach (#ffdfba)  
- Alley Cat: Light yellow (#ffffba)
- Catnip: Light green (#baffc9)
- Animal Control: Light blue (#bae1ff)
- Laser Pointer: Light purple (#e1baff)
- Card Back: Brown (#8B4513)

## Implementation

Cards are displayed using CSS background-image properties with these files as sprites or individual images. The CSS automatically handles hover states, selection highlighting, and responsive sizing.