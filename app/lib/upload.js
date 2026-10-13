// Picking photos from the phone's library and putting them into a multipart form for the API.
import { Platform } from 'react-native';
import * as ImagePicker from 'expo-image-picker';

/** Opens the photo library. Resolves the chosen assets (empty when the person cancels). */
export async function pickPhotos({ multiple = false, limit = 1, aspect } = {}) {
  const result = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: ['images'],
    allowsMultipleSelection: multiple,
    selectionLimit: multiple ? limit : 1,
    allowsEditing: !multiple && !!aspect,
    aspect,
    quality: 0.7,
    exif: false,
  });
  return result.canceled ? [] : (result.assets ?? []).slice(0, limit);
}

/** Adds a picked photo to a FormData under `field` (use `images[]` for lists). */
export async function appendPhoto(form, field, asset, name = 'photo.jpg') {
  if (Platform.OS === 'web') form.append(field, await (await fetch(asset.uri)).blob(), name);
  else form.append(field, { uri: asset.uri, name, type: asset.mimeType || 'image/jpeg' });
}

/** Axios options for sending a FormData (the browser/phone sets the boundary itself). */
export const multipart = { headers: { 'Content-Type': 'multipart/form-data' }, transformRequest: (d) => d };
