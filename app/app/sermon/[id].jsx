// A sermon, in the app: watch the video or listen to the audio right here, with the preacher, scripture,
// series and description under it. Video plays inline (file or YouTube page); audio has its own player card.
import { useEffect, useRef, useState } from 'react';
import { Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import ChurchAvatar from '../../components/ChurchAvatar';
import CoverHero from '../../components/CoverHero';
import { AudioPlayerCard, Video } from '../../components/MediaPlayers';
import Pill from '../../components/Pill';
import ScreenHeader, { BackButton, HeaderAction } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import api from '../../lib/api';
import { formatDate, formatDuration } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

export default function SermonScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/sermons/${id}`);
  const insets = useSafeAreaInsets();
  const s = data?.data;
  const [mode, setMode] = useState(null); // 'video' | 'audio' once the member taps play
  const playing = mode ?? (s?.video_url && !s?.audio_url ? 'video' : null);

  // Pressing play counts one listen (so the most listened sermons can be shown on Home).
  const counted = useRef(null);
  useEffect(() => {
    if (playing && s && counted.current !== s.id) {
      counted.current = s.id;
      api.post(`/sermons/${s.id}/listen`).catch(() => {});
    }
  }, [playing, s]);

  const share = () => Share.share({ message: `${s.title}${s.preacher ? ` — ${s.preacher}` : ''}` }).catch(() => {});

  return (
    <View style={styles.screen}>
      <StateView loading={loading} error={error} onRetry={retry}>
        {s ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false}>
            {playing === 'video' && s.video_url ? (
              <View style={{ paddingTop: insets.top, backgroundColor: '#000' }}>
                <Video url={s.video_url} />
                <View style={[styles.floatBack, { top: insets.top + 8 }]}>
                  <BackButton onDark />
                </View>
              </View>
            ) : (
              <CoverHero uri={s.thumbnail_url} height={260} right={<HeaderAction onDark icon="share-social-outline" label="Share" onPress={share} />}>
                {s.series ? <Pill label={s.series} icon="albums-outline" tone="dark" /> : null}
                {s.video_url || s.audio_url ? (
                  <Pressable onPress={() => setMode(s.video_url ? 'video' : 'audio')} style={styles.bigPlay} accessibilityRole="button" accessibilityLabel="Play sermon">
                    <Ionicons name="play" size={34} color={COLORS.primary} style={{ marginLeft: 4 }} />
                  </Pressable>
                ) : null}
              </CoverHero>
            )}

            <View style={styles.body}>
              <AppText style={styles.title}>{s.title}</AppText>
              <Pressable onPress={() => s.church && router.push({ pathname: '/church/[slug]', params: { slug: s.church.slug } })} style={styles.host}>
                <ChurchAvatar church={s.church} size={30} />
                <View style={{ flex: 1 }}>
                  <AppText style={styles.hostName}>{s.preacher || s.church?.name}</AppText>
                  <AppText variant="caption">{[s.preacher ? s.church?.name : null, formatDate(s.published_at)].filter(Boolean).join(' · ')}</AppText>
                </View>
              </Pressable>

              {s.scripture || s.duration_seconds ? (
                <View style={styles.pills}>
                  {s.scripture ? <Pill label={s.scripture} icon="book-outline" /> : null}
                  {s.duration_seconds ? <Pill label={formatDuration(s.duration_seconds)} icon="time-outline" /> : null}
                </View>
              ) : null}

              {s.audio_url ? (
                <View style={{ marginTop: SPACING.md }}>
                  {playing === 'audio' || !s.video_url ? (
                    <AudioPlayerCard key={s.id} url={s.audio_url} title={s.title} duration={s.duration_seconds} autoPlay={mode === 'audio'} onPlay={() => setMode((m) => m ?? 'audio')} />
                  ) : (
                    <Pressable onPress={() => setMode('audio')} style={styles.audioOnly} accessibilityRole="button">
                      <Ionicons name="headset-outline" size={20} color={COLORS.primary} />
                      <AppText style={styles.audioOnlyText}>Listen to the audio instead</AppText>
                    </Pressable>
                  )}
                </View>
              ) : null}

              {!s.video_url && !s.audio_url ? (
                <View style={styles.notice}>
                  <Ionicons name="information-circle-outline" size={20} color={COLORS.gray700} />
                  <AppText style={{ flex: 1, color: COLORS.gray900 }}>There is no recording for this sermon yet.</AppText>
                </View>
              ) : null}

              {s.description ? <AppText style={styles.text}>{s.description}</AppText> : null}
            </View>
          </ScrollView>
        ) : null}
      </StateView>
      {!s && !loading ? <ScreenHeader title="Sermon" /> : null}
    </View>
  );
}


const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  floatBack: { position: 'absolute', left: SPACING.md },
  bigPlay: { alignSelf: 'center', width: 76, height: 76, borderRadius: 38, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center', marginVertical: 14 },
  body: { padding: SPACING.md },
  title: { fontFamily: FONTS.bold, fontSize: 24, lineHeight: 30, color: COLORS.black },
  host: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 12 },
  hostName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  pills: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 14 },
  audioOnly: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 14 },
  audioOnlyText: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.primary },
  notice: { flexDirection: 'row', gap: 10, alignItems: 'center', backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, marginTop: SPACING.md },
  text: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 24, color: COLORS.gray900, marginTop: SPACING.md },
});
