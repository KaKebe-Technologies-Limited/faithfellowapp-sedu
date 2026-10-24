// "Enter your password to continue": what is hard to undo (suspending a church, a notification to everyone, a broadcast on every radio) asks the platform
// admin for the password again, so a phone that is left unlocked cannot do it. It can also ask for a reason, which the person on the other end is told.
import { useEffect, useState } from 'react';
import { StyleSheet, View } from 'react-native';
import AppText from '../AppText';
import Button from '../Button';
import Sheet from '../Sheet';
import TextField from '../TextField';
import { COLORS, FONTS } from '../../constants/theme';
import { errorMessage, fieldErrors } from '../../lib/api';

/**
 * `onConfirm({ password, reason })` does the action and may throw: a wrong password or a missing reason is shown under its field, anything else above the button.
 * `reason` is false (not asked), 'optional' or 'required'.
 */
export default function PasswordSheet({ visible, title, message, confirmLabel = 'Confirm', reason = false, reasonLabel = 'Reason', onClose, onConfirm }) {
  const [password, setPassword] = useState('');
  const [why, setWhy] = useState('');
  const [errors, setErrors] = useState({});
  const [problem, setProblem] = useState(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (visible) {
      setPassword('');
      setWhy('');
      setErrors({});
      setProblem(null);
    }
  }, [visible]);

  async function go() {
    setBusy(true);
    setErrors({});
    setProblem(null);
    try {
      await onConfirm({ password, reason: why.trim() || undefined });
    } catch (e) {
      const fields = fieldErrors(e);
      if (Object.keys(fields).length) setErrors(fields);
      else setProblem(errorMessage(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Sheet visible={visible} onClose={busy ? () => {} : onClose} title={title}>
      {message ? <AppText style={styles.message}>{message}</AppText> : null}
      {reason ? <TextField label={`${reasonLabel}${reason === 'optional' ? ' (optional)' : ''}`} value={why} onChangeText={setWhy} multiline maxLength={255} error={errors.reason} /> : null}
      <TextField label="Your password" value={password} onChangeText={setPassword} secureTextEntry autoCapitalize="none" autoCorrect={false} error={errors.password} />
      {problem ? <AppText style={styles.problem}>{problem}</AppText> : null}
      <View style={{ gap: 10, marginTop: 6 }}>
        <Button title={confirmLabel} onPress={go} loading={busy} disabled={!password} />
        <Button title="Cancel" variant="outline" onPress={onClose} disabled={busy} />
      </View>
    </Sheet>
  );
}

const styles = StyleSheet.create({
  message: { fontFamily: FONTS.regular, fontSize: 14.5, lineHeight: 21, color: COLORS.gray900, textAlign: 'center', marginBottom: 12 },
  problem: { fontFamily: FONTS.medium, fontSize: 13, color: COLORS.danger, textAlign: 'center', marginBottom: 6 },
});
