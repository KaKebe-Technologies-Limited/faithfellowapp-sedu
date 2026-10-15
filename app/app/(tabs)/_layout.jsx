// Bottom tab bar for the signed-in app: Home, Bible, Community, Give, Me.
// Sends signed-out visitors (or an expired session) back to the login screen.
import { Redirect, Tabs } from 'expo-router';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import AppTabBar from '../../components/AppTabBar';
import { COLORS, FONTS } from '../../constants/theme';
import { useAuth } from '../../lib/auth';

const icon = (name, focusedName) =>
  function TabIcon({ color, size, focused }) {
    return <Ionicons name={focused ? focusedName : name} size={size} color={color} />;
  };

export default function TabsLayout() {
  const { ready, token } = useAuth();
  const insets = useSafeAreaInsets();

  if (ready && !token) return <Redirect href="/(auth)/welcome" />;

  return (
    <Tabs
      tabBar={(props) => <AppTabBar {...props} />}
      screenOptions={{
        headerStyle: { backgroundColor: COLORS.primary },
        headerTintColor: COLORS.white,
        headerTitleStyle: { fontFamily: FONTS.semibold, fontSize: 18 },
        headerShadowVisible: false,
        tabBarActiveTintColor: COLORS.primary,
        tabBarInactiveTintColor: COLORS.gray500,
        tabBarLabelStyle: { fontFamily: FONTS.medium, fontSize: 11, lineHeight: 14 },
        // Room for icon + label, plus the home-indicator area on notched phones.
        tabBarStyle: { backgroundColor: COLORS.white, borderTopColor: COLORS.gray100, height: 72 + insets.bottom, paddingTop: 8, paddingBottom: 10 + insets.bottom },
        sceneStyle: { backgroundColor: COLORS.canvas },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Home', headerShown: false, tabBarIcon: icon('home-outline', 'home') }} />
      <Tabs.Screen name="bible" options={{ title: 'Bible', headerShown: false, tabBarIcon: icon('book-outline', 'book') }} />
      <Tabs.Screen name="community" options={{ title: 'Community', headerShown: false, tabBarIcon: icon('people-outline', 'people') }} />
      <Tabs.Screen name="give" options={{ title: 'Give', headerShown: false, tabBarIcon: icon('heart-outline', 'heart') }} />
      <Tabs.Screen name="profile" options={{ title: 'Me', headerShown: false, tabBarIcon: icon('person-outline', 'person') }} />
    </Tabs>
  );
}
