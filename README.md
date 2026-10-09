# Open Access Toolbar

Free WordPress plugin: a light, private accessibility toolbar for visitors (text size, spacing, contrast, readable font, and more) plus an Accessibility Check that lists common problems in your content and theme. No account, no external service, no cookies, no tracking.

The wordpress.org listing text lives in [readme.txt](readme.txt).

## Development

| Job | Command |
|---|---|
| Install dev tools | `composer install` |
| Lint (PHP syntax) | `composer lint` |
| Coding standards (WPCS + PHP 7.4 compatibility) | `vendor/bin/phpcs` |
| Unit tests (checker, settings, theme checks) | `vendor/bin/phpunit` |
| Build the release copy and zip | `bin/build.sh` → `build/open-access-toolbar/`, `build/open-access-toolbar.zip` |
| Browser test on real WordPress (SQLite) | `bin/e2e.sh` (`WP_VERSION=6.5 bin/e2e.sh` for the oldest supported; `SHOTS=$PWD/.wordpress-org` refreshes the screenshots) |
| Plugin Check | `wp plugin check open-access-toolbar --include-experimental` on a site with the built copy installed |
| Translation template | `wp i18n make-pot . languages/open-access-toolbar.pot --exclude=vendor,build,tests,bin,node_modules` |

CI (`.github/workflows/ci.yml`) runs all of the above on pull requests and on `main`.

`.wordpress-org/` holds the directory screenshots; they go to the SVN `assets/` folder, not into the plugin zip.

## License

GPL-2.0-or-later.
