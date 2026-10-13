// Daily devotionals from the churches you follow (GET /api/v1/devotionals), newest first.
import { DevotionalListCard } from '../components/ListCards';
import PagedScreen from '../components/PagedScreen';

export default function DevotionalsScreen() {
  return (
    <PagedScreen
      title="Devotionals"
      subtitle="A word for every day"
      path="/devotionals"
      renderItem={(devotional) => <DevotionalListCard devotional={devotional} />}
      emptyIcon="book-outline"
      emptyTitle="No devotionals yet"
      emptyMessage="Follow a church that posts devotionals and they will appear here."
    />
  );
}
