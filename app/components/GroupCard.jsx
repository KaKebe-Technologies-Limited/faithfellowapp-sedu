// A study group / cell / fellowship as a card: a cover picture, what kind of group it is, when and where it meets,
// who leads it and how many are in it. Tapping opens the group.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW } from '../constants/theme';
import { mediaUrl } from '../lib/api';
import AppText from './AppText';
import Pill from './Pill';

export default function GroupCard({ group, width, style }) {
  return (
    <Pressable onPress={() => router.push({ pathname: '/group/[id]', params: { id: String(group.id) } })} style={({ pressed }) => [styles.card, width && { width }, style, pressed && { opacity: 0.94 }]} accessibilityRole="button" accessibilityLabel={`Group: ${group.name}`}>
      <View style={styles.cover}>
        <LinearGradient colors={[COLORS.primaryDark, COLORS.primary]} style={StyleSheet.absoluteFill} />
        {group.cover_url ? <Image source={{ uri: mediaUrl(group.cover_url) }} style={StyleSheet.absoluteFill} contentFit="cover" transition={250} /> : null}
        <LinearGradient colors={['rgba(20,6,12,0)', 'rgba(20,6,12,0.55)']} locations={[0.4, 1]} style={StyleSheet.absoluteFill} />
        <View style={styles.pills}>
          <Pill label={group.type_label} tone="dark" />
          {group.my_status === 'pending' ? <Pill label="Requested" tone="white" /> : null}
        </View>
      </View>
      <View style={styles.info}>
        <AppText style={styles.title} numberOfLines={2}>
          {group.name}
        </AppText>
        {group.schedule || group.location ? (
          <View style={styles.meta}>
            <Ionicons name="time-outline" size={14} color={COLORS.primary} />
            <AppText variant="caption" numberOfLines={1} style={{ flex: 1 }}>
              {[group.schedule, group.location].filter(Boolean).join(' · ')}
            </AppText>
          </View>
        ) : null}
        <View style={styles.meta}>
          <Ionicons name="people-outline" size={14} color={COLORS.primary} />
          <AppText variant="caption" numberOfLines={1} style={{ flex: 1 }}>
            {group.members_count} {group.members_count === 1 ? 'member' : 'members'}
            {group.leader ? ` · led by ${group.leader.name}` : ''}
          </AppText>
        </View>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, overflow: 'hidden', ...SHADOW },
  cover: { height: 110, backgroundColor: COLORS.primaryDark },
  pills: { position: 'absolute', left: 10, top: 10, flexDirection: 'row', gap: 6 },
  info: { padding: 12, gap: 5 },
  title: { fontFamily: FONTS.bold, fontSize: 16, lineHeight: 21, color: COLORS.black },
  meta: { flexDirection: 'row', alignItems: 'center', gap: 6 },
});
