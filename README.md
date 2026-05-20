# Simple PHP D&D Session Scheduler

A single-file PHP app for scheduling D&D sessions. Players pick available dates; the DM manages everything from an admin panel.

## Requirements

- PHP 7.4+ with a web server (Apache, Nginx, or `php -S localhost:8080`)
- Write access to the project folder (stores data in `dnd_data.json` and `config.php`)

## Setup

1. edit your config.php so you don't have default passwords!
2. Drop the files into your web root (or any folder your server serves).


## Usage

**Players** — enter your name, click a day button to sign up for every session that day, or click individual date tiles to toggle.

**Admin** — click **Admin** in the top bar, enter the admin password (default: `admin123`), then:

- See a **Best Days** breakdown of sign-ups by day of week
- Adjust **allowed days**, session times, max players per session
- Change the site title, subtitle, site password, and admin password
- Remove individual sign-ups from the session grid

##
