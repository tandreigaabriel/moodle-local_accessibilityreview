# Changelog

## Unreleased

- README: "Commercial licence" section (sold through Moodle Marketplace, one perpetual licence per
  site, 14-day trial from the listing, what the licence check sends) replaces the two short licence
  headings; stray CI commands removed from the end of the file.
- CI also runs the full check (including PHPUnit) on PostgreSQL 14, as required by the Moodle Marketplace.

## v2.2.0 (2026-11-19) – 2026111900

- Commercial licence per site, purchased through Moodle Marketplace; 14-day trial keys.
- Licence page for site administrators (Site administration → Plugins → Local plugins →
  Accessibility review → Manage licence), with the plan and the trial or support end date.
- Without a valid licence, plugin pages show a licence notice and scans do not run. Existing
  data is kept. Teachers are asked to contact their site administrator.
- The licence is re-checked weekly. Network errors never disable an activated licence.

## 2.1.3 (2026-11-18) – 2026111800

- Fixes work again: stored text formats are compared as numbers, so "Fix" no longer refuses HTML
  content as "not stored as HTML". Markdown content is converted before checking and plain text is
  no longer checked as HTML.
- Undo and bulk fixes only accept POST requests with a valid sesskey.
- The copies of content kept for undo are deleted the day after that content is deleted (for example
  a glossary entry removed by a privacy request), not only after the retention period. The privacy
  metadata now lists these fields.
- Moodle coding style (PHPCS) clean; new PHPUnit tests for every text format and the cleanup task.
- Removed the one-time data migration from the plugin's earlier name.

## 2.1.2 (2026-11-11) – 2026111104

- PDFs waiting for analysis now say when the "Analyse PDFs" task last ran and runs next, or that it has
  not run yet, is disabled or is failing, so a stuck queue can be told apart from a normal wait.
- An error while analysing one PDF no longer stops the task: the file is marked as failed and the
  others are analysed.
- Removed empty-string defaults from required text columns in `db/install.xml`, which caused XMLDB
  notices during installation. The database is unchanged (Moodle already created these columns without
  a default), and new links now always set their reason column.

## 2.1.1 (2026-11-11) – 2026111103

- User manual inside Moodle: **How to use the accessibility review**, on the Overview tab and in the
  accessibility guides (`guide.php?page=manual`). It covers scanning, reading and working through
  findings, fixing from the report, PDF, video and SCORM reviews, link status, progress, the evidence
  report, administrator settings and what the review cannot tell you.

## 2.1.0 (2026-11-11) – 2026111102

- Plugin renamed to `local_accessibilityreview` (folder `local/accessibilityreview`, capabilities
  `local/accessibilityreview:*`, tables `local_accessibilityreview_*`).
- Added the missing description string for the `fix` capability.

## 2.0.1 (2026-11-11) – 2026111101

- Vimeo videos limited to specific websites are recognised through the player page (see 2.0.0).
  Vimeo links that were "Unknown" are queued for recheck by this upgrade step, so it also runs on
  sites that had already installed 2.0.0.

## 2.0.0 (2026-11-11) – 2026111100

New database tables: run the Moodle upgrade.

- 15 new checks (28 in total): links opening a new window without warning, image map areas without
  alt text, complex tables, table summaries repeating the caption, long content without headings,
  bold text used as a heading, lists typed by hand, empty legends, obsolete formatting markup,
  underlined non-links, long upper-case text, blinking or scrolling text, objects and embeds without
  titles, autoplaying media, videos without caption tracks.
- New content areas: glossary entries, lesson pages and quiz question text. Quiz question findings
  are listed only for users who can view questions.
- Fix in place (off by default, new `local/accessibilityreview:fix` capability): alt text or decorative,
  frame and object titles (video title suggested), table headers, heading levels, new-window links,
  autoplay. Preview, bulk fixing from one page, immediate re-check, undo, a Fixes tab and a log event.
- Progress over time: counts saved after every scan and shown as a trend (no percentage score).
- Rescan a course a few minutes after its content is edited (on by default).
- Accessibility guides: step-by-step instructions for every check, and a guide for students.
- Evidence report: printable record of scope, scans, findings with WCAG 2.2 level and EN 301 549
  clause, accepted exceptions, fixes and manual reviews. It is not a conformance claim.
- WCAG references now show the level, link to the W3C explanation and give the EN 301 549 clause.
- The Videos tab shows why a video could not be verified and when it was last checked.
- Vimeo videos limited to specific websites are now recognised: when Vimeo's oEmbed lookup fails
  (it can return 403, 404 or 500 for these), the player is loaded as this site would load it. They are
  reported as available or "Restricted to specific websites", not as server errors. Vimeo links that
  were "Unknown" are rechecked after the upgrade (2.0.1).
- The PDF tab says whether analysis is turned off, the tools were not found, or the file is waiting
  for the hourly task, instead of "Not analysed automatically" for all three.

## 1.0.1 (2026-11-10) – 2026111001

- Find Poppler automatically in `/usr/bin`, `/usr/local/bin` or `/bin` when no path is set. This
  covers sites where `$CFG->preventexecpath` locks the executable path fields.
- Default paths are now `/usr/bin/pdfinfo` and `/usr/bin/pdftotext`, and the settings page shows
  "PDF tools in use".
- Fewer database queries during scans: activity descriptions load with one query per module
  type per batch, and each item's links load and save in bulk.

## 1.0.0 (2026-11-10) – 2026111000

First release (beta). Read-only with respect to course content.

- Background, resumable course scans of course summaries, section summaries, activity
  descriptions and Text and media areas, Page content and Book chapters.
- 13 HTML checks across images, links, tables, headings, iframes and inline colour contrast.
  Each check is marked as either automated or needing human review.
- Finding workflow (Open, In progress, Resolved, Accepted exception) with notes, reviewer, time and
  full history. Findings keep stable identities across rescans and reopen when source content changes.
- Course and site reports with filters, scan progress and status, and CSV export protected against
  formula injection.
- Link and Vimeo/YouTube availability checks with SSRF protection, DNS pinning, re-validation of
  every redirect, caching, per-host delay and conservative classification. Disabled by default.
- PDF, video and SCORM inventories with manual review checklists.
- Optional local PDF analysis with Poppler `pdfinfo`/`pdftotext`. Disabled by default.
- Privacy API provider, CLI scan script, PHPUnit tests and GitHub Actions CI.
