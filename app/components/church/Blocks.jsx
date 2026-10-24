// The building blocks of a church's page: a titled section, the sermon you can play on the spot, an event, a giving campaign, the church's own radio channel
// and its songs and teaching, an update with photos, a notice, the devotional, the live service and the prayer card. Each plays or opens in place: a sermon or a
// song starts in the app's player right where you press it, and keeps going while you look around the page.
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import { mediaUrl } from '../../lib/api';
import { playChurchTrack, useChurchTrack } from '../../lib/churchAudio';
import { dateTile, dayAndTime, formatDuration, formatMoney, timeAgo } from '../../lib/format';
import { togglePodcast } from '../../lib/podcastPlayer';
import { playChannel, useRadio } from '../../lib/radio';
import { playSermonAudio, useSermonAudio } from '../../lib/sermonAudio';
import AppText from '../AppText';
import Equalizer from '../radio/Equalizer';
import LiveBadge from '../radio/LiveBadge';

const MARK = require('../../assets/logo-mark.png');

/** A titled block of the page, with an optional "See all" that moves to that tab. */
export function Section({ title, action, onAction, children, style }) {
  return (
    <View style={[styles.section, style]}>
      <View style={styles.sectionHead}>
        <View style={styles.bar} />
        <AppText style={styles.sectionTitle} numberOfLines={1}>
          {title}
        </AppText>
        {action ? (
          <Pressable onPress={onAction} hitSlop={8} accessibilityRole="button" accessibilityLabel={`${action}: ${title}`} style={styles.action}>
            <AppText style={styles.actionText}>{action}</AppText>
            <Ionicons name="chevron-forward" size={14} color={COLORS.primary} />
          </Pressable>
        ) : null}
      </View>
      {children}
    </View>
  );
}

export function Card({ children, style }) {
  return <View style={[styles.card, style]}>{children}</View>;
}

/** Nothing here yet, said kindly. */
export function EmptyNote({ icon = 'sparkles-outline', title, text }) {
  return (
    <View style={styles.empty}>
      <View style={styles.emptyIcon}>
        <Ionicons name={icon} size={26} color={COLORS.primary} />
      </View>
      <AppText style={styles.emptyTitle}>{title}</AppText>
      {text ? <AppText style={styles.emptyText}>{text}</AppText> : null}
    </View>
  );
}

