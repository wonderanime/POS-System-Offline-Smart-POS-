"""SmartPOS Launcher — auto-downloads PHP, self-tests, starts server, opens browser."""
import os, sys, time, socket, subprocess, threading, webbrowser, zipfile, shutil

try:
    import urllib.request as urlreq
    import urllib.error
except ImportError:
    urlreq = None

try:
    import tkinter as tk
    from tkinter import messagebox
    HAS_TK = True
except ImportError:
    HAS_TK = False

if getattr(sys, 'frozen', False):
    _SELF_DIR = sys._MEIPASS
else:
    _SELF_DIR = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(_SELF_DIR) if os.path.basename(_SELF_DIR).lower() == 'launcher' else _SELF_DIR

APP_DIR = os.path.join(ROOT, 'app')
PUBLIC_DIR = os.path.join(APP_DIR, 'public')
ROUTER = os.path.join(PUBLIC_DIR, 'router.php')
PHP_DIR = os.path.join(ROOT, 'php')
PHP_EXE = os.path.join(PHP_DIR, 'php.exe')
PHP_INI = os.path.join(PHP_DIR, 'php.ini')

HOST, PORT = '127.0.0.1', 8741
PHP_URLS = [
    'https://windows.php.net/downloads/releases/latest/php-8.3-nts-Win32-vs16-x64-latest.zip',
    'https://windows.php.net/downloads/releases/latest/php-8.2-nts-Win32-vs16-x64-latest.zip',
]

php_proc = None
_status_cb = None

def log(msg):
    print('[SmartPOS] ' + msg)
    if _status_cb:
        try: _status_cb(msg)
        except Exception: pass

def find_ext_dir():
    candidates = [os.path.join(PHP_DIR, 'ext'), PHP_DIR]
    for c in candidates:
        if os.path.isdir(c):
            names = [n.lower() for n in os.listdir(c)]
            if any('sqlite3' in n and n.endswith('.dll') for n in names):
                return c
    for dirpath, _, filenames in os.walk(PHP_DIR):
        for fn in filenames:
            if fn.lower() in ('php_sqlite3.dll', 'php_pdo_sqlite.dll'):
                return dirpath
    return os.path.join(PHP_DIR, 'ext')

def configure_ini():
    ext_dir = find_ext_dir().replace('\\', '/')
    lines = [
        '[PHP]', 'engine = On', 'short_open_tag = Off', 'memory_limit = 256M',
        'upload_max_filesize = 20M', 'post_max_size = 20M', 'max_execution_time = 60', '',
        'extension_dir = "%s"' % ext_dir, '',
        'extension=pdo_sqlite', 'extension=sqlite3', 'extension=openssl',
        'extension=mbstring', 'extension=fileinfo', 'extension=curl', 'extension=gd', '',
    ]
    with open(PHP_INI, 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))
    log('php.ini configured (extension_dir=%s)' % ext_dir)

def test_php():
    if not os.path.exists(PHP_EXE):
        return False, 'php.exe not found at:\n' + PHP_EXE
    dlls = [f for f in os.listdir(PHP_DIR) if f.lower().endswith('.dll')] if os.path.isdir(PHP_DIR) else []
    if not dlls:
        return False, ('php.exe exists but required DLLs are missing.\n\n'
                        'Extract the ENTIRE PHP zip into the php/ folder, not just php.exe.')
    configure_ini()
    kw = {'creationflags': subprocess.CREATE_NO_WINDOW} if sys.platform == 'win32' else {}
    try:
        r = subprocess.run([PHP_EXE, '-c', PHP_INI, '-r', 'echo "phpok";'],
                            capture_output=True, text=True, timeout=10, **kw)
        if 'phpok' not in r.stdout:
            err = r.stderr.strip() or r.stdout.strip() or 'no output'
            hint = ''
            if 'VCRUNTIME' in err or 'api-ms' in err.lower():
                hint = '\n\nInstall Visual C++ Runtime:\nhttps://aka.ms/vs/17/release/vc_redist.x64.exe'
            return False, 'PHP failed to run:\n' + err + hint
    except Exception as e:
        return False, 'Cannot execute php.exe: %s' % e

    try:
        r = subprocess.run([PHP_EXE, '-c', PHP_INI, '-r',
                             'try{new PDO("sqlite::memory:");echo "sqok";}catch(Exception $e){echo "ERR:".$e->getMessage();}'],
                            capture_output=True, text=True, timeout=10, **kw)
        if 'sqok' not in (r.stdout + r.stderr):
            return False, 'PHP runs but SQLite extension failed:\n' + (r.stdout + r.stderr).strip()
    except Exception as e:
        return False, 'SQLite test failed: %s' % e
    return True, ''

