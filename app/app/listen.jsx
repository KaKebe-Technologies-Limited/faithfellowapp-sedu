// The full podcast player: the cover, the episode, a progress bar you can drag, back 15 / play / forward 30, the speed,
// and shortcuts to take notes, read the Bible or open your groups while it keeps playing. What plays next is below.
// The episode carries on when you close this screen, switch apps or lock the phone.
import { Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../components/AppText';
import Scrubber from '../components/podcast/Scrubber';
import { RoundIconButton } from '../components/radio/RadioButton';
import { BackButton, goBack } from '../components/ScreenHeader';
import { FONTS, SPACING } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { cycleRate, nextEpisode, playEpisode, seekBy, seekTo, togglePodcast, usePodcast, usePodcastProgress } from '../lib/podcastPlayer';

const clock = (s) => {
  const n = Math.max(0, Math.floor(s || 0));
  const h = Math.floor(n / 3600);
  const m = Math.floor((n % 3600) / 60);
  const sec = String(n % 60).padStart(2, '0');

  return h ? `${h}:${String(m).padStart(2, '0')}:${sec}` : `${m}:${sec}`;
};

function Skip({ seconds, onPress }) {
  const back = seconds < 0;

  return (
    <Pressable onPress={onPress} hitSlop={8} accessibilityRole="button" accessibilityLabel={`${back ? 'Back' : 'Forward'} ${Math.abs(seconds)} seconds`} style={styles.skip}>
      <Ionicons name="reload" size={40} color="#fff" style={back ? { transform: [{ scaleX: -1 }] } : null} />
      <AppText style={styles.skipText}>{Math.abs(seconds)}</AppText>
    </Pressable>
  );
}

function Shortcut({ icon, label, onPress }) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={label} style={({ pressed }) => [styles.shortcut, pressed && { opacity: 0.8 }]}>
      <Ionicons name={icon} size={22} color="#fff" />
      <AppText style={styles.shortcutText}>{label}</AppText>
    </Pressable>
  );
}

