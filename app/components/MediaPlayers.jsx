// Players used by sermons and live services, so media plays inside the app:
//  - VideoPlayer: a direct video file or an HLS (.m3u8) live stream, with native controls (expo-video)
//  - EmbedPlayer: a YouTube / Facebook / Vimeo page shown in a small in-app web view
//  - AudioPlayerCard: sermon audio (expo-audio) with play/pause, back 15 s, forward 30 s and a progress bar
import { useEffect, useRef } from 'react';
import { Platform, Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAudioPlayer, useAudioPlayerStatus } from 'expo-audio';
import { VideoView, useVideoPlayer } from 'expo-video';
import { WebView } from 'react-native-webview';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import { formatDuration } from '../lib/format';
import AppText from './AppText';

/** True for page-style video links (YouTube, Facebook, Vimeo…) that need a web view rather than a video player. */
export function isEmbed(url = '') {
  return /youtube|youtu\.be|facebook\.com|vimeo|\/embed\//i.test(url) && !/\.(mp4|m3u8|mov|webm)(\?|$)/i.test(url);
}

export function VideoPlayer({ url, autoPlay = true, style }) {
  const source = mediaUrl(url);
  const player = useVideoPlayer(source, (p) => {
    if (autoPlay) p.play();
  });

  return <VideoView player={player} style={[styles.video, style]} nativeControls allowsFullscreen allowsPictureInPicture contentFit="contain" />;
}

export function EmbedPlayer({ url, style }) {
  if (Platform.OS === 'web') {
    return (
      <View style={[styles.video, style]}>
        {/* The web preview uses a plain iframe; phones use the web view below. */}
        <iframe title="Video" src={url} style={{ width: '100%', height: '100%', border: 0 }} allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowFullScreen />
      </View>
    );
  }
  return (
    <View style={[styles.video, style]}>
      <WebView source={{ uri: url }} allowsInlineMediaPlayback allowsFullscreenVideo mediaPlaybackRequiresUserAction={false} javaScriptEnabled style={{ backgroundColor: '#000' }} />
    </View>
  );
}

/** Picks the right player for a video address. */
export function Video({ url, ...rest }) {
  return isEmbed(url) ? <EmbedPlayer url={url} {...rest} /> : <VideoPlayer url={url} {...rest} />;
}

export function AudioPlayerCard({ url, title, duration: knownDuration, autoPlay = false, onPlay }) {
  const player = useAudioPlayer(mediaUrl(url));
  const status = useAudioPlayerStatus(player);
  const reported = useRef(false);

  // Tell the screen once when the audio really starts (the sermon page counts it as a listen).
  useEffect(() => {
    if (status.playing && !reported.current) {
      reported.current = true;
      onPlay?.();
    }
  }, [status.playing, onPlay]);

  useEffect(() => {
    if (autoPlay) player.play();
  }, [player, autoPlay]);

  const duration = status.duration || knownDuration || 0;
  const progress = duration ? Math.min(1, (status.currentTime || 0) / duration) : 0;
  const skip = (seconds) => player.seekTo(Math.max(0, Math.min(duration || Infinity, (status.currentTime || 0) + seconds)));

  return (
    <View style={styles.audio}>
      <View style={styles.track}>
        <View style={[styles.fill, { width: `${progress * 100}%` }]} />
      </View>
      <View style={styles.times}>
        <AppText variant="caption" style={{ color: COLORS.primaryPale }}>
          {formatDuration(status.currentTime || 0)}
        </AppText>
        <AppText variant="caption" style={{ color: COLORS.primaryPale }}>
          {formatDuration(duration)}
        </AppText>
      </View>
      <View style={styles.controls}>
        <Pressable onPress={() => skip(-15)} hitSlop={10} accessibilityLabel="Back 15 seconds">
          <Ionicons name="play-back" size={26} color={COLORS.white} />
        </Pressable>
        <Pressable onPress={() => (status.playing ? player.pause() : player.play())} style={styles.play} accessibilityLabel={status.playing ? 'Pause' : 'Play'}>
          <Ionicons name={status.playing ? 'pause' : 'play'} size={30} color={COLORS.primary} style={status.playing ? null : { marginLeft: 3 }} />
        </Pressable>
        <Pressable onPress={() => skip(30)} hitSlop={10} accessibilityLabel="Forward 30 seconds">
          <Ionicons name="play-forward" size={26} color={COLORS.white} />
        </Pressable>
      </View>
      {title ? (
        <AppText variant="caption" style={{ color: COLORS.primaryPale, textAlign: 'center', marginTop: 10 }} numberOfLines={1}>
          {title}
        </AppText>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  video: { width: '100%', aspectRatio: 16 / 9, backgroundColor: '#000', overflow: 'hidden' },
  audio: { backgroundColor: COLORS.primaryDark, borderRadius: RADIUS.xl - 4, padding: SPACING.md, ...SHADOW },
  track: { height: 5, borderRadius: 3, backgroundColor: 'rgba(255,255,255,0.25)', overflow: 'hidden' },
  fill: { height: 5, backgroundColor: COLORS.white },
  times: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 6 },
  controls: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 34, marginTop: 8 },
  play: { width: 64, height: 64, borderRadius: 32, backgroundColor: COLORS.white, alignItems: 'center', justifyContent: 'center' },
});
