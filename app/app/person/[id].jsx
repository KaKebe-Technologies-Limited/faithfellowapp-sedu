// Someone's profile: photo, where they are, a short bio, what they care about and the churches you share, with the
// one action that matters — Say Hey (ask to connect), or Message once connected. You can also disconnect or block.
// Only adults who chose to be found are ever shown; no email, phone or birthday.
import { Alert, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { router, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Avatar from '../../components/Avatar';
import Button from '../../components/Button';
import { useRelation } from '../../components/PersonCard';
import ScreenHeader, { HeaderAction } from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { countryName } from '../../lib/people';
import { useFetch } from '../../lib/useFetch';

function Body({ person, reload }) {
  const { relation, label, busy, act } = useRelation(person, () => reload());

  async function accept() {
    try {
      const list = (await api.get('/connections')).data.data.incoming;
      const item = list.find((c) => c.id === person.id);
      if (item) await api.post(`/connections/${item.connection_id}/respond`, { action: 'accept' });
      await reload();
    } catch (e) {
      Alert.alert('Could not accept', errorMessage(e));
    }
  }

  const confirm = (title, text, run) => {
    if (Platform.OS === 'web') return run();
    Alert.alert(title, text, [{ text: 'Cancel', style: 'cancel' }, { text: 'Yes', style: 'destructive', onPress: run }]);
  };

  const disconnect = () => confirm(relation === 'connected' ? 'Remove this connection?' : 'Cancel your request?', 'You can ask again later.', async () => {
    await api.delete(`/people/${person.id}/connection`).catch(() => {});
    reload();
  });
  const block = () => confirm(`Block ${person.name}?`, 'You will not see each other or be able to message. You can unblock in Me → Blocked people.', async () => {
    await api.post(`/people/${person.id}/block`).catch(() => {});
    router.back();
  });

  return (
    <ScrollView contentContainerStyle={{ paddingBottom: 48 }} showsVerticalScrollIndicator={false}>
      <LinearGradient colors={[COLORS.primaryLight, COLORS.primaryDark]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.cover} />
      <View style={styles.head}>
        <Avatar person={person} size={110} ring />
        <AppText style={styles.name}>{person.name}</AppText>
        <AppText variant="caption" style={{ fontSize: 14 }}>
          {[person.city, person.country ? countryName(person.country) : null].filter(Boolean).join(', ')}
          {person.age_range ? `  ·  ${person.age_range}` : ''}
        </AppText>
      </View>

      <View style={styles.actions}>
        {relation === 'pending_received' ? (
          <Button title="Accept request" onPress={accept} style={{ flex: 1 }} />
        ) : (
          <Button title={label} onPress={act} loading={busy} disabled={relation === 'pending_sent'} variant={relation === 'pending_sent' ? 'outline' : 'primary'} style={{ flex: 1 }} />
        )}
        {relation === 'connected' || relation === 'pending_sent' ? <Button title={relation === 'connected' ? 'Disconnect' : 'Cancel'} variant="outline" onPress={disconnect} /> : null}
      </View>

      {person.bio ? (
        <View style={styles.card}>
          <AppText style={styles.h}>About</AppText>
          <AppText style={styles.text}>{person.bio}</AppText>
        </View>
      ) : null}
      {person.interests?.length ? (
        <View style={styles.card}>
          <AppText style={styles.h}>Interested in</AppText>
          <View style={styles.wrap}>
            {person.interests.map((i) => (
              <View key={i} style={styles.pill}>
                <AppText style={styles.pillText}>{i}</AppText>
              </View>
            ))}
          </View>
        </View>
      ) : null}
      {person.shared_churches?.length ? (
        <View style={styles.card}>
          <AppText style={styles.h}>Churches you share</AppText>
          {person.shared_churches.map((c) => (
            <Pressable key={c.id} onPress={() => router.push({ pathname: '/church/[slug]', params: { slug: c.slug } })} style={styles.church} accessibilityRole="button">
              <Ionicons name="business-outline" size={18} color={COLORS.primary} />
              <AppText style={[styles.text, { flex: 1 }]}>{c.name}</AppText>
              <Ionicons name="chevron-forward" size={16} color={COLORS.gray500} />
            </Pressable>
          ))}
        </View>
      ) : null}

      <Pressable onPress={block} style={styles.block} accessibilityRole="button">
        <Ionicons name="ban-outline" size={16} color={COLORS.gray700} />
        <AppText style={{ color: COLORS.gray700, fontFamily: FONTS.medium }}>Block {person.name.split(' ')[0]}</AppText>
      </Pressable>
    </ScrollView>
  );
}

export default function PersonScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry, reload } = useFetch(`/people/${id}`);

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Profile" right={data?.data?.relation === 'connected' ? <HeaderAction icon="chatbubble-ellipses-outline" label="Message" onPress={() => router.push({ pathname: '/chat/[id]', params: { id: String(id) } })} /> : null} />
      <StateView loading={loading} error={error} onRetry={retry}>
        {data?.data ? <Body key={data.data.relation} person={data.data} reload={reload} /> : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  cover: { height: 110, marginHorizontal: SPACING.md, borderRadius: RADIUS.xl - 4 },
  head: { alignItems: 'center', marginTop: -55, gap: 4 },
  name: { fontFamily: FONTS.bold, fontSize: 24, color: COLORS.black, marginTop: 6 },
  actions: { flexDirection: 'row', gap: 10, paddingHorizontal: SPACING.md, marginTop: 16 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: SPACING.md, marginHorizontal: SPACING.md, marginTop: 12, gap: 8, ...SHADOW, shadowOpacity: 0.05 },
  h: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  text: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.gray900 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  pill: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 13, paddingVertical: 7 },
  pillText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  church: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 6 },
  block: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, marginTop: 24 },
});
