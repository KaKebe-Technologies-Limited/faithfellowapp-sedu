// Reading journeys of the Rose look: short, themed walks through the Bible, a passage and one thought for each day (Women of Faith, Psalms for every mood,
// the fruit of the Spirit, being loved and known). Each day is read in the member's own Bible version; the days you finish are kept on the phone (private,
// nothing is sent anywhere), so the path of blooms shows where you are.
import { useSyncExternalStore } from 'react';
import { readJson, writeJson } from './localFiles';

export const JOURNEYS = [
  {
    id: 'women-of-faith',
    title: 'Women of Faith',
    tagline: 'Fourteen women and moments that shaped them',
    icon: 'flower',
    tint: ['#FFD6E8', '#F59CC0'],
    days: [
      { title: 'Ruth: loyal love', ref: 'Ruth 1:1-22', thought: 'Who stayed beside you when it was hard? Whose side could you stay beside this week?' },
      { title: 'Ruth: gathering in grace', ref: 'Ruth 2:1-23', thought: 'Where has God put something good in your path that you almost walked past?' },
      { title: 'Ruth: redeemed', ref: 'Ruth 4:1-17', thought: 'Ruth was a stranger and became part of the family. What does it mean to you that you belong?' },
      { title: 'Hannah: the prayer without words', ref: '1 Samuel 1:1-20', thought: 'What have you only been able to pray with tears? God hears the prayers that have no words.' },
      { title: 'Hannah: a song of thanks', ref: '1 Samuel 2:1-10', thought: 'Write one sentence of thanks for something God did that you once asked for.' },
      { title: 'Deborah: courage to lead', ref: 'Judges 4:4-16', thought: 'Where is God asking you to be brave, even if it is small?' },
      { title: 'Esther: for such a time as this', ref: 'Esther 4:1-17', thought: 'What place or moment has God put you in? Who might it help?' },
      { title: 'Esther: brave and kind', ref: 'Esther 5:1-8', thought: 'Esther prepared before she spoke. How can you pray before a hard conversation?' },
      { title: 'Mary: a yes to God', ref: 'Luke 1:26-38', thought: 'Mary did not understand everything and still said yes. What is your yes today?' },
      { title: "Mary's song", ref: 'Luke 1:39-56', thought: 'Mary sang before she saw the answer. What can you thank God for ahead of time?' },
      { title: 'Martha and Mary: the better part', ref: 'Luke 10:38-42', thought: 'What is pulling you in many directions? What would sitting at His feet look like today?' },
      { title: 'The woman at the well: known and loved', ref: 'John 4:1-30', thought: 'Jesus knew everything about her and stayed. What do you want Him to know about you?' },
      { title: 'Lydia: an open heart', ref: 'Acts 16:11-15', thought: 'Lydia listened, and then opened her home. Who could you welcome this week?' },
      { title: 'Strength and dignity', ref: 'Proverbs 31:10-31', thought: 'Which one line here describes you on your best days? Thank God for it.' },
    ],
  },
  {
    id: 'psalms-every-mood',
    title: 'Psalms for every mood',
    tagline: 'A psalm for peace, fear, hard days and more',
    icon: 'musical-notes',
    tint: ['#E4D4FB', '#B48CE8'],
    days: [
      { title: 'When you need peace', ref: 'Psalm 23', thought: 'Read it slowly, once, as if it were written about today.' },
      { title: 'When you are afraid', ref: 'Psalm 27', thought: 'Name the thing you fear. Then read the first verse again.' },
      { title: 'On a hard day', ref: 'Psalm 34:1-19', thought: 'God is close to the broken-hearted. Say what is breaking, out loud.' },
      { title: 'When you need to be still', ref: 'Psalm 46', thought: 'Sit quietly for one minute after reading verse 10.' },
      { title: 'When you need safety', ref: 'Psalm 91', thought: 'Which phrase makes you feel most held? Keep it with you today.' },
      { title: 'When you need help', ref: 'Psalm 121', thought: 'Who is your help? Say it back to God in your own words.' },
      { title: 'Fearfully and wonderfully made', ref: 'Psalm 139:1-18', thought: 'You were made on purpose. Thank God for one thing about the way He made you.' },
    ],
  },
  {
    id: 'fruit-of-the-spirit',
    title: 'The fruit of the Spirit',
    tagline: 'Ten days of love, joy, peace and more',
    icon: 'leaf',
    tint: ['#D5F3E3', '#7FD3A8'],
    days: [
      { title: 'Living by the Spirit', ref: 'Galatians 5:16-26', thought: 'Fruit grows slowly. Which one do you most want to see grow in you?' },
      { title: 'Love', ref: '1 John 4:7-12', thought: 'Who needs your love today in a way that costs you something?' },
      { title: 'Joy', ref: 'Psalm 16:7-11', thought: 'Joy is deeper than a good mood. Where do you see God near you right now?' },
      { title: 'Peace', ref: 'Philippians 4:4-9', thought: 'Turn one worry into a prayer. Say it, then leave it with Him.' },
      { title: 'Patience', ref: 'James 5:7-11', thought: 'What are you waiting for? How could you wait well?' },
      { title: 'Kindness', ref: 'Ephesians 4:29-32', thought: 'Say one kind thing to someone today that they will remember.' },
      { title: 'Goodness', ref: 'Luke 10:25-37', thought: 'Who is "your neighbour" this week?' },
      { title: 'Faithfulness', ref: 'Lamentations 3:19-26', thought: 'Remember one time God was faithful to you. Write it down.' },
      { title: 'Gentleness', ref: 'Matthew 11:25-30', thought: 'Jesus is gentle with you. How can you be gentle with yourself and with others?' },
      { title: 'Self-control', ref: '2 Peter 1:3-8', thought: 'Choose one small thing to say no to today, and ask God to help.' },
    ],
  },
  {
    id: 'loved-and-known',
    title: 'Loved and known',
    tagline: 'Seven days of who God says you are',
    icon: 'heart',
    tint: ['#FFE0C7', '#F6A97A'],
    days: [
      { title: 'You are called by name', ref: 'Isaiah 43:1-7', thought: 'Read your own name where it says "Jacob" and "Israel".' },
      { title: 'He sings over you', ref: 'Zephaniah 3:14-20', thought: 'Imagine God singing over you. How does that change how you see today?' },
      { title: 'A future and a hope', ref: 'Jeremiah 29:10-14', thought: 'What are you hoping for? Tell Him.' },
      { title: 'Nothing can separate you', ref: 'Romans 8:31-39', thought: 'Which of the things in verses 38 and 39 feels biggest to you? It is not bigger than His love.' },
      { title: 'God\'s handiwork', ref: 'Ephesians 2:1-10', thought: 'You are His workmanship, made for good things. What might one be?' },
      { title: 'Clothed with love', ref: 'Colossians 3:12-17', thought: 'Choose one thing from the list to "put on" before you leave the house.' },
      { title: 'Wonderfully made', ref: 'Psalm 139:13-18', thought: 'Finish this sentence: "Thank You, God, for making me ..."' },
    ],
  },
];

