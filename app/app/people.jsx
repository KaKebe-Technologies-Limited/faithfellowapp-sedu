// People: find other believers — in your own churches, near you (by distance), or across the whole app for those who
// chose to be found. Nobody sees where you are, only about how far away.
// Filter by country, city, age, gender and what they care about; ask to connect with "Say Hey". Adults only; you need
// your own birth date on your profile to connect. Data: GET /api/v1/people.
import { useState } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppText from '../components/AppText';
import Button from '../components/Button';
import PagedScreen from '../components/PagedScreen';
import { PersonRow } from '../components/PersonCard';
import { COLORS, FONTS, RADIUS, SPACING, MAX_FONT_SCALE } from '../constants/theme';
import { useAuth } from '../lib/auth';
import { shareMyLocation } from '../lib/location';
import { COUNTRIES, INTERESTS } from '../lib/people';

const AGES = [['18–24', 18, 24], ['25–34', 25, 34], ['35–44', 35, 44], ['45–54', 45, 54], ['55+', 55, 100]];

function Chip({ label, on, onPress }) {
  return (
    <Pressable onPress={onPress} style={[styles.chip, on && styles.chipOn]} accessibilityRole="button" accessibilityState={{ selected: on }}>
      <AppText style={[styles.chipText, on && { color: COLORS.white }]}>{label}</AppText>
    </Pressable>
  );
}

