// Appearance: how the whole app looks. Light (the classic look), Dark, or Rose (a soft, stylish look in pinks with rounder corners and a serif heading
// font). The choice is kept on this phone. Changing it restarts the app for a second, because every screen is drawn with the look's colours from the start.
import { useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../../components/AppText';
import ScreenHeader from '../../components/ScreenHeader';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { LOOKS, chooseLook, currentLook } from '../../lib/theme';

// A little drawing of each look, in its own colours (not the colours of the look in use).
const PREVIEW = {
  light: { page: '#FAFAFA', card: '#FFFFFF', ink: '#1A1A1A', soft: '#CACACA', accent: '#BC2649', panel: ['#4A0F29', '#7D1A39'] },
  dark: { page: '#121014', card: '#1D1A1F', ink: '#F3EEF1', soft: '#43393F', accent: '#DC4166', panel: ['#1E0710', '#4A0F29'] },
  girls: { page: '#FFF4F9', card: '#FFFFFF', ink: '#3A1A2B', soft: '#EACFDD', accent: '#D93F7E', panel: ['#A02D6C', '#E0488A'] },
};

function Mini({ look }) {
  const p = PREVIEW[look];

  return (
    <View style={[styles.phone, { backgroundColor: p.page, borderRadius: look === 'girls' ? 20 : 14 }]}>
      <View style={[styles.phonePanel, { backgroundColor: p.panel[1] }]}>
        <View style={[styles.line, { width: 34, backgroundColor: 'rgba(255,255,255,0.9)' }]} />
        <View style={[styles.line, { width: 22, backgroundColor: 'rgba(255,255,255,0.5)' }]} />
      </View>
      <View style={[styles.phoneCard, { backgroundColor: p.card, borderRadius: look === 'girls' ? 12 : 7 }]}>
        <View style={[styles.line, { width: 40, backgroundColor: p.ink }]} />
        <View style={[styles.line, { width: 52, backgroundColor: p.soft }]} />
        <View style={[styles.phoneButton, { backgroundColor: p.accent, borderRadius: look === 'girls' ? 8 : 4 }]} />
      </View>
      <View style={[styles.phoneCard, { backgroundColor: p.card, borderRadius: look === 'girls' ? 12 : 7 }]}>
        <View style={[styles.line, { width: 30, backgroundColor: p.ink }]} />
        <View style={[styles.line, { width: 46, backgroundColor: p.soft }]} />
      </View>
    </View>
  );
}

export default function Appearance() {
  const now = currentLook();
  const [busy, setBusy] = useState(null);

  const pick = async (key) => {
    if (busy || key === now) return;
    setBusy(key);
    const started = await chooseLook(key);
    if (!started) setBusy(null);
  };

  return (
    <View style={styles.flex}>
      <ScreenHeader title="Appearance" subtitle="Choose how FaithFellows looks" />
      <ScrollView contentContainerStyle={styles.body}>
        {LOOKS.map((look) => {
          const on = look.key === now;

          return (
            <Pressable
              key={look.key}
              onPress={() => pick(look.key)}
              style={({ pressed }) => [styles.card, on && styles.cardOn, pressed && { opacity: 0.94 }]}
              accessibilityRole="radio"
              accessibilityState={{ selected: on }}
              accessibilityLabel={`${look.title}. ${look.text}`}
            >
              <Mini look={look.key} />
              <View style={{ flex: 1, gap: 3 }}>
                <View style={styles.titleRow}>
                  <Ionicons name={look.icon} size={16} color={COLORS.primary} />
                  <AppText style={styles.title}>{look.title}</AppText>
                </View>
                <AppText style={styles.text}>{look.text}</AppText>
              </View>
              <View style={[styles.radio, on && styles.radioOn]}>
                {busy === look.key ? <ActivityIndicator size="small" color={COLORS.onColor} /> : on ? <Ionicons name="checkmark" size={16} color={COLORS.onColor} /> : null}
              </View>
            </Pressable>
          );
        })}
        <AppText style={styles.note}>The app restarts for a second when you change the look. Your choice stays on this phone.</AppText>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: COLORS.canvas },
  body: { padding: SPACING.md, paddingBottom: 48, gap: 12 },
  card: { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: 14, borderWidth: 2, borderColor: 'transparent', ...SHADOW },
  cardOn: { borderColor: COLORS.primary },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  title: { fontFamily: FONTS.display, fontSize: 18, color: COLORS.black },
  text: { fontFamily: FONTS.regular, fontSize: 13.5, lineHeight: 19, color: COLORS.gray700 },
  radio: { width: 26, height: 26, borderRadius: 13, borderWidth: 2, borderColor: COLORS.gray300, alignItems: 'center', justifyContent: 'center' },
  radioOn: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  note: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 18, color: COLORS.gray700, textAlign: 'center', marginTop: 8, paddingHorizontal: 12 },
  phone: { width: 84, height: 128, padding: 6, gap: 5, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(120,120,120,0.25)' },
  phonePanel: { height: 36, borderRadius: 8, padding: 6, gap: 4, justifyContent: 'center' },
  phoneCard: { padding: 6, gap: 4 },
  phoneButton: { height: 9, width: 30, marginTop: 2 },
  line: { height: 4, borderRadius: 2 },
});
