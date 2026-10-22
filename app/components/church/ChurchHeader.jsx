// The top of a church's page: its banner picture (or the brand colours), its logo, name and what it is about, the Follow button, and three figures
// (followers, sermons, events). The back and share buttons float over the banner and are drawn by the screen.
import { Pressable, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { SOFT, SOFT_SHADOW } from '../../constants/soft';
import { mediaUrl } from '../../lib/api';
import AppText from '../AppText';
import BrandPlaceholder from '../BrandPlaceholder';
import ChurchAvatar from '../ChurchAvatar';
import Pill from '../Pill';

function Figure({ icon, value, label }) {
  return (
    <View style={styles.figure}>
      <Ionicons name={icon} size={18} color={COLORS.primary} />
      <AppText style={styles.figureValue}>{value ?? '–'}</AppText>
      <AppText style={styles.figureLabel} numberOfLines={1}>
        {label}
      </AppText>
    </View>
  );
}

export default function ChurchHeader({ info, stats, busy, onFollow, onUnfollow }) {
  const place = [info.location?.city, info.denomination].filter(Boolean).join(' · ');
  const followers = stats?.followers ?? info.subscribers;

  return (
    <View>
      <View style={styles.cover}>
        <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} style={StyleSheet.absoluteFill} />
        {info.cover_url ? <Image source={{ uri: mediaUrl(info.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={300} /> : <BrandPlaceholder />}
        <LinearGradient colors={['rgba(20,6,12,0.45)', 'rgba(20,6,12,0)', 'rgba(20,6,12,0.35)']} locations={[0, 0.5, 1]} style={StyleSheet.absoluteFill} />
      </View>

      <View style={styles.sheet}>
        <View style={styles.top}>
          <View style={styles.badge}>
            <ChurchAvatar church={info} size={84} />
          </View>
          <View style={{ flex: 1 }} />
          {info.is_subscribed ? (
            <Pressable onPress={onUnfollow} disabled={busy} style={({ pressed }) => [styles.follow, styles.following, pressed && { opacity: 0.85 }]} accessibilityRole="button" accessibilityLabel={`You follow ${info.name}. Tap to unfollow`}>
              <Ionicons name="checkmark-circle" size={18} color={COLORS.primary} />
              <AppText style={[styles.followText, { color: COLORS.primary }]}>{busy ? '…' : 'Following'}</AppText>
            </Pressable>
          ) : (
            <Pressable onPress={onFollow} disabled={busy} style={({ pressed }) => [styles.follow, pressed && { opacity: 0.88 }]} accessibilityRole="button" accessibilityLabel={`Follow ${info.name}`}>
              <Ionicons name="add" size={20} color="#fff" />
              <AppText style={styles.followText}>{busy ? '…' : 'Follow'}</AppText>
            </Pressable>
          )}
        </View>

        <AppText style={styles.name}>{info.name}</AppText>
        {info.tagline ? <AppText style={styles.tagline}>{info.tagline}</AppText> : null}
        <View style={styles.facts}>
          {place ? <Pill label={place} icon="location-outline" /> : null}
          {info.pastor ? <Pill label={info.pastor} icon="person-outline" /> : null}
        </View>

        <View style={styles.figures}>
          <Figure icon="people" value={followers} label={followers === 1 ? 'follower' : 'followers'} />
          <Figure icon="mic" value={stats?.sermons} label={stats?.sermons === 1 ? 'sermon' : 'sermons'} />
          <Figure icon="calendar" value={stats?.events} label={stats?.events === 1 ? 'event' : 'events'} />
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  cover: { height: 220, backgroundColor: COLORS.primaryDark },
  sheet: { marginTop: -34, backgroundColor: SOFT.surface, borderTopLeftRadius: 32, borderTopRightRadius: 32, paddingHorizontal: SPACING.md, paddingBottom: SPACING.md },
  top: { flexDirection: 'row', alignItems: 'flex-start' },
  badge: { marginTop: -42, width: 100, height: 100, borderRadius: 30, backgroundColor: '#fff', borderWidth: 4, borderColor: '#fff', alignItems: 'center', justifyContent: 'center', ...SOFT_SHADOW },
  follow: { marginTop: 14, flexDirection: 'row', alignItems: 'center', gap: 6, height: 42, paddingHorizontal: 20, borderRadius: 999, backgroundColor: COLORS.primary },
  following: { backgroundColor: '#fff', borderWidth: 1.5, borderColor: COLORS.primary },
  followText: { fontFamily: FONTS.bold, fontSize: 14.5, color: '#fff' },
  name: { fontFamily: FONTS.bold, fontSize: 25, lineHeight: 31, color: SOFT.ink, marginTop: 12 },
  tagline: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, color: SOFT.muted, marginTop: 2 },
  facts: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 12 },
  figures: { flexDirection: 'row', gap: 10, marginTop: 16 },
  figure: { flex: 1, alignItems: 'center', gap: 2, paddingVertical: 12, borderRadius: 20, backgroundColor: SOFT.glass, borderWidth: 1.5, borderColor: SOFT.glassBorder, ...SOFT_SHADOW, shadowOpacity: 0.16 },
  figureValue: { fontFamily: FONTS.bold, fontSize: 20, color: SOFT.ink },
  figureLabel: { fontFamily: FONTS.medium, fontSize: 12, color: SOFT.muted },
});
