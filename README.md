<<<<<<< HEAD
<div align="center">

# 🛒 SmartPOS

**A fast, offline-first Point of Sale system for small shops — one PC, one admin, zero internet required.**

Built with PHP + SQLite, packaged as a single Windows `.exe`.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-3-003B57?logo=sqlite&logoColor=white)
![Platform](https://img.shields.io/badge/platform-Windows-0078D6?logo=windows&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

</div>

---

## ✨ Features

| Module | What it does |
|---|---|
| 📊 **Dashboard** | Today/monthly sales & profit, 7-day sales chart, top products, low-stock alerts, clear Profit & Loss breakdown |
| 🧾 **POS** | Category filters, product grid, cart with qty steppers, discount, Cash/Card/Bank/Other, paid amount & change, Hold/Resume sale, printable receipt. Keyboard shortcuts: `F2` search · `F3` complete · `F4` hold · `Esc` cancel |
| 📦 **Products** | Add/edit with image upload *or* URL (auto-downloaded & compressed to a uniform size), search, bulk select/edit/delete |
| 🏷️ **Categories & Brands** | Simple management with product counts |
| 📈 **Stock Inventory** | Current stock, low/out-of-stock filters, adjustments with required reason + full history |
| 🚚 **Purchases & Suppliers** | Record purchases from suppliers, auto-updates stock and cost price, tracks amounts owed |
| 👥 **Customers** | Contact info, due balances for credit sales |
| 🧮 **Sales History** | Filterable list, view/print any invoice, **void a sale** (restores stock, reverses profit, requires a reason, logged permanently) |
| 💸 **Expenses** | Title, category, amount, note |
| 📑 **Reports** | Sales, Profit & Loss, Expenses, Tax, Top Products, Stock, Customers — all CSV-exportable |
| 📝 **Audit Log** | Permanent, timestamped record of every void and bulk action |
| ⚙️ **Settings** | Store info, logo, receipt preferences, PIN change — one clean page |
| 💾 **Backup & Restore** | Manual backup, daily auto-backup, download/restore any backup file |

---

## 🚀 Quick Start (development)

Requires PHP 8.x with the SQLite and GD extensions.

```bash
git clone https://github.com/<your-username>/smartpos.git
cd smartpos/app
php -S 127.0.0.1:8741 -t public public/router.php
```

Open **http://127.0.0.1:8741** — default PIN: **`1234`** (change it in Settings immediately).

---

## 🏗️ Build the Windows .exe

No manual PHP install needed — the build script downloads and configures a portable PHP runtime automatically.

```bat
BUILD.bat
```

This will:
1. Install PyInstaller if missing
2. Download & configure portable PHP (SQLite + GD extensions enabled)
3. Bundle everything into `dist/SmartPOS/SmartPOS.exe`

To run without building an `.exe`, just double-click **`START.bat`**.

**To deploy to a shop PC:** copy the entire `dist/SmartPOS/` folder. The database travels with it — no separate install step.

---

## 📁 Project Structure

```
smartpos/
├── app/
│   ├── public/
│   │   ├── router.php          # URL routing
│   │   ├── pages/              # One file per screen
│   │   ├── api/                # JSON endpoints (checkout, theme)
│   │   └── assets/              # css / js / uploaded product images
│   ├── includes/                # db.php, auth.php, pagination.php, config.php
│   └── database/
│       ├── schema.sql            # Table definitions (auto-migrates on boot)
│       └── backups/              # Auto + manual backups (gitignored)
├── launcher/launch.py            # Starts PHP server + opens browser (used by the .exe)
├── build_assets/icon.ico         # App icon
├── build_exe.py                  # Builds the Windows .exe
├── BUILD.bat / START.bat
└── README.md
```

---

## 🔒 Data & Safety

- Single admin PIN login (SQLite `password_hash`, never stored in plaintext)
- The database **auto-migrates on every boot** — pulling a new version of this repo over an existing install adds any new tables/columns without touching your data
- Voided sales are never deleted — they're marked `voided`, excluded from all profit/reporting, and logged in the Audit Log with a mandatory reason
- Daily automatic backups, kept in `app/database/backups/` (last 30 retained)

---

## 🗺️ Roadmap / Known Gaps

- [ ] Purchase-side void/return (currently only sales can be voided)
- [ ] Receipt print sizing options (58mm / 80mm / A4)
- [ ] Customer & supplier payment recording against due balances

Contributions welcome — see [Contributing](#contributing) below.

---

## Contributing

1. Fork the repo
2. Create a branch: `git checkout -b feature/your-feature`
3. Commit your changes and open a PR

Please test against `php -S 127.0.0.1:8741 -t public public/router.php` before submitting.

---

## License

[MIT](LICENSE) — free to use, modify, and distribute.
=======
# POS-System-Offline-Smart-POS-
SmartPOS is a lightweight, offline-first POS system built with PHP and SQLite. Features: sales &amp; checkout, inventory management, customer/supplier tracking, purchases, expenses, discounts, sales history, void/refund handling, reports &amp; analytics, database backups, activity logs, and a customizable dashboard — runs fully locally.
>>>>>>> 35c1365196d76ec1099f14b52d6024f884610132
