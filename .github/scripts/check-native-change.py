"""Stops an over-the-air update that an installed APK could not run.

An update carries JavaScript, text and images. It cannot add a native package, a permission or a plugin: those live inside the
APK. If a push changes one of them and the app version (expo.version in app/app.json) is not raised, an old APK would download
code that needs something it does not have. This check fails the push's update with a message instead.

    python3 .github/scripts/check-native-change.py <commit before the push> <commit after the push>

Run it after `npm ci` in app/ (it looks inside node_modules to tell native packages from JavaScript-only ones).
"""
import json
import pathlib
import subprocess
import sys

APP = pathlib.Path('app')
# Parts of app.json that end up inside the APK. `extra`, `experiments`, `web` and the like are read at run time or ignored.
NATIVE_KEYS = ('plugins', 'android', 'ios', 'scheme', 'icon', 'name', 'slug', 'orientation', 'userInterfaceStyle', 'splash',
               'newArchEnabled', 'jsEngine', 'updates', 'runtimeVersion', 'assetBundlePatterns')


def show(commit, path):
    result = subprocess.run(['git', 'show', f'{commit}:{path}'], capture_output=True, text=True)

    return json.loads(result.stdout) if result.returncode == 0 else None


def show_text(commit, path):
    result = subprocess.run(['git', 'show', f'{commit}:{path}'], capture_output=True, text=True)

    return result.stdout if result.returncode == 0 else None


def comparable(expo, key):
    """What an installed app contains for this key. The export-compliance flag in ios.infoPlist is only read when a build is uploaded to App Store
    Connect, so adding or changing it never needs a new build."""
    value = expo.get(key)
    if key == 'ios' and isinstance(value, dict):
        value = dict(value)
        plist = {k: v for k, v in (value.get('infoPlist') or {}).items() if k != 'ITSAppUsesNonExemptEncryption'}
        value.pop('infoPlist', None)
        if plist:
            value['infoPlist'] = plist

    return value


def main(before, after):
    if not before or set(before) == {'0'}:
        print('First push of the branch: nothing to compare.')
        return 0
    old_pkg, new_pkg = show(before, 'app/package.json'), show(after, 'app/package.json')
    old_app, new_app = show(before, 'app/app.json'), show(after, 'app/app.json')
    if not (old_pkg and new_pkg and old_app and new_app):
        print('Could not read the previous version of the app files: skipping the check.')
        return 0

    reasons = []
    old_deps, new_deps = old_pkg.get('dependencies', {}), new_pkg.get('dependencies', {})
    for name, version in new_deps.items():
        if old_deps.get(name) == version:
            continue
        folder = APP / 'node_modules' / name
        if (folder / 'android').is_dir() or (folder / 'ios').is_dir():
            reasons.append(f'the package {name} contains native code and was {"added" if name not in old_deps else "changed"}')
    for name in old_deps:
        if name not in new_deps:
            reasons.append(f'the package {name} was removed')
    if show_text(before, 'app/app.config.js') != show_text(after, 'app/app.config.js'):
        reasons.append('app.config.js changed (it adds config plugins and build settings)')
    if show_text(before, 'app/google-services.json') != show_text(after, 'app/google-services.json'):
        reasons.append('google-services.json changed (the Firebase settings inside the APK)')
    old_expo, new_expo = old_app.get('expo', {}), new_app.get('expo', {})
    for key in NATIVE_KEYS:
        if comparable(old_expo, key) != comparable(new_expo, key):
            reasons.append(f'app.json changed "{key}"')

    if not reasons:
        print('No native changes: the update is safe for installed apps.')
        return 0
    if old_expo.get('version') != new_expo.get('version'):
        print('Native changes, but the app version was raised, so old APKs will not receive this update (build a new APK):')
        print('\n'.join(f'  - {r}' for r in reasons))
        return 0

    print('::error::This push changes what is inside the APK, so an over-the-air update cannot carry it.')
    print('\n'.join(f'  - {r}' for r in reasons))
    print('\nRaise "version" in app/app.json (for example 1.0.0 to 1.1.0), push again, then build a new APK with:')
    print('  npx.cmd eas-cli build -p android --profile preview')
    return 1


if __name__ == '__main__':
    sys.exit(main(sys.argv[1] if len(sys.argv) > 1 else '', sys.argv[2] if len(sys.argv) > 2 else 'HEAD'))
