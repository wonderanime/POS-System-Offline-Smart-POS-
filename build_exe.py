"""SmartPOS Build Script — downloads PHP, builds SmartPOS.exe via PyInstaller."""
import os, sys, shutil, subprocess, zipfile, urllib.request

ROOT = os.path.dirname(os.path.abspath(__file__))
LAUNCHER = os.path.join(ROOT, 'launcher', 'launch.py')
APP_DIR = os.path.join(ROOT, 'app')
PHP_DIR = os.path.join(ROOT, 'php')
PHP_EXE = os.path.join(PHP_DIR, 'php.exe')
PHP_INI = os.path.join(PHP_DIR, 'php.ini')
DIST_DIR = os.path.join(ROOT, 'dist')
BLD_DIR = os.path.join(ROOT, '_build')

PHP_URLS = [
    'https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip',
    'https://windows.php.net/downloads/releases/latest/php-8.2-nts-Win32-vs16-x64-latest.zip',
]

def download_php():
    if os.path.exists(PHP_EXE):
        print('[OK] PHP already present: ' + PHP_EXE); return
    tmp = os.path.join(ROOT, '_php_tmp.zip'); ok = False
    for url in PHP_URLS:
        print('\n[DOWNLOAD] ' + url)
        try:
            def _hook(blk, bs, tot):
                if tot > 0:
                    pct = min(100, int(blk*bs*100/tot))
                    print('\r  [%s] %d%%' % ('#'*(pct//4), pct), end='', flush=True)
            urllib.request.urlretrieve(url, tmp, _hook); print(); ok = True; break
        except Exception as e:
            print('\n  Failed: %s' % e)
            if os.path.exists(tmp): os.remove(tmp)
    if not ok:
        print('\n[ERROR] Could not download PHP. Extract manually into: ' + PHP_DIR); sys.exit(1)

    print('[EXTRACT] Extracting…')
    if os.path.exists(PHP_DIR): shutil.rmtree(PHP_DIR)
    os.makedirs(PHP_DIR, exist_ok=True)
    with zipfile.ZipFile(tmp, 'r') as z: z.extractall(PHP_DIR)
    os.remove(tmp)

    ext_dir = os.path.join(PHP_DIR, 'ext').replace('\\','/')
    ini = '\n'.join([
        '[PHP]', 'engine = On', 'short_open_tag = Off', 'memory_limit = 256M',
        'upload_max_filesize = 20M', 'post_max_size = 20M', 'max_execution_time = 60', '',
        'extension_dir = "%s"' % ext_dir, '',
        'extension=pdo_sqlite', 'extension=sqlite3', 'extension=openssl',
        'extension=mbstring', 'extension=fileinfo', 'extension=curl', 'extension=gd', '',
    ])
    with open(PHP_INI, 'w', encoding='utf-8') as f: f.write(ini)
    print('[OK] PHP extracted to: ' + PHP_DIR)

def verify_php():
    kw = {'creationflags': subprocess.CREATE_NO_WINDOW} if sys.platform=='win32' else {}
    r = subprocess.run([PHP_EXE, '-c', PHP_INI, '--version'], capture_output=True, text=True, **kw)
    if r.returncode != 0 or 'PHP' not in r.stdout:
        print('[ERROR] PHP self-test failed:\n' + (r.stderr or r.stdout))
        sys.exit(1)
    print('[OK] ' + r.stdout.splitlines()[0])
    r2 = subprocess.run([PHP_EXE, '-c', PHP_INI, '-r',
                          'try{new PDO("sqlite::memory:");echo "OK";}catch(Exception $e){echo "ERR:".$e->getMessage();}'],
                         capture_output=True, text=True, **kw)
    if 'OK' not in r2.stdout:
        print('[ERROR] SQLite test failed: %s %s' % (r2.stdout, r2.stderr)); sys.exit(1)
    print('[OK] PHP SQLite extension works.')

def build():
    print('=' * 54); print('  SmartPOS — Windows EXE Build'); print('=' * 54)
    download_php(); verify_php()

    sep = ';' if sys.platform == 'win32' else ':'
    add_data_args = []
    for d in [f'{APP_DIR}{sep}app', f'{PHP_DIR}{sep}php']:
        add_data_args += ['--add-data', d]

    icon = os.path.join(ROOT, 'build_assets', 'icon.ico')
    icon_args = ['--icon', icon] if os.path.exists(icon) else []

    cmd = [sys.executable, '-m', 'PyInstaller', '--noconfirm', '--onedir', '--windowed',
           '--name', 'SmartPOS', *icon_args, *add_data_args,
           '--distpath', DIST_DIR, '--workpath', BLD_DIR, LAUNCHER]

    print('\n[BUILD] Running PyInstaller…')
    r = subprocess.run(cmd, cwd=ROOT)
    if r.returncode == 0:
        exe = os.path.join(DIST_DIR, 'SmartPOS', 'SmartPOS.exe')
        print('\n' + '='*54 + '\n  BUILD SUCCESSFUL\n' + '='*54)
        print('  EXE : ' + exe)
        print('  Distribute the entire dist/SmartPOS/ folder.')
        print('  Database: dist/SmartPOS/app/database/pos.db')
        print('='*54)
    else:
        print('\n[FAILED] Check errors above.'); sys.exit(1)

if __name__ == '__main__':
    build()
