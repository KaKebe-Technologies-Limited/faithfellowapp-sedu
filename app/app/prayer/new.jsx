// "Ask for prayer": choose the church, write the request, decide who may see it (your church family, everyone, or
// only your pastors), and whether to stay anonymous. Church requests wait for a pastor's approval before they
// appear on the wall; pastor-only requests are never shown to members.
import { useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Switch, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ScreenHeader from '../../components/ScreenHeader';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import api, { errorMessage, fieldErrors } from '../../lib/api';
import { useAuth } from '../../lib/auth';

const WHO = [
  { key: 'church', icon: 'people-outline', title: 'My church family', text: 'Members of this church can pray with you.' },
  { key: 'public', icon: 'globe-outline', title: 'Everyone', text: 'Anyone on FaithFellows can see and pray.' },
  { key: 'pastor', icon: 'lock-closed-outline', title: 'Only my pastors', text: 'Private. Your pastors pray with you and may reply.' },
];

export default function NewPrayer() {
  const { user } = useAuth();
  const churches = user?.churches ?? [];
  const [churchId, setChurchId] = useState(churches[0]?.id ?? null);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [visibility, setVisibility] = useState('church');
  const [anonymous, setAnonymous] = useState(false);
  const [chain, setChain] = useState(false);
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState({});

  async function submit() {
    if (body.trim().length < 5) {
      setErrors({ body: 'Tell us a little more (at least a few words).' });
      return;
    }
    setBusy(true);
    setErrors({});
    try {
      const { data } = await api.post('/prayer', { church_id: churchId, title: title.trim() || null, body: body.trim(), visibility, is_anonymous: anonymous, chain_enabled: chain && visibility !== 'pastor' });
      const note = visibility === 'pastor' ? 'Sent privately to your pastors. They will pray with you.' : 'Sent to your pastor for approval. Once approved, your church family can pray with you.';
      if (Platform.OS === 'web') router.replace({ pathname: '/prayer/[id]', params: { id: data.data.id } });
      else Alert.alert('Request sent 🙏', note, [{ text: 'OK', onPress: () => router.replace({ pathname: '/prayer/[id]', params: { id: data.data.id } }) }]);
    } catch (e) {
      const fields = fieldErrors(e);
      setErrors(fields);
      if (!Object.keys(fields).length) Alert.alert('Could not send', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  if (!churches.length) {
    return (
      <View style={styles.screen}>
        <ScreenHeader title="Ask for prayer" />
        <View style={styles.none}>
          <Ionicons name="home-outline" size={34} color={COLORS.primary} />
          <AppText style={styles.noneTitle}>Follow a church first</AppText>
          <AppText style={styles.noneText}>Prayer requests go to your church family and pastors. Find a church to follow, then come back.</AppText>
          <Button title="Find a church" onPress={() => router.replace('/churches')} />
        </View>
      </View>
    );
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Ask for prayer" />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {churches.length > 1 ? (
            <>
              <AppText style={styles.h}>Which church?</AppText>
              <View style={styles.wrap}>
                {churches.map((c) => (
                  <Pressable key={c.id} onPress={() => setChurchId(c.id)} style={[styles.chip, churchId === c.id && styles.chipOn]} accessibilityRole="button">
                    <AppText style={[styles.chipText, churchId === c.id && { color: COLORS.white }]}>{c.name}</AppText>
                  </Pressable>
                ))}
              </View>
            </>
          ) : null}

          <TextField label="Title (optional)" value={title} onChangeText={setTitle} placeholder="For example: My exams" maxLength={160} style={{ marginTop: churches.length > 1 ? 16 : 0 }} />
          <TextField label="Your request" value={body} onChangeText={setBody} placeholder="Share what you would like prayer for…" multiline numberOfLines={6} maxLength={2000} error={errors.body} style={{ minHeight: 120 }} />

          <AppText style={styles.h}>Who can see this?</AppText>
          <View style={{ gap: 10 }}>
            {WHO.map((w) => {
              const on = visibility === w.key;
              return (
                <Pressable key={w.key} onPress={() => setVisibility(w.key)} style={[styles.who, on && styles.whoOn]} accessibilityRole="radio" accessibilityState={{ selected: on }}>
                  <Ionicons name={w.icon} size={22} color={COLORS.primary} />
                  <View style={{ flex: 1 }}>
                    <AppText style={styles.whoTitle}>{w.title}</AppText>
                    <AppText variant="caption">{w.text}</AppText>
                  </View>
                  <Ionicons name={on ? 'radio-button-on' : 'radio-button-off'} size={22} color={on ? COLORS.primary : COLORS.gray300} />
                </Pressable>
              );
            })}
          </View>

          <View style={styles.switch}>
            <View style={{ flex: 1 }}>
              <AppText style={styles.whoTitle}>Ask anonymously</AppText>
              <AppText variant="caption">Your name is hidden from everyone but your pastors.</AppText>
            </View>
            <Switch value={anonymous} onValueChange={setAnonymous} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
          </View>
          {visibility !== 'pastor' ? (
            <View style={styles.switch}>
              <View style={{ flex: 1 }}>
                <AppText style={styles.whoTitle}>Start a prayer chain</AppText>
                <AppText variant="caption">One member at a time is asked to pray, passing it on.</AppText>
              </View>
              <Switch value={chain} onValueChange={setChain} trackColor={{ true: COLORS.primaryLight, false: COLORS.gray300 }} thumbColor={COLORS.white} />
            </View>
          ) : null}

          <Button title="Send request" onPress={submit} loading={busy} style={{ marginTop: SPACING.lg }} />
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md, paddingBottom: 48 },
  h: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black, marginBottom: 10, marginTop: 6 },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { paddingHorizontal: 14, paddingVertical: 9, borderRadius: RADIUS.full, backgroundColor: COLORS.primaryMist },
  chipOn: { backgroundColor: COLORS.primary },
  chipText: { fontFamily: FONTS.semibold, fontSize: 13.5, color: COLORS.primary },
  who: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: RADIUS.lg, backgroundColor: COLORS.white, borderWidth: 1.5, borderColor: COLORS.gray100 },
  whoOn: { borderColor: COLORS.primary, backgroundColor: '#FFF7FB' },
  whoTitle: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  switch: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, marginTop: 12 },
  none: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: SPACING.xl, gap: 10 },
  noneTitle: { fontFamily: FONTS.bold, fontSize: 20, color: COLORS.black },
  noneText: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.gray700, textAlign: 'center', marginBottom: 8 },
});
