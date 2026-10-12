// Bottom tab bar for the signed-in app: Feed, Churches, Sermons, Give, Profile.
// Sends signed-out visitors (or an expired session) back to the login screen.
import { Redirect, Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { COLORS, FONTS } from '../../constants/theme';
import { useAuth } from '../../lib/auth';

const icon = (name, focusedName) =>
  function TabIcon({ color, size, focused }) {
    return <Ionicons name={focused ? focusedName : name} size={size} color={color} />;
  };

export default function TabsLayout() {
  const { ready, token } = useAuth();
  const insets = useSafeAreaInsets();

  if (ready && !token) return <Redirect href="/(auth)/login" />;

  return (
    <Tabs
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
        sceneStyle: { backgroundColor: COLORS.gray100 },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Feed', tabBarIcon: icon('home-outline', 'home') }} />
      <Tabs.Screen name="churches" options={{ title: 'Churches', tabBarIcon: icon('people-outline', 'people') }} />
      <Tabs.Screen name="sermons" options={{ title: 'Sermons', tabBarIcon: icon('mic-outline', 'mic') }} />
      <Tabs.Screen name="give" options={{ title: 'Give', tabBarIcon: icon('heart-outline', 'heart') }} />
      <Tabs.Screen name="profile" options={{ title: 'Profile', tabBarIcon: icon('person-outline', 'person') }} />
    </Tabs>
  );
}
