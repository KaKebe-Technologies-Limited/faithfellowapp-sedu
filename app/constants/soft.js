// The soft "glass" look of the home screen: a deep wine panel on top, a pale blush surface below, frosted tiles with a gentle
// shadow. Every colour is a tint of the FaithFellows brand (Viva Magenta #BC2649), so it matches the rest of the app.
import { COLORS } from './theme';

export const SOFT = {
  surface: '#F6F0F3', // the page behind the tiles: a blush grey
  panel: ['#1E0710', '#4A0F29', '#7D1A39'], // the dark top panel, from deep wine to the brand's dark red
  glass: 'rgba(255,255,255,0.92)', // a frosted tile
  glassBorder: '#FFFFFF',
  glassOnDark: 'rgba(255,255,255,0.14)', // a frosted button on the dark panel
  glassOnDarkBorder: 'rgba(255,255,255,0.28)',
  shadow: '#A67C8E', // a rose-grey shadow: warmer and softer than black
  ink: COLORS.black,
  muted: '#8B7A83',
};

/** The shadow of a frosted tile. */
export const SOFT_SHADOW = {
  shadowColor: SOFT.shadow,
  shadowOpacity: 0.28,
  shadowRadius: 14,
  shadowOffset: { width: 0, height: 8 },
  elevation: 4,
};
