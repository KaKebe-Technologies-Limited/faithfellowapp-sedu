// Flat, friendly illustrations for the first-run slides, drawn with react-native-svg in the FaithFellows
// colours (original artwork, no image files to download). Four scenes:
//   people  – meet people of faith near you      events – groups, events and tickets
//   word    – the Bible, studies and quizzes     give   – giving and prayer together
import Svg, { Circle, Defs, G, LinearGradient, Path, Rect, Stop } from 'react-native-svg';
import { COLORS } from '../constants/theme';

const P = COLORS.primary;
const MIST = COLORS.primaryMist;
const PINK = '#FFC9E0';
const INK = '#2B1B22';
const SKINS = ['#F2C7A5', '#C98B64', '#8A5A3C', '#5E3B27'];
const HAIR = ['#2B1B22', '#4A2C1D', '#1A1210', '#7A4A2A'];

/** A simple person: head, hair, shoulders. (cx, cy) is the centre of the head; `s` scales the figure. */
function Person({ cx, cy, s = 1, skin = SKINS[0], hair = HAIR[0], shirt = P, long = false }) {
  const r = 17 * s;
  return (
    <G>
      {long ? <Rect x={cx - r - 3 * s} y={cy - r} width={2 * r + 6 * s} height={r * 2.6} rx={r} fill={hair} /> : null}
      <Path d={`M ${cx - 30 * s} ${cy + 62 * s} C ${cx - 30 * s} ${cy + 26 * s}, ${cx - 14 * s} ${cy + 20 * s}, ${cx} ${cy + 20 * s} C ${cx + 14 * s} ${cy + 20 * s}, ${cx + 30 * s} ${cy + 26 * s}, ${cx + 30 * s} ${cy + 62 * s} Z`} fill={shirt} />
      <Rect x={cx - 5 * s} y={cy + 10 * s} width={10 * s} height={14 * s} rx={4 * s} fill={skin} />
      <Circle cx={cx} cy={cy} r={r} fill={skin} />
      <Path d={`M ${cx - r - 1 * s} ${cy - 2 * s} C ${cx - r} ${cy - r - 8 * s}, ${cx + r} ${cy - r - 8 * s}, ${cx + r + 1 * s} ${cy - 2 * s} C ${cx + r - 3 * s} ${cy - 10 * s}, ${cx - r + 3 * s} ${cy - 10 * s}, ${cx - r - 1 * s} ${cy - 2 * s} Z`} fill={hair} />
      <Circle cx={cx - 6 * s} cy={cy + 2 * s} r={1.8 * s} fill={INK} />
      <Circle cx={cx + 6 * s} cy={cy + 2 * s} r={1.8 * s} fill={INK} />
      <Path d={`M ${cx - 5 * s} ${cy + 8 * s} Q ${cx} ${cy + 12 * s} ${cx + 5 * s} ${cy + 8 * s}`} stroke={INK} strokeWidth={1.6 * s} strokeLinecap="round" fill="none" />
    </G>
  );
}

function Cross({ x, y, size = 20, color = P }) {
  const t = size * 0.28;
  return (
    <G>
      <Rect x={x - t / 2} y={y - size / 2} width={t} height={size} rx={t / 3} fill={color} />
      <Rect x={x - size * 0.34} y={y - size * 0.22} width={size * 0.68} height={t} rx={t / 3} fill={color} />
    </G>
  );
}

function Heart({ x, y, size = 18, color = P }) {
  const k = size / 20;
  return <Path transform={`translate(${x - 10 * k} ${y - 9 * k}) scale(${k})`} d="M10 18 C-6 6, 3 -4, 10 4 C17 -4, 26 6, 10 18 Z" fill={color} />;
}

function Sparkle({ x, y, size = 10, color = '#FFB703' }) {
  return <Path d={`M ${x} ${y - size} L ${x + size * 0.3} ${y - size * 0.3} L ${x + size} ${y} L ${x + size * 0.3} ${y + size * 0.3} L ${x} ${y + size} L ${x - size * 0.3} ${y + size * 0.3} L ${x - size} ${y} L ${x - size * 0.3} ${y - size * 0.3} Z`} fill={color} />;
}

