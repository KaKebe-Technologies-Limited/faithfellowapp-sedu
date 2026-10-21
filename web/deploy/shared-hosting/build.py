"""Builds everything you upload to shared hosting (Hostinger and similar) for https://faithfellows.com

    python deploy/shared-hosting/build.py            # both files below
    python deploy/shared-hosting/build.py sql        # only the database file
    python deploy/shared-hosting/build.py zip        # only the site
    python deploy/shared-hosting/build.py stage DIR  # the finished site as a plain folder (for the git "production" branch)

Run it from the web folder. The results are written to deploy/shared-hosting/out (never committed):

    faithfellows-database.sql                 the FaithFellows database: tables, roles, plans, the first super admin, the blog,
                                              the public-domain "Classic Hymns" AND the sample data (five churches, members,
                                              sermons, events, giving, prayer, quizzes) so the site is not empty. The sample
                                              accounts sign in with DEMO_PASSWORD from .env.production, not the public "password".
    hymnal-mujje-tutendereze-omukama.sql      the Luganda Catholic hymn book, loaded SHOWING in the hymn list (hide it any time
                                              in Super admin, Hymn book). Optional, import it after the first file.
    faithfellows-site.zip                     the website: code, built styles, the PHP packages (no development tools), and
                                              a root .htaccess that serves the public folder.

The database file is made on a throwaway database on this computer (ff_export), checked by loading it into a second one
(ff_verify), and both are dropped at the end. Settings come from web/.env.production (see docs/DEPLOYMENT_SHARED_HOSTING.md).
"""
import base64
import datetime
import json
import os
import pathlib
import shutil
import subprocess
import sys
import zipfile

WEB = pathlib.Path(__file__).resolve().parents[2]
OUT = WEB / 'deploy' / 'shared-hosting' / 'out'
HOME = pathlib.Path.home()

PHP = os.environ.get('PHP') or shutil.which('php') or 'C:/xampp/php/php.exe'
MYSQL_BIN = pathlib.Path(os.environ.get('MYSQL_BIN') or 'C:/xampp/mysql/bin')
COMPOSER = os.environ.get('COMPOSER_PHAR') or str(HOME / 'tools' / 'composer.phar')
NODE_DIR = pathlib.Path(os.environ.get('NODE_DIR') or HOME / 'tools' / 'node')
LUGANDA = WEB / 'storage' / 'app' / 'private' / 'hymnals' / 'mujje-tutendereze-omukama.json'


def say(text):
    print(f'\n== {text}', flush=True)


def run(cmd, env=None, cwd=None, capture=False, check=True):
    result = subprocess.run([str(c) for c in cmd], env=env, cwd=cwd, text=True, encoding='utf-8', errors='replace',
                            capture_output=capture)
    if check and result.returncode != 0:
        if capture:
            print(result.stdout, result.stderr)
        sys.exit(f'FAILED ({result.returncode}): {" ".join(str(c) for c in cmd)[:200]}')
    return result


def read_env(path):
    values = {}
    for line in pathlib.Path(path).read_text(encoding='utf-8').splitlines():
        line = line.strip()
        if line and not line.startswith('#') and '=' in line:
            key, value = line.split('=', 1)
            values[key.strip()] = value.strip().strip('"')
    return values


def mysql(sql, db=None):
    cmd = [MYSQL_BIN / 'mysql.exe', '-u', 'root', '-N', '-B', '--default-character-set=utf8mb4']
    if db:
        cmd.append(db)
    cmd += ['-e', sql]
    return run(cmd, capture=True).stdout.strip()


def artisan(*args, db, extra_env=None, check=True):
    env = {**os.environ, **ARTISAN_ENV, 'DB_DATABASE': db, **(extra_env or {})}
    return run([PHP, 'artisan', *args], env=env, cwd=WEB, capture=True, check=check)


