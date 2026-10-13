// Shared lists for the people screens: what members can say they care about, and countries to filter by.
export const INTERESTS = ['Worship', 'Prayer', 'Bible study', 'Youth', 'Missions', 'Marriage', 'Family', 'Music', 'Evangelism', 'Women of faith', 'Men of faith', 'Business & faith', 'Students'];

export const COUNTRIES = [['UG', 'Uganda'], ['KE', 'Kenya'], ['TZ', 'Tanzania'], ['RW', 'Rwanda'], ['SS', 'South Sudan'], ['NG', 'Nigeria'], ['GB', 'UK'], ['US', 'USA']];

export const countryName = (code) => COUNTRIES.find(([c]) => c === code)?.[1] ?? code;