function Backdrop() {
  return (
    <>
      <Defs>
        <LinearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
          <Stop offset="0" stopColor="#FFE9F5" />
          <Stop offset="1" stopColor="#FFD2E6" />
        </LinearGradient>
        <LinearGradient id="brand" x1="0" y1="0" x2="1" y2="1">
          <Stop offset="0" stopColor={COLORS.primaryLight} />
          <Stop offset="1" stopColor={COLORS.primaryDark} />
        </LinearGradient>
      </Defs>
      <Circle cx="160" cy="140" r="118" fill="url(#bg)" />
      <Circle cx="48" cy="52" r="10" fill={PINK} />
      <Circle cx="284" cy="40" r="6" fill={PINK} />
      <Circle cx="296" cy="214" r="12" fill={PINK} />
    </>
  );
}

function People() {
  return (
    <>
      <Backdrop />
      {/* a map card with a pin */}
      <Rect x="86" y="70" width="148" height="104" rx="18" fill="#FFFFFF" />
      <Path d="M86 120 C120 100, 150 140, 180 118 S222 100, 234 112 L234 156 a18 18 0 0 1 -18 18 H104 a18 18 0 0 1 -18 -18 Z" fill="#FFF1F7" />
      <Path d="M100 92 L150 120 L200 88" stroke="#FFD2E6" strokeWidth="5" fill="none" strokeLinecap="round" />
      <Path d="M160 78 a20 20 0 0 1 20 20 c0 16 -20 34 -20 34 s-20 -18 -20 -34 a20 20 0 0 1 20 -20 Z" fill="url(#brand)" />
      <Circle cx="160" cy="98" r="7" fill="#FFFFFF" />
      {/* three people around it */}
      <Person cx={64} cy={168} s={0.95} skin={SKINS[2]} hair={HAIR[2]} shirt="#7C4DFF" />
      <Person cx={160} cy={190} s={1.1} skin={SKINS[0]} hair={HAIR[1]} shirt={P} long />
      <Person cx={256} cy={168} s={0.95} skin={SKINS[1]} hair={HAIR[0]} shirt="#00A896" />
      <Heart x={110} y={70} size={14} />
      <Heart x={222} y={60} size={11} color={COLORS.primaryLight} />
      <Cross x={40} y={110} size={14} color={PINK} />
    </>
  );
}

function Events() {
  return (
    <>
      <Backdrop />
      {/* calendar */}
      <Rect x="58" y="62" width="130" height="136" rx="18" fill="#FFFFFF" />
      <Path d="M58 80 a18 18 0 0 1 18 -18 h94 a18 18 0 0 1 18 18 v22 H58 Z" fill="url(#brand)" />
      <Rect x="86" y="50" width="8" height="24" rx="4" fill={INK} />
      <Rect x="152" y="50" width="8" height="24" rx="4" fill={INK} />
      {[0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11].map((i) => (
        <Circle key={i} cx={80 + (i % 4) * 28} cy={122 + Math.floor(i / 4) * 24} r="6" fill={i === 6 ? P : MIST} />
      ))}
      {/* ticket */}
      <G transform="rotate(-8 230 78)">
        <Rect x="188" y="52" width="96" height="52" rx="12" fill={P} />
        <Circle cx="188" cy="78" r="8" fill="#FFE9F5" />
        <Circle cx="284" cy="78" r="8" fill="#FFE9F5" />
        <Rect x="202" y="66" width="42" height="7" rx="3.5" fill="#FFFFFF" />
        <Rect x="202" y="82" width="28" height="6" rx="3" fill="#FFB3D1" />
        <Cross x={264} y={78} size={18} color="#FFFFFF" />
      </G>
      {/* group bubble */}
      <Rect x="170" y="128" width="112" height="70" rx="20" fill="#FFFFFF" />
      <Path d="M196 198 l-4 20 l24 -20 Z" fill="#FFFFFF" />
      <Circle cx="202" cy="160" r="14" fill={SKINS[1]} />
      <Circle cx="226" cy="160" r="14" fill={SKINS[0]} />
      <Circle cx="250" cy="160" r="14" fill={SKINS[2]} />
      <Path d="M193 154 a9 9 0 0 1 18 0 Z" fill={HAIR[0]} />
      <Path d="M217 154 a9 9 0 0 1 18 0 Z" fill={HAIR[1]} />
      <Path d="M241 154 a9 9 0 0 1 18 0 Z" fill={HAIR[2]} />
      <Sparkle x={290} y={116} size={9} />
      <Heart x={48} y={206} size={13} color={COLORS.primaryLight} />
    </>
  );
}

