# Accessibility review for Moodle

`local_accessibilityreview`

Maintained by Andrei Toma

## Description

Helps administrators and course creators find and manage accessibility issues in Moodle courses.
It runs automated checks on stored course content, says plainly where automation stops, and gives
reviewers checklists and a tracked workflow for the parts only a person can assess, such as PDFs,
videos and SCORM.

> **Important:** a scan with no issues is **not** WCAG certification or proof that a course is
> accessible. The checks cover only part of WCAG, and they run on stored HTML, not on rendered pages
> or the theme. Every report says this.

By default the plugin is **read-only** with respect to course content. An administrator can turn on
**fixing from the report** (off by default): it changes only the element being fixed, keeps the
previous content so every fix can be undone, and never replaces files, changes visibility or edits
quiz questions. The plugin does not use paid services or external AI services; the only outbound
requests are listed under [External requests](#external-requests).

## Features

- Background, incremental and resumable scans that run through cron, with one scan per course at a time.
- Optional rescan a few minutes after course content is edited, so new content is checked soon after
  it is added.
- 28 HTML checks covering images, image maps, links (empty, unclear, URL as text, opening a new window),
  tables (missing headers, complex tables, duplicated summaries), headings (skipped levels, empty,
  bold text used as a heading, long content without headings), lists typed by hand, empty legends,
  obsolete formatting markup, underlined non-links, long upper-case text, blinking or scrolling text,
  frames and embedded objects without titles, autoplaying media, videos without caption tracks, and
  inline colour contrast (only when both colours are reliably known).
- Content checked: course and section summaries, activity descriptions and Text and media areas, Page
  content, Book chapters, glossary entries, lesson pages and the text of quiz questions.
- Each finding is marked **Automated** (a definite issue) or **Needs review** (needs human judgement).
- **Fix in place** (optional): alternative text (or mark as decorative), frame and object titles (with
  the video title suggested when known), table header rows and columns, heading levels, new-window
  links and autoplay, one at a time with a preview, or **several at once** from one page. Every fix
  is logged, re-checked immediately and can be undone.
- **Progress over time**: counts saved after every scan and shown as a trend. No percentage score.
- **Guides**: step-by-step instructions for each check in the Moodle editor, and a guide for students
  on reader mode, zoom, read-aloud, contrast and captions.
- **Evidence report**: a printable record of scope, scans, findings by check (with WCAG 2.2 level and
  EN 301 549 clause), accepted exceptions with reasons, fixes and manual reviews. It states that it is
  not a conformance claim.
- Finding states: Open, In progress, Resolved, Accepted exception / not applicable. Each change records
  the reviewer, time, notes and history. Findings keep a stable identity across rescans and **reopen
  automatically when the source content changes**.
- Course report (Overview, Findings, Link availability, PDFs, Videos, SCORM) and site report (Courses,
  Findings), with filters for course, category, content type, check, severity and review status.
- CSV export with protection against spreadsheet formula injection.
- Link and Vimeo/YouTube availability checks with SSRF protection, kept as a separate category from
  accessibility.
- PDF, video and SCORM inventories with manual review checklists.
- Optional local PDF analysis with Poppler (`pdfinfo` and `pdftotext`).

## Requirements

- Moodle 4.5 (developed against 4.5.x; `$plugin->requires = 2024100700`).
- PHP 8.1 or later (target: 8.2), with the `dom` and `curl` extensions.
- MariaDB, MySQL or PostgreSQL.
- Cron running at least every few minutes, for ad-hoc tasks.
- Optional: Poppler utilities and PHP `proc_open` for PDF analysis.

## What do I need to install?

Only the plugin itself is required. Everything else is optional.

**Required**

1. Copy this folder into Moodle as `local/accessibilityreview`. The folder must be named exactly
   `accessibilityreview`.
2. Go to **Site administration → Notifications** and click **Upgrade**, or run
   `php admin/cli/upgrade.php`.
3. Make sure Moodle cron is running. Scans only progress through cron.

**Optional, only if you want these features**

| Feature | What to install or set up |
|---|---|
| Automatic PDF checks (text, tags, language) | **Poppler utilities** on the server, e.g. `sudo apt install poppler-utils`. Then in the plugin settings enter `/usr/bin/pdfinfo` and `/usr/bin/pdftotext` and tick "Enable PDF analysis". |
| Link and Vimeo checking | Nothing to install. Tick "Enable link checking" in the plugin settings. |
| Vimeo caption detection | Nothing to install. Paste a Vimeo API access token into the plugin settings. |
| Running the automated tests | PHPUnit for Moodle, or `moodle-plugin-ci`. This is for developers only. |

### Checking whether Poppler is already installed (AWS)

Run these commands on the server that runs Moodle's PHP and cron. That is usually the EC2 instance,
reached with SSH or **AWS Console → EC2 → Connect → Session Manager**. If Moodle runs in Docker
(ECS/Fargate), run them inside the Moodle container, for example with `docker exec -it <container> bash`.

```bash
# 1. Is Poppler installed? Each command should print a path such as /usr/bin/pdfinfo.
which pdfinfo pdftotext

# 2. Does it run? This prints a version, for example "pdfinfo version 22.02.0".
pdfinfo -v

# 3. Can PHP start external programs? Nothing printed means proc_open is allowed.
php -r 'echo ini_get("disable_functions"), PHP_EOL;' | grep -o proc_open

# 4. Can the web server or cron user run it? (Use your PHP user: www-data, apache or nginx.)
sudo -u www-data /usr/bin/pdfinfo -v
```

If `which` prints nothing, install Poppler:

```bash
# Ubuntu / Debian
sudo apt update && sudo apt install -y poppler-utils

# Amazon Linux 2023 / RHEL / Rocky
sudo dnf install -y poppler-utils

# Amazon Linux 2
sudo yum install -y poppler-utils
```

Notes:

- If Moodle runs on several EC2 instances (for example behind a load balancer, or in an Auto Scaling
  group), Poppler must be installed on every instance that runs cron. For Auto Scaling, add it to the
  AMI or the launch template's user data, otherwise new instances won't have it.
- For Docker/ECS, add `poppler-utils` to the Moodle image's Dockerfile, rather than installing it into
  a running container.
- You normally don't need to type any paths. The plugin finds `pdfinfo` and `pdftotext` in
  `/usr/bin`, `/usr/local/bin` or `/bin` automatically. This matters because many servers set
  `$CFG->preventexecpath = true` in `config.php`, which locks the path fields in the admin UI
  ("Setting executable and local paths disabled in config.php"). The settings page shows
  **PDF tools in use** with the paths that were found. To use a different location while the fields
  are locked, set it from the command line:
  `php admin/cli/cfg.php --component=local_accessibilityreview --name=pathpdfinfo --set=/custom/path/pdfinfo`
- Otherwise, use the exact paths printed by `which` in the plugin settings. If the paths are wrong or the
  executables are missing, the plugin keeps working; PDFs just show "Not analysed automatically".

## Installation

1. Copy this folder to `<moodleroot>/local/accessibilityreview` (the folder name must be exactly
   `accessibilityreview`). Do not deploy `plugin.md` or the `tests/` and `.github/` folders to production
   unless you want them there.
2. Run the upgrade: either **Site administration → Notifications**, or the CLI:
   ```bash
   php admin/cli/upgrade.php
   ```
3. Purge caches:
   ```bash
   php admin/cli/purge_caches.php
   ```
4. Configure the plugin at **Site administration → Plugins → Local plugins → Accessibility review**.

Uninstall: `php admin/cli/uninstall_plugins.php --plugins=local_accessibilityreview --run`

## Configuration

| Setting | Default | Notes |
|---|---|---|
| Items per batch | 50 | Progress is saved after each batch. |
| Scan time per task run | 120 s | After this time a scan saves its position and continues in a new ad-hoc task. |
| Scheduled scans | Off | When on, queues visible courses whose last completed scan is older than the rescan interval. |
| Rescan interval / courses per run | 7 days / 20 | Used by scheduled scans. |
| Bulk queue limit | 500 | Maximum courses queued by one bulk request from the site report. |
| Rescan courses after edits | On | Queues a scan after course content is created or edited. |
| Delay before rescanning after an edit | 10 min | Edits within this time share one scan. |
| Retention | 365 days | Old scan records, findings or items absent for this long (with their history), progress snapshots and fix records (including the content kept for undo) are deleted. |
| Unclear link text / generic alt text | built-in lists | One entry per line. |
| Allow fixing content from the report | **Off** | Turn on to let users with the `fix` capability change course content from the report. |
| Enable link checking | **Off** | Turn on to make outbound HTTP requests. |
| Links per run / time limit / timeout | 100 / 300 s / 10 s | |
| Delay per host | 1000 ms | Concurrency control: one runner, spaced requests per host. |
| Max redirects | 5 | Every hop is validated again. |
| Recheck interval | 7 days | Results are cached per unique URL across all courses. |
| Checks before confirming unavailable | 2 | 404/410 and DNS failures must repeat before a link is reported as **Confirmed unavailable**. |
| Vimeo API access token | empty | Optional. See below. |
| Enable PDF analysis | **Off** | Requires Poppler. See below. |
| pdfinfo / pdftotext paths | `/usr/bin/…` | Full paths. When empty or locked by `$CFG->preventexecpath`, `/usr/bin`, `/usr/local/bin` and `/bin` are tried. |
| Timeout per tool / max size / pages / PDFs per run | 30 s / 50 MB / 5 / 20 | Resource limits. |

### Cron and tasks

All processing happens in tasks. **Nothing is scanned during a web request.**

| Task | Type | Default schedule | Purpose |
|---|---|---|---|
| `\local_accessibilityreview\task\scan_course` | ad-hoc | when queued | Processes one course scan for a bounded time, then re-queues itself. |
| `\local_accessibilityreview\task\scheduled_scan` | scheduled | 01:15 daily | Queues scheduled scans (only if enabled). |
| `\local_accessibilityreview\task\check_links` | scheduled | every 30 min | Checks links and videos that are due (only if enabled). |
| `\local_accessibilityreview\task\analyse_pdfs` | scheduled | hourly at :45 | Runs PDF analysis (only if enabled). |
| `\local_accessibilityreview\task\cleanup` | scheduled | 03:30 daily | Resumes stalled scans, applies retention, removes data for deleted courses. |

You can change schedules at **Site administration → Server → Tasks → Scheduled tasks**.
To run tasks manually:

```bash
php admin/cli/adhoc_task.php --execute
php admin/cli/scheduled_task.php --execute='\local_accessibilityreview\task\check_links'
php local/accessibilityreview/cli/scan.php --courseid=123 --links   # synchronous scan, useful on staging
```

### Optional dependency: PDF analysis

1. Install Poppler on the Moodle server, for example `apt install poppler-utils` (Debian/Ubuntu)
   or `dnf install poppler-utils` (RHEL).
2. Make sure PHP's `proc_open` is not listed in `disable_functions` for the CLI or cron PHP.
3. Set the paths to `pdfinfo` and `pdftotext`, then enable PDF analysis.

How it works: each PDF is read through the Moodle File API, so ObjectFS/S3 storage works. It is
copied to a private temporary file, analysed by local executables with no shell and a timeout, and
then the temporary file is deleted. Files above the size limit are skipped. **Documents never leave
the server.**

For extra resource limits, run cron under `nice`/`ionice`, or use systemd `CPUQuota`/`MemoryMax` for
the cron service. The plugin itself limits wall-clock time, file size, pages and the number of files
per run.

If Poppler is not available, everything else still works: the PDF tab shows the inventory and the
manual checklist, and each item reads "Not analysed automatically".

### Optional: Vimeo API token

Without a token, availability is checked through Vimeo's public oEmbed endpoint, with this Moodle
site sent as the Referer. Caption status is then always **Unknown**.

With a personal access token (scopes `public` and `private`) for the account that owns the videos:

- caption and subtitle tracks are read from the API, giving a present/none status with evidence;
- a video is reported as **Confirmed unavailable** only when both oEmbed and the API report it missing.

The token is stored as a password-type setting and is never shown in reports or logs.

## Feature and limitation matrix

| Area | Automated (reliable) | Flagged for human review | Not assessed / limitation |
|---|---|---|---|
| Images | Missing `alt` attribute (error); image map `area` without alt (error) | `alt=""` (confirm decorative); alt text that looks like a file name, a generic word or is very long | Whether alt text is accurate or adequate |
| Links | Link with no accessible name (error) | "Click here", "read more" and similar; raw URL as link text; `target="_blank"` without a warning in the text (advisory, WCAG AAA) | Whether context makes the link clear |
| Tables | Summary attribute repeating the caption | Multi-row, multi-column table with no `th`, `scope` or header roles (could be a layout table); data table with merged cells and no `headers` associations | Whether header associations are correct, captions |
| Headings and structure | Skipped levels within one content area (good practice, no WCAG SC claimed); empty headings; empty `legend`; obsolete `font`/`center`/`big`/`tt`/`strike` markup (good practice) | Paragraph containing only short bold text (probably a visual heading); two or more paragraphs starting with bullets or numbers (list typed by hand); over 3,000 characters with no headings | Page-level heading outline (depends on theme and page) |
| Text | `blink`, `marquee` or `text-decoration: blink` (error) | Underlined text that is not a link; 30+ letters of upper-case text (advisory) | Font size, line spacing, reading level |
| Iframes and objects | Missing title on `iframe`, `object` or `embed` (error) | Generic title ("video", "iframe") | Accessibility of the embedded content |
| HTML5 media | `autoplay` on audio, or on video that is not muted | `video` with no `track kind="captions"` or `"subtitles"` (captions may be burned in) | Caption accuracy, transcripts, audio description |
| Contrast | Ratio below 3:1, or below 4.5:1 for text known to be normal size, when both colours are inline in the HTML | 3:1–4.5:1 when the text size is unknown | Theme CSS, classes, CSS variables, gradients, images, transparency: **never inferred** |
| Link availability | HTTP status, DNS, redirects (separate from accessibility) | 401/403 shown as "restricted", not broken | Content behind login; pages that block automated checks |
| Vimeo | oEmbed availability; captions only with an API token | Caption accuracy, transcript, audio description | Without a token, captions are always "unknown" |
| YouTube | oEmbed availability | Captions, transcript, audio description | Caption detection (no API used) |
| PDFs | Inventory; optionally: extractable text (first N pages), tags present, encryption, parse errors, `/Lang` found | Reading order, headings, image descriptions, tables, language, usability (checklist) | Tag correctness; a missing `/Lang` is reported as "not determined" |
| SCORM | Inventory (name, version, package size) | Keyboard, focus, screen reader, text alternatives, captions, contrast (checklist) | Package contents are never opened, parsed or run |
| Theme / rendered page | – | – | Not assessed. Scanning stored HTML does not test the Adaptable theme |
| Quiz questions | Question text of questions placed directly in this course's quizzes (the version each slot uses) | As for other HTML | Random questions, answers and feedback. Findings are listed only for users with `moodle/question:viewall`. Never fixed from the report (questions are versioned) |
| Glossary and lesson | Approved glossary entry definitions; lesson page contents | As for other HTML | Glossary entries awaiting approval |
| Submissions, forums | – | – | Excluded (student content) |

WCAG references are given only where the mapping is accurate: 1.1.1, 1.2.2, 1.3.1, 1.4.2, 1.4.3, 2.2.2,
2.4.4, 2.4.6, 3.2.5 (AAA, advisory) and 4.1.2. Each reference shows the WCAG 2.2 level, a link to the
W3C "Understanding" page and the matching EN 301 549 clause (clause 9 numbers follow WCAG: 1.1.1 is
9.1.1.1). Heading-level jumps and obsolete markup are labelled as good practice.

## Capabilities

| Capability | Context | Default roles | Purpose |
|---|---|---|---|
| `local/accessibilityreview:view` | course | editingteacher, teacher, manager | View the course report. At system level, the site report. |
| `local/accessibilityreview:scan` | course | editingteacher, manager | Queue scans |
| `local/accessibilityreview:review` | course | editingteacher, manager | Change finding states, add notes, complete checklists |
| `local/accessibilityreview:fix` | course | editingteacher, manager | Apply and undo fixes from the report (only when **Allow fixing content from the report** is on). The user also needs the normal editing capability for that content, for example `moodle/course:manageactivities` or `mod/book:edit`. Marked RISK_XSS because it changes course content. |
| `local/accessibilityreview:manage` | system | manager | Bulk queue scans from the site report |

Students get nothing by default. Every page, export and action checks login and capability on the
server, in the course context of the record being accessed. All changes are POST requests checked
with a session key; review forms use Moodle forms, which check the session key automatically.

## Administrator guide

1. Install, then review the settings. On staging, enable link checking and, if Poppler is installed,
   PDF analysis.
2. Make sure cron runs. Ad-hoc tasks are needed for scans to progress.
3. Open **Site administration → Reports → Accessibility review**.
   - The **Courses** tab lists every course with its scan status, last completed scan, open findings
     by severity, confirmed unavailable links and inventory items needing review. Filter by category.
   - With the `manage` capability, **Queue scans for all courses in this category** queues background
     scans. Courses that already have a scan queued or running are skipped.
   - The **Findings** tab works across courses, with filters and CSV export.
4. Scan status values:
   - **Not scanned**
   - **Scan in progress**
   - **Scan incomplete**: the scan failed, or some items could not be scanned. Messages are shown.
   - **Issues to review**
   - **No automated issues detected**
   - **Manually reviewed**: nothing open, and every PDF, video and SCORM item has a completed review.
   There is no compliance percentage.
5. If a scan stalls, the cleanup task resumes it after 2 hours and marks it failed after 1 day.
6. **Rescan courses after edits** (on by default) queues a scan a few minutes after content is created
   or edited. Edits within **Delay before rescanning** share one scan. Turn it off if you prefer only
   nightly or manual scans.
7. **Allow fixing content from the report** (off by default) lets editing teachers and managers fix
   issues from the report. Before turning it on, test on staging: apply a fix, check the page, undo it.
   Every fix is recorded in the course's **Fixes** tab and in Moodle's logs
   (event "Accessibility fix applied or undone").

## Course reviewer guide

The same guidance, written for teachers, is built into the plugin: **How to use the accessibility review**
(Overview tab, or `/local/accessibilityreview/guide.php?page=manual`). Any logged-in user can open it.

1. In your course, open **More → Accessibility review**. In Adaptable this is in the course
   administration menu.
2. On **Overview**, select **Queue scan**. The scan runs in the background; refresh to see progress.
3. On **Findings**, filter by category, check, severity, content type, type (Automated or Needs
   review) or status. Each row has **Details**, plus **View** and **Edit** links to the source when
   you have permission.
4. Fix the problem in one of two ways:
   - **Fix this** (when fixing is enabled, for alt text, titles, table headers, heading levels,
     new-window links and autoplay): enter the correction, select **Preview** to see the change, then
     **Apply fix**. The item is re-checked straight away. **Fix several at once** (on the Overview and
     Fixes tabs) lists every image without alt text, every frame without a title and so on, with one
     field each. Any fix can be undone from the **Fixes** tab while the content has not been edited since.
   - **Edit** opens Moodle's own editor. **How to fix** opens a step-by-step guide. Rescan afterwards
     (or wait for the automatic rescan).
   Fixed issues are marked "No longer detected" and resolved automatically.
5. For items that need judgement, open **Details** and set a status:
   - **In progress**: you are working on it.
   - **Resolved**: you have fixed it.
   - **Accepted exception / not applicable**: requires a note explaining why. For example, the image
     really is decorative, or the link text is clear in context.
   If the content later changes, resolved items and exceptions are reopened automatically for another look.
6. On **PDFs**, **Videos** and **SCORM**, open **Review** for each item and complete the checklist,
   with notes. Marking an item "Reviewed: no issues found" requires every question to be answered.
   If a file is replaced, its review is reopened.
7. **Link availability** shows link status separately. "Restricted" (login or private) and "Unknown"
   are not the same as broken. Only "Confirmed unavailable" has been verified by repeated checks.
   The Videos tab shows the reason a video could not be verified, and when it was last checked.
8. **Progress over time** on the Overview tab shows open errors, warnings and notices after each scan.
9. **Evidence report** (Overview tab) is a printable summary for quality reviews and accessibility
   statements: scope, scans, findings by check with WCAG and EN 301 549 references, accepted
   exceptions with their reasons, fixes and manual reviews. It is a record of the process, not a
   conformance claim.
10. **Accessibility guides** explain every check, with steps for the Moodle editor. The student guide
    (reader mode, zoom, read-aloud, contrast, captions) can be linked from a course.

## External requests

Everything runs on your Moodle server. No course content or student data is sent anywhere. The plugin
makes these outbound requests only:

- **Licence check** (always on): when a licence key is activated and about once a week afterwards
  (scheduled task), the site host name, the licence key and the product name are sent to
  `https://tandreig.com/api/license/activate` and `…/renew`. Nothing else is included.
- **Link and video availability checker** (optional, off by default — "Enable link checking"): the
  server requests the URLs found in course content to see whether they still work, and asks the
  YouTube and Vimeo oEmbed endpoints whether a video exists; the video id is sent to them. Requests
  carry the plugin's User-Agent and, to Vimeo, the site URL as Referer.
- **Vimeo API token** (optional): when a token is configured, caption detection calls
  `api.vimeo.com` with that token.

Local PDF analysis with Poppler runs on the server and sends nothing.

## Backup and restore

Manual review work is included in course backups and restored with the course: the state, reviewer,
time, notes and history of findings that were reviewed (In progress, Resolved, Accepted exception or
with notes) and the checklists, status and notes of reviewed PDF, video and SCORM items. Open findings,
scan runs, progress snapshots, link caches and applied fixes are not backed up: the first scan of the
restored course regenerates the findings and matches the restored reviews to them (by rebuilt identity,
or by check and excerpt for findings last reviewed before 2.3.0), and fixes belong to the original
content records. Course duplication and import use the same mechanism. Reviewer ids are mapped only
when user data is included in the backup; otherwise they are cleared and the history keeps the notes.

## Security notes

- **SSRF**: only http and https are allowed. URLs with credentials, non-standard ports, numeric or
  hex IP shorthands, `localhost`/`.internal`/`.local` and similar names, and any host that resolves
  (A or AAAA) to a loopback, private, link-local, CGNAT, reserved, multicast or cloud metadata address
  are blocked. The validated addresses are pinned with `CURLOPT_RESOLVE`, which prevents DNS rebinding.
  Redirects are followed manually and every target is validated again. Moodle's own
  `curlsecurityblockedhosts` and `curlsecurityallowedport` settings are also applied.
  - If Moodle is configured to use an outbound **proxy**, the proxy resolves host names, so pinning
    does not apply. Make sure the proxy itself blocks internal destinations.
- Link checks send no cookies, Moodle session data or credentials. The only extra headers are a
  User-Agent, the site URL as Referer to Vimeo, and the optional Vimeo API token to api.vimeo.com.
- Vimeo privacy hashes are kept for verification but redacted from excerpts, error messages and task
  logs. Task logs never print URLs.
- Output is escaped, SQL uses placeholders, and CSV cells starting with `= + - @ |`, tab or CR are
  prefixed with an apostrophe.

## Architecture and extending

- `classes/local/content/*_provider.php` are content providers. Each one pages through its records
  with a monotonic key, which is what makes scans resumable.
- Other plugins can add providers through the hook `\local_accessibilityreview\hook\collect_providers`.
  Register a callback in your plugin's `db/hooks.php` and call `$hook->add_provider(new your_provider())`.
- `classes/local/checks/` contains the HTML checks (`html_checker`) and the check definitions with
  WCAG mapping (`check_registry`).
- `classes/local/finding_manager.php` handles the lifecycle: identity is SHA-1 of course, provider,
  item, field, check and a position-independent element signature.
- `classes/local/links/` contains `url_guard` (SSRF), `security_helper` (Moodle curl integration and
  DNS pinning), `http_fetcher`, `link_classifier` and `vimeo`.

### Quiz questions

`question_provider` reads the question text of questions placed directly in this course's quizzes,
through question references: the version each slot uses, or the latest non-draft version for "always
latest". Random questions, answers, feedback and student attempts are never read. Findings for quiz
questions are listed (on screen and in CSV exports) only for users who also have
`moodle/question:viewall`, because excerpts contain question text. Questions are never changed by the
plugin, because they are versioned and may already have been attempted.

### How fixing works

- `classes/local/fix/fix_engine.php` computes the change and `classes/local/fix/html_editor.php` makes
  it. Edits are made to the stored text, touching only the tag being fixed (and its matching end tag
  for headings, or the header row and column for tables). The rest of the field, including filter
  syntax such as `{mlang}`, entities and whitespace, is left byte-for-byte unchanged.
- Before editing, the issue is detected again in the current content and matched by its stable
  identity, so a fix never lands on the wrong element even if content was added since the scan. The
  start tag found in the text must have exactly the attributes the parser reported, or nothing is changed.
- `classes/local/fixer.php` checks the setting and capabilities, reads and writes each content area
  (course and section summaries, activity descriptions, Page, Book, glossary and lesson content), keeps
  the previous content in `local_accessibilityreview_fix`, re-checks the item immediately, clears caches
  and fires `\local_accessibilityreview\event\content_fixed`. Writes run in a transaction that re-reads the
  field first, so a fix cannot overwrite an edit saved a moment earlier.
- Undo restores the previous content only if the field still contains exactly what the fix saved.
- Only stored HTML (HTML or Moodle auto-format) can be fixed. Plain text and Markdown fields are refused.

## Testing

PHPUnit tests are in `tests/`:

- `html_checker_test`: every check, contrast maths and undeterminable cases, stable signatures, excerpt safety.
- `url_security_test`: SSRF blocking (schemes, credentials, ports, IP shorthands, private, metadata,
  IPv6, mapped addresses, DNS to private, mixed DNS answers), DNS pinning, site blocklist, redirect
  resolution, HTTP classification, Vimeo parsing and redaction.
- `finding_lifecycle_test`: an end-to-end scan; decisions kept on unchanged rescans; reopening on
  content change; system resolve when no longer detected; reappearance; source removal; no duplicate
  scans; content left unchanged; resumable scans; inventory review reopening.
- `permissions_test`: default role capabilities, student refused server-side, course-scoped filters,
  edit links gated by capability, CSV formula injection.
- `privacy/provider_test`: contexts, export, anonymisation.
- `fix_engine_test`: each fix type, byte-for-byte preservation of the rest of the content, comments
  and scripts skipped, quoted `>` inside attributes, stale positions, nested tables refused, validation.
- `fixer_test`: fix and undo on a Page (content, revision, finding resolved and reopened), undo refused
  after another edit, permissions and the site setting, content changed since the scan, progress
  snapshots, glossary and quiz question scanning, quiz questions never fixable.

To run them:

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_accessibilityreview_testsuite
# or
moodle-plugin-ci phpunit
```

Code checks:

```bash
moodle-plugin-ci phplint ./local/accessibilityreview
moodle-plugin-ci phpcs ./local/accessibilityreview --max-warnings 0
moodle-plugin-ci phpdoc ./local/accessibilityreview --max-warnings 0
moodle-plugin-ci validate ./local/accessibilityreview
```

There are no Mustache templates or AMD modules, so the Mustache and Grunt checks can be skipped.

### Verification status of this release

Verified while building:

- All PHP files pass `php -l`, and `db/install.xml` parses.
- Moodle APIs used were checked against Moodle 4.5.10+ source: the curl security helper and
  `CURLOPT_RESOLVE` flow, the hook manager and attributes, ad-hoc task queueing, the lock factory,
  admin setting classes, `csv_export_writer`, formslib session-key handling, and Book/section edit URLs.
- Every language string referenced in code exists, and the language file is sorted.
- The pure-logic tests (HTML checks including the 15 added in 2.0, contrast, SSRF guard, classifier,
  Vimeo parsing, CSV safety, pdfinfo parsing, and the fix engine) were executed outside Moodle with a
  stub harness: 49 cases, 221 assertions, all passing.
- Version 1.0.1 was installed on staging and scanned real courses.

**Not yet verified (still needs to be checked on staging):**

- The 2.0.0 upgrade step (two new tables) on Moodle **4.5.12** with MariaDB.
- Running the full PHPUnit suite inside Moodle, including the database tests (`fixer_test` is new).
- Fixing content on staging: every fix type in each content area, the page afterwards in Adaptable,
  undo, and the course page after fixing a Text and media area (course cache rebuild).
- The rescan-after-edit observer, the glossary, lesson and quiz question providers on real courses,
  and the evidence report printed from a browser.
- `moodle-plugin-ci` phpcs, phpdoc and validate results.
- Rendering, navigation placement and keyboard and screen reader use under the **Adaptable** theme.
- Real outbound link checks through the site's network and any proxy, and Vimeo oEmbed and API
  behaviour for private, unlisted and domain-restricted videos.
- PDF analysis with Poppler on the staging OS, and file access through ObjectFS/S3.

## Troubleshooting

- **The scan stays "queued"**: cron is not running ad-hoc tasks. Run `php admin/cli/adhoc_task.php --execute`.
- **The scan is "incomplete"**: open the Overview tab to see which items failed and why.
- **All links show "Not checked yet"**: link checking is off, or the `check_links` task has not run.
- **Every link is "Unknown – address not permitted"**: the URLs resolve to private addresses, or the
  site's cURL blocklist blocks them.
- **PDFs show "Not analysed: PDF analysis is turned off"**: tick **Enable PDF analysis**.
- **PDFs show "Not analysed: pdfinfo and pdftotext were not found"**: install Poppler, or `proc_open` is
  disabled for PHP.
- **PDFs show "Waiting for analysis"**: the hourly task has not run yet. To run it now:
  `php admin/cli/scheduled_task.php --execute='\local_accessibilityreview\task\analyse_pdfs'`
- **Videos show "Unknown / unable to verify"**: the reason is shown next to the status. "Vimeo reports
  not found" usually means a private video; add a Vimeo API token for the account that owns the
  videos. "Could not connect" or "Secure connection problem" means the server cannot reach Vimeo:
  check outbound HTTPS and any proxy.
- **"Fix this" is not shown**: fixing is off in the settings, the finding is not open, the content
  area cannot be fixed (quiz questions), or you lack the `fix` capability or the editing capability
  for that content.

## Support

Developed and maintained by Andrei Toma.

## Commercial licence

Accessibility Review is a **commercial plugin sold through Moodle Marketplace**. The code is GPL v3 or
later; using it on a site requires a licence key for that site.

- **Purchase:** one licence per Moodle site (domain), **paid once** (perpetual licence), bought on the
  plugin's Moodle Marketplace listing. The licence key is issued after the purchase and is bound to the
  site's domain. The licence includes updates and e-mail support for the period shown on the licence
  page (Site administration → Plugins → Local plugins → Accessibility review → Manage licence); extending
  support afterwards is optional and yearly.
- **Trial:** a 14-day trial key can be requested from the Marketplace listing before purchasing.
- **Without a valid key** the plugin pages show a licence notice and scans do not run. Existing findings
  and reports are kept; teachers are asked to contact their site administrator.
- **What is sent to the licence service:** the site host name, the licence key and the product name, to
  `https://tandreig.com/api/license/activate` when the key is activated and to `…/renew` once a week by
  the scheduled task "Refresh the site licence". No course content, findings or user data are sent;
  all review data stays on your Moodle site. A network error never disables an activated licence.

## License

GNU GPL v3 or later. See `LICENSE`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
