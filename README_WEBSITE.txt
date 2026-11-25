ANSAARU SUNNA ACADEMY WEBSITE (24 November 2026)
The website now takes its pages, news, events, pictures, menus and design from the school portal
(Portal → Website, Super Admin only). Change things there; publish; the website shows them within two minutes.

FIRST TIME ONLY
1. In the hosting panel, open ansaarusunnaboys.co.ke → File Manager → public_html.
   Download a backup of everything there first (select all → Compress → download the zip).
2. Upload this zip into public_html and Extract it there (overwrite = yes).
3. Rename config.sample.php to config.php, open it (Edit) and paste the key from
   Portal → Website → Settings → "Connect the website". Save.
4. Open https://ansaarusunnaboys.co.ke and press Ctrl+F5. The old .html addresses (about.html …) move to the
   new ones (/about …) by themselves. The old .html files may stay; they are no longer used.

LATER UPDATES
Upload the new website zip and extract over the old files. config.php and cache/ are never in the zip, so the
key stays.

NEEDS: PHP 8.0 or newer (with cURL or allow_url_fopen) on the website's hosting.
