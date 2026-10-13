// Edit your profile: photo, name, where you live, birthday, a short bio and what you care about — and who may find
// you. Being found by others (your churches' members, or any believer on the app) needs a birth date and is for
// adults only; nobody ever sees your email, phone number or birthday. Saved with PATCH /api/v1/me.
import { useEffect, useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Switch, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { router } from 'expo-router';
import AppText from '../../components/AppText';
import Avatar from '../../components/Avatar';
import Button from '../../components/Button';
import ScreenHeader from '../../components/ScreenHeader';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage, fieldErrors } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { COUNTRIES, INTERESTS } from '../../lib/people';
import { useFetch } from '../../lib/useFetch';
import StateView from '../../components/StateView';

function Chip({ label, on, onPress }) {
  return (
    <Pressable onPress={onPress} style={[styles.chip, on && styles.chipOn]} accessibilityRole="button" accessibilityState={{ selected: on }}>
      <AppText style={[styles.chipText, on && { color: COLORS.white }]}>{label}</AppText>
    </Pressable>
  );
}

/** Sends the picked picture as a multipart upload (a File on the web, a file reference on phones). */
async function uploadAvatar(asset) {
  const form = new FormData();
  if (Platform.OS === 'web') {
    const blob = await (await fetch(asset.uri)).blob();
    form.append('avatar', blob, 'avatar.jpg');
  } else {
    form.append('avatar', { uri: asset.uri, name: 'avatar.jpg', type: asset.mimeType || 'image/jpeg' });
  }
  const { data } = await api.post('/me/avatar', form, { headers: { 'Content-Type': 'multipart/form-data' }, transformRequest: (d) => d });
  return data.data;
}

