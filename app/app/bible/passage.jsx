// A passage opened from the Bible search box ("John 3:16", "Matthew 3-5", "Psalm 23").
import { ScrollView, StyleSheet, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import PassageView from '../../components/PassageView';
import ScreenHeader from '../../components/ScreenHeader';
import { COLORS, SPACING } from '../../constants/theme';

export default function PassageScreen() {
  const { ref } = useLocalSearchParams();

  return (
    <View style={styles.screen}>
      <ScreenHeader title={String(ref ?? 'Passage')} subtitle="Bible" />
      <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 48 }} showsVerticalScrollIndicator={false}>
        <PassageView reference={String(ref ?? '')} fontSize={18} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
});
