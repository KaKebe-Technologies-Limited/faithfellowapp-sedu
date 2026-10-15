// A gift receipt, read in the app: church, amount, what it was for, how it was paid and the receipt number.
// "Share" sends the receipt as text through the phone's share sheet (WhatsApp, email, …).
import { ScrollView, Share, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { formatDate, formatMoney } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

function Line({ label, value }) {
  if (!value) return null;
  return (
    <View style={styles.line}>
      <AppText variant="caption" style={{ fontSize: 13 }}>
        {label}
      </AppText>
      <AppText style={styles.value}>{value}</AppText>
    </View>
  );
}

export default function ReceiptScreen() {
  const { reference } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/giving/${reference}/receipt`);
  const r = data?.data;

  function share() {
    if (!r) return;
    Share.share({
      message: `Receipt ${r.receipt_number}\n${r.church.name}\n${formatMoney(r.amount, r.currency)} — ${r.campaign || r.category}\n${formatDate(r.paid_at, true)}\nThank you for giving.`,
    }).catch(() => {});
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Receipt" />
      <StateView loading={loading} error={error} onRetry={retry}>
        {r ? (
          <ScrollView contentContainerStyle={styles.content}>
            <View style={styles.card}>
              <ChurchAvatar church={r.church} size={56} />
              <AppText style={styles.church}>{r.church.name}</AppText>
              <View style={styles.badge}>
                <Ionicons name={r.status === 'refunded' ? 'return-down-back' : 'checkmark-circle'} size={16} color={r.status === 'refunded' ? COLORS.gray700 : COLORS.success} />
                <AppText style={[styles.badgeText, { color: r.status === 'refunded' ? COLORS.gray700 : COLORS.success }]}>{r.status === 'refunded' ? 'Refunded' : 'Received'}</AppText>
              </View>
              <AppText style={styles.amount}>{formatMoney(r.amount, r.currency)}</AppText>
              <View style={styles.rule} />
              <Line label="For" value={r.campaign || r.category} />
              <Line label="Given by" value={r.giver} />
              <Line label="Paid with" value={r.method} />
              <Line label="Date" value={formatDate(r.paid_at, true)} />
              <Line label="Receipt no." value={r.receipt_number} />
              <Line label="Reference" value={r.reference} />
            </View>
            <Button title="Share receipt" onPress={share} style={{ marginTop: SPACING.md }} />
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl, padding: SPACING.lg, alignItems: 'center', ...SHADOW },
  church: { fontFamily: FONTS.bold, fontSize: 17, color: COLORS.black, marginTop: 10, textAlign: 'center' },
  badge: { flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 8 },
  badgeText: { fontFamily: FONTS.semibold, fontSize: 13 },
  amount: { fontFamily: FONTS.bold, fontSize: 34, color: COLORS.black, marginTop: 14 },
  rule: { alignSelf: 'stretch', height: 1, backgroundColor: COLORS.gray100, marginVertical: 18 },
  line: { alignSelf: 'stretch', flexDirection: 'row', justifyContent: 'space-between', gap: 16, paddingVertical: 7 },
  value: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.black, flexShrink: 1, textAlign: 'right' },
});
