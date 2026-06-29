# Changelog

All notable changes to `report_discoursestats` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [1.2.3] — 2026-06-30

### Fixed

- **CI matrix corrected against Moodle environment requirements** — the previous matrix tested Moodle 5.0 with PHP 8.1 (not supported; minimum is 8.2) and Moodle 5.2 with PHP 8.2 (not supported; minimum is 8.3), and omitted PHP 8.4 from the Moodle 5.0 column (which is supported). Matrix is now: Moodle 5.0 → PHP 8.2–8.4; Moodle 5.1 → PHP 8.2–8.4; Moodle 5.2 → PHP 8.3–8.4.
- **`results.json` example context missing `showreportlink`** — the companion context file for `results.mustache` did not include the `showreportlink` boolean added in v1.2.0, so the "Complete Report" column was absent from mustache lint renders. Field added (`true`).
- **Language strings re-sorted alphabetically** — `inactivemembers` and `nationalities` were placed near the top of the file (after `activemembers`) instead of their correct alphabetical positions; `reactionsreceived` appeared after `reporttype_*` entries; `uniqueview` appeared after `unknowncountry`; and the `privacy:metadata:discoursestats_grading_log` block appeared after `discoursestats_results` instead of before it. All 125 strings are now sorted.

---

## [1.2.2] — 2026-06-30

### Added

- **Course reset support** — a new event observer (`\report_discoursestats\event\observer`) subscribes to `\core\event\course_reset_ended`. When a teacher resets a course and selects "Delete all posts" (`reset_forum_all`), all Discourse Stats schedules, per-student results, aggregate results, and grading log entries for that course are automatically deleted. Moodle does not call `_reset_course_userdata` hooks for `report_` type plugins, so an event observer is the correct approach.

### Fixed

- **Privacy provider missing `discoursestats_grading_log` metadata** — `get_metadata()` declared the schedules and results tables but not the grading log, which stores each student's pushed grade and feedback. The table is now registered in `get_metadata()` with its three personal-data fields (`userid`, `rawgrade`, `feedback`), and the corresponding lang strings have been added.
- **Privacy provider missing `discoursestats_aggregate_results` deletions** — all three deletion functions (`delete_data_for_all_users_in_context`, `delete_data_for_user`, `delete_data_for_users`) omitted the `discoursestats_aggregate_results` table, leaving orphaned aggregate rows when a privacy erasure request was processed. All three functions now delete from this table.

---

## [1.2.1] — 2026-06-30

### Fixed

- **Orphaned aggregate results on schedule auto-purge** — when a new report was submitted, the auto-purge of older non-grading schedules deleted rows from `discoursestats_results` and `discoursestats_schedules` but not from `discoursestats_aggregate_results`, leaving orphaned group/country rows in the database. The purge now covers all three tables.
- **Raw sub-query DELETEs replaced with Moodle DML API** — the two `$DB->execute()` calls that used `DELETE … WHERE … IN (SELECT …)` sub-queries have been replaced with `$DB->get_fieldset_select()` + `$DB->delete_records_list()`. All database writes now go through the Moodle DML layer.
- **`count_records_sql()` / `get_record_sql()` replaced with typed API calls** — `count_records_sql()` with a `SELECT COUNT(id) …` string is replaced by `count_records_select()` for the database entry and comment counts; `get_record_sql()` used solely to extract a `COUNT()` result is replaced by `count_records_sql()`; and a plain-WHERE `get_records_sql()` for forum discussions is replaced by `get_records_select()`. Only queries that genuinely require JOINs retain `get_records_sql()`.
- **Corrected test assertion for operator precedence** — `test_evaluate_formula_parentheses` expected `{posts} + {replies} * 10` (posts=3, replies=2) to evaluate to `5.0`. The formula evaluator correctly applies `*` before `+`, giving `3 + 20 = 23`. Expected value updated to `23.0`.

---

## [1.2.0] — 2026-06-30

### Added

- **Column visibility control** — the report form now has a "Hidden columns" multi-select (autocomplete) that lets teachers choose which columns to suppress from the results table and CSV export. The following columns are hidden by default: Group, Country, Institution, Direct replies to original post, Word count, Multimedia, Images, Videos, Audios, Links, 1st–4th+ Engagement, First Post, Last Post.
- **Conditional DB columns** — the Database Entries and Database Comments columns are only included in student reports when "Include database module activity" was enabled for the schedule; otherwise they are omitted entirely.
- **Conditional reactions columns** — the Reactions Given and Reactions Received columns are only included when `local_reactforum` is installed and at least one of the covered forums has reactions enabled (`reactiontype != 'none'` in `local_reactforum_settings`).
- **Group report type** — a new "Group report" option aggregates per-student results into per-group rows, showing active members, inactive members, nationalities, and summed/averaged metrics for each group. Group reports are never used for grading.
- **Country report type** — a new "Country report" option aggregates per-student results by country profile field, showing active members, inactive members, and summed/averaged metrics per country. Country reports are never used for grading.
- Aggregate results (group and country) are stored in a new `discoursestats_aggregate_results` table so they can be re-viewed and downloaded after the initial run.
- New DB fields: `discoursestats_schedules.reporttype` (INT, default 1) and `discoursestats_schedules.hiddencolumns` (TEXT, JSON array).

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
