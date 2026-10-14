// "Give to your church" on Home: a calm green-and-gold card with the cheerful-giver verse, a Give button for each church you
// follow (it opens the gift screen, where you type the amount and your mobile money number and the prompt arrives on your
// phone), and the church campaign that is closest to its goal.
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router } from 'expo-router';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { formatMoney } from '../../lib/format';
import AppText from '../AppText';
import ChurchAvatar from '../ChurchAvatar';

export default function GiveCard({ churches = [], campaigns = [] }) {
  if (!churches.length) return null;
  const campaign = [...campaigns].sort((a, b) => b.percent - a.percent)[0];
  const pct = campaign ? Math.max(3, Math.min(100, Math.round(campaign.percent))) : 0;

  return (
    <View style={styles.wrap}>
      <LinearGradient colors={['#0F5C46', '#1E8A63', '#3DB27F']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.card}>
        <View style={styles.glowA} />
        <View style={styles.glowB} />
        <View style={styles.head}>
          <View style={styles.iconBox}>
            <Ionicons name="heart" size={22} color="#fff" />
          </View>
          <View style={{ flex: 1 }}>
            <AppText style={styles.title}>Give with joy</AppText>
            <AppText style={styles.verse}>“God loves a cheerful giver.” 2 Corinthians 9:7</AppText>
          </View>
        </View>

        {churches.slice(0, 3).map((c) => (
          <Pressable key={c.id} onPress={() => router.push({ pathname: '/give/[slug]', params: { slug: c.slug } })} style={({ pressed }) => [styles.church, pressed && { opacity: 0.92 }]} accessibilityRole="button" accessibilityLabel={`Give to ${c.name}`}>
            <ChurchAvatar church={c} size={40} />
            <View style={{ flex: 1 }}>
              <AppText style={styles.churchName} numberOfLines={1}>
                {c.name}
              </AppText>
              <AppText style={styles.churchSub}>Tithe, offering or a project</AppText>
            </View>
            <View style={styles.giveBtn}>
              <AppText style={styles.giveText}>Give</AppText>
            </View>
          </Pressable>
        ))}

        {campaign ? (
          <Pressable onPress={() => router.push({ pathname: '/campaign/[id]', params: { id: campaign.id } })} style={styles.campaign} accessibilityRole="button" accessibilityLabel={`Campaign ${campaign.title}`}>
            <View style={styles.campaignTop}>
              <Ionicons name="flag" size={14} color="#FFD98A" />
              <AppText style={styles.campaignTitle} numberOfLines={1}>
                {campaign.title}
              </AppText>
              <AppText style={styles.pct}>{pct}%</AppText>
            </View>
            <View style={styles.track}>
              <View style={[styles.fill, { width: `${pct}%` }]} />
            </View>
            <AppText style={styles.raised}>
              {formatMoney(campaign.raised, campaign.currency)} of {formatMoney(campaign.goal, campaign.currency)}
            </AppText>
          </Pressable>
        ) : null}

        <View style={styles.secure}>
          <Ionicons name="shield-checkmark" size={15} color="#C8F2DC" />
          <AppText style={styles.secureText}>MTN and Airtel Mobile Money, paid securely through ioTec Pay</AppText>
        </View>
      </LinearGradient>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { paddingHorizontal: SPACING.md, marginBottom: SPACING.lg, shadowColor: '#0F5C46', shadowOpacity: 0.25, shadowRadius: 16, shadowOffset: { width: 0, height: 9 }, elevation: 7 },
  card: { borderRadius: 26, padding: 18, overflow: 'hidden' },
  glowA: { position: 'absolute', right: -40, top: -50, width: 180, height: 180, borderRadius: 90, backgroundColor: 'rgba(255,255,255,0.1)' },
  glowB: { position: 'absolute', left: -30, bottom: -60, width: 150, height: 150, borderRadius: 75, backgroundColor: 'rgba(255,217,138,0.15)' },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 14 },
  iconBox: { width: 44, height: 44, borderRadius: 15, backgroundColor: 'rgba(255,255,255,0.2)', alignItems: 'center', justifyContent: 'center' },
  title: { fontFamily: FONTS.bold, fontSize: 21, color: '#fff' },
  verse: { fontFamily: FONTS.medium, fontStyle: 'italic', fontSize: 12.5, lineHeight: 17, color: 'rgba(255,255,255,0.85)', marginTop: 1 },
  church: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: 'rgba(255,255,255,0.95)', borderRadius: 18, padding: 10, marginBottom: 8 },
  churchName: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black },
  churchSub: { fontFamily: FONTS.regular, fontSize: 12, color: COLORS.gray700 },
  giveBtn: { backgroundColor: '#12704F', borderRadius: 20, paddingHorizontal: 20, height: 36, alignItems: 'center', justifyContent: 'center' },
  giveText: { fontFamily: FONTS.bold, fontSize: 14, color: '#fff' },
  campaign: { backgroundColor: 'rgba(0,0,0,0.2)', borderRadius: 16, padding: 12, marginTop: 4 },
  campaignTop: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  campaignTitle: { flex: 1, fontFamily: FONTS.bold, fontSize: 14, color: '#fff' },
  pct: { fontFamily: FONTS.bold, fontSize: 14, color: '#FFD98A' },
  track: { height: 8, borderRadius: 4, backgroundColor: 'rgba(255,255,255,0.25)', marginTop: 10, overflow: 'hidden' },
  fill: { height: 8, borderRadius: 4, backgroundColor: '#FFD98A' },
  raised: { fontFamily: FONTS.medium, fontSize: 12, color: 'rgba(255,255,255,0.85)', marginTop: 6 },
  secure: { flexDirection: 'row', alignItems: 'center', gap: 7, marginTop: 14 },
  secureText: { flex: 1, fontFamily: FONTS.medium, fontSize: 11.5, color: '#C8F2DC' },
});
