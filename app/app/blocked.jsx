// People you have blocked. They cannot find you, connect with you or message you, and you do not see them.
// Unblock to allow it again. Data: GET /api/v1/people/blocked, DELETE /people/{id}/block.
import { Alert, FlatList, Pressable, StyleSheet, View } from 'react-native';
import AppText from '../components/AppText';
import Avatar from '../components/Avatar';
import ScreenHeader from '../components/ScreenHeader';
import StateView from '../components/StateView';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import api, { errorMessage } from '../lib/api';
import { useFetch } from '../lib/useFetch';

export default function BlockedScreen() {
  const { data, loading, error, retry, reload } = useFetch('/people/blocked');
  const list = data?.data ?? [];

  async function unblock(p) {
    try {
      await api.delete(`/people/${p.id}/block`);
      await reload();
    } catch (e) {
      Alert.alert('Could not unblock', errorMessage(e));
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Blocked people" subtitle="They cannot reach you" />
      <StateView loading={loading} error={error} onRetry={retry} empty={!loading && !error && !list.length} emptyIcon="ban-outline" emptyTitle="You have not blocked anyone" emptyMessage="You can block someone from their profile.">
        <FlatList
          data={list}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ padding: SPACING.md, gap: 10 }}
          renderItem={({ item: p }) => (
            <View style={styles.row}>
              <Avatar person={p} size={46} />
              <AppText style={styles.name}>{p.name}</AppText>
              <Pressable onPress={() => unblock(p)} style={styles.btn} accessibilityRole="button" accessibilityLabel={`Unblock ${p.name}`}>
                <AppText style={styles.btnText}>Unblock</AppText>
              </Pressable>
            </View>
          )}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 12 },
  name: { flex: 1, fontFamily: FONTS.semibold, fontSize: 15.5, color: COLORS.black },
  btn: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 16, paddingVertical: 8 },
  btnText: { fontFamily: FONTS.bold, fontSize: 13, color: COLORS.primary },
});