export default function ListenScreen() {
  const insets = useSafeAreaInsets();
  const { phase, episode, queue, rate, error } = usePodcast();
  const { position, duration } = usePodcastProgress();

  if (!episode) {
    return (
      <View style={[styles.screen, { paddingTop: insets.top + 8, paddingHorizontal: SPACING.md }]}>
        <BackButton onDark icon="chevron-down" />
        <AppText style={[styles.hint, { marginTop: 80 }]}>{error ?? 'Nothing is playing. Pick an episode from Podcasts.'}</AppText>
        <Pressable onPress={() => router.replace('/podcasts')} style={styles.findButton} accessibilityRole="button">
          <AppText style={styles.findText}>Find a podcast</AppText>
        </Pressable>
      </View>
    );
  }

  const playing = phase === 'playing';
  const total = duration || episode.duration || 0;
  const podcast = episode.podcast;
  const sermon = episode.source === 'sermon';
  const note = () => router.push({ pathname: '/note/[id]', params: { id: 'new', context: sermon ? `sermon:${episode.sermon_id}` : `podcast:${episode.id}`, reference: `${podcast?.name ?? (sermon ? 'Sermon' : 'Podcast')}: ${episode.title}` } });

  return (
    <View style={styles.screen}>
      <LinearGradient colors={['#241633', '#17171C', '#17171C']} style={StyleSheet.absoluteFill} />
      <ScrollView contentContainerStyle={{ paddingTop: insets.top + 8, paddingBottom: insets.bottom + 40, paddingHorizontal: SPACING.md }} showsVerticalScrollIndicator={false}>
        <View style={styles.top}>
          <BackButton onDark icon="chevron-down" onPress={goBack} />
          <View style={{ flex: 1, alignItems: 'center' }}>
            <AppText style={styles.eyebrow}>{playing ? 'NOW PLAYING' : sermon ? 'SERMON' : 'PODCAST'}</AppText>
            <AppText style={styles.station} numberOfLines={1}>
              {podcast?.name}
            </AppText>
          </View>
          <RoundIconButton icon="share-social-outline" label="Share this episode" onPress={() => Share.share({ message: sermon ? `Listening to the sermon "${episode.title}"${podcast?.name ? ` by ${podcast.name}` : ''} on FaithFellows.` : `Listening to "${episode.title}" on ${podcast?.name}, a podcast on FaithFellows.` }).catch(() => {})} />
        </View>

        <View style={styles.coverWrap}>
          <Image source={{ uri: mediaUrl(podcast?.cover_url) }} style={styles.cover} contentFit="cover" transition={300} />
        </View>

        <AppText style={styles.title} numberOfLines={3}>
          {episode.title}
        </AppText>
        <Pressable onPress={() => podcast?.id && router.push({ pathname: '/podcast/[id]', params: { id: podcast.id } })} accessibilityRole="link">
          <AppText style={styles.host} numberOfLines={1}>
            {podcast?.host ? `${podcast.name} · ${podcast.host}` : podcast?.name}
          </AppText>
        </Pressable>

        <View style={{ marginTop: 22 }}>
          <Scrubber position={position} duration={total} onSeek={seekTo} onStep={seekBy} />
          <View style={styles.times}>
            <AppText style={styles.time}>{clock(position)}</AppText>
            <AppText style={styles.time}>{total ? `-${clock(Math.max(0, total - position))}` : ''}</AppText>
          </View>
        </View>

        <View style={styles.controls}>
          <Pressable onPress={cycleRate} accessibilityRole="button" accessibilityLabel={`Speed ${rate} times. Tap to change`} style={styles.rate}>
            <AppText style={styles.rateText}>{rate}×</AppText>
          </Pressable>
          <Skip seconds={-15} onPress={() => seekBy(-15)} />
          <Pressable onPress={togglePodcast} accessibilityRole="button" accessibilityLabel={playing ? 'Pause' : 'Play'} style={styles.play}>
            {phase === 'loading' ? <Ionicons name="hourglass-outline" size={34} color="#1A1A1A" /> : <Ionicons name={playing ? 'pause' : 'play'} size={38} color="#1A1A1A" style={playing ? null : { marginLeft: 4 }} />}
          </Pressable>
          <Skip seconds={30} onPress={() => seekBy(30)} />
          <Pressable onPress={() => nextEpisode(false)} disabled={!queue.length} accessibilityRole="button" accessibilityLabel="Next episode" style={[styles.rate, !queue.length && { opacity: 0.35 }]}>
            <Ionicons name="play-skip-forward" size={22} color="#fff" />
          </Pressable>
        </View>

        <AppText style={styles.hint}>{error ? error : phase === 'loading' ? 'Getting the episode ready…' : 'Keeps playing while you read, write notes or chat.'}</AppText>

        <View style={styles.shortcuts}>
          <Shortcut icon="create-outline" label="Take notes" onPress={note} />
          <Shortcut icon="book-outline" label="Bible" onPress={() => router.push('/(tabs)/bible')} />
          <Shortcut icon="chatbubbles-outline" label="Groups" onPress={() => router.push('/groups')} />
        </View>

        {episode.summary ? (
          <View style={{ marginTop: 26 }}>
            <AppText style={styles.section}>About this episode</AppText>
            <AppText style={styles.about}>{episode.summary}</AppText>
          </View>
        ) : null}

        {queue.length ? (
          <View style={{ marginTop: 28 }}>
            <AppText style={styles.section}>Up next</AppText>
            {queue.slice(0, 4).map((item) => (
              <Pressable key={item.id} onPress={() => playEpisode(item, queue)} style={styles.row} accessibilityRole="button" accessibilityLabel={`Play ${item.title}`}>
                <Ionicons name="play-circle" size={30} color="#FF7A9A" />
                <View style={{ flex: 1 }}>
                  <AppText style={styles.rowTitle} numberOfLines={2}>
                    {item.title}
                  </AppText>
                  {item.duration ? <AppText style={styles.rowNote}>{clock(item.duration)}</AppText> : null}
                </View>
              </Pressable>
            ))}
          </View>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#17171C' },
  top: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  eyebrow: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.4, color: '#FF8AA0' },
  station: { fontFamily: FONTS.semibold, fontSize: 15, color: '#fff', marginTop: 1 },
  coverWrap: { alignSelf: 'center', marginTop: 24, borderRadius: 24, shadowColor: '#000', shadowOpacity: 0.5, shadowRadius: 24, shadowOffset: { width: 0, height: 14 }, elevation: 14 },
  cover: { width: 280, height: 280, maxWidth: '100%', borderRadius: 24, backgroundColor: '#2A2A32' },
  title: { fontFamily: FONTS.bold, fontSize: 23, lineHeight: 30, color: '#fff', textAlign: 'center', marginTop: 24 },
  host: { fontFamily: FONTS.medium, fontSize: 15.5, color: 'rgba(255,255,255,0.7)', textAlign: 'center', marginTop: 4 },
  times: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 2 },
  time: { fontFamily: FONTS.medium, fontSize: 12.5, color: 'rgba(255,255,255,0.6)' },
  controls: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 14, paddingHorizontal: 2 },
  rate: { minWidth: 46, height: 36, borderRadius: 18, paddingHorizontal: 8, backgroundColor: '#2E2E36', alignItems: 'center', justifyContent: 'center' },
  rateText: { fontFamily: FONTS.bold, fontSize: 13.5, color: '#fff' },
  skip: { width: 48, height: 48, alignItems: 'center', justifyContent: 'center' },
  skipText: { position: 'absolute', fontFamily: FONTS.bold, fontSize: 12, color: '#fff' },
  play: { width: 76, height: 76, borderRadius: 38, backgroundColor: '#fff', alignItems: 'center', justifyContent: 'center' },
  hint: { fontFamily: FONTS.medium, fontSize: 13, color: 'rgba(255,255,255,0.55)', textAlign: 'center', marginTop: 16 },
  shortcuts: { flexDirection: 'row', gap: 10, marginTop: 20 },
  shortcut: { flex: 1, minHeight: 64, borderRadius: 16, backgroundColor: '#25252B', alignItems: 'center', justifyContent: 'center', gap: 4, paddingVertical: 8 },
  shortcutText: { fontFamily: FONTS.semibold, fontSize: 12.5, color: '#fff' },
  section: { fontFamily: FONTS.bold, fontSize: 18, color: '#fff', marginBottom: 10 },
  about: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, color: 'rgba(255,255,255,0.75)' },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: '#25252B', borderRadius: 14, padding: 12, marginBottom: 8 },
  rowTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: '#fff' },
  rowNote: { fontFamily: FONTS.regular, fontSize: 12.5, color: 'rgba(255,255,255,0.55)', marginTop: 1 },
  findButton: { alignSelf: 'center', marginTop: 20, paddingHorizontal: 22, paddingVertical: 12, borderRadius: 24, backgroundColor: '#fff' },
  findText: { fontFamily: FONTS.bold, fontSize: 15, color: '#1A1A1A' },
});