/** A picture with the brand colour behind it, so a missing or slow picture never leaves a grey box. */
function Picture({ uri, style, children }) {
  return (
    <View style={[styles.picture, style]}>
      <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      <Image source={MARK} contentFit="contain" style={styles.pictureMark} />
      {uri ? <Image source={{ uri: mediaUrl(uri) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : null}
      {children}
    </View>
  );
}

/** The round play / pause button of a sermon, a song or the channel. */
function PlayCircle({ playing, loading, onPress, size = 44, label, light = false }) {
  return (
    <Pressable
      onPress={onPress}
      hitSlop={6}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={({ pressed }) => [styles.play, { width: size, height: size, borderRadius: size / 2 }, light ? styles.playLight : styles.playDark, pressed && { opacity: 0.88 }]}
    >
      {loading ? (
        <ActivityIndicator color={light ? COLORS.primary : '#fff'} />
      ) : (
        <Ionicons name={playing ? 'pause' : 'play'} size={size * 0.46} color={light ? COLORS.primary : '#fff'} style={playing ? null : { marginLeft: size * 0.05 }} />
      )}
    </Pressable>
  );
}

const sermonMeta = (s) => [s.preacher, s.duration_seconds ? formatDuration(s.duration_seconds) : null, s.published_at ? timeAgo(s.published_at) : null].filter(Boolean).join(' · ');
const openSermon = (s) => router.push({ pathname: '/sermon/[id]', params: { id: s.id } });

/** A sermon in a list: its picture, name and who preached it, and a round button that plays the recording right here. */
export function SermonRow({ sermon, list }) {
  const { current, playing, loading, toggle } = useSermonAudio(sermon);

  return (
    <View style={[styles.row, current && styles.rowOn]}>
      <Pressable onPress={() => openSermon(sermon)} style={styles.rowMain} accessibilityRole="button" accessibilityLabel={`Sermon: ${sermon.title}. Open it`}>
        <Picture uri={sermon.thumbnail_url} style={styles.thumb} />
        <View style={{ flex: 1, gap: 2 }}>
          <AppText style={styles.rowTitle} numberOfLines={2}>
            {sermon.title}
          </AppText>
          <AppText style={styles.rowMeta} numberOfLines={1}>
            {sermonMeta(sermon)}
          </AppText>
          {playing ? (
            <View style={styles.now}>
              <Equalizer playing height={12} color={COLORS.primary} />
              <AppText style={styles.nowText}>Playing</AppText>
            </View>
          ) : sermon.video_url && !sermon.audio_url ? (
            <AppText style={styles.rowTag}>Video</AppText>
          ) : null}
        </View>
      </Pressable>
      {sermon.audio_url ? (
        <PlayCircle playing={playing} loading={loading} label={playing ? `Pause ${sermon.title}` : `Play ${sermon.title}`} onPress={() => (current ? toggle() : playSermonAudio(sermon, list ?? [sermon]))} />
      ) : (
        <Pressable onPress={() => openSermon(sermon)} hitSlop={8} accessibilityRole="button" accessibilityLabel={`Watch ${sermon.title}`}>
          <Ionicons name={sermon.video_url ? 'videocam' : 'chevron-forward'} size={22} color={COLORS.primary} />
        </Pressable>
      )}
    </View>
  );
}

/** The latest sermon, big: its picture with the play button on it. */
export function SermonFeature({ sermon, list }) {
  const { current, playing, loading, toggle } = useSermonAudio(sermon);

  return (
    <View style={styles.feature}>
      <Pressable onPress={() => openSermon(sermon)} accessibilityRole="button" accessibilityLabel={`Sermon: ${sermon.title}. Open it`}>
        <Picture uri={sermon.thumbnail_url} style={styles.featurePicture}>
          <LinearGradient colors={['rgba(20,6,12,0)', 'rgba(20,6,12,0.7)']} locations={[0.3, 1]} style={StyleSheet.absoluteFill} />
          <View style={styles.badge}>
            <AppText style={styles.badgeText}>LATEST SERMON</AppText>
          </View>
          <View style={styles.featureText}>
            <AppText style={styles.featureTitle} numberOfLines={2}>
              {sermon.title}
            </AppText>
            <AppText style={styles.featureMeta} numberOfLines={1}>
              {[sermon.preacher, sermon.scripture].filter(Boolean).join(' · ') || sermonMeta(sermon)}
            </AppText>
          </View>
        </Picture>
      </Pressable>
      <View style={styles.featureBar}>
        {sermon.audio_url ? (
          <Pressable onPress={() => (current ? toggle() : playSermonAudio(sermon, list ?? [sermon]))} style={({ pressed }) => [styles.featureButton, pressed && { opacity: 0.9 }]} accessibilityRole="button" accessibilityLabel={playing ? 'Pause the sermon' : 'Listen to the sermon'}>
            {loading ? <ActivityIndicator color="#fff" /> : <Ionicons name={playing ? 'pause' : 'headset'} size={18} color="#fff" />}
            <AppText style={styles.featureButtonText}>{playing ? 'Pause' : current ? 'Resume' : 'Listen'}</AppText>
          </Pressable>
        ) : null}
        <Pressable onPress={() => openSermon(sermon)} style={({ pressed }) => [styles.featureButton, styles.featureGhost, pressed && { opacity: 0.9 }]} accessibilityRole="button" accessibilityLabel={sermon.video_url ? 'Watch the sermon' : 'Open the sermon'}>
          <Ionicons name={sermon.video_url ? 'videocam' : 'book'} size={18} color={COLORS.primary} />
          <AppText style={[styles.featureButtonText, { color: COLORS.primary }]}>{sermon.video_url ? 'Watch' : 'Notes'}</AppText>
        </Pressable>
      </View>
    </View>
  );
}

/** An event: the date, the name, when and where. */
export function EventRow({ event }) {
  const tile = dateTile(event.starts_at);

  return (
    <Pressable onPress={() => router.push({ pathname: '/event/[id]', params: { id: event.id } })} style={({ pressed }) => [styles.row, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Event: ${event.title}`}>
      <View style={styles.tile}>
        <AppText style={styles.tileMonth}>{tile.month}</AppText>
        <AppText style={styles.tileDay}>{tile.day}</AppText>
      </View>
      <View style={{ flex: 1, gap: 2 }}>
        <AppText style={styles.rowTitle} numberOfLines={2}>
          {event.title}
        </AppText>
        <AppText style={styles.rowMeta} numberOfLines={1}>
          {[dayAndTime(event.starts_at), event.is_online ? 'Online' : event.location?.name].filter(Boolean).join(' · ')}
        </AppText>
        {event.is_paid ? <AppText style={styles.rowTag}>Tickets</AppText> : null}
      </View>
      <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />
    </Pressable>
  );
}

/** A giving campaign: what it is for, how far it has got, and a button to give. */
export function CampaignCard({ campaign }) {
  const pct = Math.max(0, Math.min(100, Math.round(campaign.percent ?? 0)));

  return (
    <Pressable onPress={() => router.push({ pathname: '/campaign/[id]', params: { id: campaign.id } })} style={({ pressed }) => [styles.campaign, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Campaign: ${campaign.title}`}>
      <View style={styles.campaignTop}>
        <Picture uri={campaign.cover_url} style={styles.campaignPicture} />
        <View style={{ flex: 1, gap: 2 }}>
          <AppText style={styles.rowTitle} numberOfLines={2}>
            {campaign.title}
          </AppText>
          {campaign.description ? (
            <AppText style={styles.rowMeta} numberOfLines={2}>
              {campaign.description}
            </AppText>
          ) : null}
        </View>
      </View>
      <View style={styles.track}>
        <View style={[styles.fill, { width: `${Math.max(pct, 3)}%` }]} />
      </View>
      <View style={styles.campaignFoot}>
        <AppText style={styles.raised} numberOfLines={1}>
          {formatMoney(campaign.raised, campaign.currency)} <AppText style={styles.goal}>of {formatMoney(campaign.goal, campaign.currency)} · {pct}%</AppText>
        </AppText>
        <View style={styles.giveButton}>
          <Ionicons name="heart" size={14} color="#fff" />
          <AppText style={styles.giveText}>Give</AppText>
        </View>
      </View>
    </Pressable>
  );
}

/** The live service, or the next one. */
export function LiveCard({ live, next }) {
  const stream = live ?? next;
  if (!stream) return null;

  return (
    <Pressable onPress={() => router.push({ pathname: '/live/[id]', params: { id: stream.id } })} style={({ pressed }) => [styles.live, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`${live ? 'Live now' : 'Next live service'}: ${stream.title}`}>
      <LinearGradient colors={['#2A0A18', '#B02A52']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      <Ionicons name="radio" size={110} color="#fff" style={styles.liveMark} />
      {live ? <LiveBadge onAir /> : <AppText style={styles.liveKicker}>NEXT LIVE SERVICE</AppText>}
      <AppText style={styles.liveTitle} numberOfLines={2}>
        {stream.title}
      </AppText>
      <View style={styles.liveFoot}>
        <AppText style={styles.liveWhen}>{live ? 'Happening now' : dayAndTime(stream.scheduled_at)}</AppText>
        <View style={styles.liveButton}>
          <AppText style={styles.liveButtonText}>{live ? 'Join now' : 'See details'}</AppText>
          <Ionicons name="arrow-forward" size={14} color={COLORS.primary} />
        </View>
      </View>
    </Pressable>
  );
}

/** The church's own radio channel: it plays round the clock, and everyone who follows hears the same thing. */
export function ChannelCard({ channel, church, following, onFollow }) {
  const { stationId, phase } = useRadio();
  const on = stationId === channel.slug && (phase === 'playing' || phase === 'loading' || phase === 'paused');
  const playing = on && phase === 'playing';
  const loading = on && phase === 'loading';

  return (
    <View style={styles.channel}>
      <LinearGradient colors={['#4527A0', '#8E6BD6']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      <Image source={MARK} contentFit="contain" style={styles.channelMark} />
      <View style={styles.channelTop}>
        <LiveBadge onAir={playing} />
        <Equalizer playing={playing} height={22} />
      </View>
      <AppText style={styles.channelName} numberOfLines={2}>
        {channel.name}
      </AppText>
      <AppText style={styles.channelTag} numberOfLines={2}>
        {channel.tagline} · {channel.songs} {channel.songs === 1 ? 'recording' : 'recordings'}
      </AppText>
      {channel.can_listen ? (
        <Pressable onPress={() => playChannel(channel.slug)} style={({ pressed }) => [styles.channelButton, pressed && { opacity: 0.9 }]} accessibilityRole="button" accessibilityLabel={playing ? `Stop ${channel.name}` : `Listen live to ${channel.name}`}>
          {loading ? <ActivityIndicator color="#4527A0" /> : <Ionicons name={playing ? 'stop' : 'play'} size={18} color="#4527A0" />}
          <AppText style={styles.channelButtonText}>{playing ? 'Stop' : 'Listen live'}</AppText>
        </Pressable>
      ) : (
        <Pressable onPress={onFollow} style={({ pressed }) => [styles.channelButton, pressed && { opacity: 0.9 }]} accessibilityRole="button" accessibilityLabel={`Follow ${church.name} to listen`}>
          <Ionicons name="lock-closed" size={16} color="#4527A0" />
          <AppText style={styles.channelButtonText}>{following ? 'Open' : `Follow to listen`}</AppText>
        </Pressable>
      )}
    </View>
  );
}

/** One song or teaching of the church's channel, played on its own. */
export function TrackRow({ track, list, church }) {
  const { current, playing, loading, toggle } = useChurchTrack(track);

  return (
    <View style={[styles.row, current && styles.rowOn]}>
      <Picture uri={track.cover_url} style={styles.thumbSmall} />
      <View style={{ flex: 1, gap: 2 }}>
        <AppText style={styles.rowTitle} numberOfLines={2}>
          {track.title}
        </AppText>
        <AppText style={styles.rowMeta} numberOfLines={1}>
          {[track.artist, track.duration ? formatDuration(track.duration) : null].filter(Boolean).join(' · ')}
        </AppText>
        {playing ? (
          <View style={styles.now}>
            <Equalizer playing height={12} color={COLORS.primary} />
            <AppText style={styles.nowText}>Playing</AppText>
          </View>
        ) : null}
      </View>
      <PlayCircle playing={playing} loading={loading} label={playing ? `Pause ${track.title}` : `Play ${track.title}`} onPress={() => (current ? toggle() : playChurchTrack(track, list, church))} />
    </View>
  );
}

/** An update from the church, with its photos. */
export function UpdateCard({ update, church }) {
  const images = update.images ?? [];

  return (
    <Card style={{ marginBottom: 12 }}>
      <View style={styles.updateHead}>
        <View style={styles.updateAvatar}>
          <AppText style={styles.updateInitial}>{String(update.author || church.name || '?').charAt(0).toUpperCase()}</AppText>
        </View>
        <View style={{ flex: 1 }}>
          <AppText style={styles.rowTitle} numberOfLines={1}>
            {update.author || church.name}
          </AppText>
          <AppText style={styles.rowMeta}>{update.published_at ? timeAgo(update.published_at) : ''}</AppText>
        </View>
        {update.is_pinned ? <Ionicons name="pin" size={16} color={COLORS.primary} /> : null}
      </View>
      {update.body ? <AppText style={styles.updateBody}>{update.body}</AppText> : null}
      {images.length ? (
        <View style={styles.photos}>
          {images.slice(0, 4).map((uri, i) => (
            <Image key={`${uri}-${i}`} source={{ uri: mediaUrl(uri) }} style={[styles.photo, images.length === 1 && styles.photoOne]} contentFit="cover" transition={250} />
          ))}
        </View>
      ) : null}
    </Card>
  );
}

/** A notice from the church; an urgent one is red. */
export function NoticeCard({ notice }) {
  return (
    <Pressable onPress={() => router.push({ pathname: '/announcement/[id]', params: { id: notice.id } })} style={({ pressed }) => [styles.notice, notice.urgent && styles.noticeUrgent, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`${notice.urgent ? 'Urgent notice' : 'Notice'}: ${notice.title}`}>
      <View style={[styles.noticeIcon, notice.urgent && { backgroundColor: '#D63031' }]}>
        <Ionicons name={notice.urgent ? 'alert' : 'megaphone'} size={18} color="#fff" />
      </View>
      <View style={{ flex: 1, gap: 2 }}>
        <AppText style={[styles.rowTitle, notice.urgent && { color: COLORS.dangerInk }]} numberOfLines={2}>
          {notice.title}
        </AppText>
        {notice.body ? (
          <AppText style={styles.rowMeta} numberOfLines={2}>
            {notice.body}
          </AppText>
        ) : null}
      </View>
    </Pressable>
  );
}

/** The devotional of the day. */
export function DevotionalCard({ devotional }) {
  return (
    <Pressable onPress={() => router.push({ pathname: '/devotional/[id]', params: { id: devotional.id } })} style={({ pressed }) => [styles.devotional, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Devotional: ${devotional.title}`}>
      <LinearGradient colors={[COLORS.goldFrom, COLORS.goldTo]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />
      <Ionicons name="book" size={90} color="#C77700" style={styles.devotionalMark} />
      <AppText style={styles.devotionalKicker}>DEVOTIONAL</AppText>
      <AppText style={styles.devotionalTitle} numberOfLines={2}>
        {devotional.title}
      </AppText>
      {devotional.scripture_text || devotional.excerpt ? (
        <AppText style={styles.devotionalText} numberOfLines={3}>
          {devotional.scripture_text ? `“${devotional.scripture_text}”` : devotional.excerpt}
        </AppText>
      ) : null}
      <View style={styles.devotionalFoot}>
        <AppText style={styles.devotionalRef} numberOfLines={1}>
          {devotional.scripture}
        </AppText>
        <AppText style={styles.devotionalRead}>Read it</AppText>
      </View>
    </Pressable>
  );
}

/** Prayer requests are waiting. */
export function PrayerCard({ count }) {
  return (
    <Pressable onPress={() => router.push('/prayer')} style={({ pressed }) => [styles.prayer, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel="Open the prayer wall">
      <View style={styles.prayerIcon}>
        <Ionicons name="hand-left" size={22} color="#fff" />
      </View>
      <View style={{ flex: 1 }}>
        <AppText style={styles.rowTitle}>{count > 0 ? `${count} ${count === 1 ? 'request is' : 'requests are'} waiting for prayer` : 'Pray with the church'}</AppText>
        <AppText style={styles.rowMeta}>Open the prayer wall</AppText>
      </View>
      <Ionicons name="chevron-forward" size={18} color={COLORS.primary} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.lg },
  sectionHead: { flexDirection: 'row', alignItems: 'center', gap: 9, marginBottom: 12 },
  bar: { width: 4, height: 20, borderRadius: 2, backgroundColor: COLORS.primary },
  sectionTitle: { flex: 1, fontFamily: FONTS.display, fontSize: 18, color: SOFT.ink },
  action: { flexDirection: 'row', alignItems: 'center', gap: 2 },
  actionText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  card: { backgroundColor: SOFT.glass, borderRadius: 24, borderWidth: 1.5, borderColor: SOFT.glassBorder, padding: 16, ...SOFT_SHADOW },
  empty: { alignItems: 'center', paddingVertical: 36, paddingHorizontal: 24 },
  emptyIcon: { width: 56, height: 56, borderRadius: 28, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center', marginBottom: 12 },
  emptyTitle: { fontFamily: FONTS.bold, fontSize: 16, color: SOFT.ink, textAlign: 'center' },
  emptyText: { fontFamily: FONTS.regular, fontSize: 13.5, lineHeight: 20, color: SOFT.muted, textAlign: 'center', marginTop: 4 },
  picture: { overflow: 'hidden', backgroundColor: COLORS.primaryDark },
  pictureMark: { position: 'absolute', right: '-10%', top: '-10%', width: '90%', height: '90%', tintColor: '#fff', opacity: 0.1 },
  play: { alignItems: 'center', justifyContent: 'center' },
  playDark: { backgroundColor: COLORS.primary },
  playLight: { backgroundColor: '#fff' },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: SOFT.glass, borderRadius: 22, borderWidth: 1.5, borderColor: SOFT.glassBorder, padding: 10, marginBottom: 10, ...SOFT_SHADOW, shadowOpacity: 0.16 },
  rowOn: { borderColor: COLORS.primaryPale, backgroundColor: COLORS.selected },
  rowMain: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 12 },
  rowTitle: { fontFamily: FONTS.bold, fontSize: 15, lineHeight: 20, color: SOFT.ink },
  rowMeta: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 17, color: SOFT.muted },
  rowTag: { alignSelf: 'flex-start', fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 0.8, color: COLORS.primary, backgroundColor: COLORS.primaryMist, borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2, marginTop: 3, overflow: 'hidden' },
  thumb: { width: 66, height: 66, borderRadius: 16 },
  thumbSmall: { width: 54, height: 54, borderRadius: 14 },
  now: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 3 },
  nowText: { fontFamily: FONTS.bold, fontSize: 11.5, color: COLORS.primary },
  badge: { position: 'absolute', left: 12, top: 12, backgroundColor: 'rgba(20,6,12,0.5)', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4 },
  badgeText: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 1.3, color: '#fff' },
  feature: { backgroundColor: SOFT.glass, borderRadius: 26, borderWidth: 1.5, borderColor: SOFT.glassBorder, overflow: 'hidden', ...SOFT_SHADOW },
  featurePicture: { height: 190 },
  featureText: { position: 'absolute', left: 16, right: 16, bottom: 14 },
  featureTitle: { fontFamily: FONTS.bold, fontSize: 20, lineHeight: 25, color: '#fff' },
  featureMeta: { fontFamily: FONTS.medium, fontSize: 13, color: 'rgba(255,255,255,0.85)', marginTop: 2 },
  featureBar: { flexDirection: 'row', gap: 10, padding: 12 },
  featureButton: { flex: 1, minHeight: 44, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderRadius: 999, backgroundColor: COLORS.primary },
  featureGhost: { backgroundColor: COLORS.primaryMist },
  featureButtonText: { fontFamily: FONTS.bold, fontSize: 14.5, color: '#fff' },
  tile: { width: 54, paddingVertical: 7, borderRadius: 15, backgroundColor: COLORS.primaryMist, alignItems: 'center' },
  tileMonth: { fontFamily: FONTS.bold, fontSize: 10.5, letterSpacing: 0.8, color: COLORS.primary },
  tileDay: { fontFamily: FONTS.bold, fontSize: 21, lineHeight: 24, color: SOFT.ink },
  campaign: { backgroundColor: SOFT.glass, borderRadius: 24, borderWidth: 1.5, borderColor: SOFT.glassBorder, padding: 14, marginBottom: 12, ...SOFT_SHADOW, shadowOpacity: 0.18 },
  campaignTop: { flexDirection: 'row', gap: 12, marginBottom: 12 },
  campaignPicture: { width: 62, height: 62, borderRadius: 16 },
  track: { height: 8, borderRadius: 4, backgroundColor: 'rgba(18,112,79,0.16)', overflow: 'hidden' },
  fill: { height: 8, borderRadius: 4, backgroundColor: '#12704F' },
  campaignFoot: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginTop: 10 },
  raised: { flex: 1, fontFamily: FONTS.bold, fontSize: 14, color: SOFT.ink },
  goal: { fontFamily: FONTS.regular, fontSize: 12.5, color: SOFT.muted },
  giveButton: { flexDirection: 'row', alignItems: 'center', gap: 6, height: 34, paddingHorizontal: 16, borderRadius: 999, backgroundColor: '#12704F' },
  giveText: { fontFamily: FONTS.bold, fontSize: 13.5, color: '#fff' },
  live: { borderRadius: 26, overflow: 'hidden', padding: 18, gap: 8, borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.5)' },
  liveMark: { position: 'absolute', right: -14, top: -10, opacity: 0.14 },
  liveKicker: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.4, color: 'rgba(255,255,255,0.85)' },
  liveTitle: { fontFamily: FONTS.bold, fontSize: 21, lineHeight: 27, color: '#fff' },
  liveFoot: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginTop: 4 },
  liveWhen: { flex: 1, fontFamily: FONTS.medium, fontSize: 13, color: 'rgba(255,255,255,0.88)' },
  liveButton: { flexDirection: 'row', alignItems: 'center', gap: 6, height: 36, paddingHorizontal: 14, borderRadius: 999, backgroundColor: '#fff' },
  liveButtonText: { fontFamily: FONTS.bold, fontSize: 13.5, color: COLORS.primary },
  channel: { borderRadius: 26, overflow: 'hidden', padding: 18, gap: 6, borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.45)' },
  channelMark: { position: 'absolute', right: -40, top: -36, width: 220, height: 220, tintColor: '#fff', opacity: 0.12 },
  channelTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 10 },
  channelName: { fontFamily: FONTS.bold, fontSize: 22, lineHeight: 27, color: '#fff' },
  channelTag: { fontFamily: FONTS.medium, fontSize: 13.5, lineHeight: 19, color: 'rgba(255,255,255,0.88)' },
  channelButton: { flexDirection: 'row', alignItems: 'center', alignSelf: 'flex-start', gap: 8, height: 42, paddingHorizontal: 20, borderRadius: 999, backgroundColor: '#fff', marginTop: 10 },
  channelButtonText: { fontFamily: FONTS.bold, fontSize: 14.5, color: '#4527A0' },
  updateHead: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 10 },
  updateAvatar: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  updateInitial: { fontFamily: FONTS.bold, fontSize: 16, color: '#fff' },
  updateBody: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 22, color: COLORS.ink },
  photos: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 10 },
  photo: { width: '48.8%', aspectRatio: 1, borderRadius: 14, backgroundColor: COLORS.gray100 },
  photoOne: { width: '100%', aspectRatio: 1.5 },
  notice: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.selected, borderRadius: 20, padding: 12, marginBottom: 10 },
  noticeUrgent: { backgroundColor: COLORS.dangerMist },
  noticeIcon: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  devotional: { borderRadius: 24, overflow: 'hidden', padding: 18, gap: 4 },
  devotionalMark: { position: 'absolute', right: -6, top: -6, opacity: 0.16 },
  devotionalKicker: { fontFamily: FONTS.bold, fontSize: 11, letterSpacing: 1.5, color: '#C77700' },
  devotionalTitle: { fontFamily: FONTS.bold, fontSize: 19, lineHeight: 25, color: COLORS.goldInk },
  devotionalText: { fontFamily: FONTS.regular, fontSize: 14, lineHeight: 21, color: COLORS.goldInk2, marginTop: 2 },
  devotionalFoot: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginTop: 8 },
  devotionalRef: { flex: 1, fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.goldInk },
  devotionalRead: { fontFamily: FONTS.bold, fontSize: 13.5, color: '#C77700' },
  prayer: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: SOFT.glass, borderRadius: 22, borderWidth: 1.5, borderColor: SOFT.glassBorder, padding: 14, ...SOFT_SHADOW, shadowOpacity: 0.16 },
  prayerIcon: { width: 44, height: 44, borderRadius: 22, backgroundColor: '#E65100', alignItems: 'center', justifyContent: 'center' },
});
