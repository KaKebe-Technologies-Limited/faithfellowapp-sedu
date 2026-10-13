// Terms of Service and Privacy Policy, read inside the app (GET /api/v1/pages/{terms|privacy}). The text lives
// on the server in one place, so the app and the website always say the same thing.
import { ScrollView, StyleSheet, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import AppText from '../../components/AppText';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, SPACING } from '../../constants/theme';
import { useFetch } from '../../lib/useFetch';

export default function LegalScreen() {
  const { page } = useLocalSearchParams();
  const { data, loading, error, retry } = useFetch(`/pages/${page}`);
  const doc = data?.data;

  return (
    <View style={styles.screen}>
      <ScreenHeader title={doc?.title || (page === 'privacy' ? 'Privacy Policy' : 'Terms of Service')} />
      <StateView loading={loading} error={error} onRetry={retry}>
        {doc ? (
          <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
            {doc.updated ? (
              <AppText variant="caption" style={{ marginBottom: 6 }}>
                {doc.updated}
              </AppText>
            ) : null}
            {doc.blocks.map((b, i) =>
              b.type === 'heading' ? (
                <AppText key={i} style={styles.heading}>
                  {b.text}
                </AppText>
              ) : b.type === 'bullet' ? (
                <View key={i} style={styles.bullet}>
                  <AppText style={styles.dot}>•</AppText>
                  <AppText style={[styles.text, { flex: 1 }]}>{b.text}</AppText>
                </View>
              ) : (
                <AppText key={i} style={styles.text}>
                  {b.text}
                </AppText>
              ),
            )}
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { padding: SPACING.md, paddingBottom: 48, gap: 10 },
  heading: { fontFamily: FONTS.bold, fontSize: 18, color: COLORS.black, marginTop: 14 },
  text: { fontFamily: FONTS.regular, fontSize: 15, lineHeight: 24, color: COLORS.gray900 },
  bullet: { flexDirection: 'row', gap: 8, paddingLeft: 4 },
  dot: { fontFamily: FONTS.bold, color: COLORS.primary, lineHeight: 24 },
});
