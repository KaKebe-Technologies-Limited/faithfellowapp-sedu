"""Builds everything you upload to shared hosting (Hostinger and similar) for https://ff.bodaerp.site

    python deploy/shared-hosting/build.py            # both files below
    python deploy/shared-hosting/build.py sql        # only the database file
    python deploy/shared-hosting/build.py zip        # only the site

Run it from the web folder. The results are written to deploy/shared-hosting/out (never committed):

    faithfellows-database.sql                 the FaithFellows database: tables, roles, plans, the first super admin, the blog,
                                              the public-domain "Classic Hymns" AND the sample data (five churches, members,
                                              sermons, events, giving, prayer, quizzes) so the site is not empty. The sample
                                              accounts sign in with DEMO_PASSWORD from .env.production, not the public "password".
    hymnal-mujje-tutendereze-omukama.sql      the Luganda Catholic hymn book, loaded HIDDEN (not shown in the app until you
                                              publish it in Super admin, Hymn book). Optional, import it after the first file.
    faithfellows-site.zip                     the website: code, built styles, the PHP packages (no development tools), and
                                              a root .htaccess that serves the public folder.

The database file is made on a throwaway database on this computer (ff_export), checked by loading it into a second one
(ff_verify), and both are dropped at the end. Settings come from web/.env.production (see docs/DEPLOYMENT_SHARED_HOSTING.md).
"""
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


def artisan(*args, db, extra_env=None):
    env = {**os.environ, **ARTISAN_ENV, 'DB_DATABASE': db, **(extra_env or {})}
    return run([PHP, 'artisan', *args], env=env, cwd=WEB, capture=True)


def dump(db, target, *tables, extra=()):
    cmd = [MYSQL_BIN / 'mysqldump.exe', '-u', 'root', '--default-character-set=utf8mb4', '--single-transaction', '--skip-lock-tables',
           '--skip-add-locks', '--no-tablespaces', '--skip-comments', '--skip-dump-date', '--net-buffer-length=60000',
           f'--result-file={target}', *extra, db, *tables]
    run(cmd, capture=True)


prod = {}
ARTISAN_ENV = {}


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
    ARTISAN_ENV = {
        'APP_ENV': 'production', 'APP_DEBUG': 'false', 'APP_URL': prod.get('APP_URL', 'https://ff.bodaerp.site'),
        'DB_CONNECTION': 'mysql', 'DB_HOST': '127.0.0.1', 'DB_PORT': '3306', 'DB_USERNAME': 'root', 'DB_PASSWORD': '',
        'SEED_SUPER_ADMIN_EMAIL': prod.get('SEED_SUPER_ADMIN_EMAIL', 'admin@faithfellows.app'),
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
    header = ('-- FaithFellows database for https://ff.bodaerp.site (with sample data).\n'
              '-- Import it into an EMPTY database. It drops and recreates every table, so NEVER import it again on a live site.\n')
    main.write_text(header + main.read_text(encoding='utf-8'), encoding='utf-8', newline='\n')

    has_luganda = LUGANDA.exists()
    if has_luganda:
        say('Adding the Luganda Catholic hymn book as a HIDDEN hymnal and writing its own file')
        artisan('hymns:import', LUGANDA, '--hidden', db='ff_export')
        slug = "'mujje-tutendereze-omukama'"
        dump('ff_export', luganda, 'hymnals', extra=['--no-create-info', '--skip-extended-insert', '--complete-insert', f'--where=slug={slug}'])
        part = OUT / 'part.sql'
        dump('ff_export', part, 'hymns', extra=['--no-create-info', '--skip-extended-insert', '--complete-insert',
                                                 f'--where=hymnal_id=(SELECT id FROM hymnals WHERE slug={slug})'])
        with open(luganda, 'a', encoding='utf-8', newline='\n') as f:
            f.write('\n' + part.read_text(encoding='utf-8'))
        part.unlink()
        header = ('-- The Luganda Catholic hymn book "Mujje Tutendereze Omukama", loaded HIDDEN (is_published = 0).\n'
                  '-- Import it AFTER faithfellows-database.sql and before adding other hymnals. It stays out of the app until you\n'
                  '-- publish it in Super admin, Hymn book, and the book is under copyright: publish it only with its publisher\'s permission.\n')
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
    checks = {
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
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L,QSA]
</IfModule>
<FilesMatch "^\\.(?!htaccess)">
    Require all denied
</FilesMatch>
"""


def build_zip():
    OUT.mkdir(parents=True, exist_ok=True)
    stage = OUT / 'stage'
    target = OUT / 'faithfellows-site.zip'
    if stage.exists():
        shutil.rmtree(stage)

    say('Building the styles and scripts (npm run build)')
    env = {**os.environ, 'PATH': str(NODE_DIR) + os.pathsep + os.environ['PATH']}
    run([NODE_DIR / 'npm.cmd', 'run', 'build'], cwd=WEB, env=env, capture=True)

    say('Copying the code')
    for src in WEB.rglob('*'):
        rel = src.relative_to(WEB)
        parts = rel.parts
        if parts[0] in SKIP_DIRS or src.name in SKIP_FILES or (src.name.startswith('.env') and src.name != '.env.example'):
            continue
        if parts[0] == 'public' and (src.name == 'hot' or parts[1:2] == ('storage',)):
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
    run([PHP, COMPOSER, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress'], cwd=stage, env=env, capture=True)

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
    if what not in ('all', 'sql', 'zip'):
        sys.exit(__doc__)
    load_settings()
    if what in ('all', 'sql'):
        build_sql()
    if what in ('all', 'zip'):
        build_zip()
    print('\nDone. Files are in', OUT)
