// The purple of the Podcasts area (the Podcast radio card, its chips, play buttons and comments), next to the FaithFellows wine of the rest of the app.
import { IS_DARK, IS_GIRLS } from './theme';

export const PURPLE = IS_DARK
  ? { light: '#A184E6', main: '#8A63DA', dark: '#4527A0', mist: '#2A2140', pale: '#43356B' }
  : IS_GIRLS
    ? { light: '#B48CE8', main: '#9558D6', dark: '#6A3FC8', mist: '#F3EAFE', pale: '#E0CCFA' }
    : {
        light: '#8E6BD6',
        main: '#6A3FC8',
        dark: '#4527A0',
        mist: '#F1EAFD',
        pale: '#D9CAF6',
      };