def _urlopen_tolerant(url, timeout=30):
    import ssl
    try:
        return urlreq.urlopen(url, timeout=timeout)
    except urllib.error.URLError as e:
        if 'CERTIFICATE_VERIFY_FAILED' in str(e):
            ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
            return urlreq.urlopen(url, timeout=timeout, context=ctx)
        raise

def download_and_install_php(progress_cb=None):
    if not urlreq:
        return False, 'urllib not available.'
    tmp_zip = os.path.join(ROOT, '_php_download.zip')
    ok_url = None
    for url in PHP_URLS:
        try:
            log('Trying: ' + url)
            if progress_cb: progress_cb('Connecting to windows.php.net…')
            resp = _urlopen_tolerant(url, timeout=30)
            total = int(resp.headers.get('Content-Length', 0)); downloaded = 0
            with open(tmp_zip, 'wb') as out:
                while True:
                    chunk = resp.read(65536)
                    if not chunk: break
                    out.write(chunk); downloaded += len(chunk)
                    if total > 0 and progress_cb:
                        pct = min(100, int(downloaded*100/total))
                        progress_cb('Downloading PHP… %d%% (%d MB)' % (pct, total//(1024*1024)))
            ok_url = url; break
        except Exception as e:
            log('Failed (%s): %s' % (url, e))
            if os.path.exists(tmp_zip): os.remove(tmp_zip)

    if not ok_url:
        return False, ('Could not download PHP automatically.\n\nManual steps:\n'
                        '1. https://windows.php.net/download/\n'
                        '2. Download PHP 8.3 VS16 x64 Non Thread Safe ZIP\n'
                        '3. Extract into the php/ folder (php/php.exe must exist)\n'
                        '4. Run SmartPOS again.')

    if progress_cb: progress_cb('Extracting PHP…')
    try:
        if os.path.exists(PHP_DIR): shutil.rmtree(PHP_DIR)
        os.makedirs(PHP_DIR, exist_ok=True)
        with zipfile.ZipFile(tmp_zip, 'r') as z: z.extractall(PHP_DIR)
        os.remove(tmp_zip)
    except Exception as e:
        return False, 'Extraction failed: %s' % e

    configure_ini()
    ok, err = test_php()
    if not ok: return False, 'PHP downloaded but self-test failed:\n' + err
    log('PHP installed and tested OK.')
    return True, ''

def port_free(port):
    with socket.socket() as s: return s.connect_ex((HOST, port)) != 0

def wait_server(timeout=25):
    t = time.time()
    while time.time()-t < timeout:
        if not port_free(PORT): return True
        time.sleep(0.35)
    return False

def build_cmd():
    cmd = [PHP_EXE]
    if os.path.exists(PHP_INI): cmd += ['-c', PHP_INI]
    cmd += ['-S', '%s:%d' % (HOST, PORT), '-t', PUBLIC_DIR, ROUTER]
    return cmd

def start_server():
    global php_proc
    kw = {'creationflags': subprocess.CREATE_NO_WINDOW} if sys.platform == 'win32' else {}
    php_proc = subprocess.Popen(build_cmd(), stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, **kw)

def stop_server():
    global php_proc
    if php_proc and php_proc.poll() is None:
        php_proc.terminate()
        try: php_proc.wait(timeout=5)
        except Exception: php_proc.kill()

def open_browser(): webbrowser.open('http://%s:%d' % (HOST, PORT))

def run_gui():
    global _status_cb
    root = tk.Tk(); root.title('SmartPOS'); root.geometry('400x220'); root.resizable(False, False)
    BG, FG, ACCENT = '#111827', '#f3f4f6', '#4f46e5'
    root.configure(bg=BG)
    tk.Label(root, text='SmartPOS', font=('Segoe UI', 18, 'bold'), bg=BG, fg=ACCENT).pack(pady=(20,2))
    status_var = tk.StringVar(value='Starting…')
    _status_cb = status_var.set
    tk.Label(root, textvariable=status_var, font=('Segoe UI', 10), bg=BG, fg=FG, wraplength=370, justify='center').pack(pady=6)
    url_lbl = tk.Label(root, text='http://%s:%d' % (HOST, PORT), font=('Segoe UI', 9), bg=BG, fg='#6b7280')
    url_lbl.pack()
    btn_frame = tk.Frame(root, bg=BG); btn_frame.pack(pady=14)
    def open_click(): open_browser()
    def quit_click():
        if messagebox.askyesno('SmartPOS', 'Stop the POS server and close?'):
            stop_server(); root.destroy()
    open_btn = tk.Button(btn_frame, text='Open Browser', command=open_click, bg=ACCENT, fg='#fff',
                          font=('Segoe UI',10,'bold'), padx=16, pady=6, relief='flat', cursor='hand2', state='disabled')
    open_btn.pack(side='left', padx=5)
    tk.Button(btn_frame, text='Stop & Exit', command=quit_click, bg='#1f2937', fg=FG,
              font=('Segoe UI',10), padx=16, pady=6, relief='flat', cursor='hand2').pack(side='left', padx=5)
    root.protocol('WM_DELETE_WINDOW', quit_click)

    def startup():
        global php_proc
        ok, err = test_php()
        if not ok:
            log('PHP not ready: ' + err)
            ok, err = download_and_install_php(progress_cb=log)
            if not ok:
                def _show(): messagebox.showerror('SmartPOS — PHP Setup Failed', err)
                root.after(0, _show); return
        log('Starting PHP server…')
        start_server()
        if wait_server():
            log('Ready  ·  http://%s:%d' % (HOST, PORT))
            def _ready():
                url_lbl.config(fg=ACCENT); open_btn.config(state='normal'); open_browser()
            root.after(0, _ready)
        else:
            def _fail(): messagebox.showerror('SmartPOS', 'PHP server failed to start.')
            root.after(0, _fail)
        def _watch():
            while True:
                time.sleep(6)
                if php_proc and php_proc.poll() is not None:
                    log('Server stopped unexpectedly.'); return
        threading.Thread(target=_watch, daemon=True).start()

    threading.Thread(target=startup, daemon=True).start()
    root.mainloop()

def run_headless():
    global php_proc
    ok, err = test_php()
    if not ok:
        print('[SmartPOS] PHP not ready: ' + err)
        ok, err = download_and_install_php(progress_cb=print)
        if not ok: print('\n[SmartPOS] FAILED:\n' + err); sys.exit(1)
    print('[SmartPOS] Starting server on http://%s:%d…' % (HOST, PORT))
    start_server()
    if wait_server():
        print('[SmartPOS] Ready — opening browser…'); open_browser()
        try:
            while True: time.sleep(1)
        except KeyboardInterrupt: pass
    else:
        print('[SmartPOS] Server failed to start.')
    stop_server()

if __name__ == '__main__':
    print('[SmartPOS] ROOT    : ' + ROOT)
    print('[SmartPOS] PHP_EXE : ' + PHP_EXE)
    print('[SmartPOS] ROUTER  : ' + ROUTER)
    if not port_free(PORT):
        open_browser(); sys.exit(0)
    if HAS_TK: run_gui()
    else: run_headless()
    stop_server()
