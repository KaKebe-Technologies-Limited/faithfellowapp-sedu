// The full radio player: the song on air with a big cover, the glowing play / stop button, what comes next and what was
// just played. The radio keeps playing when you close this screen, switch apps or lock the phone.
import { ScrollView, Share, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../components/AppText';
import RadioButton, { RoundIconButton } from '../components/radio/RadioButton';
import { BackButton } from '../components/ScreenHeader';
import { FONTS, SPACING } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { timeAgo } from '../lib/format';
import { useRadio, useStationInfo } from '../lib/radio';

const clock = (iso) => new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });

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
  const { station, track, phase, upcoming, recent, error } = useRadio();
  const live = phase === 'playing';

  return (
    <View style={styles.screen}>
      <LinearGradient colors={['#2B0D18', '#17171C', '#17171C']} style={StyleSheet.absoluteFill} />
      <ScrollView contentContainerStyle={{ paddingTop: insets.top + 8, paddingBottom: insets.bottom + 40, paddingHorizontal: SPACING.md }} showsVerticalScrollIndicator={false}>
        <View style={styles.top}>
          <BackButton onDark icon="chevron-down" />
          <View style={{ flex: 1, alignItems: 'center' }}>
            <AppText style={styles.onAir}>{live ? 'ON AIR' : 'LISTEN LIVE'}</AppText>
            <AppText style={styles.station}>{station?.name ?? 'FaithFellows Radio'}</AppText>
          </View>
          <RoundIconButton icon="share-social-outline" label="Share the radio" onPress={() => Share.share({ message: `Listen to ${station?.name ?? 'FaithFellows Radio'}: gospel music, day and night, on FaithFellows.` }).catch(() => {})} />
        </View>

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
            <AppText style={styles.hint}>{error ? error : live ? 'Playing live. Keeps going when you lock your phone.' : phase === 'loading' ? 'Joining the radio…' : 'Tap play to join the live radio.'}</AppText>
          </>
        ) : (
          <AppText style={[styles.hint, { marginTop: 80 }]}>{station && !station.enabled ? 'The radio is switched off for now.' : 'The radio is quiet right now. Check again soon.'}</AppText>
        )}

        {upcoming.length ? (
          <View style={{ marginTop: 34 }}>
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
  onAir: { fontFamily: FONTS.bold, fontSize: 11.5, letterSpacing: 1.4, color: '#FF8AA0' },
  station: { fontFamily: FONTS.semibold, fontSize: 15, color: '#fff', marginTop: 1 },
  coverWrap: { alignSelf: 'center', marginTop: 26, borderRadius: 28, shadowColor: '#000', shadowOpacity: 0.5, shadowRadius: 24, shadowOffset: { width: 0, height: 14 }, elevation: 14 },
  cover: { width: 300, height: 300, maxWidth: '100%', borderRadius: 28, backgroundColor: '#2A2A32' },
  title: { fontFamily: FONTS.bold, fontSize: 25, lineHeight: 31, color: '#fff', textAlign: 'center', marginTop: 24 },
  artist: { fontFamily: FONTS.medium, fontSize: 16.5, color: 'rgba(255,255,255,0.7)', textAlign: 'center', marginTop: 4 },
  buttonRow: { alignItems: 'center', marginTop: 24 },
  hint: { fontFamily: FONTS.medium, fontSize: 13.5, color: 'rgba(255,255,255,0.6)', textAlign: 'center', marginTop: 16 },
  section: { fontFamily: FONTS.bold, fontSize: 18, color: '#fff', marginBottom: 10 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: '#25252B', borderRadius: 14, padding: 8, marginBottom: 8 },
  thumb: { width: 54, height: 54, borderRadius: 10, backgroundColor: '#3A3A44' },
  rowTitle: { fontFamily: FONTS.semibold, fontSize: 15.5, color: '#fff' },
  rowArtist: { fontFamily: FONTS.regular, fontSize: 13.5, color: 'rgba(255,255,255,0.6)' },
  rowNote: { fontFamily: FONTS.medium, fontSize: 12.5, color: 'rgba(255,255,255,0.5)', paddingRight: 6 },
});
