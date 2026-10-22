// A sermon, in the app: listen to the audio (it plays in the background, no video and no YouTube needed) or watch the video right here, with the
// preacher, scripture, series and description under it. If the video will not play, the audio is offered instead; a YouTube video can also be
// opened in YouTube.
import { useEffect, useRef, useState } from 'react';
import { Linking, Pressable, ScrollView, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router, useLocalSearchParams } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../../components/AppText';
import ChurchAvatar from '../../components/ChurchAvatar';
import CoverHero from '../../components/CoverHero';
import { Video, youtubeWatchUrl } from '../../components/MediaPlayers';
import Pill from '../../components/Pill';
import ScreenHeader, { BackButton, HeaderAction } from '../../components/ScreenHeader';
import SermonAudioCard from '../../components/sermons/SermonAudioCard';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import api from '../../lib/api';
import { formatDate, formatDuration } from '../../lib/format';
import { stopPodcast } from '../../lib/podcastPlayer';
import { playSermonAudio, useSermonAudio } from '../../lib/sermonAudio';
import { useFetch } from '../../lib/useFetch';

export default function SermonScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/sermons/${id}`);
  const insets = useSafeAreaInsets();
  const s = data?.data;
  const audio = useSermonAudio(s);
  const [watching, setWatching] = useState(false);
  const [videoProblem, setVideoProblem] = useState(false);
  const videoOnly = Boolean(s?.video_url && !s?.audio_url);
  const showVideo = Boolean(s?.video_url) && (watching || videoOnly);

  // Playing the video counts one listen (the audio counts its own, in the player), so the most listened sermons can be shown.
  const counted = useRef(null);
  useEffect(() => {
    if (showVideo && s && counted.current !== s.id) {
      counted.current = s.id;
      api.post(`/sermons/${s.id}/listen`).catch(() => {});
    }
  }, [showVideo, s]);

  const share = () => Share.share({ message: `${s.title}${s.preacher ? ` — ${s.preacher}` : ''}` }).catch(() => {});
  const watch = () => {
    // Video and audio never play together: the audio of this sermon stops when its video starts.
    if (audio.current) stopPodcast();
    setVideoProblem(false);
    setWatching(true);
  };
  const listen = () => {
    setWatching(false);
    playSermonAudio(s);
  };
  const youtube = s ? youtubeWatchUrl(s.video_url) : null;

  return (
    <View style={styles.screen}>
      <StateView loading={loading} error={error} onRetry={retry}>
        {s ? (
          <ScrollView contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false}>
            {showVideo ? (
              <View style={{ paddingTop: insets.top, backgroundColor: '#000' }}>
                <Video url={s.video_url} onProblem={() => setVideoProblem(true)} />
                <View style={[styles.floatBack, { top: insets.top + 8 }]}>
                  <BackButton onDark />
                </View>
              </View>
            ) : (
              <CoverHero uri={s.thumbnail_url} height={260} right={<HeaderAction onDark icon="share-social-outline" label="Share" onPress={share} />}>
                {s.series ? <Pill label={s.series} icon="albums-outline" tone="dark" /> : null}
                {s.audio_url || s.video_url ? (
                  <Pressable onPress={s.audio_url ? listen : watch} style={styles.bigPlay} accessibilityRole="button" accessibilityLabel={s.audio_url ? 'Listen to the sermon' : 'Play the video'}>
                    <Ionicons name={s.audio_url ? 'headset' : 'play'} size={s.audio_url ? 32 : 34} color={COLORS.primary} style={s.audio_url ? null : { marginLeft: 4 }} />
                  </Pressable>
                ) : null}
              </CoverHero>
            )}

            {showVideo && videoProblem && (s.audio_url || youtube) ? (
              <View style={styles.problem}>
                <Ionicons name="alert-circle-outline" size={22} color={COLORS.primary} />
                <View style={{ flex: 1 }}>
                  <AppText style={styles.problemTitle}>The video could not play</AppText>
                  <AppText style={styles.problemText}>{s.audio_url ? 'You can listen to this sermon instead.' : 'You can watch it on YouTube.'}</AppText>
                </View>
                <Pressable onPress={s.audio_url ? listen : () => Linking.openURL(youtube).catch(() => {})} style={styles.problemButton} accessibilityRole="button">
                  <AppText style={styles.problemButtonText}>{s.audio_url ? 'Listen' : 'Watch'}</AppText>
                </Pressable>
              </View>
            ) : null}

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
                  <SermonAudioCard sermon={s} />
                </View>
              ) : null}

              {s.video_url && s.audio_url && !showVideo ? (
                <Pressable onPress={watch} style={styles.secondary} accessibilityRole="button">
                  <Ionicons name="videocam-outline" size={20} color={COLORS.primary} />
                  <AppText style={styles.secondaryText}>Watch the video</AppText>
                </Pressable>
              ) : null}
              {showVideo && s.audio_url && !audio.current ? (
                <Pressable onPress={listen} style={styles.secondary} accessibilityRole="button">
                  <Ionicons name="headset-outline" size={20} color={COLORS.primary} />
                  <AppText style={styles.secondaryText}>Listen to the audio instead</AppText>
                </Pressable>
              ) : null}
              {showVideo && youtube ? (
                <Pressable onPress={() => Linking.openURL(youtube).catch(() => {})} style={styles.linkRow} accessibilityRole="link">
                  <Ionicons name="logo-youtube" size={18} color={COLORS.gray700} />
                  <AppText style={styles.linkText}>Video not playing? Open it in YouTube</AppText>
                </Pressable>
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
  secondary: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, marginTop: SPACING.sm, borderWidth: 1, borderColor: COLORS.primaryPale },
  secondaryText: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.primary },
  linkRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, paddingVertical: 12 },
  linkText: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.gray700, textDecorationLine: 'underline' },
  problem: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: COLORS.primaryMist, padding: 14, paddingHorizontal: SPACING.md },
  problemTitle: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primary },
  problemText: { fontFamily: FONTS.regular, fontSize: 12.5, color: COLORS.gray900 },
  problemButton: { backgroundColor: COLORS.primary, borderRadius: RADIUS.full, paddingHorizontal: 18, paddingVertical: 9 },
  problemButtonText: { fontFamily: FONTS.bold, fontSize: 13, color: '#fff' },
  notice: { flexDirection: 'row', gap: 10, alignItems: 'center', backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, marginTop: SPACING.md },
  text: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 24, color: COLORS.gray900, marginTop: SPACING.md },
});
