// Which platform features this person sees: podcasts, the hymn book and the gospel radio. The super admin switches them on or off
// church by church (GET /api/v1/me answers `features`). Everything is on until the server says otherwise, so a new sign-up, an old
// cached profile and a slow connection all show every feature.
import { useAuth } from './auth';

export function useFeatures() {
  const { user } = useAuth();
  const features = user?.features ?? {};

  return { podcasts: features.podcasts !== false, hymns: features.hymns !== false, radio: features.radio !== false };
}
