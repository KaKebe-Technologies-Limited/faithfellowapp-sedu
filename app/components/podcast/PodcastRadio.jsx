// "Podcast radio" on the Podcasts screen, under Our Podcasts (which plays the recordings on demand): the live side. The platform's own Podcast radio
// (teaching and podcasts the admin has put on, playing day and night) is the big card, and the podcast channels of the churches you follow are listed under
// it. Press play and it joins the live channel; it keeps playing while you move around the app, with the bar above the tabs. Tap a card to open the full
// player.
import { ActivityIndicator, Image, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { PURPLE } from '../../constants/podcastTheme';
import { playChannel, selectStation, useRadio, useStationInfo } from '../../lib/radio';
import { useFetch } from '../../lib/useFetch';
import AppText from '../AppText';
import ChurchAvatar from '../ChurchAvatar';
import CommentsButton from './CommentsButton';

const MARK = require('../../assets/logo-mark.png');

const openPlayer = (slug) => {
  selectStation(slug);
  router.push({ pathname: '/radio', params: { kind: 'talk' } });
};

/** The round play / pause button of a channel: pause when it is the one on air. */
function PlayButton({ slug, size = 56, light = false }) {
  const { stationId, phase } = useRadio();
  const current = stationId === slug && (phase === 'playing' || phase === 'loading' || phase === 'paused');
  const playing = current && phase === 'playing';
  const loading = current && phase === 'loading';
  const color = light ? '#4527A0' : '#fff';

  return (
    <Pressable
      onPress={() => playChannel(slug)}
      hitSlop={6}
      accessibilityRole="button"
      accessibilityLabel={playing ? 'Stop the podcast radio' : 'Play the podcast radio'}
      style={({ pressed }) => [styles.play, { width: size, height: size, borderRadius: size / 2, backgroundColor: light ? '#fff' : PURPLE.main }, pressed && { opacity: 0.88 }]}
    >
      {loading ? <ActivityIndicator color={color} /> : <Ionicons name={playing ? 'pause' : 'play'} size={size * 0.46} color={color} style={playing ? null : { marginLeft: size * 0.05 }} />}
    </Pressable>
  );
}

/** The big card with what is on air now: the platform's Podcast radio, else the first of the member's churches' channels. */
function MainCard({ channel }) {
  const { stationId, phase } = useRadio();
  const now = useFetch(`/radio/stations/${channel.slug}`);
  const onNow = now.data?.data?.now?.track;
  const live = stationId === channel.slug && phase === 'playing';

  return (
    <View style={styles.card}>
      <LinearGradient colors={['#8E6BD6', '#4527A0']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      <Image source={MARK} resizeMode="contain" pointerEvents="none" style={styles.mark} />
      <LinearGradient colors={['rgba(20,6,40,0)', 'rgba(20,6,40,0.5)']} locations={[0.35, 1]} style={StyleSheet.absoluteFill} />
      <Pressable onPress={() => openPlayer(channel.slug)} style={StyleSheet.absoluteFill} accessibilityRole="button" accessibilityLabel={`${channel.name}. Open the player`} />

      <View style={styles.cardTop} pointerEvents="none">
        <View style={styles.live}>
          <View style={[styles.dot, live && { backgroundColor: '#5CFF9A' }]} />
          <AppText style={styles.liveText}>{live ? 'ON AIR' : 'LIVE 24/7'}</AppText>
        </View>
      </View>
      <View style={styles.cardBottom} pointerEvents="box-none">
        <View style={{ flex: 1 }} pointerEvents="none">
          <AppText style={styles.cardTitle} numberOfLines={2}>
            {channel.name}
          </AppText>
          <AppText style={styles.cardSub} numberOfLines={2}>
            {onNow ? `Now: ${onNow.title}${onNow.artist ? ` · ${onNow.artist}` : ''}` : channel.tagline || 'Teaching and podcasts, day and night'}
          </AppText>
        </View>
        <PlayButton slug={channel.slug} size={60} light />
      </View>
    </View>
  );
}

/** A church's own podcast channel. */
function ChurchRow({ channel }) {
  return (
    <View style={styles.row}>
      <Pressable onPress={() => openPlayer(channel.slug)} style={styles.rowMain} accessibilityRole="button" accessibilityLabel={`${channel.name}. Open the player`}>
        <ChurchAvatar church={channel.church ?? { name: channel.name }} size={48} />
        <View style={{ flex: 1, gap: 2 }}>
          <AppText style={styles.rowTitle} numberOfLines={1}>
            {channel.name}
          </AppText>
          <AppText variant="caption" numberOfLines={1}>
            {channel.songs} {channel.songs === 1 ? 'episode' : 'episodes'} · {channel.church ? 'always on' : 'FaithFellows'}
          </AppText>
        </View>
      </Pressable>
      <PlayButton slug={channel.slug} size={44} />
    </View>
  );
}

/** Nothing shows until a channel has something on: the Podcast radio, and the churches' channels that have episodes. */
export default function PodcastRadio() {
  useStationInfo();
  const { stations } = useRadio();
  const talk = stations.filter((s) => s.kind === 'talk');
  const platform = talk.find((s) => !s.church);
  const own = talk.filter((s) => s.church);
  // The Podcast radio is the big live card; the churches' channels (the ones the member follows) are listed under it.
  const main = platform ?? own[0];
  const churches = platform ? own : own.slice(1);
  if (!main && !churches.length) return null;

  return (
    <View style={{ marginBottom: SPACING.lg }}>
      <View style={styles.sectionHead}>
        <View style={styles.sectionBar} />
        <AppText style={styles.section}>Podcast radio</AppText>
      </View>
      {main ? (
        <>
          <View style={{ paddingHorizontal: SPACING.md }}>
            <MainCard channel={main} />
          </View>
          <CommentsButton key={main.slug} base={`/radio/stations/${main.slug}`} title="Comments" style={styles.commentsButton} />
        </>
      ) : null}
      {churches.length ? (
        <View style={{ marginTop: SPACING.md }}>
          <AppText style={styles.subsection}>Live church channels</AppText>
          {churches.map((c) => (
            <ChurchRow key={c.slug} channel={c} />
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  sectionHead: { flexDirection: 'row', alignItems: 'center', gap: 9, paddingHorizontal: SPACING.md, marginBottom: 12 },
  sectionBar: { width: 4, height: 20, borderRadius: 2, backgroundColor: PURPLE.main },
  section: { fontFamily: FONTS.display, fontSize: 19, color: COLORS.black, flex: 1 },
  commentsButton: { marginHorizontal: SPACING.md, marginTop: 12 },
  subsection: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.gray700, paddingHorizontal: SPACING.md, marginBottom: 8 },
  mark: { position: 'absolute', right: -30, top: -34, width: 220, height: 220, tintColor: '#fff', opacity: 0.12 },
  card: { height: 160, borderRadius: RADIUS.xl, overflow: 'hidden', backgroundColor: '#4527A0', ...SHADOW, shadowOpacity: 0.16 },
  cardTop: { position: 'absolute', left: 16, top: 16, flexDirection: 'row' },
  live: { flexDirection: 'row', alignItems: 'center', gap: 7, backgroundColor: 'rgba(20,6,40,0.45)', borderRadius: RADIUS.full, paddingHorizontal: 11, paddingVertical: 5 },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#FF7A9A' },
  liveText: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.3, color: '#fff' },
  cardBottom: { position: 'absolute', left: 18, right: 14, bottom: 16, flexDirection: 'row', alignItems: 'flex-end', gap: 12 },
  cardTitle: { fontFamily: FONTS.bold, fontSize: 23, lineHeight: 28, color: '#fff' },
  cardSub: { fontFamily: FONTS.medium, fontSize: 13.5, lineHeight: 19, color: 'rgba(255,255,255,0.86)', marginTop: 3 },
  play: { alignItems: 'center', justifyContent: 'center', ...SHADOW, shadowOpacity: 0.2 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 10, marginHorizontal: SPACING.md, marginBottom: 10, ...SHADOW },
  rowMain: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 12 },
  rowTitle: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black },
});
