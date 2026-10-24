// Platform admin → Notify everyone: one notification to every phone that has the app. It asks for your password again before it goes, because it cannot be taken
// back. Data: POST /api/v1/platform/announce.
import { useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import PasswordSheet from '../../components/platform/PasswordSheet';
import ScreenHeader from '../../components/ScreenHeader';
import TextField from '../../components/TextField';
import { COLORS, FONTS, RADIUS, SPACING } from '../../constants/theme';
import api from '../../lib/api';

export default function PlatformNotify() {
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [asking, setAsking] = useState(false);
  const [done, setDone] = useState(null);

  async function send({ password }) {
    const { data } = await api.post('/platform/announce', { title: title.trim(), body: body.trim(), password });
    setAsking(false);
    setDone(data.message);
    setTitle('');
    setBody('');
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Notify everyone" subtitle="One message to every phone with the app" />
      <ScrollView contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
        {done ? (
          <View style={styles.done}>
            <Ionicons name="checkmark-circle" size={20} color={COLORS.success} />
            <AppText style={styles.doneText}>{done}</AppText>
          </View>
        ) : null}
        <TextField label={`Title · ${title.length}/80`} value={title} onChangeText={setTitle} maxLength={80} placeholder="Revival week starts Monday" />
        <TextField label={`Message · ${body.length}/240`} value={body} onChangeText={setBody} maxLength={240} multiline placeholder="Join your church family all week. Details in the app." />
        <View style={styles.preview}>
          <Ionicons name="notifications" size={18} color={COLORS.primary} />
          <View style={{ flex: 1 }}>
            <AppText style={styles.previewTitle} numberOfLines={1}>
              {title.trim() || 'Your title'}
            </AppText>
            <AppText style={styles.previewBody} numberOfLines={3}>
              {body.trim() || 'Your message appears like this on the phone.'}
            </AppText>
          </View>
        </View>
        <Button title="Send to everyone" onPress={() => setAsking(true)} disabled={!title.trim() || !body.trim()} />
        <AppText style={styles.note}>People who turned announcements off do not get it. It cannot be taken back once sent.</AppText>
      </ScrollView>
      <PasswordSheet visible={asking} title="Send to everyone?" message={`“${title.trim()}” goes to every phone that has the app.`} confirmLabel="Send now" onClose={() => setAsking(false)} onConfirm={send} />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  body: { padding: SPACING.md, gap: 12, paddingBottom: 48 },
  done: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: COLORS.successMist, borderRadius: RADIUS.md, padding: 12 },
  doneText: { flex: 1, fontFamily: FONTS.medium, fontSize: 14, color: COLORS.black },
  preview: { flexDirection: 'row', gap: 10, backgroundColor: COLORS.white, borderRadius: RADIUS.lg, padding: 14, borderWidth: 1, borderColor: COLORS.gray100 },
  previewTitle: { fontFamily: FONTS.bold, fontSize: 14.5, color: COLORS.black },
  previewBody: { fontFamily: FONTS.regular, fontSize: 13.5, lineHeight: 19, color: COLORS.gray900, marginTop: 2 },
  note: { fontFamily: FONTS.regular, fontSize: 12.5, lineHeight: 18, color: COLORS.gray700, textAlign: 'center' },
});
