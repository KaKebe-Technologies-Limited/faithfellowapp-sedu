// "Have a church code?": type the code a church gave you (GRACE7KQ), or paste its link, and the app shows the church. Works signed in or out.
import { useState } from 'react';
import { KeyboardAvoidingView, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import KeyboardScroll from '../../components/KeyboardScroll';
import ScreenHeader from '../../components/ScreenHeader';
import TextField from '../../components/TextField';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { parseJoinCode } from '../../lib/join';

export default function JoinCode() {
  const [text, setText] = useState('');
  const [error, setError] = useState(null);
  const code = parseJoinCode(text);

  function next() {
    if (code.length < 4) return setError('Type the church code, or paste the link you were sent.');
    setError(null);
    router.push({ pathname: '/join/[code]', params: { code } });
  }

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Join your church" />
      <KeyboardAvoidingView style={{ flex: 1 }} behavior="padding">
        <KeyboardScroll contentContainerStyle={styles.body} keyboardShouldPersistTaps="handled">
          <AppText style={styles.lead}>Your church has a code, like GRACE7KQ. Type it here, or paste the link they sent you, and we will take you to your church.</AppText>
          <TextField
            label="Church code or link"
            value={text}
            onChangeText={setText}
            error={error}
            autoCapitalize="characters"
            autoCorrect={false}
            autoComplete="off"
            placeholder="GRACE7KQ"
            returnKeyType="go"
            onSubmitEditing={next}
          />
          <Button title="Continue" onPress={next} style={{ marginTop: SPACING.md }} />
        </KeyboardScroll>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.white },
  body: { padding: SPACING.lg },
  lead: { fontFamily: FONTS.regular, fontSize: 15.5, lineHeight: 23, color: COLORS.gray700, marginBottom: SPACING.lg },
});
