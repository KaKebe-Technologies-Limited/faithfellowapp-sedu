// FaithFellows brand tokens for the mobile app: colours, fonts, spacing, radii and shadow.
// Mirrors the web design system (Viva Magenta #BC2649, Clarity City) and its mobile look:
// a soft light canvas with white rounded cards and pink accents.
//
// Three looks share the same names: "light" (the brand look), "dark" and "girls" (a soft rose look with rounder corners and a serif heading font).
// The member's choice is kept on the phone and read here, once, when the app opens, so every screen's styles are built with the right colours; changing
// the look saves it and restarts the app's JavaScript (see lib/theme.js and Me → Appearance).
import { Appearance, Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

export const THEME_KEY = 'ff_theme';
export const THEMES = ['light', 'dark', 'girls'];

function storedTheme() {
  try {
    const raw = Platform.OS === 'web' ? globalThis.localStorage?.getItem(THEME_KEY) : SecureStore.getItem(THEME_KEY);
    const name = String(raw ?? '').replace(/"/g, '');

    return THEMES.includes(name) ? name : 'light';
  } catch {
    return 'light';
  }
}

/** 'light' | 'dark' | 'girls': the look this run of the app was started with. */
export const THEME = storedTheme();
export const IS_DARK = THEME === 'dark';
export const IS_GIRLS = THEME === 'girls';

// Names that mean the same in every look. `white` is the surface of a card (so it turns dark in the dark look), `onColor` is the white of text and icons
// that sit on a coloured button or photo (always white), `black` is the text colour, `primaryInk` the wine-coloured text on a pale background.
const LIGHT = {
  primary: '#BC2649',
  primaryDark: '#8E1C37',
  primaryInk: '#8E1C37',
  primaryLight: '#D34268',
  primarySoft: '#F27EA8',
  primaryPale: '#F7B2D1',
  primaryMist: '#FFE9F5',
  black: '#1A1A1A',
  gray900: '#575757',
  gray700: '#727272',
  gray500: '#A5A5A5',
  gray300: '#CACACA',
  gray100: '#F1F1F1',
  white: '#FFFFFF',
  onColor: '#FFFFFF',
  success: '#1E9E5A',
  warning: '#E8A317',
  danger: '#D63031',
  info: '#2D7FF9',
  // Screen background behind the white cards.
  canvas: '#FAFAFA',
  // The pink devotional card and the pale red of urgent notices.
  pink: '#FFE3EF',
  dangerMist: '#FDECEC',
  // Pale notes: a chosen card, a done step, a waiting item, a gentle warning.
  selected: '#FFF7FB',
  successMist: '#EAF7EF',
  warningMist: '#FFF3E0',
  warningInk: '#C25B00',
  noteMist: '#FFF8E6',
  infoMist: '#F2F7FF',
  // The text of a paragraph (a little softer than `black`), the grey of an empty track, the red of an urgent notice's words and the gold card of the day's devotional.
  ink: '#2B2B2B',
  track: '#ECE4E8',
  dangerInk: '#7A1B1B',
  goldFrom: '#FFF6E0',
  goldTo: '#FFE3A8',
  goldInk: '#6B4309',
  goldInk2: '#8C6422',
};

const DARK = {
  ...LIGHT,
  primary: '#DC4166',
  primaryDark: '#A8284B',
  primaryInk: '#FF93AC',
  primaryLight: '#EB6A89',
  primaryMist: '#3A1B27',
  black: '#F3EEF1',
  gray900: '#CFC7CB',
  gray700: '#ABA2A7',
  gray500: '#7E757A',
  gray300: '#43393F',
  gray100: '#2A2429',
  white: '#1D1A1F',
  success: '#34C77B',
  warning: '#F2B01E',
  danger: '#FF6B6B',
  info: '#6AA6FF',
  canvas: '#121014',
  pink: '#3A1B29',
  dangerMist: '#3D1A1C',
  selected: '#2C1A23',
  successMist: '#16301F',
  warningMist: '#3A2A12',
  warningInk: '#F2B01E',
  noteMist: '#332B14',
  infoMist: '#16233A',
  ink: '#E6E0E4',
  track: '#35292F',
  dangerInk: '#FFB4B4',
  goldFrom: '#2E2512',
  goldTo: '#4A3A14',
  goldInk: '#FFD98A',
  goldInk2: '#E8C27A',
};

const GIRLS = {
  ...LIGHT,
  primary: '#D93F7E',
  primaryDark: '#A82A5E',
  primaryInk: '#A82A5E',
  primaryLight: '#EE6FA1',
  primarySoft: '#F59CC0',
  primaryPale: '#FAC7DC',
  primaryMist: '#FFE6F1',
  black: '#3A1A2B',
  gray900: '#654356',
  gray700: '#86687A',
  gray500: '#B499A8',
  gray300: '#EACFDD',
  gray100: '#FBEAF3',
  canvas: '#FFF4F9',
  pink: '#FFDCEB',
  dangerMist: '#FFE4E8',
  selected: '#FFEFF6',
  successMist: '#E8F7F0',
  warningMist: '#FFF0E1',
  ink: '#3F2433',
  track: '#F5DDE9',
  goldFrom: '#FFF1E0',
  goldTo: '#FFD9B8',
};

const PALETTES = { light: LIGHT, dark: DARK, girls: GIRLS };

export const COLORS = PALETTES[THEME];

// The native side (keyboard, alerts, scroll bars) follows the look too, where the app's build lets it.
try {
  Appearance.setColorScheme?.(IS_DARK ? 'dark' : 'light');
} catch {
  // Not available on this build: only our own screens change.
}

export const FONTS = {
  regular: 'ClarityCity_400Regular',
  medium: 'ClarityCity_500Medium',
  semibold: 'ClarityCity_600SemiBold',
  bold: 'ClarityCity_700Bold',
  // The headings: the same bold typeface, and a serif italic in the girls look.
  display: IS_GIRLS ? 'PlayfairDisplay_700Bold_Italic' : 'ClarityCity_700Bold',
  displayRegular: IS_GIRLS ? 'PlayfairDisplay_500Medium_Italic' : 'ClarityCity_500Medium',
};

// A phone set to a large font (many Samsung phones are) still grows our words, but never past this multiple of their size,
// so nothing looks oversized or gets cut off. Bible and hymn text have their own A- / A+ buttons.
export const MAX_FONT_SCALE = 1.1;

export const SPACING = { xs: 4, sm: 8, md: 16, lg: 24, xl: 32, xxl: 48 };

export const RADIUS = IS_GIRLS ? { sm: 8, md: 14, lg: 20, xl: 28, full: 9999 } : { sm: 6, md: 10, lg: 16, xl: 24, full: 9999 };

export const SHADOW = {
  shadowColor: IS_DARK ? '#000000' : IS_GIRLS ? '#7A1F4A' : '#2A0A14',
  shadowOpacity: IS_DARK ? 0.35 : IS_GIRLS ? 0.1 : 0.07,
  shadowRadius: 14,
  shadowOffset: { width: 0, height: 5 },
  elevation: 3,
};
