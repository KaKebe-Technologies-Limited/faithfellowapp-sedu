// "How would you like to pay?": the church's payment methods as big selectable rows, the mobile money number
// when the method needs one, and the pay button. Shared by giving and event-ticket payment, so both look and
// behave the same. Methods come from GET /churches/{slug}/giving ({ type, label, needs_phone }).
import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { COLORS, FONTS, RADIUS, SPACING } from '../constants/theme';
import AppText from './AppText';
import Button from './Button';
import TextField from './TextField';

const METHOD_STYLE = {
  mtn_momo: { icon: 'phone-portrait-outline', tint: '#F5B800', hint: 'MTN Mobile Money' },
  airtel_money: { icon: 'phone-portrait-outline', tint: '#E40000', hint: 'Airtel Money' },
  iotec: { icon: 'phone-portrait-outline', tint: COLORS.primary, hint: 'MTN or Airtel Money, paid securely through ioTec Pay' },
  stripe: { icon: 'card-outline', tint: '#635BFF', hint: 'Visa or Mastercard, on a secure page' },
};

export default function PayForm({ methods = [], savedPhone = '', submitLabel = 'Pay now', loading = false, error = null, onSubmit }) {
  const [method, setMethod] = useState(methods[0]?.type ?? null);
  const [phone, setPhone] = useState(savedPhone || '');
  const [touched, setTouched] = useState(false);

  const chosen = methods.find((m) => m.type === method);
  // The server sends the pattern, so while ioTec's test mode is on its test numbers are accepted too.
  const phoneBad = chosen?.needs_phone && !new RegExp(chosen.phone_pattern || String.raw`^(\+?256|0)?7\d{8}$`).test(phone.replace(/[\s-]/g, ''));

  if (!methods.length) {
    return (
      <View style={styles.none}>
        <Ionicons name="alert-circle-outline" size={22} color={COLORS.warning} />
        <AppText style={{ flex: 1, color: COLORS.gray900 }}>This church has not switched on online payments yet. Please ask the church office how to give.</AppText>
      </View>
    );
  }

  function submit() {
    setTouched(true);
    if (!chosen || phoneBad) return;
    onSubmit({ method: chosen.type, phone: chosen.needs_phone ? phone.replace(/[\s-]/g, '') : undefined });
  }

  return (
    <View>
      <AppText style={styles.label}>Pay with</AppText>
      <View style={styles.list}>
        {methods.map((m) => {
          const look = METHOD_STYLE[m.type] ?? { icon: 'wallet-outline', tint: COLORS.primary, hint: '' };
          const selected = m.type === method;
          return (
            <Pressable
              key={m.type}
              onPress={() => setMethod(m.type)}
              accessibilityRole="radio"
              accessibilityState={{ selected }}
              style={[styles.method, selected && styles.methodOn]}
            >
              <View style={[styles.badge, { backgroundColor: `${look.tint}1F` }]}>
                <Ionicons name={look.icon} size={20} color={look.tint} />
              </View>
              <View style={{ flex: 1 }}>
                <AppText style={styles.methodName}>{m.label}</AppText>
                {look.hint ? (
                  <AppText variant="caption" numberOfLines={1}>
                    {look.hint}
                  </AppText>
                ) : null}
              </View>
              <Ionicons name={selected ? 'radio-button-on' : 'radio-button-off'} size={22} color={selected ? COLORS.primary : COLORS.gray300} />
            </Pressable>
          );
        })}
      </View>

      {chosen?.needs_phone ? (
        <TextField
          label="Mobile money number"
          value={phone}
          onChangeText={setPhone}
          keyboardType="phone-pad"
          placeholder="0772 123 456"
          autoComplete="tel"
          error={touched && phoneBad ? 'Enter a Ugandan mobile money number, for example 0772 123 456.' : undefined}
        />
      ) : null}
      {chosen?.needs_phone ? <AppText variant="caption" style={styles.note}>A prompt will be sent to this phone. Enter your mobile money PIN to approve it, and the amount is deducted from your mobile money.</AppText> : null}

      {error ? <AppText style={styles.error}>{error}</AppText> : null}
      <Button title={submitLabel} onPress={submit} loading={loading} style={{ marginTop: SPACING.sm }} />
    </View>
  );
}

const styles = StyleSheet.create({
  label: { fontFamily: FONTS.semibold, fontSize: 14, color: COLORS.black, marginBottom: 8 },
  list: { gap: 10, marginBottom: SPACING.md },
  method: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, borderRadius: RADIUS.lg, backgroundColor: COLORS.white, borderWidth: 1.5, borderColor: COLORS.gray100 },
  methodOn: { borderColor: COLORS.primary, backgroundColor: '#FFF7FB' },
  badge: { width: 42, height: 42, borderRadius: 13, alignItems: 'center', justifyContent: 'center' },
  methodName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  note: { marginTop: -8, marginBottom: 8 },
  error: { color: COLORS.danger, fontFamily: FONTS.medium, marginBottom: 6 },
  none: { flexDirection: 'row', gap: 10, padding: 14, borderRadius: RADIUS.lg, backgroundColor: '#FFF8E6', alignItems: 'center' },
});
