// Admin: the churches you help run. With one church it opens straight to that church's dashboard; with several you
// pick one. The super admin can open any church: search by name or town. Everyone else never sees this area (the server also
// checks every action).
import { useState } from 'react';
import { FlatList, Pressable, StyleSheet, TextInput, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { Redirect, router } from 'expo-router';
import AppText from '../../components/AppText';
import ChurchAvatar from '../../components/ChurchAvatar';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, MAX_FONT_SCALE, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { useAuth } from '../../lib/auth';
import { useFetch } from '../../lib/useFetch';

export default function AdminHome() {
  const { user } = useAuth();
  const superAdmin = !!user?.is_super_admin;
  const [typed, setTyped] = useState('');
  const [query, setQuery] = useState('');
  const { data, loading, error, retry } = useFetch('/admin/churches', { params: query ? { q: query } : {} });
  const churches = data?.data ?? [];

  if (!superAdmin && !loading && churches.length === 1) return <Redirect href={{ pathname: '/admin/[slug]', params: { slug: churches[0].church.slug } }} />;

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Admin" subtitle={superAdmin ? 'Open any church to update it' : 'Run your church from your phone'} />
      {superAdmin ? (
        <View style={styles.search}>
          <Ionicons name="search" size={18} color={COLORS.gray700} />
          <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={typed} onChangeText={setTyped} onSubmitEditing={() => setQuery(typed.trim())} returnKeyType="search" placeholder="Search a church by name or town" placeholderTextColor={COLORS.gray500} style={styles.input} autoCorrect={false} accessibilityLabel="Search churches" />
        </View>
      ) : null}
      <StateView loading={loading} error={error} onRetry={retry} empty={!loading && !error && !churches.length} emptyIcon="lock-closed-outline" emptyTitle="You are not church staff" emptyMessage="Church administrators can give you a role on the website's Staff page.">
        <FlatList
          data={churches}
          keyExtractor={(c) => String(c.church.id)}
          contentContainerStyle={{ padding: SPACING.md, gap: 12 }}
          renderItem={({ item: c }) => (
            <Pressable onPress={() => router.push({ pathname: '/admin/[slug]', params: { slug: c.church.slug } })} style={styles.card} accessibilityRole="button" accessibilityLabel={`Manage ${c.church.name}`}>
              <ChurchAvatar church={c.church} size={52} />
              <View style={{ flex: 1 }}>
                <AppText style={styles.name}>{c.church.name}</AppText>
                <AppText variant="caption">{c.title}</AppText>
              </View>
              <Ionicons name="chevron-forward" size={18} color={COLORS.gray500} />
            </Pressable>
          )}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  card: { flexDirection: 'row', alignItems: 'center', gap: 14, backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, ...SHADOW, shadowOpacity: 0.05 },
  name: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  search: { flexDirection: 'row', alignItems: 'center', gap: 8, margin: SPACING.md, marginBottom: 0, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  input: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
});
