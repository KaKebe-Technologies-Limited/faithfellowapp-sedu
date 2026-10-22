// The full radio player: the track on air with a big cover, the glowing play / stop button, what comes next and what was
// just played. Above it, the channels to choose from: the Gospel radio, the Podcast radio and the channel of each church you follow.
// The radio keeps playing when you close this screen, switch apps or lock the phone.
import { useEffect } from 'react';
import { Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../components/AppText';
import Comments from '../components/podcast/Comments';
import RadioButton, { RoundIconButton } from '../components/radio/RadioButton';
import { BackButton } from '../components/ScreenHeader';
import { FONTS, SPACING } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { timeAgo } from '../lib/format';
import { selectStation, useRadio, useStationInfo } from '../lib/radio';

const clock = (iso) => new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

/** "Today 4:00 pm", "Tomorrow 9:00 am" or "Sun 26 Oct, 4:00 pm". */
function dayAndClock(iso) {
  const at = new Date(iso);
  const days = Math.round((new Date(at.getFullYear(), at.getMonth(), at.getDate()) - new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate())) / 86400000);
  const day = days === 0 ? 'Today' : days === 1 ? 'Tomorrow' : at.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });

  return `${day} ${clock(iso)}`;
}

function Row({ track, note }) {
  return (
    <View style={styles.row}>
      <Image source={{ uri: mediaUrl(track.cover_url) }} style={styles.thumb} contentFit="cover" />
      <View style={{ flex: 1 }}>
        <AppText style={styles.rowTitle} numberOfLines={1}>
          {track.title}
        </AppText>
        <AppText style={styles.rowArtist} numberOfLines={1}>
          {track.artist || ' '}
        </AppText>
      </View>
      {note ? <AppText style={styles.rowNote}>{note}</AppText> : null}
    </View>
  );
}

