// First-run helper under the welcome card: a few churches from the directory with a Follow button, so a
// new member can fill their home screen without leaving it. Tapping a row opens the church page.
import { useState } from 'react';
import { Alert, Pressable, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { useFetch } from '../../lib/useFetch';
import AppText from '../AppText';
import Button from '../Button';
import ChurchAvatar from '../ChurchAvatar';
import SectionHeader from '../SectionHeader';
import Skeleton from '../Skeleton';

export default function SuggestedChurches({ onFollowed }) {
  const { data, loading, error } = useFetch('/churches', { params: { per_page: 5 } });
  const [busy, setBusy] = useState(null);
  const churches = data?.data ?? [];

  async function follow(church) {
    setBusy(church.slug);
    try {
      await api.post(`/churches/${church.slug}/subscription`);
      onFollowed?.();
    } catch (e) {
      Alert.alert('Could not follow', errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  if (error || (!loading && !churches.length)) return null;

  return (
    <View style={styles.section}>
      <SectionHeader title="Churches to follow" actionLabel="See all" onAction={() => router.push('/churches')} />
      <View style={styles.list}>
        {loading
          ? [0, 1, 2].map((i) => <Skeleton key={i} height={76} radius={RADIUS.lg} />)
          : churches.map((church) => (
              <Pressable
                key={church.id}
                onPress={() => router.push({ pathname: '/church/[slug]', params: { slug: church.slug } })}
                accessibilityLabel={`Open ${church.name}`}
                style={styles.row}
              >
                <ChurchAvatar church={church} size={50} />
                <View style={styles.text}>
                  <AppText style={styles.name} numberOfLines={2}>
                    {church.name}
                  </AppText>
                  <AppText variant="caption" numberOfLines={1}>
                    {[church.location?.city, church.denomination].filter(Boolean).join(' · ')}
                  </AppText>
                </View>
                <Button title="Follow" small loading={busy === church.slug} onPress={() => follow(church)} />
              </Pressable>
            ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginBottom: SPACING.md },
  list: { paddingHorizontal: SPACING.md, gap: 10 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 12, ...SHADOW },
  text: { flex: 1, gap: 2 },
  name: { fontFamily: FONTS.bold, fontSize: 15, lineHeight: 20, color: COLORS.black },
});
