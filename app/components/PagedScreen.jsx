// A full list screen: header with back button, pull-to-refresh, "load more" at the bottom, and the shared
// loading / error / empty states. Screens such as Events, Campaigns and Devotionals are a few lines each:
// give it an API path and how to draw one item.
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, View } from 'react-native';
import { COLORS, SPACING } from '../constants/theme';
import { usePaged } from '../lib/usePaged';
import ScreenHeader from './ScreenHeader';
import StateView from './StateView';

export default function PagedScreen({ title, subtitle, path, params, renderItem, keyOf = (item) => String(item.id), header, right, emptyTitle, emptyMessage, emptyIcon, emptyAction }) {
  const list = usePaged(path, params);

  return (
    <View style={styles.screen}>
      <ScreenHeader title={title} subtitle={subtitle} right={right} />
      <StateView loading={list.loading} error={list.error && !list.items.length ? list.error : null} onRetry={list.retry} empty={!list.loading && !list.error && !list.items.length && !header} emptyTitle={emptyTitle} emptyMessage={emptyMessage} emptyIcon={emptyIcon} emptyAction={emptyAction}>
        <FlatList
          data={list.items}
          keyExtractor={keyOf}
          renderItem={({ item }) => renderItem(item)}
          ListHeaderComponent={header}
          ListEmptyComponent={
            header && !list.loading ? (
              <StateView empty emptyTitle={emptyTitle} emptyMessage={emptyMessage} emptyIcon={emptyIcon} emptyAction={emptyAction} />
            ) : null
          }
          contentContainerStyle={styles.content}
          onEndReached={list.loadMore}
          onEndReachedThreshold={0.4}
          initialNumToRender={8}
          refreshControl={<RefreshControl refreshing={list.refreshing} onRefresh={list.reload} tintColor={COLORS.primary} colors={[COLORS.primary]} />}
          ListFooterComponent={list.loadingMore ? <ActivityIndicator color={COLORS.primary} style={{ margin: SPACING.md }} /> : <View style={{ height: 24 }} />}
        />
      </StateView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: COLORS.canvas },
  content: { paddingTop: 6, paddingBottom: 24 },
});