def dump(db, target, *tables, extra=()):
    cmd = [MYSQL_BIN / 'mysqldump.exe', '-u', 'root', '--default-character-set=utf8mb4', '--single-transaction', '--skip-lock-tables',
           '--skip-add-locks', '--no-tablespaces', '--skip-comments', '--skip-dump-date', '--net-buffer-length=60000',
           f'--result-file={target}', *extra, db, *tables]
    run(cmd, capture=True)


prod = {}
ARTISAN_ENV = {}
DECRYPT_CHECK = (r"foreach ([\App\Models\GivingChannel::class, \App\Models\CounselingAppointment::class, \App\Models\HomeVisit::class, "
                 r"\App\Models\LiveStream::class, \App\Models\PastoralNote::class, \App\Models\PaymentMethod::class, \App\Models\PrayerRequest::class, "
                 r"\App\Models\PlatformSetting::class, \App\Models\ReadingCircleEntry::class, \App\Models\WelfareRecord::class] as $model) { $model::withoutGlobalScopes()->get()->each(function ($m) { foreach ($m->getCasts() as $attr => $cast) { if (str_starts_with((string) $cast, 'encrypted')) { $m->getAttribute($attr); } } }); } "
                 r"echo 'decrypt-ok';")


def load_settings():
    global prod, ARTISAN_ENV
    env_file = WEB / '.env.production'
    if not env_file.exists():
        sys.exit('web/.env.production is missing. Create it first (see docs/DEPLOYMENT_SHARED_HOSTING.md).')
    prod = read_env(env_file)
    if not prod.get('SEED_SUPER_ADMIN_PASSWORD') or not prod.get('DEMO_PASSWORD') or prod['DEMO_PASSWORD'] == 'password':
        sys.exit('Set SEED_SUPER_ADMIN_PASSWORD and a strong DEMO_PASSWORD in web/.env.production first.')
    cached = WEB / 'bootstrap' / 'cache' / 'config.php'
    if cached.exists():
        sys.exit('bootstrap/cache/config.php exists and would override the settings used here. Run: php artisan config:clear')
    if not prod.get('APP_KEY', '').startswith('base64:'):
        sys.exit('web/.env.production needs an APP_KEY (base64:...).')
    ARTISAN_ENV = {
        # The sample data is seeded with the SERVER's key: some columns are encrypted with it (giving channels, pastoral notes,
        # prayer replies, stream keys) and would not open on the server if they had been written with the key on this computer.
        'APP_KEY': prod['APP_KEY'],
        'APP_ENV': 'production', 'APP_DEBUG': 'false', 'APP_URL': prod.get('APP_URL', 'https://faithfellows.com'),
        'DB_CONNECTION': 'mysql', 'DB_HOST': '127.0.0.1', 'DB_PORT': '3306', 'DB_USERNAME': 'root', 'DB_PASSWORD': '',
        'SEED_SUPER_ADMIN_EMAIL': prod.get('SEED_SUPER_ADMIN_EMAIL', 'admin@faithfellows.com'),
        'SEED_SUPER_ADMIN_PASSWORD': prod['SEED_SUPER_ADMIN_PASSWORD'],
        'CACHE_STORE': 'array', 'SESSION_DRIVER': 'array', 'QUEUE_CONNECTION': 'sync', 'MAIL_MAILER': 'array',
    }


