# open-access-toolbar: notes for Claude sessions

Free wordpress.org plugin: visitor accessibility toolbar + Accessibility Check (Tools menu). Part of Enrique's plugin portfolio; project charter and queue live in the project's shared folder (`autopilot/`, `plans/`).

## Commands
See the table in README.md. Every gate before a PR is "ready for review": `composer lint`, `vendor/bin/phpcs`, `vendor/bin/phpunit`, `bin/build.sh` + Plugin Check on the built copy, `bin/e2e.sh` on 7.1.3 and on 6.5.
Locally the e2e test needs axe-core: `npm install --no-save --prefix build/node axe-core@4.10.3` then `NODE_PATH=$(npm root -g):$PWD/build/node/node_modules bin/e2e.sh`. Run Composer as root with `COMPOSER_ALLOW_SUPERUSER=1`.

## Architecture
- `assets/toolbar.js`: the visitor toolbar, built in a shadow root on `#oatb-root` so theme CSS can't reach it. Choices live in `localStorage["oatb-prefs"]` (`{tools:{id:true}, text:step}`); no cookies, no requests.
- `assets/page.css`: page effects, each scoped to a class on `<html>` (`oatb-<tool-id-with-dashes>`). Rules exclude `#oatb-root`.
- `includes/class-oatb-frontend.php`: enqueues; an inline head script re-applies class-based tools before paint; `window.oatbConfig` carries settings and strings.
- `includes/class-oatb-checker.php`: pure HTML analysis with `WP_HTML_Tag_Processor::next_token()` (needs WP 6.5). Unit tested against the HTML API from the `johnpbloch/wordpress-core` dev dependency.
- `includes/class-oatb-scanner.php`: batches of 10 posts over AJAX, renders `do_blocks` + `do_shortcode` (output buffered), stores results in the non-autoloaded `oatb_check` option; theme check is a loopback `wp_safe_remote_get` of the home page.
- `includes/class-oatb-admin.php`: Settings > Accessibility Toolbar, Tools > Accessibility Check, statement draft via admin-post.

## Conventions and traps
- Prefix `oatb`/`OATB_`. Text domain `open-access-toolbar`; regenerate the .pot when strings change.
- Never claim the plugin makes a site "compliant" (ADA/EAA/WCAG) in code, readme or UI: the FTC fined an overlay vendor for that. Keep claims factual.
- Plugin Check caps the readme short description at 150 characters.
- `php -S` serves one request at a time; `bin/e2e.sh` sets `PHP_CLI_SERVER_WORKERS=4` so the theme check's loopback request works.
- In the tag processor, SVG `<title>` is one token whose text is `get_modifiable_text()`, not a `#text` token.
- The cloud sandbox can't reach wordpress.org; `bin/e2e.sh` pulls WordPress, WP-CLI and the SQLite drop-in from GitHub.
