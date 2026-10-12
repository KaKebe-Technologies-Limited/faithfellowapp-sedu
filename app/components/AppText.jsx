// Text with the FaithFellows typography (Space Grotesk) applied through simple variants.
import { Text } from 'react-native';
import { COLORS, FONTS } from '../constants/theme';

const VARIANTS = {
  title: { fontFamily: FONTS.bold, fontSize: 26, lineHeight: 32, color: COLORS.black },
  heading: { fontFamily: FONTS.semibold, fontSize: 18, lineHeight: 24, color: COLORS.black },
  body: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.gray900 },
  label: { fontFamily: FONTS.medium, fontSize: 13, lineHeight: 18, color: COLORS.black },
  caption: { fontFamily: FONTS.regular, fontSize: 12, lineHeight: 16, color: COLORS.gray700 },
};

export default function AppText({ variant = 'body', style, children, ...rest }) {
  return (
    <Text style={[VARIANTS[variant], style]} {...rest}>
      {children}
    </Text>
  );
}