export default function RadioScreen() {
  useStationInfo();
  const insets = useSafeAreaInsets();
  const { station, stations, stationId, track, phase, upcoming, recent, scheduled, error } = useRadio();
  const live = phase === 'playing';

  // This screen is the music radio, or the podcast radio when it is opened from Podcasts (?kind=talk). Whatever is playing decides what it shows.
  const { kind } = useLocalSearchParams();
  const wanted = kind === 'talk' ? 'talk' : 'music';
  const showing = phase !== 'idle' && station?.kind ? station.kind : wanted;
  const channels = stations.filter((s) => (s.kind === 'talk') === (showing === 'talk'));
  useEffect(() => {
    // Opened for one kind while the other was the last one chosen, and nothing is playing: move to the first channel of the kind asked for.
    if (phase !== 'idle' || !stations.length || (station?.kind ?? 'music') === wanted) return;
    const first = stations.find((s) => (s.kind === 'talk') === (wanted === 'talk'));
    if (first) selectStation(first.slug);
  }, [wanted, stations, phase, station?.kind]);

  return (
    <View style={styles.screen}>
      <LinearGradient colors={['#2B0D18', '#17171C', '#17171C']} style={StyleSheet.absoluteFill} />
      <ScrollView contentContainerStyle={{ paddingTop: insets.top + 8, paddingBottom: insets.bottom + 40, paddingHorizontal: SPACING.md }} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" automaticallyAdjustKeyboardInsets>
        <View style={styles.top}>
          <BackButton onDark icon="chevron-down" />
          <View style={{ flex: 1, alignItems: 'center' }}>
            <AppText style={styles.onAir}>{live ? 'ON AIR' : 'LISTEN LIVE'}</AppText>
            <AppText style={styles.station}>{station?.name ?? 'FaithFellows Radio'}</AppText>
          </View>
          <RoundIconButton icon="share-social-outline" label="Share the radio" onPress={() => Share.share({ message: `Listen to ${station?.name ?? 'FaithFellows Radio'}: ${(station?.tagline ?? 'Gospel music, day and night').toLowerCase()}, on FaithFellows.` }).catch(() => {})} />
        </View>

        {channels.length > 1 ? (
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.chipScroll} contentContainerStyle={styles.chips}>
            {channels.map((s) => {
              const on = s.slug === stationId;
              return (
                <Pressable key={s.slug} onPress={() => selectStation(s.slug)} accessibilityRole="button" accessibilityState={{ selected: on }} accessibilityLabel={`Channel: ${s.name}`} style={[styles.chip, on && styles.chipOn]}>
                  <Ionicons name={s.kind === 'music' ? 'musical-notes' : s.church ? 'business' : 'mic'} size={14} color={on ? '#fff' : 'rgba(255,255,255,0.75)'} />
                  <AppText style={[styles.chipText, on && { color: '#fff' }]} numberOfLines={1}>
                    {s.name}
                  </AppText>
                </Pressable>
              );
            })}
          </ScrollView>
        ) : null}

        {track ? (
          <>
            <View style={styles.coverWrap}>
              <Image source={{ uri: mediaUrl(track.cover_url) }} style={styles.cover} contentFit="cover" transition={300} />
            </View>
            <AppText style={styles.title} numberOfLines={2}>
              {track.title}
            </AppText>
            {track.artist ? <AppText style={styles.artist}>{track.artist}</AppText> : null}
            <View style={styles.buttonRow}>
              <RadioButton size={92} />
            </View>
            <AppText style={styles.hint}>{error ? error : live ? 'Playing live. Keeps going when you lock your phone.' : phase === 'loading' ? 'Joining the radio…' : 'Tap play to join live.'}</AppText>
            {station?.fallbackFor ? (
              <AppText style={styles.notice}>
                Nothing is on {station.fallbackFor.name} right now, so here is {station.name}.
              </AppText>
            ) : null}
          </>
        ) : (
          <AppText style={[styles.hint, { marginTop: 80 }]}>{station && !station.enabled ? 'This channel is quiet or switched off for now.' : 'This channel is quiet right now. Check again soon.'}</AppText>
        )}

        {showing === 'talk' && station?.slug ? (
          <View style={{ marginTop: 30 }}>
            <Comments key={station.slug} base={`/radio/stations/${station.slug}`} dark title="Listeners' comments" />
          </View>
        ) : null}

        {scheduled ? (
          <View style={{ marginTop: 30 }}>
            <AppText style={styles.section}>Scheduled</AppText>
            <Row track={scheduled.track} note={dayAndClock(scheduled.starts_at)} />
          </View>
        ) : null}

        {upcoming.length ? (
          <View style={{ marginTop: scheduled ? 26 : 34 }}>
            <AppText style={styles.section}>Up next</AppText>
            {upcoming.slice(0, 3).map((t, i) => (
              <Row key={`${t.id}-${i}`} track={t} note={t.starts_at ? clock(t.starts_at) : null} />
            ))}
          </View>
        ) : null}

        {recent.length ? (
          <View style={{ marginTop: 26 }}>
            <AppText style={styles.section}>Last played</AppText>
            {recent.map((t, i) => (
              <Row key={`${t.id}-${i}`} track={t} note={t.played_at ? timeAgo(t.played_at) : null} />
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
  chipScroll: { marginTop: 18, marginHorizontal: -SPACING.md },
  chips: { gap: 8, paddingHorizontal: SPACING.md },
  chip: { flexDirection: 'row', alignItems: 'center', gap: 6, maxWidth: 220, paddingVertical: 8, paddingHorizontal: 14, borderRadius: 999, backgroundColor: 'rgba(255,255,255,0.1)' },
  chipOn: { backgroundColor: '#BC2649' },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: 'rgba(255,255,255,0.75)' },
  onAir: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.4, color: '#FF8AA0' },
  station: { fontFamily: FONTS.semibold, fontSize: 15, color: '#fff', marginTop: 1 },
  coverWrap: { alignSelf: 'center', marginTop: 26, borderRadius: 28, shadowColor: '#000', shadowOpacity: 0.5, shadowRadius: 24, shadowOffset: { width: 0, height: 14 }, elevation: 14 },
  cover: { width: 300, height: 300, maxWidth: '100%', borderRadius: 28, backgroundColor: '#2A2A32' },
  title: { fontFamily: FONTS.bold, fontSize: 25, lineHeight: 31, color: '#fff', textAlign: 'center', marginTop: 24 },
  artist: { fontFamily: FONTS.medium, fontSize: 16.5, color: 'rgba(255,255,255,0.7)', textAlign: 'center', marginTop: 4 },
  buttonRow: { alignItems: 'center', marginTop: 24 },
  hint: { fontFamily: FONTS.medium, fontSize: 13.5, color: 'rgba(255,255,255,0.6)', textAlign: 'center', marginTop: 16 },
  notice: { fontFamily: FONTS.medium, fontSize: 12.5, color: 'rgba(255,255,255,0.45)', textAlign: 'center', marginTop: 6 },
  section: { fontFamily: FONTS.bold, fontSize: 18, color: '#fff', marginBottom: 10 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: '#25252B', borderRadius: 14, padding: 8, marginBottom: 8 },
  thumb: { width: 54, height: 54, borderRadius: 10, backgroundColor: '#3A3A44' },
  rowTitle: { fontFamily: FONTS.semibold, fontSize: 15.5, color: '#fff' },
  rowArtist: { fontFamily: FONTS.regular, fontSize: 13.5, color: 'rgba(255,255,255,0.6)' },
  rowNote: { fontFamily: FONTS.medium, fontSize: 12.5, color: 'rgba(255,255,255,0.5)', paddingRight: 6 },
});
