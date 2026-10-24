// The soft "glass" look of the home screen: a deep wine panel on top, a pale blush surface below, frosted tiles with a gentle
// shadow. Every colour is a tint of the FaithFellows brand (Viva Magenta #BC2649), so it matches the rest of the app. The dark look has a
// dark surface and smoked tiles; the girls look a rose panel and a pink surface.
import { COLORS, IS_DARK, IS_GIRLS } from './theme';

const LOOK = IS_DARK
  ? {
      surface: '#161215',
      panel: ['#1E0710', '#4A0F29', '#7D1A39'],
      glass: 'rgba(36,30,36,0.94)',
      glassBorder: '#3A2F37',
      shadow: '#000000',
      muted: '#A99BA2',
    }
  : IS_GIRLS
    ? {
        surface: '#FFF0F6',
        panel: ['#4B1239', '#A02D6C', '#E0488A'],
        glass: 'rgba(255,255,255,0.94)',
        glassBorder: '#FFFFFF',
        shadow: '#C77AA3',
        muted: '#9A7488',
      }
    : {
        surface: '#F6F0F3', // the page behind the tiles: a blush grey
        panel: ['#1E0710', '#4A0F29', '#7D1A39'], // the dark top panel, from deep wine to the brand's dark red
        glass: 'rgba(255,255,255,0.92)', // a frosted tile
        glassBorder: '#FFFFFF',
        shadow: '#A67C8E', // a rose-grey shadow: warmer and softer than black
        muted: '#8B7A83',
      };

export const SOFT = {
  ...LOOK,
  glassOnDark: 'rgba(255,255,255,0.14)', // a frosted button on the dark panel
  glassOnDarkBorder: 'rgba(255,255,255,0.28)',
  ink: COLORS.black,
};

/** The shadow of a frosted tile. */
export const SOFT_SHADOW = {
  shadowColor: SOFT.shadow,
  shadowOpacity: IS_DARK ? 0.5 : 0.28,
  shadowRadius: 14,
  shadowOffset: { width: 0, height: 8 },
  elevation: 4,
};
