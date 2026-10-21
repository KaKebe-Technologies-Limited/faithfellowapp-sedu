// Shared loading / error / empty states. Every screen wraps its content in <StateView> so
// the three states look and behave the same everywhere.
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { COLORS, SPACING } from '../constants/theme';
import { isConnectionMessage } from '../lib/errors';
import AppText from './AppText';
import Button from './Button';

export default function StateView({
  loading = false,
  error = null,
  empty = false,
  onRetry,
  emptyIcon = 'file-tray-outline',
  emptyTitle = 'Nothing here yet',
  emptyMessage = '',
  emptyAction,
  children,
}) {
  if (loading) {
    return (
      <View style={styles.center} accessibilityLabel="Loading">
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  if (error) {
    const offline = isConnectionMessage(error);

    return (
      <View style={styles.center}>
        <View style={styles.icon}>
          <Ionicons name="cloud-offline-outline" size={30} color={COLORS.primary} />
        </View>
        <AppText variant="heading" style={styles.title}>
          {offline ? 'No internet connection' : 'Something went wrong'}
        </AppText>
        <AppText style={styles.message}>{error}</AppText>
        {onRetry ? <Button title="Try again" variant="outline" small onPress={onRetry} style={styles.action} /> : null}
      </View>
    );
  }

  if (empty) {
    return (
      <View style={styles.center}>
        <View style={styles.icon}>
          <Ionicons name={emptyIcon} size={30} color={COLORS.primary} />
        </View>
        <AppText variant="heading" style={styles.title}>
          {emptyTitle}
        </AppText>
        {emptyMessage ? <AppText style={styles.message}>{emptyMessage}</AppText> : null}
        {emptyAction ? (
          <Button title={emptyAction.title} variant="outline" small onPress={emptyAction.onPress} style={styles.action} />
        ) : null}
      </View>
    );
  }

  return children;
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: SPACING.xl, minHeight: 240 },
  icon: { width: 64, height: 64, borderRadius: 32, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center', marginBottom: SPACING.md },
  title: { textAlign: 'center', marginBottom: 4 },
  message: { textAlign: 'center', color: COLORS.gray700 },
  action: { marginTop: SPACING.md },
});
