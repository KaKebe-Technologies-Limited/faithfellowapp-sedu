// Giving campaigns of your churches (GET /api/v1/campaigns), each opening its own in-app page with Give.
import { CampaignListCard } from '../components/ListCards';
import PagedScreen from '../components/PagedScreen';

export default function CampaignsScreen() {
  return (
    <PagedScreen
      title="Campaigns"
      subtitle="Projects you can give towards"
      path="/campaigns"
      renderItem={(campaign) => <CampaignListCard campaign={campaign} />}
      emptyIcon="flag-outline"
      emptyTitle="No campaigns right now"
      emptyMessage="When a church starts a giving campaign, it will appear here."
    />
  );
}