function Word() {
  return (
    <>
      <Backdrop />
      {/* rays of light */}
      {[-50, -25, 0, 25, 50].map((a) => (
        <Path key={a} d="M160 120 L148 36 L172 36 Z" fill="#FFD36B" opacity={0.35} transform={`rotate(${a} 160 120)`} />
      ))}
      <Circle cx="160" cy="110" r="26" fill="#FFE29A" opacity={0.7} />
      {/* open Bible */}
      <Path d="M52 128 C92 112, 130 118, 160 136 C190 118, 228 112, 268 128 L268 206 C228 190, 190 196, 160 214 C130 196, 92 190, 52 206 Z" fill={COLORS.primaryDark} />
      <Path d="M60 126 C96 114, 130 120, 156 138 L156 206 C130 190, 96 186, 60 198 Z" fill="#FFFFFF" />
      <Path d="M164 138 C190 120, 224 114, 260 126 L260 198 C224 186, 190 190, 164 206 Z" fill="#FFF7EC" />
      {[0, 1, 2, 3].map((i) => (
        <G key={i}>
          <Path d={`M72 ${146 + i * 12} C100 ${138 + i * 12}, 124 ${144 + i * 12}, 144 ${156 + i * 12}`} stroke="#E9C7D6" strokeWidth="3" strokeLinecap="round" fill="none" />
          <Path d={`M176 ${156 + i * 12} C196 ${144 + i * 12}, 220 ${138 + i * 12}, 248 ${146 + i * 12}`} stroke="#E9C7D6" strokeWidth="3" strokeLinecap="round" fill="none" />
        </G>
      ))}
      <Cross x={160} y={88} size={26} color="#FFFFFF" />
      {/* quiz bubble and trophy star */}
      <Rect x="226" y="50" width="62" height="44" rx="14" fill="url(#brand)" />
      <Path d="M246 94 l-6 14 l18 -14 Z" fill={COLORS.primaryDark} />
      <Path d="M251 66 q0 -8 9 -8 q9 0 9 7 q0 5 -6 8 q-3 2 -3 5" stroke="#FFFFFF" strokeWidth="4" strokeLinecap="round" fill="none" />
      <Circle cx="260" cy="83" r="2.6" fill="#FFFFFF" />
      <Sparkle x={50} y={80} size={11} />
      <Sparkle x={282} y={150} size={8} color={P} />
    </>
  );
}

function Give() {
  return (
    <>
      <Backdrop />
      {/* big heart with a cross */}
      <Path transform="translate(86 62) scale(7.4)" d="M10 18 C-6 6, 3 -4, 10 4 C17 -4, 26 6, 10 18 Z" fill="url(#brand)" />
      <Cross x={160} y={112} size={44} color="#FFFFFF" />
      {/* coins */}
      {[0, 1, 2].map((i) => (
        <G key={i}>
          <Rect x={226} y={170 - i * 12} width="50" height="14" rx="7" fill="#FFB703" />
          <Rect x={226} y={166 - i * 12} width="50" height="10" rx="5" fill="#FFD36B" />
        </G>
      ))}
      <Circle cx="251" cy="124" r="13" fill="#FFD36B" />
      <Path d="M251 117 v14 M246 121 h8 a3 3 0 0 1 0 6 h-8" stroke="#B7791F" strokeWidth="2.4" strokeLinecap="round" fill="none" />
      {/* candle */}
      <Rect x="58" y="154" width="22" height="46" rx="6" fill="#FFFFFF" />
      <Path d="M69 128 c8 10 8 20 0 24 c-8 -4 -8 -14 0 -24 Z" fill="#FFB703" />
      <Path d="M69 138 c4 5 4 9 0 11 c-4 -2 -4 -6 0 -11 Z" fill="#FFF3C4" />
      {/* two people */}
      <Person cx={116} cy={196} s={0.7} skin={SKINS[1]} hair={HAIR[0]} shirt="#7C4DFF" />
      <Person cx={204} cy={200} s={0.7} skin={SKINS[0]} hair={HAIR[1]} shirt="#00A896" long />
      <Sparkle x={290} y={70} size={10} />
      <Sparkle x={44} y={96} size={8} color={P} />
    </>
  );
}

const SCENES = { people: People, events: Events, word: Word, give: Give };

export default function Illustration({ kind = 'people', width = 300 }) {
  const Scene = SCENES[kind] ?? People;
  return (
    <Svg width={width} height={(width * 250) / 320} viewBox="0 20 320 250">
      <Scene />
    </Svg>
  );
}