export default function EditProfile() {
  const { setUser } = useAuth();
  const me = useFetch('/me');
  const user = me.data?.data;
  const [form, setForm] = useState(null);

  // Start from the freshest copy of your profile from the server.
  useEffect(() => {
    if (user && !form) {
      setForm({
        name: user.name ?? '',
        phone: user.phone ?? '',
        city: user.city ?? '',
        country: user.country ?? 'UG',
        gender: user.gender ?? null,
        date_of_birth: user.date_of_birth ?? '',
        bio: user.bio ?? '',
        interests: user.interests ?? [],
        directory_opt_in: !!user.directory_opt_in,
        open_to_connect: !!user.open_to_connect,
      });
    }
  }, [user, form]);

  const [busy, setBusy] = useState(false);
  const [photoBusy, setPhotoBusy] = useState(false);
  const [errors, setErrors] = useState({});

  const set = (key, value) => setForm((f) => ({ ...f, [key]: value }));
  if (!form) {
    return (
      <View style={styles.screen}>
        <ScreenHeader title="Edit profile" />
        <StateView loading={me.loading} error={me.error} onRetry={me.retry} />
      </View>
    );
  }
  const toggleInterest = (i) => set('interests', form.interests.includes(i) ? form.interests.filter((x) => x !== i) : form.interests.length >= 8 ? form.interests : [...form.interests, i]);

  async function pickPhoto() {
    const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], allowsEditing: true, aspect: [1, 1], quality: 0.7 });
    if (result.canceled || !result.assets?.length) return;
    setPhotoBusy(true);
    try {
      setUser(await uploadAvatar(result.assets[0]));
    } catch (e) {
      Alert.alert('Could not set the photo', errorMessage(e));
    } finally {
      setPhotoBusy(false);
    }
  }

  async function removePhoto() {
    try {
      setUser((await api.delete('/me/avatar')).data.data);
    } catch (e) {
      Alert.alert('Could not remove', errorMessage(e));
    }
  }

  async function save() {
    setBusy(true);
    setErrors({});
    try {
      const { data } = await api.patch('/me', { ...form, phone: form.phone || null, city: form.city || null, bio: form.bio || null, date_of_birth: form.date_of_birth || null });
      setUser(data.data);
      router.back();
    } catch (e) {
      const fields = fieldErrors(e);
      setErrors(fields);
      if (!Object.keys(fields).length) Alert.alert('Could not save', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Edit profile" />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 60 }} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
          <View style={styles.photo}>
            <Avatar person={user} size={96} />
            <View style={{ gap: 8, alignItems: 'flex-start' }}>
              <Button title={photoBusy ? 'Uploading…' : 'Change photo'} small variant="outline" onPress={pickPhoto} loading={photoBusy} />
              {user?.avatar_url ? (
                <Pressable onPress={removePhoto} accessibilityRole="button">
                  <AppText style={{ color: COLORS.gray700, textDecorationLine: 'underline', fontSize: 13 }}>Remove photo</AppText>
                </Pressable>
              ) : null}
            </View>
          </View>

          <TextField label="Name" value={form.name} onChangeText={(t) => set('name', t)} error={errors.name} />
          <TextField label="Phone" value={form.phone} onChangeText={(t) => set('phone', t)} error={errors.phone} keyboardType="phone-pad" placeholder="0772 123 456" />
          <TextField label="City" value={form.city} onChangeText={(t) => set('city', t)} error={errors.city} placeholder="For example: Lira" />

          <AppText style={styles.label}>Country</AppText>
          <View style={styles.wrap}>
            {COUNTRIES.map(([code, name]) => (
              <Chip key={code} label={name} on={form.country === code} onPress={() => set('country', code)} />
            ))}
          </View>

          <AppText style={[styles.label, { marginTop: 16 }]}>Gender</AppText>
          <View style={styles.wrap}>
            <Chip label="Man" on={form.gender === 'male'} onPress={() => set('gender', form.gender === 'male' ? null : 'male')} />
            <Chip label="Woman" on={form.gender === 'female'} onPress={() => set('gender', form.gender === 'female' ? null : 'female')} />
          </View>

          <TextField label="Date of birth (YYYY-MM-DD)" value={form.date_of_birth} onChangeText={(t) => set('date_of_birth', t)} error={errors.date_of_birth} placeholder="1995-04-23" keyboardType="numbers-and-punctuation" style={{ marginTop: 16 }} />
          <TextField label="About you" value={form.bio} onChangeText={(t) => set('bio', t)} error={errors.bio} placeholder="A line or two about your walk with God" multiline numberOfLines={4} maxLength={500} style={{ minHeight: 90 }} />

          <AppText style={styles.label}>What do you care about? (up to 8)</AppText>
          <View style={styles.wrap}>
            {INTERESTS.map((i) => (
              <Chip key={i} label={i} on={form.interests.includes(i)} onPress={() => toggleInterest(i)} />
            ))}
          </View>

          <View style={styles.privacy}>
            <View style={styles.privacyHead}>
              <Ionicons name="shield-checkmark-outline" size={20} color={COLORS.primary} />
              <AppText style={styles.privacyTitle}>Who can find you</AppText>
            </View>
            <View style={styles.switchRow}>
              <View style={{ flex: 1 }}>
                <AppText style={styles.switchTitle}>Members of my churches</AppText>
                <AppText variant="caption">List me in the member directory of the churches I follow.</AppText>
              </View>
              <Switch value={form.directory_opt_in} onValueChange={(v) => set('directory_opt_in', v)} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
            </View>
            <View style={styles.switchRow}>
              <View style={{ flex: 1 }}>
                <AppText style={styles.switchTitle}>Believers across the app</AppText>
                <AppText variant="caption">Let any adult on FaithFellows find me and ask to connect.</AppText>
              </View>
              <Switch value={form.open_to_connect} onValueChange={(v) => set('open_to_connect', v)} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
            </View>
            <AppText variant="caption" style={{ lineHeight: 18 }}>
              Only your name, photo, city, age range (never your birthday), about line and interests are shown. Your email and phone stay private. Being found needs your birth date, and is for members aged 18 and over. You can switch this off any time.
            </AppText>
            {errors.date_of_birth ? <AppText style={{ color: COLORS.danger, fontFamily: FONTS.medium }}>{errors.date_of_birth}</AppText> : null}
          </View>

          <Button title="Save profile" onPress={save} loading={busy} style={{ marginTop: SPACING.lg }} />
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  photo: { flexDirection: 'row', alignItems: 'center', gap: 16, marginBottom: SPACING.lg },
  label: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.black, marginBottom: 8 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingHorizontal: 13, paddingVertical: 8, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13, color: COLORS.primary },
  privacy: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, gap: 12, marginTop: SPACING.lg, ...SHADOW, shadowOpacity: 0.05 },
  privacyHead: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  privacyTitle: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  switchTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
});