def build_sql():
    OUT.mkdir(parents=True, exist_ok=True)
    main = OUT / 'faithfellows-database.sql'
    luganda = OUT / 'hymnal-mujje-tutendereze-omukama.sql'

    say('Creating a clean database (ff_export) and filling it the way production needs it')
    mysql('DROP DATABASE IF EXISTS ff_export; CREATE DATABASE ff_export CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
    artisan('migrate', '--force', db='ff_export')
    artisan('db:seed', '--force', db='ff_export')
    say('Adding the sample data (five churches, members, sermons, events, giving, prayer, quizzes)')
    artisan('db:seed', '--class=DemoSeeder', '--force', db='ff_export', extra_env={'DEMO_SKIP_MEDIA': '1'})

    say('Giving every sample account the DEMO_PASSWORD instead of the public "password"')
    admin = ARTISAN_ENV['SEED_SUPER_ADMIN_EMAIL']
    hashed = run([PHP, '-r', "echo password_hash(getenv('DEMO_PW'), PASSWORD_BCRYPT, ['cost' => 12]);"],
                 env={**os.environ, 'DEMO_PW': prod['DEMO_PASSWORD']}, capture=True).stdout.strip()
    if not hashed.startswith('$2y$'):
        sys.exit('Could not hash the demo password.')
    mysql(f"UPDATE users SET password='{hashed}' WHERE email <> '{admin}'", 'ff_export')
    leftover = mysql("SELECT COUNT(*) FROM users WHERE email <> '" + admin + "' AND password NOT LIKE '$2y$12$%'", 'ff_export')
    print('users:', mysql('SELECT COUNT(*) FROM users', 'ff_export'), '| churches:', mysql('SELECT COUNT(*) FROM churches', 'ff_export'),
          '| tables:', mysql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='ff_export'"), '| not re-hashed:', leftover)
    if leftover != '0':
        sys.exit('Some sample accounts kept another kind of password hash.')

    say('Writing faithfellows-database.sql')
    dump('ff_export', main)
    header = ('-- FaithFellows database for https://faithfellows.com (with sample data).\n'
              '-- Import it into an EMPTY database. It drops and recreates every table, so NEVER import it again on a live site.\n')
    main.write_text(header + main.read_text(encoding='utf-8'), encoding='utf-8', newline='\n')

    has_luganda = LUGANDA.exists()
    if has_luganda:
        say('Adding the Luganda Catholic hymn book as a published hymnal and writing its own file')
        artisan('hymns:import', LUGANDA, db='ff_export')
        slug = "'mujje-tutendereze-omukama'"
        dump('ff_export', luganda, 'hymnals', extra=['--no-create-info', '--skip-extended-insert', '--complete-insert', f'--where=slug={slug}'])
        part = OUT / 'part.sql'
        dump('ff_export', part, 'hymns', extra=['--no-create-info', '--skip-extended-insert', '--complete-insert',
                                                 f'--where=hymnal_id=(SELECT id FROM hymnals WHERE slug={slug})'])
        with open(luganda, 'a', encoding='utf-8', newline='\n') as f:
            f.write('\n' + part.read_text(encoding='utf-8'))
        part.unlink()
        header = ('-- The Luganda Catholic hymn book "Mujje Tutendereze Omukama", loaded SHOWING in the hymn list (is_published = 1).\n'
                  '-- Import it AFTER faithfellows-database.sql and before adding other hymnals. Hide it any time in Super admin, Hymn book.\n'
                  '-- The book is under copyright: keep the publisher\'s permission (Inter-Diocesan Committee, Archdiocese of Kampala) on file.\n')
        luganda.write_text(header + luganda.read_text(encoding='utf-8'), encoding='utf-8', newline='\n')

    say('Checking the files by loading them into a second database (ff_verify)')
    mysql('DROP DATABASE IF EXISTS ff_verify; CREATE DATABASE ff_verify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
    for sql_file in [main] + ([luganda] if has_luganda else []):
        with open(sql_file, 'rb') as f:
            result = subprocess.run([str(MYSQL_BIN / 'mysql.exe'), '-u', 'root', '--default-character-set=utf8mb4', 'ff_verify'], stdin=f,
                                    capture_output=True)
        if result.returncode != 0:
            sys.exit(f'{sql_file.name} did not load: {result.stderr.decode("utf-8", "replace")[:500]}')
    status = artisan('migrate:status', db='ff_verify').stdout
    if 'Pending' in status:
        sys.exit('Some migrations are still pending after loading the file.')
    # Every encrypted column must open with the server's key.
    opened = artisan('tinker', '--execute=' + DECRYPT_CHECK, db='ff_verify').stdout
    if 'decrypt-ok' not in opened:
        sys.exit('An encrypted column could not be read with the production APP_KEY:\n' + opened[-600:])
    # The same check with a wrong key must fail, otherwise it proves nothing.
    wrong = 'base64:' + base64.b64encode(os.urandom(32)).decode()
    if 'decrypt-ok' in artisan('tinker', '--execute=' + DECRYPT_CHECK, db='ff_verify', extra_env={'APP_KEY': wrong}, check=False).stdout:
        sys.exit('The encrypted-column check cannot tell a wrong key from the right one.')
    checks = {
        'encrypted columns open with the production key': 'yes',
        'users': mysql('SELECT COUNT(*) FROM users', 'ff_verify'),
        'roles': mysql('SELECT COUNT(*) FROM roles', 'ff_verify'),
        'plans': mysql('SELECT COUNT(*) FROM plans', 'ff_verify'),
        'churches': mysql('SELECT COUNT(*) FROM churches', 'ff_verify'),
        'hymnals (published/all)': mysql('SELECT CONCAT(SUM(is_published), "/", COUNT(*)) FROM hymnals', 'ff_verify'),
        'hymns': mysql('SELECT COUNT(*) FROM hymns', 'ff_verify'),
        'migrations ran': mysql('SELECT COUNT(*) FROM migrations', 'ff_verify'),
    }
    print(json.dumps(checks, indent=1))
    if int(checks['users']) < 40 or int(checks['churches']) < 5:
        sys.exit('The sample data is missing from the database file.')
    # Nobody may be able to sign in with the public demo password, and the hymn book must not be in the main file.
    stored = mysql("SELECT password FROM users WHERE email='member@faithfellows.test'", 'ff_verify')
    ok = run([PHP, '-r', "echo password_verify('password', getenv('H')) ? 'OPEN' : 'closed';"], env={**os.environ, 'H': stored}, capture=True).stdout
    if ok != 'closed':
        sys.exit('A sample account still accepts the public password.')
    text = main.read_text(encoding='utf-8')
    for needle in ('Mujje', 'ChangeMe'):
        if needle in text:
            sys.exit(f'The database file contains "{needle}"; it should not.')

    mysql('DROP DATABASE IF EXISTS ff_export; DROP DATABASE IF EXISTS ff_verify')
    for f in [main] + ([luganda] if has_luganda else []):
        print(f'{f.name}: {f.stat().st_size / 1024 / 1024:.1f} MB')


SKIP_DIRS = {'.git', 'node_modules', 'vendor', 'tests', '.claude', '.phpunit.cache', 'deploy', 'scripts', 'storage'}
SKIP_FILES = {'setup.php', 'run.bat', 'serve.bat', '.phpunit.result.cache', 'phpunit.xml'}
HTACCESS = """# Sends every request to the public folder, so nothing else in this folder can be reached from the web.
Options -Indexes
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP_HOST} ^www\\.(.+)$ [NC]
    RewriteRule ^ https://%1%{REQUEST_URI} [R=301,L]
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L,QSA]
</IfModule>
<FilesMatch "^\\.(?!htaccess)">
    Require all denied
</FilesMatch>
"""


# What the production branch tracks: the finished site, but never the settings, uploads, logs or caches of the server.
GIT_IGNORE = """/.env
/.env.*
!/.env.example
/public/hot
/public/storage
/bootstrap/cache/*.php
/storage/app/public/*
/storage/app/private/*
/storage/logs/*
/storage/framework/cache/data/*
/storage/framework/sessions/*
/storage/framework/views/*
!.gitkeep
"""


def npm():
    for name in ('npm.cmd', 'npm'):
        if (NODE_DIR / name).exists():
            return NODE_DIR / name
    return shutil.which('npm') or 'npm'


def composer():
    return [PHP, COMPOSER] if pathlib.Path(COMPOSER).exists() else [shutil.which('composer') or 'composer']


def build_stage(stage, for_git=False):
    """The finished site in `stage`: code, built styles, production PHP packages, root .htaccess.

    for_git=True also leaves out the package caches and writes a .gitignore for the production branch (which, unlike the
    development .gitignore, tracks vendor/ and public/build).
    """
    stage = pathlib.Path(stage)
    if stage.exists():
        shutil.rmtree(stage)

    say('Building the styles and scripts (npm run build)')
    env = {**os.environ, 'PATH': str(NODE_DIR) + os.pathsep + os.environ['PATH']}
    run([npm(), 'run', 'build'], cwd=WEB, env=env, capture=True)

    say('Copying the code')
    for src in WEB.rglob('*'):
        rel = src.relative_to(WEB)
        parts = rel.parts
        if parts[0] in SKIP_DIRS or src.name in SKIP_FILES or (src.name.startswith('.env') and src.name != '.env.example'):
            continue
        if parts[0] == 'public' and (src.name == 'hot' or parts[1:2] == ('storage',)):
            continue
        if parts[:2] in (('docs', 'documentation'), ('docs', 'play-store')):  # printed documentation and store pictures stay in the repository
            continue
        if parts[:2] == ('bootstrap', 'cache') and src.suffix == '.php':
            continue
        if src.suffix == '.log':
            continue
        dest = stage / rel
        if src.is_dir():
            dest.mkdir(parents=True, exist_ok=True)
        else:
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(src, dest)
    for d in ('storage/app/public', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions',
              'storage/framework/views', 'storage/logs', 'bootstrap/cache'):
        (stage / d).mkdir(parents=True, exist_ok=True)
        (stage / d / '.gitkeep').write_text('', encoding='utf-8')
    (stage / '.htaccess').write_text(HTACCESS, encoding='utf-8', newline='\n')

    say('Installing the PHP packages for production (no development tools)')
    env = {**os.environ, 'COMPOSER_PROCESS_TIMEOUT': '0', 'APP_KEY': 'base64:' + 'A' * 43 + '=', 'APP_ENV': 'production'}
    run([*composer(), 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress'], cwd=stage, env=env, capture=True)

    # Shown by https://faithfellows.com/api/ping as "release", so you can see which update is live.
    sha = os.environ.get('GITHUB_SHA') or run(['git', 'rev-parse', 'HEAD'], cwd=WEB, capture=True, check=False).stdout.strip()
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M UTC')
    (stage / 'RELEASE').write_text(f'{sha[:7] or "unknown"} {stamp}\n', encoding='utf-8', newline='\n')

    if for_git:
        for cache in (stage / 'bootstrap' / 'cache').glob('*.php'):
            cache.unlink()
        (stage / '.gitignore').write_text(GIT_IGNORE, encoding='utf-8', newline='\n')
        (stage / '.gitattributes').write_text('* -text\n', encoding='utf-8', newline='\n')
    print('site folder:', stage)


def build_zip():
    OUT.mkdir(parents=True, exist_ok=True)
    stage = OUT / 'stage'
    target = OUT / 'faithfellows-site.zip'
    build_stage(stage)

    say('Zipping')
    if target.exists():
        target.unlink()
    with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as z:
        for src in sorted(stage.rglob('*')):
            z.write(src, src.relative_to(stage).as_posix() + ('/' if src.is_dir() else ''))
    shutil.rmtree(stage)
    print(f'{target.name}: {target.stat().st_size / 1024 / 1024:.1f} MB')


if __name__ == '__main__':
    what = sys.argv[1] if len(sys.argv) > 1 else 'all'
    if what not in ('all', 'sql', 'zip', 'stage'):
        sys.exit(__doc__)
    if what == 'stage':
        # The finished site as a plain folder, for the production git branch (used by .github/workflows/production-branch.yml).
        if len(sys.argv) < 3:
            sys.exit('Usage: python deploy/shared-hosting/build.py stage <folder>')
        build_stage(pathlib.Path(sys.argv[2]).resolve(), for_git=True)
        sys.exit(0)
    if what in ('all', 'sql'):
        load_settings()
        build_sql()
    if what in ('all', 'zip'):
        build_zip()
    print('\nDone. Files are in', OUT)
