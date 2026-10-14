// FaithFellows brand tokens for the mobile app: colours, fonts, spacing, radii and shadow.
// Mirrors the web design system (Viva Magenta #BC2649, Clarity City) and its mobile look:
// a soft light canvas with white rounded cards and pink accents.

export const COLORS = {
  primary: '#BC2649',
  primaryDark: '#8E1C37',
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
  success: '#1E9E5A',
  warning: '#E8A317',
  danger: '#D63031',
  info: '#2D7FF9',
  // Screen background behind the white cards.
  canvas: '#FAFAFA',
  // The pink devotional card and the pale red of urgent notices.
  pink: '#FFE3EF',
  dangerMist: '#FDECEC',
};

export const FONTS = {
  regular: 'ClarityCity_400Regular',
  medium: 'ClarityCity_500Medium',
  semibold: 'ClarityCity_600SemiBold',
  bold: 'ClarityCity_700Bold',
};

// A phone set to a large font (many Samsung phones are) still grows our words, but never past this multiple of their size,
// so nothing looks oversized or gets cut off. Bible and hymn text have their own A- / A+ buttons.
export const MAX_FONT_SCALE = 1.1;

export const SPACING = { xs: 4, sm: 8, md: 16, lg: 24, xl: 32, xxl: 48 };

export const RADIUS = { sm: 6, md: 10, lg: 16, xl: 24, full: 9999 };

export const SHADOW = {
  shadowColor: '#2A0A14',
  shadowOpacity: 0.07,
  shadowRadius: 14,
  shadowOffset: { width: 0, height: 5 },
  elevation: 3,
};