export const journeyById = (id) => JOURNEYS.find((j) => j.id === id);

const FILE = 'journeys.json';

// { [journeyId]: { done: [dayNumber, ...], at: ISO date of the last day read } }
let state = {};
let loaded = false;
const listeners = new Set();
const set = (next) => {
  state = next;
  listeners.forEach((l) => l());
  writeJson(FILE, state).catch(() => {});
};

function load() {
  if (loaded) return;
  loaded = true;
  readJson(FILE).then((saved) => {
    if (saved && typeof saved === 'object') {
      state = saved;
      listeners.forEach((l) => l());
    }
  });
}

/** What the member has finished, per journey. */
export function useJourneyProgress() {
  load();

  return useSyncExternalStore((l) => (listeners.add(l), () => listeners.delete(l)), () => state);
}

/** The days (1-based) finished in a journey. */
export const doneDays = (progress, id) => progress?.[id]?.done ?? [];

/** Marks a day read (or takes the mark away again). */
export function setDayDone(id, day, done = true) {
  const mine = new Set(doneDays(state, id));
  if (done) mine.add(day);
  else mine.delete(day);
  set({ ...state, [id]: { done: [...mine].sort((a, b) => a - b), at: done ? new Date().toISOString() : state[id]?.at ?? null } });
}

/** Starts a journey over. */
export function resetJourney(id) {
  const { [id]: _gone, ...rest } = state;
  set(rest);
}

/** The first day not finished yet (1-based), or null when the whole journey is done. */
export function nextDay(progress, journey) {
  const done = new Set(doneDays(progress, journey.id));
  const day = journey.days.findIndex((_, i) => !done.has(i + 1));

  return day === -1 ? null : day + 1;
}
