// Give tab: your giving history (GET /api/v1/giving). Starting a gift from the app (mobile money) is
// the next milestone, so for now the "Give" button opens the church giving page on the website.
import { FlatList, Linking, RefreshControl, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import Card from '../../components/Card';
import StateView from '../../components/StateView';
import { COLORS, RADIUS, SPACING } from '../../constants/theme';
import { SITE_URL } from '../../lib/api';
import { formatDate, formatMoney } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

const STATUS = {
  succeeded: { label: 'Received', color: COLORS.success },
  pending: { label: 'Pending', color: COLORS.warning },
  processing: { label: 'Processing', color: COLORS.warning },
  failed: { label: 'Failed', color: COLORS.danger },
  refunded: { label: 'Refunded', color: COLORS.gray700 },
};

export default function GiveScreen() {
  const { data, loading, refreshing, error, reload, retry } = useFetch('/giving');
  const gifts = data?.data ?? [];
  const total = gifts.filter((g) => g.status === 'succeeded').reduce((sum, g) => sum + g.amount, 0);

  const header = (
    <View style={styles.header}>
      <Card style={styles.hero}>
        <Ionicons name="heart" size={26} color={COLORS.white} />
        <AppText variant="heading" style={{ color: COLORS.white }}>
          Give with joy
        </AppText>
        <AppText style={{ color: COLORS.primaryPale }}>Tithes, offerings and campaigns, by mobile money or card.</AppText>
        <Button title="Give on the website" variant="light" small onPress={() => Linking.openURL(`${SITE_URL}/giving`)} style={styles.give} />
      </Card>
      {gifts.length ? (
        <AppText variant="label" style={styles.total}>
          Given on this page: {formatMoney(total)}
        </AppText>
      ) : null}
    </View>
  );

  return (
    <StateView
      loading={loading}
      error={error}
      onRetry={retry}
      empty={false}
    >
      <FlatList
        data={gifts}
        keyExtractor={(gift) => gift.reference}
        contentContainerStyle={styles.list}
        ListHeaderComponent={header}
        ListEmptyComponent={
          <View style={styles.empty}>
            <Ionicons name="receipt-outline" size={28} color={COLORS.primary} />
            <AppText variant="heading">No gifts yet</AppText>
            <AppText style={{ textAlign: 'center', color: COLORS.gray700 }}>When you give, your receipts and history will appear here.</AppText>
          </View>
        }
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
        renderItem={({ item: gift }) => {
          const status = STATUS[gift.status] ?? { label: gift.status, color: COLORS.gray700 };
          return (
            <Card style={styles.row}>
              <View style={styles.rowText}>
                <AppText variant="heading">{formatMoney(gift.amount, gift.currency)}</AppText>
                <AppText variant="caption" numberOfLines={1}>
                  {[gift.church?.name, gift.campaign?.title ?? gift.category].filter(Boolean).join(' · ')}
                </AppText>
                <AppText variant="caption">{formatDate(gift.paid_at ?? gift.created_at)}</AppText>
              </View>
              <View style={[styles.pill, { backgroundColor: `${status.color}1F` }]}>
                <AppText variant="caption" style={{ color: status.color }}>
                  {status.label}
                </AppText>
              </View>
            </Card>
          );
        }}
      />
    </StateView>
  );
}

const styles = StyleSheet.create({
  list: { padding: SPACING.md, gap: SPACING.sm },
  header: { gap: SPACING.sm, marginBottom: SPACING.sm },
  hero: { backgroundColor: COLORS.primary, gap: 4 },
  give: { alignSelf: 'flex-start', marginTop: SPACING.sm },
  total: { color: COLORS.gray900 },
  row: { flexDirection: 'row', alignItems: 'center', gap: SPACING.md },
  rowText: { flex: 1, gap: 2 },
  pill: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: RADIUS.full },
  empty: { alignItems: 'center', gap: 6, padding: SPACING.xl },
});
