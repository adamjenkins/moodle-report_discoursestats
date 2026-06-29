# Changelog

All notable changes to `report_discoursestats` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [1.1.0] — 2026-06-30

### Added

- **Copy action for grading schedules** — each grading schedule row in the "Automatically generated grades" table now has a "Copy" link that pre-populates the grading form with all settings from the selected schedule, allowing quick duplication.
- **Overdue grading schedule processing** — grading schedules whose end date has already passed are now detected and processed immediately on the next scheduled task run, before the configured execution-hour guard is applied. This ensures grades are pushed within 5 minutes of the end date rather than waiting for the next configured execution hour.

### Fixed

- **Grading schedules purged on new report request** — running a new report for a user previously deleted all their older schedules and results, including grading schedules. The DELETE statements in `discoursestats_executeschedule()` now exclude grading schedules (`gradingname IS NULL OR gradingname = ''`).
- **Feedback template blank for NULL numeric fields** — fields such as `dbcomments` are NULL for users with no database activity. `discoursestats_apply_feedback_template()` now substitutes `'0'` (instead of `''`) for NULL values in formula fields, so expressions like `{posts}+{replies}+{dbentries}+{dbcomments}` render correctly.
- **Grades silently not pushed to gradebook** — `grade_update()` with `itemtype='report'` always returns `GRADE_UPDATE_FAILED` because `is_raw_used()` requires `is_external_item()` which only returns true for `itemtype='mod'`. `discoursestats_push_grades()` has been rewritten to use `itemtype='manual'` grade items identified by `idnumber='discoursestats_N'`, with grades written via `grade_item::update_final_grade()`, which bypasses all module-callback paths.
- **Scheduled task fired hourly instead of every 5 minutes** — `db/tasks.php` now sets `'minute' => '*/5'` so the task runs every 5 minutes, enabling faster report and grade processing.

---

## [1.0.0] — 2026-06-29

Initial release.

### Added

**Core report engine**
- Multi-forum selection: choose one or more forums per report, or leave empty for all course forums (stored as JSON in `discoursestats_schedules.forums`)
- Per-user counts: original posts, replies, stale replies, self-replies, direct replies to seed post, word count, multimedia (images, videos, audio, links), unique active days, view count, unique view days
- Three engagement calculation methods: Person-to-Person, Thread Total Count, Thread Engagement
- International engagement filter (count only cross-country replies)
- Group and country filters; teachers without `viewothergroups` are restricted to their own groups
- Date-range filtering applied at post creation time
- Configurable stale reply threshold (1–28 days)

**Database module metrics**
- Optional inclusion of Database module activity per report
- Counts entries added to selected `mod_data` instances and comments on those entries
- Checkbox + autocomplete selector: unchecked disables DB metrics; checked with no selection counts all course databases

**Engagement levels (E1–E4+)**
- Aggregated from per-discussion engagement calculators
- Average and maximum engagement level reported per user

**Automated grading**
- Separate grading form visible only to users with `pushgrades` capability
- Grade formula evaluated with safe expression parser (whitelist: digits, operators, `min/max/round/ceil/floor/abs`)
- Feedback template with `{fieldname}` substitution
- Grade item pushed to Moodle gradebook via `grade_update()` once the report end date passes
- Ad-hoc task (`grading_task`) queued automatically; audit log written to `discoursestats_grading_log`
- Grade category and hidden-from-students options

**UI**
- Index page: report form, "My Requested Reports" table, "Automatically generated grades" table (capability-gated)
- View page: report details, full results table, CSV download, delete with confirmation
- Bootstrap 5 collapse for the "Add grading schedule" form
- AMD placeholder picker: clicking a field name in the picker inserts it at cursor position in the formula or feedback textarea

**Infrastructure**
- Scheduled task (`schedule_task`) runs hourly, processes queued schedules at configured hours
- Instant report mode for users with `getinstantreport` capability
- Privacy API: export and deletion of user data from schedules and results tables
- GitHub Actions CI matrix: Moodle 5.0–5.2, PHP 8.1–8.4 (compatible combinations), pgsql
- PHPUnit tests for formula evaluator, feedback template, forum ID resolution, and sort column validation
- Mustache templates with companion `.json` example context files for all templates
- All lang strings sorted alphabetically

**Bug fixes (relative to source plugins)**
- Fixed `repliestoseed` always returning 0: the parent-post lookup map was built from replies only (`WHERE parent > 0`), so replies whose parent was a seed post (parent = 0) were never counted. Map now covers all posts in the course.
- Fixed `selfreply` not detected when a user replies to their own seed post (same root cause as above).
- Post-info lookup scoped to the current course only (previously queried the entire `forum_posts` table globally).
- Fixed `engagementcalculator::getposts()` constructing `stdClass` DB rows instead of typed `engagedpost` instances, causing silent `TypeError` in PHP 8 that left reports in error state with zero results.
