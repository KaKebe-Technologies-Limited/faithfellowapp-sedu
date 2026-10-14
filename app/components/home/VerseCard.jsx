// "Verse of the day": a warm gradient card with the scripture in large type, its reference and translation, and two
// buttons: share it as a neat message (copy and send anywhere) or read the whole chapter in the Bible.
import { useState } from 'react';
import { Pressable, Share, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Clipboard from 'expo-clipboard';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { FONTS, SPACING } from '../../constants/theme';
import { scriptureMessage } from '../../lib/bible';
import AppText from '../AppText';

export default function VerseCard({ verse }) {
  const [copied, setCopied] = useState(false);
  if (!verse) return null;

  async function share() {
    const message = scriptureMessage({ reference: verse.reference, translation: verse.translation, verses: [{ v: verse.verse, t: verse.text }] });
    Clipboard.setStringAsync(message).then(() => setCopied(true)).catch(() => {});
    Share.share({ message }).catch(() => {});
  }

  return (
    <View style={styles.wrap}>
      <View style={styles.shadow}>
        <LinearGradient colors={['#5E1030', '#A3223F', '#D1536C']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.card}>
          <View style={styles.glowA} />
          <View style={styles.glowB} />
          <AppText style={styles.quoteMark}>“</AppText>
          <View style={styles.label}>
            <Ionicons name="sunny-outline" size={15} color="#FFD98A" />
            <AppText style={styles.labelText}>VERSE OF THE DAY</AppText>
          </View>
          <AppText style={styles.text}>{verse.text}</AppText>
          <AppText style={styles.reference}>
            {verse.reference} <AppText style={styles.translation}>{verse.translation}</AppText>
          </AppText>
          <View style={styles.actions}>
            <Pressable onPress={share} style={styles.action} accessibilityRole="button" accessibilityLabel="Share this verse">
              <Ionicons name={copied ? 'checkmark' : 'share-social-outline'} size={17} color="#fff" />
              <AppText style={styles.actionText}>{copied ? 'Copied' : 'Share'}</AppText>
            </Pressable>
            <Pressable onPress={() => router.push({ pathname: '/bible/[book]/[chapter]', params: { book: verse.book, chapter: String(verse.chapter) } })} style={[styles.action, styles.read]} accessibilityRole="button" accessibilityLabel={`Read ${verse.reference} in the Bible`}>
              <Ionicons name="book-outline" size={17} color="#7A1832" />
              <AppText style={[styles.actionText, { color: '#7A1832' }]}>Read chapter</AppText>
            </Pressable>
          </View>
        </LinearGradient>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, marginBottom: SPACING.lg },
  shadow: { borderRadius: 26, backgroundColor: '#8E1C37', shadowColor: '#5E1030', shadowOpacity: 0.3, shadowRadius: 18, shadowOffset: { width: 0, height: 10 }, elevation: 8 },
  card: { borderRadius: 26, padding: 22, overflow: 'hidden' },
  glowA: { position: 'absolute', right: -50, top: -50, width: 190, height: 190, borderRadius: 95, backgroundColor: 'rgba(255,255,255,0.1)' },
  glowB: { position: 'absolute', left: -40, bottom: -70, width: 170, height: 170, borderRadius: 85, backgroundColor: 'rgba(255,200,120,0.14)' },
  quoteMark: { position: 'absolute', right: 18, top: -6, fontFamily: FONTS.bold, fontSize: 120, lineHeight: 130, color: 'rgba(255,255,255,0.16)' },
  label: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 12 },
  labelText: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.6, color: '#FFD98A' },
  text: { fontFamily: FONTS.medium, fontSize: 20, lineHeight: 30, color: '#fff' },
  reference: { fontFamily: FONTS.bold, fontSize: 15, color: '#FFD98A', marginTop: 14 },
  translation: { fontFamily: FONTS.semibold, fontSize: 12, color: 'rgba(255,255,255,0.75)' },
  actions: { flexDirection: 'row', gap: 10, marginTop: 18 },
  action: { flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: 'rgba(255,255,255,0.18)', borderRadius: 24, paddingHorizontal: 16, height: 42 },
  read: { backgroundColor: '#fff' },
  actionText: { fontFamily: FONTS.bold, fontSize: 13.5, color: '#fff' },
});
