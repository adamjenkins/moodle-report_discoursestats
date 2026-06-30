# Discourse Stats — Moodle Report Plugin

A Moodle course report plugin that measures forum engagement and (optionally) pushes calculated grades to the gradebook. It combines efficient SQL-based post counting with per-thread engagement analysis, stale/self-reply detection, and optional Database module metrics.

## Requirements

- Moodle 4.0 or later
- PHP 8.1 or later
- `mod_forum` (core)

## Installation

1. Place the plugin directory at `<moodleroot>/report/discoursestats/`.
2. Log in as site administrator and visit **Site administration → Notifications** to run the database upgrade.
3. The report appears under **Course administration → Reports → Discourse Stats**.

## Features

### Report metrics (per enrolled user)

| Column | Description |
|---|---|
| Posts | Original discussion posts started (parent = 0) |
| Replies | Replies to existing posts (parent > 0) |
| Direct replies to original post | Replies whose parent is the seed/first post of a discussion |
| Self-replies | Replies to the user's own earlier post |
| Stale replies | Replies posted more than N days after the parent post |
| Engagement 1–4+ | Count of replies at engagement levels 1, 2, 3, and 4+ |
| Average / Maximum engagement | Computed from the selected engagement method |
| Word count | Total words across all posts |
| Multimedia | Inline images, videos, audio, and links counted separately |
| Views | Forum discussion views (from Moodle event log) |
| Unique days active | Distinct calendar days on which the user posted |
| Unique days viewed | Distinct calendar days on which the user viewed discussions |
| Database entries | Entries added to selected Database module instances |
| Database comments | Comments on Database module entries |

### Engagement calculation methods

Three methods (selectable per report):

- **Person-to-Person** — engagement level rises each time a user replies to the same person in the same thread
- **Thread Total Count** — engagement level rises each time a user participates in the same thread
- **Thread Engagement** — engagement level rises when a user participates in a reply chain they already appear in

### Report types

| Type | Description |
|---|---|
| **Student report** (default) | One row per enrolled user with per-student metrics |
| **Group report** | One row per course group; metrics are summed across group members; also shows active/inactive member counts and number of distinct nationalities |
| **Country report** | One row per country (based on user profile field); metrics are summed across all users from that country |

Group and country reports are never used for grading.

### Filters

- **Forums** — select one or more forums, or leave empty for all course forums
- **Group** — restrict to a single group (teachers without `viewothergroups` are limited to their own groups)
- **Country** — filter by user country profile field
- **Date range** — start and end timestamps applied to post creation time
- **Stale reply threshold** — number of days after which a reply is counted as stale (1–28)
- **International engagement only** — when checked, only counts replies between users from different countries
- **Database module activity** — include entry and comment counts from selected Database instances
- **Hidden columns** (student reports only) — multi-select to suppress specific columns from the results table and CSV; defaults hide less-commonly-needed columns (group, country, institution, word count, multimedia breakdown, engagement levels 1–4, first/last post dates)

### Automated grading (editing teacher / manager only)

A separate "Add grading schedule" form lets teachers configure a grade item that is automatically pushed to the gradebook once the report's end date passes:

- **Grade item name** — appears in the Moodle gradebook
- **Grade formula** — mathematical expression using report field names (e.g. `min(replies * 2 + posts * 5, 100)`)
- **Feedback template** — plain-text template with `{fieldname}` placeholders (NULL numeric fields substitute `0`)
- **Maximum grade** — caps the calculated value
- **Grade category** — target gradebook category
- **Hidden** — hide the grade item from students

Existing grading schedules can be duplicated with the **Copy** link in the schedules table.

Supported formula functions: `min()`, `max()`, `round()`, `ceil()`, `floor()`, `abs()`.

Available field names: `posts`, `replies`, `views`, `wordcount`, `multimedia`, `images`, `videos`, `audios`, `links`, `engagement1`, `engagement2`, `engagement3`, `engagement4`, `averageengagement`, `maximumengagement`, `stalereply`, `selfreply`, `repliestoseed`, `dbentries`, `dbcomments`, `uniquedaysactive`, `uniquedaysviewed`.

## Capabilities

| Capability | Default roles | Description |
|---|---|---|
| `report/discoursestats:view` | Teacher, Editing teacher, Manager | View the report |
| `report/discoursestats:viewothergroups` | Manager | See data for users outside own groups |
| `report/discoursestats:getinstantreport` | Editing teacher, Manager | Run a report immediately (bypasses the scheduled queue) |
| `report/discoursestats:pushgrades` | Editing teacher, Manager | Configure automated grading schedules |

## Site-level settings

Under **Site administration → Plugins → Reports → Discourse Stats**:

- **Default engagement calculation method** — the method pre-selected in the report form
- **Execution schedule** — comma-separated 24-hour clock hours at which the scheduled task processes queued reports (e.g. `3,15` for 3 AM and 3 PM)

## Report scheduling

Reports are processed by a scheduled task (`\report_discoursestats\task\schedule_task`) that runs every 5 minutes:

- **Grading schedules whose end date has passed** are detected and processed immediately on the next task run, regardless of the configured execution hours.
- **Regular report schedules** (and grading schedules whose end date has not yet passed) are only processed at the configured execution hours.

Users with `getinstantreport` can bypass the queue and receive results immediately.

## Course reset

When a teacher resets a course and selects **Delete all posts** (the forum "All posts" option), all Discourse Stats schedules, results, aggregate results, and grading log entries for the course are automatically deleted. This is implemented via a `\core\event\course_reset_ended` event observer, since Moodle's reset form only calls `_reset_course_userdata` hooks for activity modules (`mod_` type), not for report plugins.

Resetting a course without deleting forum posts leaves the existing report data intact.

## Uninstall

When the plugin is uninstalled via **Site administration → Plugins → Plugins overview**, the uninstall hook (`db/uninstall.php`) removes all manual grade items that this plugin created in course gradebooks. Grade items are identified by the `discoursestats_<scheduleid>` idnumber prefix. The plugin tables themselves are dropped by Moodle automatically.

## Backup and restore

Report data is derived entirely from forum posts, which are themselves included in the standard Moodle course backup. Backing up the Discourse Stats report data separately is therefore not necessary — running a fresh report after restore will produce equivalent results from the restored forum data. No backup or restore implementation is provided.

## License

GNU GPL v3 or later — see <https://www.gnu.org/licenses/gpl-3.0.html>.

Copyright 2026 Adam Jenkins <adam@wisecat.net>
