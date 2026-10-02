# Laghavi Video

A private AI video generation site. Users log in, write a prompt, optionally
attach reference images or a reference video, pick any output width, height and
aspect ratio, and get the finished video back on their dashboard.

- **No public signup.** The site shows only a login page. The admin (you)
  creates every account from the **Users** page.
- **Everything is behind login**: pages, video files, status checks. Uploads and
  generated videos are served only through `video.php`, which checks the owner.
- **Built for Hostinger basic shared hosting**: plain PHP 8.1+ and MySQL, no
  Node, no Composer, no background processes. A cron job runs every minute to
  send work to the video API and collect finished videos.
- **Video API through Replicate** by default, so you can switch models by
  changing `config.php`. Other providers plug in through
  `app/Providers/VideoProvider.php`.

## How it works

```
User submits the form (generate.php)
   → files saved in storage/uploads, job saved in MySQL as "queued"
Cron, every minute (cron/worker.php)
   → queued jobs: reference files uploaded to Replicate, prediction started → "processing"
   → processing jobs: status checked; when done, the video is downloaded to storage/videos → "completed"
Dashboard checks job_status.php every 10 s while something is in progress and refreshes when it finishes.
```

Failed API calls are retried on the next cron run (3 attempts), and jobs stuck
longer than `job_timeout_minutes` are marked failed. Each user can have at most
`max_active_jobs_per_user` videos in progress, which caps spend.

### About custom sizes

The width and height a user picks are validated (rounded to even numbers,
within `dimensions.min`/`max`), stored, and sent to the model. Whether the video
comes back at that exact size depends on the model: some accept `width`/`height`
or a size string, many only accept a short list of aspect ratios. For those, the
closest supported ratio is sent (`aspect_ratio_mode => 'nearest'`). Check the
model's API tab on replicate.com and fill in `input_map` accordingly.

## Deploying on Hostinger

### 1. Create the database

hPanel → **Databases → MySQL Databases**. Create a database and user and note
the three values (Hostinger prefixes them, e.g. `u123456789_video`).

### 2. Upload the files

hPanel → **Files → File Manager** → open `public_html` for the domain you want
to use (for an extra domain it's `domains/yourdomain.com/public_html`).
Upload everything in this repository (a zip of it, then **Extract**). The
`.htaccess` files must come along; File Manager shows them if you enable
hidden files.

Make sure the `storage` folder is writable (permissions 755); uploads and
finished videos are saved there.

### 3. PHP settings

hPanel → **Advanced → PHP Configuration**: choose PHP 8.1 or newer, and raise
`upload_max_filesize` and `post_max_size` to at least 50 MB (`post_max_size` a
bit higher, since several files share one request). Turn on the free SSL
certificate for the domain; `.htaccess` redirects to HTTPS.

### 4. Run the setup page

Open `https://yourdomain.com/` in your browser. With no `config.php` yet, it
sends you to `setup.php`, which asks for:

- **Database name, username and password**: from step 1 (hPanel → Databases →
  MySQL Databases; the password is the one you set there, and you can change it
  on that page if you've lost it).
- **Admin username and password**: your own login (10+ characters).
- **Replicate API token** (optional): from replicate.com → Account → API tokens.

It checks the database details, creates the tables and your admin account, and
writes `config.php` for you. If the server won't let it save the file, it shows
the contents to paste into a new `config.php` in File Manager. Once
`config.php` exists, the setup page disappears. It only accepts a database on
this hosting account (`localhost`), so a stranger can't finish setup before you.

### 5. Optional settings in config.php

Everything else has sensible defaults. To change them, edit `config.php` in
File Manager (each setting is explained in it):

- `replicate.api_token`, if you skipped it during setup.
- `replicate.model` and `replicate.input_map`: see "About custom sizes" above.
- `storage_path`: by default videos are kept in `storage/`, which `.htaccess`
  blocks from direct access. You can move it outside the site, e.g.
  `/home/u123456789/video-storage`.
- To try the site before you have an API key, set `'provider' => 'mock'` and
  `mock_sample_video_url` to any public `.mp4` link.

Prefer to do it by hand? Copy `config.example.php` to `config.php`, fill it in,
set `install_token`, and open `install.php` (or run `php cli/install.php
yourname` over SSH) to create the admin. Delete `install.php` afterwards.

### 6. Add the cron job

Log in and open **Settings**: it shows the exact command for your site and
whether the cron job is running. In hPanel → **Advanced → Cron Jobs**, add a
Custom job that runs every minute (`* * * * *`) with that command, which looks
like:

```
/usr/bin/php /home/u123456789/domains/yourdomain.com/public_html/cron/worker.php
```

Until the cron job runs, videos still move forward while someone has the
Create page open (the page's status checks do the work), just more slowly.
Settings is also where you add or change the Replicate API token and model; if
either is missing, queued videos wait and the dashboard says why.

### 7. Create users

Log in, open **Users**, and create accounts. Share the username and password
privately; users can change their password under their name in the top bar.
You can reset passwords, disable accounts (takes effect on their next click),
or delete users along with their videos. The admin account itself can't be
disabled or deleted from the panel.

## Development

Requires PHP 8.1+ with `pdo_sqlite`, `curl` and `fileinfo`. No other
dependencies.

```
php tests/run.php            # all tests (uses SQLite, no MySQL needed)
php tests/run.php Worker     # only tests whose name matches
```

The suite covers login and lockout, the admin-only user management, size and
upload validation, the Replicate request mapping, the cron worker's state
machine, and a live run against PHP's built-in server that checks every page
redirects to login, regular users can't open the admin panel, and generation
works end to end.

### Security notes

- Passwords use `password_hash`; 5 failed logins for a username (or 20 from one
  IP) lock it for 15 minutes.
- All forms carry a CSRF token; session cookies are `HttpOnly`, `SameSite=Lax`
  and `Secure` over HTTPS; the session id is regenerated on login.
- Uploaded files are checked by content type (not extension), renamed randomly,
  and stored where the web server won't serve them.
- `cron/` and `cli/` scripts refuse to run from the web.
