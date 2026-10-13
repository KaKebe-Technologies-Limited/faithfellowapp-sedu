// A tiny JSON file layer for things kept on the phone (the offline Bible, your notes, changes waiting to sync).
// Phones: files in the app's document folder via expo-file-system. The web preview: localStorage.
// Every call is safe: a missing file reads as null and a full disk reports false, nothing is ever thrown.
import { Platform } from 'react-native';

const ROOT = 'offline';
const native = Platform.OS !== 'web';

let fsModule = null;
const fs = () => (fsModule ??= require('expo-file-system'));

function fileFor(name) {
  const { File, Paths } = fs();
  return new File(Paths.document, ROOT, ...name.split('/'));
}

export async function readJson(name) {
  try {
    if (!native) {
      const raw = globalThis.localStorage?.getItem(`${ROOT}/${name}`);
      return raw ? JSON.parse(raw) : null;
    }
    const file = fileFor(name);
    return file.exists ? JSON.parse(await file.text()) : null;
  } catch {
    return null;
  }
}

/** Resolves true when written. */
export async function writeJson(name, value) {
  const raw = JSON.stringify(value);
  try {
    if (!native) {
      globalThis.localStorage?.setItem(`${ROOT}/${name}`, raw);
      return true;
    }
    const file = fileFor(name);
    file.parentDirectory.create({ intermediates: true, idempotent: true });
    if (!file.exists) file.create({ intermediates: true });
    file.write(raw);
    return true;
  } catch {
    return false;
  }
}

export function removeFile(name) {
  try {
    if (!native) return globalThis.localStorage?.removeItem(`${ROOT}/${name}`);
    const file = fileFor(name);
    if (file.exists) file.delete();
  } catch {
    // Already gone.
  }
}

/** Removes a folder and everything in it. */
export function removeDir(name) {
  try {
    if (!native) {
      const prefix = `${ROOT}/${name}/`;
      Object.keys(globalThis.localStorage ?? {}).filter((k) => k.startsWith(prefix)).forEach((k) => globalThis.localStorage.removeItem(k));
      return;
    }
    const { Directory, Paths } = fs();
    const dir = new Directory(Paths.document, ROOT, name);
    if (dir.exists) dir.delete();
  } catch {
    // Already gone.
  }
}

// Work on the same file waits its turn, so two writers (a download and a page you are reading) cannot lose each other's data.
const chains = new Map();
export function serial(key, task) {
  const next = (chains.get(key) ?? Promise.resolve()).then(task, task);
  chains.set(key, next.catch(() => {}));
  return next;
}
