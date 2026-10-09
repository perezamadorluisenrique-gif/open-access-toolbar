=== Open Access Toolbar ===
Contributors: wporg-username
Tags: accessibility, a11y, accessibility toolbar, wcag, font size
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private accessibility toolbar for visitors, plus a checker for common accessibility problems in your content. No account, no cloud.

== Description ==

Open Access Toolbar adds a small button to your site. Visitors open it to make your pages easier for them to read: bigger text, more spacing, higher contrast, a plainer font and more. It also gives you, the site owner, a checker that lists the problems in your own content that make the site harder to use, with what to fix.

No account to create, no external service, no tracking. Everything runs on your own site.

= Tools for your visitors =

* **Text size**: five steps, up to 150%.
* **Line height** and **letter spacing**: more room between lines, letters and words.
* **Readable font**: switches the page to a plain sans-serif font.
* **High contrast**: dark (white on black) or light (black on white).
* **Grayscale**.
* **Underline links**, so links don't depend on color.
* **Highlight focus**: a strong outline around the link or button that has keyboard focus.
* **Stop animations**: stops CSS animations and transitions and pauses autoplaying video.
* **Big cursor**.
* **Reading guide**: a band that follows the mouse to help keep your place in a line.
* **Reset all**.

Each visitor's choices are remembered in their own browser (localStorage) and apply on every page. No cookies are set and nothing is sent anywhere.

= Built to stay out of the way =

* One small script and one small stylesheet, loaded only on the front end. No jQuery, no CDN, no web fonts.
* The toolbar is drawn in a shadow root, so your theme's CSS can't break it and it can't break your theme.
* The toolbar itself works with a keyboard and a screen reader: real buttons, announced states, Escape to close, visible focus.
* Nothing changes on your pages until a visitor turns a tool on.
* Nothing is added to the WordPress dashboard except its two screens. No upsells, no notices.

= Accessibility Check for site owners =

**Tools > Accessibility Check** reads your published posts, pages and other public content and lists:

* images without alt text
* links and buttons with no text (for example an icon link with no label)
* empty headings and skipped heading levels
* form fields without a label
* embedded frames (videos, maps) without a title

Each item links to its editor, with the reason and the fix. The check runs in small batches from your browser, so it works on large sites and on cheap hosting.

A second check looks at your home page for things your theme controls: the page language, whether pinch zoom is blocked, a skip link, and a main content landmark.

= Theme fixes =

Two optional fixes for every visitor, toolbar or not:

* add a "Skip to content" link if your theme has none
* always show a visible focus outline for keyboard users

= Accessibility statement =

Create a draft accessibility statement page with one click: an outline with your commitment, what you have done, known limitations and how to contact you. You fill in the details and publish it, and the toolbar links to it.

= What this plugin does not do =

A toolbar does not make a website accessible by itself, and no plugin can make a site "compliant" with a law on its own. Real accessibility comes from the content and the theme: alt text, headings, labels, contrast and keyboard support. Open Access Toolbar gives visitors useful reading adjustments and helps you find and fix the problems in your content. It does not claim more than that.

== Installation ==

1. Install and activate Open Access Toolbar from **Plugins > Add New**.
2. The toolbar button appears on your site straight away, at the bottom right.
3. Go to **Settings > Accessibility Toolbar** to choose the tools, position, size and color.
4. Go to **Tools > Accessibility Check** and click **Check my content**.

== Frequently Asked Questions ==

= Does it set cookies or need a consent banner? =

No cookies are set. Each visitor's choices are stored in their own browser's localStorage, are never sent to your server or anyone else, and are only read by the toolbar.

= Will it slow my site down? =

The script and stylesheet are small, have no dependencies and load with `defer`. The page is not changed until a visitor uses a tool.

= Does it make my site ADA, EAA or WCAG compliant? =

No plugin can do that by itself. Accessibility depends on your content and theme. The Accessibility Check helps you find common content problems so you can fix them, and the toolbar offers visitors extra reading adjustments.

= Can I choose which tools appear? =

Yes, in **Settings > Accessibility Toolbar**. You can also hide the toolbar on small screens.

= Which content does the check read? =

Published content of every public post type except media attachments. Blocks and shortcodes are rendered first, so images and embeds they produce are checked. Developers can change the list with the `oatb_check_post_types` filter.

= Can I hide the toolbar on some pages? =

Yes, with the `oatb_show_toolbar` filter. For example, return false on a landing page.

= Does it work with page builders and caching plugins? =

The toolbar runs entirely in the visitor's browser and does not depend on the page's HTML, so cached pages work. It is added at the end of the page and does not touch your builder's layout.

== Screenshots ==

1. The toolbar panel on the front end.
2. Settings: choose the tools, position, size and color.
3. Accessibility Check: problems found in your content, with the fix for each.

== Changelog ==

= 1.0.0 =
* First release: visitor toolbar with 12 tools, Accessibility Check for content and theme, skip link and focus outline fixes, accessibility statement draft.

== Upgrade Notice ==

= 1.0.0 =
First release.
