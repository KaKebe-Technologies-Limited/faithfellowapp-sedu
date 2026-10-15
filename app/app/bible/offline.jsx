// Offline Bible: keep the Bible on this phone so it can be read with no internet. Download the whole Bible, the Old or
// New Testament, or single books, in any translation; see what is stored and how much room it takes; remove it again.
// Downloads carry on while you read or leave this screen. Data: GET /api/v1/bible and GET /api/v1/bible/download/{book}.
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Alert, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useFocusEffect } from 'expo-router';
import AppText from '../../components/AppText';
import Button from '../../components/Button';
import Chip from '../../components/Chip';
import ScreenHeader from '../../components/ScreenHeader';
import StateView from '../../components/StateView';
import { COLORS, FONTS, RADIUS, SHADOW, SPACING } from '../../constants/theme';
import { cancelDownload, startDownload, useBibleIndex, useDownloadJob } from '../../lib/bibleOffline';
import { downloadedCounts, formatBytes, removeBook, removeTranslation, storageUsed } from '../../lib/bibleStore';

const confirm = (title, message, action) => {
  if (Platform.OS === 'web') return action();
  Alert.alert(title, message, [{ text: 'Keep', style: 'cancel' }, { text: 'Remove', style: 'destructive', onPress: action }]);
};

