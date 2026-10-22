// Players used by sermons and live services, so media plays inside the app:
//  - VideoPlayer: a direct video file or an HLS (.m3u8) live stream, with native controls (expo-video)
//  - EmbedPlayer: a YouTube / Facebook / Vimeo page shown in a small in-app web view. YouTube refuses to play for a page that does not say which site is
//    showing it ("Video player configuration error", error 153), so the web view shows a small page of ours with the YouTube player inside it and
//    the site's address as its base; the player reports its errors back, so the screen can offer "Watch on YouTube" instead of a black box.
//  - AudioPlayerCard: sermon audio (expo-audio) with play/pause, back 15 s, forward 30 s and a progress bar
import { useEffect, useRef } from 'react';
import { Platform, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
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

/** The page address of a YouTube video from its embed address, so a member can open it in YouTube when the embedded player will not play it. */
export function youtubeWatchUrl(url = '') {
  const id = String(url).match(/(?:youtube(?:-nocookie)?\.com\/(?:embed\/|watch\?v=|live\/)|youtu\.be\/)([\w-]{11})/)?.[1];

  return id ? `https://www.youtube.com/watch?v=${id}` : null;
}

/** The site that shows the embedded players. YouTube (and some Vimeo videos) check it: a player with no site to point to is refused. */
const EMBED_ORIGIN = 'https://faithfellows.com';

const isYoutubeEmbed = (url = '') => /youtube(?:-nocookie)?\.com\/embed\//i.test(url);

/** A page with the YouTube player in it, and a script that tells the app when the player says it cannot play the video. */
function youtubePage(url) {
  const src = `${url}${url.includes('?') ? '&' : '?'}enablejsapi=1&playsinline=1&rel=0&origin=${encodeURIComponent(EMBED_ORIGIN)}`.replace(/&/g, '&amp;');

  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>html,body{margin:0;height:100%;background:#000;overflow:hidden}iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0}</style></head><body>
<iframe id="p" src="${src}" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
<script>
(function () {
  var frame = document.getElementById('p');
  var tell = function (text) { if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage(text); };
  frame.addEventListener('load', function () {
    try { frame.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: 1, channel: 'widget' }), '*'); } catch (e) {}
  });
  window.addEventListener('message', function (e) {
    try {
      var d = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
      if (d && d.event === 'onError') tell('error:' + d.info);
      else if (d && (d.event === 'onReady' || d.event === 'initialDelivery' || d.event === 'infoDelivery' || d.event === 'onStateChange')) tell('ready');
    } catch (err) {}
  });
})();
</script></body></html>`;
}

/** `onProblem` is called when the video cannot be played (the picture never starts, or the page says no), so the screen can offer the audio instead. */
export function VideoPlayer({ url, autoPlay = true, style, onProblem }) {
  const source = mediaUrl(url);
  const player = useVideoPlayer(source, (p) => {
    if (autoPlay) p.play();
  });

  useEffect(() => {
    const sub = player.addListener('statusChange', ({ status }) => status === 'error' && onProblem?.());

    return () => sub.remove();
  }, [player, onProblem]);

  return <VideoView player={player} style={[styles.video, style]} nativeControls allowsFullscreen allowsPictureInPicture contentFit="contain" />;
}

export function EmbedPlayer({ url, style, onProblem }) {
  const youtube = isYoutubeEmbed(url);
  const ready = useRef(false);
  const problem = useRef(onProblem);
  problem.current = onProblem; // the screen's callback changes on every draw; the timer below must not restart each time

  // A YouTube player that never says it is ready (it stays black) is as good as an error: the screen can offer YouTube itself.
  useEffect(() => {
    ready.current = false;
    if (!youtube || Platform.OS === 'web') return undefined;
    const timer = setTimeout(() => !ready.current && problem.current?.(), 15000);

    return () => clearTimeout(timer);
  }, [url, youtube]);

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
      <WebView
        source={youtube ? { html: youtubePage(url), baseUrl: EMBED_ORIGIN } : { uri: url, headers: { Referer: EMBED_ORIGIN } }}
        originWhitelist={['*']}
        allowsInlineMediaPlayback
        allowsFullscreenVideo
        mediaPlaybackRequiresUserAction={false}
        javaScriptEnabled
        domStorageEnabled
        setSupportMultipleWindows={false}
        onMessage={(e) => {
          const said = String(e.nativeEvent.data);
          if (said === 'ready') ready.current = true;
          else if (said.startsWith('error')) problem.current?.();
        }}
        onError={() => onProblem?.()}
        onHttpError={() => onProblem?.()}
        style={{ backgroundColor: '#000' }}
      />
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
