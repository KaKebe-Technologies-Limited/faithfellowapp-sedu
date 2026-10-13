// One prayer request: the full text, how many have prayed, the "I prayed" button, a pastor's reply if the
// request is yours, and (for your own requests) marking it answered with a note of praise, or removing it.
import { useState } from 'react';
import { Alert, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import ChurchAvatar from '../../components/ChurchAvatar';
import Pill from '../../components/Pill';
import { PrayedButton } from '../../components/PrayerCard';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import api, { errorMessage } from '../../lib/api';
import { initials, timeAgo } from '../../lib/format';
import { useFetch } from '../../lib/useFetch';

const WHO = { church: 'Your church family', public: 'Everyone', pastor: 'Only your pastors' };

export default function PrayerScreen() {
  const { id } = useLocalSearchParams();
  const { data, loading, error, retry, reload } = useFetch(`/prayer/${id}`);
  const p = data?.data;
  const [note, setNote] = useState('');
  const [showAnswer, setShowAnswer] = useState(false);
  const [busy, setBusy] = useState(false);

  async function markAnswered() {
    setBusy(true);
    try {
      await api.post(`/prayer/${id}/answered`, { answer_note: note.trim() || null });
      setShowAnswer(false);
      await reload();
    } catch (e) {
      Alert.alert('Could not update', errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  async function remove() {
    const run = async () => {
      try {
        await api.delete(`/prayer/${id}`);
        router.replace('/prayer');
      } catch (e) {
        Alert.alert('Could not remove', errorMessage(e));
      }
    };
    if (Platform.OS === 'web') return run();
    Alert.alert('Remove this request?', 'It will be taken off the prayer wall.', [
      { text: 'Keep it', style: 'cancel' },
      { text: 'Remove', style: 'destructive', onPress: run },
    ]);
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Prayer request" />
      <StateView loading={loading} error={error} onRetry={retry}>
        {p ? (
          <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
            <View style={styles.card}>
              <View style={styles.head}>
                <View style={styles.person}>
                  <AppText style={styles.initial}>{p.author === 'Anonymous' ? '🙏' : initials(p.author)}</AppText>
                </View>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.name}>{p.is_mine ? 'You' : p.author}</AppText>
                  <AppText variant="caption">{timeAgo(p.created_at)}</AppText>
                </View>
                <ChurchAvatar church={p.church} size={30} />
              </View>

              {p.status === 'pending' ? <Pill label="Waiting for a pastor's approval" icon="time-outline" tone="urgent" style={{ marginTop: 12 }} /> : null}
              {p.status === 'declined' ? <Pill label="Not shared on the wall" icon="eye-off-outline" tone="urgent" style={{ marginTop: 12 }} /> : null}
              {p.status === 'answered' ? <Pill label="Answered ✓" icon="sparkles-outline" style={{ marginTop: 12 }} /> : null}

              {p.title ? <AppText style={styles.title}>{p.title}</AppText> : null}
              <AppText style={styles.body}>{p.body}</AppText>
              {p.is_mine ? (
                <AppText variant="caption" style={{ marginTop: 12 }}>
                  Visible to: {WHO[p.visibility] ?? p.visibility} · {p.church?.name}
                </AppText>
              ) : null}

              {p.status === 'answered' && p.answer_note ? (
                <View style={styles.answer}>
                  <AppText style={styles.answerTitle}>God answered 🎉</AppText>
                  <AppText style={styles.answerText}>{p.answer_note}</AppText>
                </View>
              ) : null}
              {p.pastor_response ? (
                <View style={styles.reply}>
                  <AppText style={styles.answerTitle}>A reply from your pastors</AppText>
                  <AppText style={styles.answerText}>{p.pastor_response}</AppText>
                </View>
              ) : null}

              {p.status === 'open' || p.status === 'answered' ? (
                <View style={{ marginTop: 16, flexDirection: 'row' }}>
                  <PrayedButton prayer={p} />
                </View>
              ) : null}
            </View>

            {p.is_mine && p.status === 'open' ? (
              showAnswer ? (
                <View style={styles.card}>
                  <AppText style={styles.h}>Praise report</AppText>
                  <TextField placeholder="How did God answer? (optional)" value={note} onChangeText={setNote} multiline numberOfLines={4} maxLength={2000} style={{ minHeight: 90 }} />
                  <Button title="Mark as answered" onPress={markAnswered} loading={busy} />
                  <Button title="Cancel" variant="outline" onPress={() => setShowAnswer(false)} style={{ marginTop: 8 }} />
                </View>
              ) : (
                <Button title="God answered this prayer" onPress={() => setShowAnswer(true)} />
              )
            ) : null}
            {p.is_mine ? <Button title="Remove this request" variant="outline" onPress={remove} /> : null}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md, paddingBottom: 48, gap: 12 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.xl - 4, padding: SPACING.md, borderTopWidth: 4, borderTopColor: COLORS.primaryPale, ...SHADOW },
  head: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  person: { width: 42, height: 42, borderRadius: 21, backgroundColor: COLORS.primaryLight, alignItems: 'center', justifyContent: 'center' },
  initial: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.white },
  name: { fontFamily: FONTS.bold, fontSize: 15, color: COLORS.black },
  title: { fontFamily: FONTS.bold, fontSize: 20, lineHeight: 26, color: COLORS.black, marginTop: 14 },
  body: { fontFamily: FONTS.regular, fontSize: 16.5, lineHeight: 26, color: '#2B2B2B', marginTop: 10 },
  answer: { backgroundColor: COLORS.primaryMist, borderRadius: RADIUS.md, padding: 14, marginTop: 16, gap: 4 },
  reply: { backgroundColor: '#F2F7FF', borderRadius: RADIUS.md, padding: 14, marginTop: 12, gap: 4 },
  answerTitle: { fontFamily: FONTS.bold, fontSize: 14, color: COLORS.primaryDark },
  answerText: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 22, color: COLORS.gray900 },
  h: { fontFamily: FONTS.bold, fontSize: 16, color: COLORS.black, marginBottom: 8 },
});