export default function OfflineBible() {
  const { bible, loading, error, retry, offline } = useBibleIndex();
  const job = useDownloadJob();
  const [translation, setTranslation] = useState(null);
  const [counts, setCounts] = useState({});
  const [bytes, setBytes] = useState(0);

  // The ESV is licensed text that is read online only, so it is not offered here.
  const downloadable = useMemo(() => (bible?.translations ?? []).filter((t) => t.offline !== false), [bible]);
  const wanted = translation ?? bible?.translation;
  const current = downloadable.some((t) => t.key === wanted) ? wanted : (downloadable[0]?.key ?? wanted);
  const books = useMemo(() => bible?.books ?? [], [bible]);
  const name = bible?.translations?.find((t) => t.key === current)?.short ?? String(current ?? '').toUpperCase();

  const refresh = useCallback(async () => {
    if (!current) return;
    setCounts(await downloadedCounts(current));
    setBytes(await storageUsed(current));
  }, [current]);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );
  // While a download runs the numbers change by themselves.
  useEffect(() => {
    refresh();
  }, [refresh, job.done, job.running]);

  const complete = books.filter((b) => (counts[b.code] ?? 0) >= b.chapters).length;
  const totalChapters = books.reduce((n, b) => n + b.chapters, 0);
  const haveChapters = books.reduce((n, b) => n + Math.min(counts[b.code] ?? 0, b.chapters), 0);
  const busy = job.running;

  const start = (list, label) => startDownload(current, list, label);
  const testament = (code) => books.filter((b) => b.testament === code);
  const percent = job.total ? Math.round((job.done / job.total) * 100) : 0;

  return (
    <View style={styles.screen}>
      <ScreenHeader title="Offline Bible" subtitle="Read without internet" />
      <StateView loading={loading && !bible} error={!bible ? error : null} onRetry={retry}>
        {bible ? (
          <ScrollView contentContainerStyle={{ padding: SPACING.md, paddingBottom: 56, gap: 14 }} showsVerticalScrollIndicator={false}>
            {downloadable.length > 1 ? (
              <View style={styles.chips}>
                {downloadable.map((t) => (
                  <Chip key={t.key} label={t.short} on={t.key === current} onPress={() => !busy && setTranslation(t.key)} />
                ))}
              </View>
            ) : null}

            <View style={styles.card}>
              <View style={styles.summaryRow}>
                <View style={styles.cloud}>
                  <Ionicons name={haveChapters >= totalChapters && totalChapters ? 'checkmark-done' : 'cloud-download-outline'} size={26} color={COLORS.primary} />
                </View>
                <View style={{ flex: 1 }}>
                  <AppText style={styles.h}>{name} on this phone</AppText>
                  <AppText variant="caption">{haveChapters ? `${complete} of ${books.length} books · ${haveChapters.toLocaleString()} chapters · ${formatBytes(bytes)}` : 'Nothing downloaded yet'}</AppText>
                </View>
              </View>

              {busy ? (
                <View style={{ gap: 8 }}>
                  <View style={styles.track}>
                    <View style={[styles.fill, { width: `${Math.max(3, percent)}%` }]} />
                  </View>
                  <AppText variant="caption" numberOfLines={1}>{job.label} · {percent}%</AppText>
                  <Button title="Stop" variant="outline" small onPress={cancelDownload} style={{ alignSelf: 'flex-start' }} />
                </View>
              ) : (
                <>
                  {job.error ? <AppText style={styles.err}>{job.error}</AppText> : null}
                  {job.finished && !job.error ? <AppText style={styles.done}>Finished. These books are ready to read offline.</AppText> : null}
                  {offline ? <AppText variant="caption">You are offline. Connect to download more.</AppText> : null}
                  {haveChapters < totalChapters ? <Button title={`Download the whole Bible (${name})`} onPress={() => start(books, name)} disabled={offline} /> : null}
                  <View style={styles.row2}>
                    <Button title="Old Testament" small variant="outline" onPress={() => start(testament('OT'), `${name} Old Testament`)} disabled={offline} />
                    <Button title="New Testament" small variant="outline" onPress={() => start(testament('NT'), `${name} New Testament`)} disabled={offline} />
                  </View>
                  <AppText variant="caption">The whole Bible takes about 4 MB. The first time a book is downloaded it can take a minute or two while it is made ready.</AppText>
                </>
              )}
            </View>

            {['OT', 'NT'].map((code) => (
              <View key={code} style={styles.card}>
                <AppText style={styles.h}>{code === 'OT' ? 'Old Testament' : 'New Testament'}</AppText>
                {testament(code).map((b) => {
                  const have = Math.min(counts[b.code] ?? 0, b.chapters);
                  const whole = have >= b.chapters;
                  return (
                    <View key={b.code} style={styles.book}>
                      <Pressable onPress={() => !busy && !whole && !offline && start([b], b.name)} style={{ flex: 1 }} accessibilityRole="button" accessibilityLabel={whole ? `${b.name} is on your phone` : `Download ${b.name}`}>
                        <AppText style={styles.bookName}>{b.name}</AppText>
                        <AppText variant="caption">{whole ? `All ${b.chapters} chapters` : have ? `${have} of ${b.chapters} chapters` : `${b.chapters} chapters`}</AppText>
                      </Pressable>
                      {whole ? <Ionicons name="checkmark-circle" size={22} color={COLORS.success} /> : !busy && !offline ? <Ionicons name="cloud-download-outline" size={21} color={COLORS.primary} /> : null}
                      {have ? (
                        <Pressable onPress={() => confirm(`Remove ${b.name}?`, 'It will need internet to read again.', async () => { await removeBook(current, b.code); refresh(); })} hitSlop={8} accessibilityLabel={`Remove ${b.name} from this phone`} style={{ marginLeft: 8 }}>
                          <Ionicons name="trash-outline" size={19} color={COLORS.gray500} />
                        </Pressable>
                      ) : null}
                    </View>
                  );
                })}
              </View>
            ))}

            {haveChapters ? (
              <Button title={`Remove all ${name} downloads`} variant="outline" onPress={() => confirm(`Remove ${name} from this phone?`, 'You can download it again any time.', async () => { await removeTranslation(current); refresh(); })} disabled={busy} />
            ) : null}
            <AppText variant="caption" style={{ textAlign: 'center' }}>Chapters you read are kept automatically. Highlights, notes, giving and everything else still need a connection.</AppText>
          </ScrollView>
        ) : null}
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  card: { backgroundColor: COLORS.white, borderRadius: RADIUS.lg + 2, padding: 14, gap: 12, ...SHADOW, shadowOpacity: 0.05 },
  summaryRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  cloud: { width: 48, height: 48, borderRadius: 16, backgroundColor: COLORS.primaryMist, alignItems: 'center', justifyContent: 'center' },
  h: { fontFamily: FONTS.bold, fontSize: 16.5, color: COLORS.black },
  track: { height: 10, borderRadius: 5, backgroundColor: COLORS.primaryMist, overflow: 'hidden' },
  fill: { height: 10, borderRadius: 5, backgroundColor: COLORS.primary },
  row2: { flexDirection: 'row', gap: 10, flexWrap: 'wrap' },
  book: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 4, borderTopWidth: 1, borderTopColor: COLORS.gray100 },
  bookName: { fontFamily: FONTS.semibold, fontSize: 15, color: COLORS.black },
  err: { fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.danger },
  done: { fontFamily: FONTS.medium, fontSize: 13.5, color: COLORS.success },
});
