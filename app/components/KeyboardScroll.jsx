// A ScrollView for forms: when the keyboard opens, the box being typed in is scrolled up above it, so it is never hidden.
//
// KeyboardAvoidingView makes room for the keyboard (see `behavior="padding"` in the screens); this scrolls the focused box into the
// room that is left. It works the same whether or not the phone also shrinks the window for the keyboard (Samsung and other
// Android phones with the full-screen display do not), because it measures where the box and the keyboard really are.
import { forwardRef, useCallback, useEffect, useImperativeHandle, useRef } from 'react';
import { Keyboard, ScrollView, TextInput } from 'react-native';

const MARGIN = 24; // clear space kept between the box and the keyboard

const KeyboardScroll = forwardRef(function KeyboardScroll({ onScroll, ...props }, forwarded) {
  const scroll = useRef(null);
  const offset = useRef(0);
  useImperativeHandle(forwarded, () => scroll.current, []);

  const reveal = useCallback((keyboardTop) => {
    const input = TextInput.State?.currentlyFocusedInput?.();
    if (!input?.measureInWindow || !scroll.current) return;
    input.measureInWindow((x, y, width, height) => {
      const hidden = y + height + MARGIN - keyboardTop;
      if (hidden > 0) scroll.current?.scrollTo({ y: offset.current + hidden, animated: true });
    });
  }, []);

  useEffect(() => {
    const shown = Keyboard.addListener('keyboardDidShow', (event) => {
      const top = event.endCoordinates?.screenY;
      if (!top) return;
      // The screen is still moving up for the keyboard: look again once it has settled.
      setTimeout(() => reveal(top), 60);
      setTimeout(() => reveal(top), 350);
    });

    return () => shown.remove();
  }, [reveal]);

  return (
    <ScrollView
      {...props}
      ref={scroll}
      scrollEventThrottle={16}
      onScroll={(event) => {
        offset.current = event.nativeEvent.contentOffset.y;
        onScroll?.(event);
      }}
    />
  );
});

export default KeyboardScroll;
