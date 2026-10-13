// A devotional, made for reading: the scripture first in a soft pink card, the message in comfortable type,
// then a closing prayer. If the church recorded an audio version it plays right on the page.
import { Platform, ScrollView, Share, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Clipboard from 'expo-clipboard';
import { router, useLocalSearchParams } from 'expo-router';
import { Pressable } from 'react-native';
import AppText from '../../components/AppText';
import ChurchAvatar from '../../components/ChurchAvatar';
import CoverHero from '../../components/CoverHero';
import { AudioPlayerCard } from '../../components/MediaPlayers';
import Pill from '../../components/Pill';
import { HeaderAction } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import { scriptureMessage } from '../../lib/bible';
import { formatDate } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

export default function DevotionalScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/devotionals/${id}`);
  const d = data?.data;

  // The day's scripture as a neat card (copied too, so it can be pasted anywhere), then the share sheet.
  const share = async () => {
    const message = d.scripture_text
      ? scriptureMessage({ reference: d.scripture || d.title, verses: [{ v: 1, t: d.scripture_text }] })
      : `📖 ${d.title}${d.scripture ? ` — ${d.scripture}` : ''}\n\n✝️ Shared from FaithFellows`;
    await Clipboard.setStringAsync(message).catch(() => {});
    if (Platform.OS !== 'web') await Share.share({ message }).catch(() => {});
  };

  return (
    <View style={styles.screen}>
      <StateView loading={loading} error={error} onRetry={retry}>
        {d ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 48 }} showsVerticalScrollIndicator={false}>
            <CoverHero uri={d.cover_url} height={230} right={<HeaderAction onDark icon="share-social-outline" label="Share" onPress={share} />}>
              <Pill label="Devotional" icon="book-outline" tone="dark" />
              <AppText style={styles.heroTitle}>{d.title}</AppText>
            </CoverHero>

            <View style={styles.body}>
              <Pressable onPress={() => d.church && router.push({ pathname: '/church/[slug]', params: { slug: d.church.slug } })} style={styles.host}>
                <ChurchAvatar church={d.church} size={30} />
                <View style={{ flex: 1 }}>
                  <AppText style={styles.hostName}>{d.church?.name}</AppText>
                  <AppText variant="caption">{[d.author, formatDate(d.publish_date)].filter(Boolean).join(' · ')}</AppText>
                </View>
              </Pressable>

              {d.scripture || d.scripture_text ? (
                <View style={styles.verse}>
                  <Ionicons name="chatbox-ellipses-outline" size={22} color={COLORS.primary} />
                  {d.scripture_text ? <AppText style={styles.quote}>“{d.scripture_text}”</AppText> : null}
                  {d.scripture ? <AppText style={styles.ref}>{d.scripture}</AppText> : null}
                </View>
              ) : null}

              {d.audio_url ? (
                <View style={{ marginBottom: SPACING.md }}>
                  <AudioPlayerCard url={d.audio_url} title="Listen to this devotional" />
                </View>
              ) : null}

              {d.body ? <AppText style={styles.text}>{d.body}</AppText> : null}

              {d.prayer ? (
                <View style={styles.prayer}>
                  <AppText style={styles.prayerTitle}>🙏  Let us pray</AppText>
                  <AppText style={[styles.text, { fontStyle: 'italic', marginTop: 8 }]}>{d.prayer}</AppText>
                </View>
              ) : null}
            </View>
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  heroTitle: { fontFamily: FONTS.bold, fontSize: 26, lineHeight: 32, color: COLORS.white, marginTop: 4 },
  body: { padding: SPACING.md },
  host: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: SPACING.md },
  hostName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  verse: { backgroundColor: COLORS.pink, borderRadius: RADIUS.xl - 4, padding: SPACING.lg, gap: 10, marginBottom: SPACING.md },
  quote: { fontFamily: FONTS.regular, fontStyle: 'italic', fontSize: 18, lineHeight: 28, color: COLORS.black },
  ref: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  text: { fontFamily: FONTS.regular, fontSize: 16.5, lineHeight: 27, color: '#2B2B2B' },
  prayer: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, padding: SPACING.lg, marginTop: SPACING.lg },
  prayerTitle: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.primary },
});
