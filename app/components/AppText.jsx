// Text with the FaithFellows typography (Clarity City) applied through simple variants.
// A screen can pass a bigger fontSize (an amount, initials in a circle). The variant's own line height would then be
// too short for the letters and iPhones cut them off, so the line height grows with the text unless one is given.
import { StyleSheet, Text } from 'react-native';
import { COLORS, FONTS, MAX_FONT_SCALE } from '../constants/theme';

const VARIANTS = {
  title: { fontFamily: FONTS.bold, fontSize: 26, lineHeight: 32, color: COLORS.black },
  heading: { fontFamily: FONTS.semibold, fontSize: 18, lineHeight: 24, color: COLORS.black },
  body: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.gray900 },
  label: { fontFamily: FONTS.medium, fontSize: 13, lineHeight: 18, color: COLORS.black },
  caption: { fontFamily: FONTS.regular, fontSize: 12, lineHeight: 16, color: COLORS.gray700 },
};

export default function AppText({ variant = 'body', style, children, ...rest }) {
  const base = VARIANTS[variant] ?? VARIANTS.body;
  const own = StyleSheet.flatten(style) ?? {};
  const grown = own.fontSize && own.lineHeight === undefined && base.lineHeight < own.fontSize * 1.25 ? { lineHeight: Math.ceil(own.fontSize * 1.3) } : null;

  return (
    // A phone set to large text still grows the words a little, but not so much that amounts and buttons look oversized or are cut off.
    <Text style={[base, style, grown]} maxFontSizeMultiplier={MAX_FONT_SCALE} {...rest}>
      {children}
    </Text>
  );
}