export default function PeopleScreen() {
  const insets = useSafeAreaInsets();
  const { user, setUser } = useAuth();
  const [scope, setScope] = useState('church');
  const [radius, setRadius] = useState(50);
  const [hasLocation, setHasLocation] = useState(!!user?.has_location);
  const [locating, setLocating] = useState(false);
  const [locationError, setLocationError] = useState(null);
  const [refresh, setRefresh] = useState(0);
  const [query, setQuery] = useState('');
  const [search, setSearch] = useState('');
  const [filters, setFilters] = useState({});
  const [draft, setDraft] = useState({});
  const [open, setOpen] = useState(false);

  const active = Object.entries(filters).filter(([, v]) => v !== undefined && v !== '');
  const canConnect = !!user?.date_of_birth && new Date(user.date_of_birth) <= new Date(Date.now() - 18 * 365.25 * 864e5);

  async function useMyLocation() {
    setLocating(true);
    setLocationError(null);
    const result = await shareMyLocation();
    setLocating(false);
    if (!result.ok) return setLocationError(result.message);
    setUser(result.user);
    setHasLocation(true);
    setRefresh((n) => n + 1);
  }

  const set = (key, value) => setDraft((d) => ({ ...d, [key]: d[key] === value ? undefined : value }));
  const ageKey = (f) => AGES.find(([, min, max]) => f.age_min === min && f.age_max === max)?.[0];

  const header = (
    <View style={{ gap: 10, paddingHorizontal: SPACING.md, paddingBottom: 10 }}>
      {!canConnect ? (
        <Pressable onPress={() => router.push('/profile/edit')} style={styles.notice} accessibilityRole="button">
          <Ionicons name="id-card-outline" size={22} color={COLORS.primary} />
          <AppText style={styles.noticeText}>Add your birth date to your profile to connect with others. Connecting is for members aged 18 and over.</AppText>
          <Ionicons name="chevron-forward" size={16} color={COLORS.primary} />
        </Pressable>
      ) : null}
      <View style={styles.seg}>
        {[['church', 'My church'], ['nearby', 'Near me'], ['everyone', 'Everyone']].map(([key, label]) => (
          <Pressable key={key} onPress={() => setScope(key)} style={[styles.segBtn, scope === key && styles.segOn]} accessibilityRole="tab" accessibilityState={{ selected: scope === key }}>
            <AppText style={[styles.segText, scope === key && { color: COLORS.white }]}>{label}</AppText>
          </Pressable>
        ))}
      </View>
      {scope === 'nearby' ? (
        hasLocation ? (
          <View style={styles.wrap}>
            {[10, 25, 50, 100].map((km) => (
              <Chip key={km} label={`${km} km`} on={radius === km} onPress={() => setRadius(km)} />
            ))}
          </View>
        ) : (
          <View style={styles.notice}>
            <Ionicons name="location-outline" size={22} color={COLORS.primary} />
            <View style={{ flex: 1, gap: 6 }}>
              <AppText style={styles.noticeText}>Share your approximate area to see believers near you. Others only see how far away you are, never where.</AppText>
              {locationError ? <AppText style={[styles.noticeText, { color: COLORS.danger }]}>{locationError}</AppText> : null}
              <Button title="Use my location" small onPress={useMyLocation} loading={locating} style={{ alignSelf: 'flex-start' }} />
            </View>
          </View>
        )
      ) : null}
      <View style={{ flexDirection: 'row', gap: 8 }}>
        <View style={styles.search}>
          <Ionicons name="search" size={18} color={COLORS.gray700} />
          <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={query} onChangeText={setQuery} onSubmitEditing={() => setSearch(query.trim())} returnKeyType="search" placeholder="Search by name" placeholderTextColor={COLORS.gray500} style={styles.input} />
        </View>
        <Pressable onPress={() => { setDraft(filters); setOpen(true); }} style={[styles.filterBtn, active.length > 0 && { backgroundColor: COLORS.primary }]} accessibilityRole="button" accessibilityLabel="Filters">
          <Ionicons name="options-outline" size={20} color={active.length ? COLORS.white : COLORS.primary} />
          {active.length ? <AppText style={styles.filterCount}>{active.length}</AppText> : null}
        </Pressable>
      </View>
      {active.length ? (
        <View style={styles.wrap}>
          {filters.country ? <Chip on label={COUNTRIES.find(([c]) => c === filters.country)?.[1] ?? filters.country} onPress={() => setFilters((f) => ({ ...f, country: undefined }))} /> : null}
          {filters.city ? <Chip on label={filters.city} onPress={() => setFilters((f) => ({ ...f, city: undefined }))} /> : null}
          {filters.age_min ? <Chip on label={`Age ${ageKey(filters) ?? filters.age_min}`} onPress={() => setFilters((f) => ({ ...f, age_min: undefined, age_max: undefined }))} /> : null}
          {filters.gender ? <Chip on label={filters.gender === 'male' ? 'Men' : 'Women'} onPress={() => setFilters((f) => ({ ...f, gender: undefined }))} /> : null}
          {filters.interest ? <Chip on label={filters.interest} onPress={() => setFilters((f) => ({ ...f, interest: undefined }))} /> : null}
        </View>
      ) : null}
    </View>
  );

  return (
    <View style={{ flex: 1 }}>
      <PagedScreen
        key={`${scope}-${radius}-${refresh}`}
        title="People"
        subtitle="Believers to walk with"
        path="/people"
        params={{ scope, q: search || undefined, ...(scope === 'nearby' ? { radius } : {}), ...Object.fromEntries(active) }}
        header={header}
        renderItem={(person) => <PersonRow person={person} />}
        emptyIcon="people-outline"
        emptyTitle="No one found yet"
        emptyMessage={scope === 'church' ? 'People from your churches who chose to be listed will appear here. Try "Near me" or "Everyone" to meet believers across the app.' : scope === 'nearby' ? (hasLocation ? 'No one has shared an area within this distance yet. Try a wider distance.' : 'Share your approximate area above to see believers near you.') : 'Try fewer filters. Only adults who chose to be found are shown.'}
        right={
          <Pressable onPress={() => router.push('/connections')} style={styles.reqBtn} accessibilityRole="button" accessibilityLabel="My connections">
            <Ionicons name="people-circle-outline" size={20} color={COLORS.primary} />
            <AppText style={styles.reqText}>Mine</AppText>
          </Pressable>
        }
      />

      <Modal visible={open} animationType="slide" transparent onRequestClose={() => setOpen(false)}>
        <Pressable style={styles.backdrop} onPress={() => setOpen(false)} accessibilityLabel="Close" />
        <View style={[styles.sheet, { paddingBottom: insets.bottom + 16 }]}>
          <View style={styles.grab} />
          <AppText style={styles.sheetTitle}>Filters</AppText>
          <ScrollView style={{ maxHeight: 460 }} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
            <AppText style={styles.label}>Country</AppText>
            <View style={styles.wrap}>
              {COUNTRIES.map(([code, name]) => (
                <Chip key={code} label={name} on={draft.country === code} onPress={() => set('country', code)} />
              ))}
            </View>
            <AppText style={styles.label}>City</AppText>
            <TextInput maxFontSizeMultiplier={MAX_FONT_SCALE} value={draft.city ?? ''} onChangeText={(t) => setDraft((d) => ({ ...d, city: t || undefined }))} placeholder="For example: Lira" placeholderTextColor={COLORS.gray500} style={styles.cityInput} />
            <AppText style={styles.label}>Age</AppText>
            <View style={styles.wrap}>
              {AGES.map(([name, min, max]) => (
                <Chip key={name} label={name} on={draft.age_min === min && draft.age_max === max} onPress={() => setDraft((d) => (d.age_min === min ? { ...d, age_min: undefined, age_max: undefined } : { ...d, age_min: min, age_max: max }))} />
              ))}
            </View>
            <AppText style={styles.label}>Gender</AppText>
            <View style={styles.wrap}>
              <Chip label="Men" on={draft.gender === 'male'} onPress={() => set('gender', 'male')} />
              <Chip label="Women" on={draft.gender === 'female'} onPress={() => set('gender', 'female')} />
            </View>
            <AppText style={styles.label}>Interested in</AppText>
            <View style={styles.wrap}>
              {INTERESTS.map((i) => (
                <Chip key={i} label={i} on={draft.interest === i} onPress={() => set('interest', i)} />
              ))}
            </View>
          </ScrollView>
          <View style={{ flexDirection: 'row', gap: 10, marginTop: 14 }}>
            <Button title="Clear" variant="outline" onPress={() => { setFilters({}); setDraft({}); setOpen(false); }} style={{ flex: 1 }} />
            <Button title="Show people" onPress={() => { setFilters(draft); setOpen(false); }} style={{ flex: 1.4 }} />
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  notice: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.lg, padding: 12 },
  noticeText: { flex: 1, fontFamily: FONTS.medium, fontSize: 13, lineHeight: 18, color: COLORS.primaryDark },
  seg: { flexDirection: 'row', backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, padding: 4 },
  segBtn: { flex: 1, alignItems: 'center', paddingVertical: 9, borderRadius: RADIUS.full },
  segOn: { backgroundColor: COLORS.primary },
  segText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  search: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: SPACING.md, borderRadius: RADIUS.full, backgroundColor: COLORS.white },
  input: { flex: 1, minHeight: 44, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
  filterBtn: { width: 46, height: 46, borderRadius: 23, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  filterCount: { position: 'absolute', top: -2, right: -2, minWidth: 18, textAlign: 'center', backgroundColor: COLORS.black, color: COLORS.white, fontFamily: FONTS.bold, fontSize: 11, borderRadius: 9, overflow: 'hidden', paddingHorizontal: 4 },
  reqBtn: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.full, paddingHorizontal: 12, paddingVertical: 8 },
  reqText: { fontFamily: FONTS.bold, fontSize: 12.5, color: COLORS.primary },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingHorizontal: 13, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  backdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(20,6,12,0.5)' },
  sheet: { position: 'absolute', left: 0, right: 0, bottom: 0, backgroundColor: COLORS.white, borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: SPACING.lg },
  grab: { width: 44, height: 5, borderRadius: 3, backgroundColor: COLORS.gray300, alignSelf: 'center', marginBottom: 12 },
  sheetTitle: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.black, textAlign: 'center', marginBottom: 8 },
  label: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.black, marginTop: 14, marginBottom: 8 },
  cityInput: { borderWidth: 1.5, borderColor: COLORS.gray300, borderRadius: RADIUS.md, paddingHorizontal: 14, minHeight: 46, fontFamily: FONTS.regular, fontSize: 15, color: COLORS.black },
});
