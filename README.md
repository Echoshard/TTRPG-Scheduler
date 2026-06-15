# Simple PHP D&D Session Scheduler

A single-file PHP app for scheduling D&D sessions. Players pick available dates; the DM manages everything from an admin panel.

## Features

- **Multi-Theme Support**: Choose from 5 premium themes: Neon/Sci-Fi Grid, Sci-Fi Starship, Fantasy Parchment, Gothic Grim Dark, and Eberron/Steampunk. Theme changes preview live in the admin panel before you save.
- **Custom Banner Upload**: Easily upload any custom header image through the admin panel.
- **Session Waiting List**: When session slots are full, players can sign up to a waiting list, which displays as `⏳ Name` (Waiting).
- **DM "No" Sessions**: The DM can block/cancel specific dates by setting their status to "No", disabling player sign-ups.
- **Delete Player by Name**: Quickly clear all sign-ups for a player across all dates in one click.
- **Authentication Toggle**: Control whether the site password gate is required or bypassed.

## Requirements

- PHP 7.4+ (stores data in `dnd_data.json` and `config.php`)

## Setup & Running

1. If you are on Windows, simply double-click `run.bat` to launch the PHP built-in server.
2. Open `http://localhost:8080` in your web browser.
3. Access the Admin panel using the default admin password: `admin123`.

## Configuration

App settings (passwords, title, timezone, default theme) live in `config.php`. This file is **not** committed to git because it holds your credentials. On first run, the app auto-generates `config.php` with default values.

To configure manually, copy the template and edit it:

```sh
cp config.example.php config.php
```

| Constant          | Description                                                        |
| ----------------- | ------------------------------------------------------------------ |
| `ADMIN_PASSWORD`  | Password for the Admin panel (default `admin123`)                  |
| `SITE_PASSWORD`   | Password for the site gate when `REQUIRE_LOGIN` is on (default `NEON`) |
| `SITE_TITLE`      | Header title                                                       |
| `SITE_SUBTITLE`   | Header subtitle                                                    |
| `SITE_TIMEZONE`   | PHP timezone identifier (e.g. `America/New_York`)                  |
| `REQUIRE_LOGIN`   | Whether the site password gate is required                         |
| `DEFAULT_THEME`   | `neon`, `scifi`, `fantasy`, `grimdark`, or `steampunk`             |

Most of these can also be changed at runtime from the Admin panel, which writes back to `config.php`. Session sign-up data is stored separately in `dnd_data.json` (also git-ignored).

## Usage

**Players** — Enter your name, click a day button to sign up for every session that day, or click individual date tiles to toggle.

**Admin** — Click **Admin** in the top bar, enter the admin password, then:

- View a **Best Days** breakdown of sign-ups by day of week.
- Adjust **allowed days**, session times, timezone, and max players per session.
- Grouped under **Site Customization**: change theme, upload a custom banner, toggle site login, modify titles, and update passwords.
- Block specific sessions (toggle Yes/No).
- Delete a player's signups by name.
- Remove individual sign-ups from the session grid.

## Credits

Original banner image is from Neon Odyssey: https://www.kickstarter.com/projects/legendsofavantris/neon-odyssey
