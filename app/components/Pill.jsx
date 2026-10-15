// Small rounded label with an optional icon: the "Event" / "Sermon" / "Urgent" tags on cards.
// Tones: soft (pink, default), urgent (red tint), live (solid red), dark (on photos).
import { StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { COLORS, FONTS, RADIUS } from '../constants/theme';
import AppText from './AppText';

const TONES = {
  soft: { bg: COLORS.primaryMist, fg: COLORS.primary },
  urgent: { bg: COLORS.dangerMist, fg: COLORS.danger },
  live: { bg: COLORS.danger, fg: COLORS.white },
  dark: { bg: 'rgba(0,0,0,0.45)', fg: COLORS.white },
  white: { bg: COLORS.white, fg: COLORS.primary },
};

export default function Pill({ label, icon, tone = 'soft', style }) {
  const { bg, fg } = TONES[tone] ?? TONES.soft;

  return (
    <View style={[styles.pill, { backgroundColor: bg }, style]}>
      {icon ? <Ionicons name={icon} size={13} color={fg} /> : null}
      <AppText style={{ fontFamily: FONTS.semibold, fontSize: 12, color: fg }} numberOfLines={1}>
        {label}
      </AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  pill: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 10, paddingVertical: 5, borderRadius: RADIUS.md, alignSelf: 'flex-start' },
});
