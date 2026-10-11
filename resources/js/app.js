import './bootstrap';

// Self-hosted Space Grotesk (works offline / on slow networks — no Google Fonts round-trip)
import '@fontsource/space-grotesk/300.css';
import '@fontsource/space-grotesk/400.css';
import '@fontsource/space-grotesk/500.css';
import '@fontsource/space-grotesk/600.css';
import '@fontsource/space-grotesk/700.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

import * as bootstrap from 'bootstrap';
import Alpine from 'alpinejs';
import { registerComponents, boot } from './faithfellows';

window.bootstrap = bootstrap;
window.Alpine = Alpine;

registerComponents(Alpine);
Alpine.start();

boot();
