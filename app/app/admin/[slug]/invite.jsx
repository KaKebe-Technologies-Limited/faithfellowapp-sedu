// The church's invite link, for staff to share: the link and its QR code, one tap to share it on WhatsApp or anywhere, to copy it, and how many people
// opened it and joined through it. Whoever opens the link is taken to the app (or its download) and follows this church once they have an account.
// Data: GET /api/v1/admin/{church}/invite.
import { useState } from 'react';
import { Alert, RefreshControl, ScrollView, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import * as Clipboard from 'expo-clipboard';
import { useLocalSearchParams } from 'expo-router';
import QRCode from 'react-native-qrcode-svg';
import AppText from '../../../components/AppText';
import Button from '../../../components/Button';
import ScreenHeader from '../../../components/ScreenHeader';
import StateView from '../../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../../constants/theme';
import { useFetch } from '../../../lib/useFetch';

function Stat({ icon, value, label, note }) {
  return (
    <View style={styles.stat}>
      <Ionicons name={icon} size={20} color={COLORS.primary} />
      <AppText style={styles.statValue}>{value}</AppText>
      <AppText style={styles.statLabel}>{label}</AppText>
      <AppText variant="caption">{note}</AppText>
    </View>
  );
}

export default function AdminInvite() {
  const { slug } = useLocalSearchParams();
  const { data, loading, refreshing, error, reload, retry } = useFetch(`/admin/${slug}/invite`);
  const [copied, setCopied] = useState(false);
  const d = data?.data;

  async function copy() {
    await Clipboard.setStringAsync(d.url);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  const share = () => Share.share({ message: d.message }).catch((e) => Alert.alert('Could not share', String(e?.message ?? e)));

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Invite link" subtitle="Bring people into the app" />
      <StateView loading={loading && !d} error={!d ? error : null} onRetry={retry}>
        {d && !d.url ? (
          <View style={{ padding: SPACING.lg }}>
            <AppText style={styles.lead}>Invite links are being switched on for the platform. Please check again soon.</AppText>
          </View>
        ) : null}
        {d?.url ? (
          <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 40 }} showsVerticalScrollIndicator={false} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}>
            <AppText style={styles.lead}>One link for your church. Share it on WhatsApp, on social media or in the bulletin: whoever opens it is taken to the app, and joins your church as soon as they have an account.</AppText>

            <View style={styles.card}>
              <View style={styles.qr} accessibilityRole="image" accessibilityLabel="QR code of the invite link">
                <QRCode value={d.url} size={190} />
              </View>
              <AppText style={styles.code}>{d.code}</AppText>
              <AppText variant="caption" style={{ textAlign: 'center' }}>
                {d.url.replace(/^https?:\/\//, '')}
              </AppText>
              <Button title="Share the link" onPress={share} style={{ marginTop: SPACING.md }} />
              <Button title={copied ? 'Copied' : 'Copy the link'} variant="outline" onPress={copy} style={{ marginTop: 10 }} />
            </View>

            <View style={styles.stats}>
              <Stat icon="eye-outline" value={d.stats.opened} label="link opened" note={`${d.stats.opened_30} in the last 30 days`} />
              <Stat icon="person-add-outline" value={d.stats.joined} label="joined by link" note={`${d.stats.joined_30} in the last 30 days`} />
            </View>

            <AppText variant="caption" style={{ textAlign: 'center', marginTop: SPACING.md }}>To change the code or print a QR poster, open Invite link on the website (Church admin → People).</AppText>
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  lead: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, color: COLORS.gray700, marginBottom: SPACING.md },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: SPACING.lg, alignItems: 'stretch', ...SHADOW },
  qr: { alignSelf: 'center', padding: 12, backgroundColor: '#fff', borderRadius: RADIUS.lg, borderWidth: 1, borderColor: COLORS.gray300 },
  code: { fontFamily: FONTS.bold, fontSize: 28, letterSpacing: 4, color: COLORS.primaryInk, textAlign: 'center', marginTop: SPACING.md },
  stats: { flexDirection: 'row', gap: 12, marginTop: SPACING.md },
  stat: { flex: 1, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: SPACING.md, gap: 2, ...SHADOW },
  statValue: { fontFamily: FONTS.bold, fontSize: 26, color: COLORS.black },
  statLabel: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black },
});
