<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library functions for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use report_discoursestats\engagement;
use report_discoursestats\engagementresult;

/** @var int Schedule status: queued and waiting to run. */
const DISCOURSESTATS_STATUS_SCHEDULED = 0;
/** @var int Schedule status: currently being processed. */
const DISCOURSESTATS_STATUS_EXECUTING = 1;
/** @var int Schedule status: processing failed. */
const DISCOURSESTATS_STATUS_ERROR     = 2;
/** @var int Schedule status: processing completed successfully. */
const DISCOURSESTATS_STATUS_FINISH    = 3;
/** @var int Schedule status: run manually without scheduling. */
const DISCOURSESTATS_STATUS_MANUAL    = 4;

/**
 * Add the report link to the course navigation.
 *
 * @param \navigation_node $navigation
 * @param \stdClass $course
 * @param \context $context
 */
function report_discoursestats_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('report/discoursestats:view', $context)) {
        $url = new \moodle_url('/report/discoursestats/index.php', ['id' => $course->id]);
        $navigation->add(
            get_string('pluginname', 'report_discoursestats'),
            $url,
            \navigation_node::TYPE_SETTING,
            null,
            null,
            new \pix_icon('i/report', '')
        );
    }
}

/**
 * Delete a schedule and all its results.
 *
 * @param int $scheduleid
 */
function discoursestats_removeschedule(int $scheduleid) {
    global $DB;
    $DB->delete_records('discoursestats_results', ['schedule' => $scheduleid]);
    $DB->delete_records('discoursestats_grading_log', ['scheduleid' => $scheduleid]);
    $DB->delete_records('discoursestats_schedules', ['id' => $scheduleid]);
}

/**
 * Remove any previously queued (not yet run) schedules for a user.
 *
 * @param int $userid 0 = current user
 */
function discoursestats_removeexistingschedule(int $userid = 0) {
    global $DB, $USER;
    $userid = $userid ?: $USER->id;
    $schedules = $DB->get_records_select(
        'discoursestats_schedules',
        'userid = :userid AND status = :status AND (gradingname IS NULL OR gradingname = :empty)',
        ['userid' => $userid, 'status' => DISCOURSESTATS_STATUS_SCHEDULED, 'empty' => ''],
        '',
        'id'
    );
    foreach ($schedules as $schedule) {
        discoursestats_removeschedule($schedule->id);
    }
}

/**
 * Create a new schedule record and optionally run it instantly.
 *
 * @param \stdClass $formdata
 * @param \core\context\course $coursecontext
 * @param int $userid 0 = current user
 * @return int Schedule ID
 */
function discoursestats_addschedule(\stdClass $formdata, \core\context\course $coursecontext, int $userid = 0) {
    global $DB, $USER;

    $schedule                        = new \stdClass();
    $schedule->userid                = $userid ?: $USER->id;
    $schedule->createdtime           = time();
    $schedule->status                = DISCOURSESTATS_STATUS_SCHEDULED;
    $schedule->course                = $formdata->course;
    $schedule->country               = $formdata->country ?? null;
    $schedule->groupid               = $formdata->group ?? null;
    $schedule->starttime             = $formdata->starttime ?? null;
    $schedule->endtime               = $formdata->endtime ?? null;
    $schedule->engagementmethod      = $formdata->engagementmethod ?? null;
    $schedule->engagementinternational = $formdata->engagementinternational ?? null;
    $schedule->stalethreshold        = $formdata->stalethreshold ?? 3;
    $schedule->gradingtriggered      = 0;

    // Multi-forum selection: store as JSON.
    $forums = !empty($formdata->forums) ? $formdata->forums : [];
    $schedule->forums = count($forums) > 0 ? json_encode(array_map('intval', $forums)) : null;

    // Database modules: null = disabled; '[]' = all; '[1,2]' = specific IDs.
    if (!empty($formdata->includedbinstances)) {
        $dbinstances = !empty($formdata->dbinstances) ? $formdata->dbinstances : [];
        $schedule->dbinstances = json_encode(array_map('intval', $dbinstances));
    } else {
        $schedule->dbinstances = null;
    }

    // Grading fields (all nullable).
    $schedule->gradingname     = !empty($formdata->gradingname) ? trim($formdata->gradingname) : null;
    $schedule->gradingformula  = !empty($formdata->gradingformula) ? trim($formdata->gradingformula) : null;
    $schedule->gradingfeedback = !empty($formdata->gradingfeedback) ? trim($formdata->gradingfeedback) : null;
    $schedule->gradingmax      = isset($formdata->gradingmax) && $formdata->gradingmax > 0 ? (float)$formdata->gradingmax : 100.0;
    $schedule->gradingcategory = isset($formdata->gradingcategory) ? (int)$formdata->gradingcategory : 0;
    $schedule->gradinghidden   = !empty($formdata->gradinghidden) ? 1 : 0;

    $schedule->id = $DB->insert_record('discoursestats_schedules', $schedule);

    if (!empty($formdata->instant) && has_capability('report/discoursestats:getinstantreport', $coursecontext)) {
        discoursestats_executeschedule($schedule);
    }

    return $schedule->id;
}

/**
 * Get status label and CSS class for a schedule status.
 *
 * @param int $status
 * @return array [label, css_class]
 */
function discoursestats_getstatus(int $status): array {
    switch ($status) {
        case DISCOURSESTATS_STATUS_SCHEDULED:
            return [get_string('status_scheduled', 'report_discoursestats'), ''];
        case DISCOURSESTATS_STATUS_EXECUTING:
            return [get_string('status_executing', 'report_discoursestats'), 'text-primary'];
        case DISCOURSESTATS_STATUS_ERROR:
            return [get_string('status_error', 'report_discoursestats'), 'text-danger'];
        case DISCOURSESTATS_STATUS_FINISH:
            return [get_string('status_finish', 'report_discoursestats'), 'text-success'];
        case DISCOURSESTATS_STATUS_MANUAL:
            return [get_string('status_manual', 'report_discoursestats'), 'text-secondary'];
    }
    return ['', ''];
}

/**
 * Get the download URL for a finished schedule.
 *
 * @param \stdClass $schedule
 * @return \moodle_url|null
 */
function discoursestats_getdownloadurl(\stdClass $schedule) {
    if ($schedule->status != DISCOURSESTATS_STATUS_FINISH) {
        return null;
    }
    return new \moodle_url('/report/discoursestats/view.php', ['id' => $schedule->id, 'action' => 'download']);
}

/**
 * Get the delete URL for a schedule.
 *
 * @param \stdClass $schedule
 * @return \moodle_url
 */
function discoursestats_getdeleteurl(\stdClass $schedule) {
    return new \moodle_url('/report/discoursestats/view.php', ['id' => $schedule->id, 'action' => 'delete']);
}

/**
 * Get the next scheduled execution time.
 *
 * @return int|null Unix timestamp
 */
function discoursestats_getnextscheduledtime() {
    $lastexecution = get_config('report_discoursestats', 'lastexecution');
    if (!$lastexecution) {
        return time() - 1;
    }
    $lastexecutionh = (int)date('G', $lastexecution);
    $hrs = explode(',', get_config('report_discoursestats', 'executionschedule'));
    if (!count($hrs)) {
        return null;
    }
    foreach ($hrs as $hr) {
        if (!is_numeric(trim($hr))) {
            continue;
        }
        $hr = (int)trim($hr);
        if ($lastexecutionh >= $hr) {
            continue;
        }
        $m = (int)date('n', $lastexecution);
        $d = (int)date('j', $lastexecution);
        $y = (int)date('Y', $lastexecution);
        return mktime($hr, 0, 0, $m, $d, $y);
    }
    $firsthr = (int)trim($hrs[0]);
    $m = (int)date('n', $lastexecution);
    $d = (int)date('j', $lastexecution);
    $y = (int)date('Y', $lastexecution);
    return mktime($firsthr + 24, 0, 0, $m, $d, $y);
}

/**
 * Build the "My Reports" template context for a user.
 *
 * @param int $userid
 * @return array
 */
function discoursestats_getreportscontext(int $userid): array {
    global $DB;
    $reports = [];
    $records = $DB->get_records_select(
        'discoursestats_schedules',
        'userid = :userid AND (gradingname IS NULL OR gradingname = :empty)',
        ['userid' => $userid, 'empty' => ''],
        'createdtime DESC'
    );
    foreach ($records as $record) {
        $scheduledtime = $record->status == DISCOURSESTATS_STATUS_SCHEDULED
            ? discoursestats_getnextscheduledtime()
            : $record->processedtime;

        $status = discoursestats_getstatus($record->status);
        $reports[] = [
            'requestedtime' => userdate($record->createdtime, get_string('strftimedaydatetime', 'langconfig')),
            'scheduledtime' => $scheduledtime ? userdate($scheduledtime, get_string('strftimedaydatetime', 'langconfig')) : '-',
            'status'        => $status[0],
            'statusClass'   => $status[1],
            'viewurl'       => new \moodle_url('/report/discoursestats/view.php', ['id' => $record->id]),
            'downloadurl'   => discoursestats_getdownloadurl($record),
            'deleteurl'     => discoursestats_getdeleteurl($record),
        ];
    }
    return ['reports' => $reports, 'hasreports' => count($reports) > 0];
}

/**
 * Build the "Automatically Generated Grades" template context for a course.
 *
 * @param int $courseid
 * @return array
 */
function discoursestats_getgradingschedulescontext(int $courseid): array {
    global $DB;
    $schedules = [];
    $records = $DB->get_records_select(
        'discoursestats_schedules',
        'course = :course AND gradingname IS NOT NULL AND gradingname != :empty',
        ['course' => $courseid, 'empty' => ''],
        'createdtime DESC'
    );
    $datefmt = get_string('strftimedatetimeshort', 'langconfig');
    foreach ($records as $record) {
        $status = discoursestats_getstatus($record->status);
        $schedules[] = [
            'gradingname'      => $record->gradingname,
            'gradingformula'   => $record->gradingformula ?? '',
            'starttime'        => $record->starttime ? userdate($record->starttime, $datefmt) : '-',
            'endtime'          => $record->endtime ? userdate($record->endtime, $datefmt) : '-',
            'status'           => $status[0],
            'statusClass'      => $status[1],
            'gradingtriggered' => !empty($record->gradingtriggered),
            'viewurl'          => (new \moodle_url('/report/discoursestats/view.php', ['id' => $record->id]))->out(false),
            'deleteurl'        => (new \moodle_url(
                '/report/discoursestats/view.php',
                ['id' => $record->id, 'action' => 'delete']
            ))->out(false),
        ];
    }
    return ['gradingschedules' => $schedules, 'hasgradingschedules' => count($schedules) > 0];
}

/**
 * Decode the forums JSON field and return an array of forum IDs.
 * Returns all course forum IDs when no specific selection is stored.
 *
 * @param \stdClass $schedule
 * @return int[]
 */
function discoursestats_getforumids(\stdClass $schedule): array {
    global $DB;
    if (!empty($schedule->forums)) {
        $ids = json_decode($schedule->forums, true);
        if (is_array($ids) && count($ids) > 0) {
            return array_map('intval', $ids);
        }
    }
    return array_keys($DB->get_records('forum', ['course' => $schedule->course], '', 'id'));
}

/**
 * Build a lookup of forum ID → module context ID for word/multimedia counting.
 *
 * @param int $courseid
 * @param int[] $forumids
 * @return int[]
 */
function discoursestats_getforummodcontextidlookup(int $courseid, array $forumids): array {
    $lookup = [];
    foreach ($forumids as $forumid) {
        $cm = get_coursemodule_from_instance('forum', $forumid, $courseid, false, MUST_EXIST);
        $lookup[$forumid] = \core\context\module::instance($cm->id)->id;
    }
    return $lookup;
}

/**
 * Build a SQL time-range condition fragment.
 *
 * @param string $fieldname
 * @param int $starttime
 * @param int $endtime
 * @param string $prefix
 * @return string
 */
function discoursestats_gettimecondition(string $fieldname, int $starttime, int $endtime, string $prefix): string {
    if ($starttime > 0 && $endtime > 0) {
        return "AND {$fieldname} BETWEEN :{$prefix}starttime AND :{$prefix}endtime";
    }
    if ($starttime > 0) {
        return "AND {$fieldname} >= :{$prefix}starttime";
    }
    if ($endtime > 0) {
        return "AND {$fieldname} <= :{$prefix}endtime";
    }
    return '';
}

/**
 * Get the basic per-user forum statistics (posts, replies, views, active days).
 * Prefers the efficient single-query approach from block_forum_report.
 *
 * @param int $userid  The teacher requesting the report (for group capability check)
 * @param \core\context\course $coursecontext
 * @param int $courseid
 * @param int[] $forumids  Empty array = all forums
 * @param int $groupid
 * @param string|null $country
 * @param int $starttime
 * @param int $endtime
 * @return \stdClass[]
 */
function discoursestats_getbasicreports(
    int $userid,
    \core\context\course $coursecontext,
    int $courseid,
    array $forumids,
    int $groupid = 0,
    $country = null,
    int $starttime = 0,
    int $endtime = 0
): array {
    global $DB;

    $capacityjoin = get_with_capability_join($coursecontext, 'mod/forum:viewdiscussion', 'u.id');
    $params = $capacityjoin->params;

    // Group filtering.
    if (has_capability('report/discoursestats:viewothergroups', $coursecontext, $userid)) {
        $groupjoin      = $groupid ? 'JOIN' : 'LEFT OUTER JOIN';
        $groupcondition = $groupid ? 'AND ug.id = :group' : '';
        if ($groupid) {
            $params['group'] = $groupid;
        }
    } else {
        $groupjoin  = 'JOIN';
        $mygroups   = groups_get_user_groups($courseid, $userid);
        $mygroupids = array_values($mygroups[0]);
        if (!count($mygroupids)) {
            return [];
        }
        if ($groupid && !in_array($groupid, $mygroupids)) {
            return [];
        }
        $querygroupids = $groupid ? [$groupid] : $mygroupids;
        [$groupsql, $groupparams] = $DB->get_in_or_equal($querygroupids, SQL_PARAMS_NAMED, 'groupid_');
        $groupcondition = 'AND ug.id ' . $groupsql;
        $params = array_merge($params, $groupparams);
    }

    $countrycondition = ($country && $country !== '0') ? 'AND u.country = :country' : '';
    if ($country && $country !== '0') {
        $params['country'] = $country;
    }

    // Forum filter: one or more forums vs all course forums.
    if (count($forumids) > 0) {
        [$forumsql, $forumparams] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'fid_');
        $discussioncondition = "fd.forum $forumsql";
        $params = array_merge($params, $forumparams);

        // Log condition: per-forum context.
        $contextids = [];
        foreach ($forumids as $fid) {
            $cm = get_coursemodule_from_instance('forum', $fid, $courseid, false, MUST_EXIST);
            $contextids[] = \core\context\module::instance($cm->id)->id;
        }
        [$ctxsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx_');
        $logcondition = "lsl.contextid $ctxsql";
        $params = array_merge($params, $ctxparams);
    } else {
        $discussioncondition = 'fd.course = :fdcourse';
        $params['fdcourse']  = $courseid;
        $logcondition        = 'lsl.courseid = :lslcourse';
        $params['lslcourse'] = $courseid;
    }

    $params['lsleventname'] = '\mod_forum\event\discussion_viewed';

    $posttimecondition = discoursestats_gettimecondition('fp.created', $starttime, $endtime, 'fp');
    if ($starttime > 0) {
        $params['fpstarttime'] = $starttime;
    }
    if ($endtime > 0) {
        $params['fpendtime'] = $endtime;
    }

    $logtimecondition = discoursestats_gettimecondition('lsl.timecreated', $starttime, $endtime, 'lsl');
    if ($starttime > 0) {
        $params['lslstarttime'] = $starttime;
    }
    if ($endtime > 0) {
        $params['lslendtime'] = $endtime;
    }

    $selectgroupsql = $DB->get_dbfamily() === 'postgres'
        ? "array_to_string(array_agg(DISTINCT g.groupname), ',') groupnames"
        : "GROUP_CONCAT(DISTINCT g.groupname SEPARATOR ',') groupnames";

    $sql = <<<SQL
        SELECT
            t1.id, username, firstname, lastname, groupnames, country, institution,
            posts, replies, unique_activedays, firstpost, lastpost,
            viewscount, uniqueviewdays
        FROM (
            SELECT
                u.id,
                username,
                firstname,
                lastname,
                {$selectgroupsql},
                country,
                institution
            FROM {user} u
                {$capacityjoin->joins}
                {$groupjoin} (
                    SELECT ug.id id, ug.name groupname, gm.userid userid
                    FROM {groups_members} gm
                        JOIN {groups} ug ON gm.groupid = ug.id
                    WHERE ug.courseid = :ugcourse {$groupcondition}
                ) g ON g.userid = u.id
            WHERE {$capacityjoin->wheres} {$countrycondition}
            GROUP BY u.id, username, firstname, lastname, country, institution
        ) t1 LEFT OUTER JOIN (
            SELECT
                u.id,
                SUM(CASE WHEN fp.parent = 0 THEN 1 ELSE 0 END) posts,
                SUM(CASE WHEN fp.parent != 0 THEN 1 ELSE 0 END) replies,
                COUNT(DISTINCT FLOOR(fp.created / 86400)) unique_activedays,
                MIN(fp.created) firstpost,
                MAX(fp.created) lastpost
            FROM {user} u
                LEFT OUTER JOIN {forum_posts} fp ON fp.userid = u.id
            WHERE fp.discussion IN (
                SELECT fd.id FROM {forum_discussions} fd WHERE {$discussioncondition}
            ) {$posttimecondition}
            GROUP BY u.id
        ) t2 ON t1.id = t2.id LEFT OUTER JOIN (
            SELECT
                u.id,
                COUNT(DISTINCT lsl.id) viewscount,
                COUNT(DISTINCT FLOOR(lsl.timecreated / 86400)) uniqueviewdays
            FROM {user} u
                LEFT OUTER JOIN {logstore_standard_log} lsl ON lsl.userid = u.id
            WHERE lsl.eventname = :lsleventname
                AND {$logcondition}
                {$logtimecondition}
            GROUP BY u.id
        ) t3 ON t2.id = t3.id
    SQL;

    $params['ugcourse'] = $courseid;

    return $DB->get_records_sql($sql, $params);
}

/**
 * Count attachment multimedia for a post.
 *
 * @param int $modcontextid
 * @param int $postid
 * @return \stdClass
 */
function discoursestats_countattachmentmultimedia(int $modcontextid, int $postid): \stdClass {
    $count        = new \stdClass();
    $count->num   = 0;
    $count->img   = 0;
    $count->video = 0;
    $count->audio = 0;
    $count->link  = 0;

    $fs    = get_file_storage();
    $files = $fs->get_area_files($modcontextid, 'mod_forum', 'attachment', $postid);
    foreach ($files as $file) {
        $mimetype = $file->get_mimetype();
        if (substr($mimetype, 0, 6) === 'image/') {
            $count->num++;
            $count->img++;
        } else if (substr($mimetype, 0, 6) === 'video/') {
            $count->num++;
            $count->video++;
        } else if (substr($mimetype, 0, 6) === 'audio/') {
            $count->num++;
            $count->audio++;
        }
    }
    return $count;
}

/**
 * Count multimedia elements (images, video, audio, links) in post HTML.
 *
 * @param string $text
 * @return \stdClass
 */
function discoursestats_getmultimedianum(string $text): \stdClass {
    $count        = new \stdClass();
    $count->num   = 0;
    $count->img   = 0;
    $count->video = 0;
    $count->audio = 0;
    $count->link  = 0;

    if (empty($text)) {
        return $count;
    }
    if (
        stripos($text, '</a>') === false
        && stripos($text, '</video>') === false
        && stripos($text, '</audio>') === false
        && stripos($text, '<img') === false
    ) {
        return $count;
    }

    $matches = preg_split('/(<[^>]*>)/i', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    if (!$matches) {
        return $count;
    }

    $embedmarkers = \core_media_manager::instance()->get_embeddable_markers();
    foreach ($matches as $tag) {
        if (!preg_match('/<(a|img|video|audio)\s[^>]*/i', $tag, $tagmatches)) {
            continue;
        }
        $tagname = strtolower($tagmatches[1]);
        if ($tagname === 'a') {
            preg_match("<a\\s+href=\".*({$embedmarkers}).*\".*>", $tag, $embedmarkermatch);
            $embed = $embedmarkermatch[1] ?? null;
            if ($embed) {
                if (
                    in_array($embed, ['.fmp4', '.mov', '.mp4', '.m4v', '.ogv', '.webm',
                    'youtube.com', 'youtube-nocookie.com', 'youtu.be', 'y2u.be'])
                ) {
                    $count->video++;
                    $count->num++;
                } else if (
                    in_array($embed, ['.m3u8', '.mpd', '.aac', '.flac', '.mp3',
                    '.m4a', '.oga', '.ogg', '.wav'])
                ) {
                    $count->audio++;
                    $count->num++;
                } else {
                    $count->link++;
                    $count->num++;
                }
            }
        } else if ($tagname === 'img') {
            $count->img++;
            $count->num++;
        } else if ($tagname === 'video') {
            $count->video++;
            $count->num++;
        } else if ($tagname === 'audio') {
            $count->audio++;
            $count->num++;
        }
    }
    return $count;
}

/**
 * Count words and multimedia across all posts by a user in selected forums.
 *
 * @param int[] $modcontextidlookup Forum ID → context ID
 * @param int $userid
 * @param int $courseid
 * @param int[] $forumids
 * @param int $starttime
 * @param int $endtime
 * @return \stdClass
 */
function discoursestats_countwordmultimedia(
    array $modcontextidlookup,
    int $userid,
    int $courseid,
    array $forumids,
    int $starttime,
    int $endtime
): \stdClass {
    global $DB;

    $result                    = new \stdClass();
    $result->wordcount         = 0;
    $result->multimedia        = 0;
    $result->multimedia_image  = 0;
    $result->multimedia_video  = 0;
    $result->multimedia_audio  = 0;
    $result->multimedia_link   = 0;

    $timecondition = discoursestats_gettimecondition('fp.created', $starttime, $endtime, '');
    $params        = ['userid' => $userid, 'courseid' => $courseid];

    if (count($forumids) > 0) {
        [$forumsql, $forumparams] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'fid_');
        $forumcondition = "fd.forum $forumsql";
        $params = array_merge($params, $forumparams);
    } else {
        $forumcondition = 'fd.course = :courseid';
    }

    $sql = <<<SQL
        SELECT fp.id, fp.message, fd.forum
          FROM {forum_posts} fp
          JOIN {forum_discussions} fd ON fp.discussion = fd.id
         WHERE fp.userid = :userid
           AND {$forumcondition}
           {$timecondition}
    SQL;

    if ($starttime) {
        $params['starttime'] = $starttime;
    }
    if ($endtime) {
        $params['endtime'] = $endtime;
    }

    $posts = $DB->get_records_sql($sql, $params);
    foreach ($posts as $post) {
        $multimedia  = discoursestats_getmultimedianum($post->message);
        $contextid   = $modcontextidlookup[$post->forum] ?? 0;
        $attachment  = $contextid ? discoursestats_countattachmentmultimedia($contextid, $post->id) : new \stdClass();

        $result->wordcount        += count_words($post->message);
        $result->multimedia       += ($multimedia->num ?? 0) + ($attachment->num ?? 0);
        $result->multimedia_image += ($multimedia->img ?? 0) + ($attachment->img ?? 0);
        $result->multimedia_video += ($multimedia->video ?? 0) + ($attachment->video ?? 0);
        $result->multimedia_audio += ($multimedia->audio ?? 0) + ($attachment->audio ?? 0);
        $result->multimedia_link  += ($multimedia->link ?? 0) + ($attachment->link ?? 0);
    }

    return $result;
}

/**
 * Check if the local_reactforum plugin is installed.
 *
 * @return bool
 */
function discoursestats_reactforuminstalled(): bool {
    return isset(\core\plugin_manager::instance()->get_installed_plugins('local')['reactforum']);
}

/**
 * Count reactions given by a user in selected forums.
 *
 * @param int $userid
 * @param int $courseid
 * @param int[] $forumids
 * @param int $starttime
 * @param int $endtime
 * @return int
 */
function discoursestats_getreactionsgiven(int $userid, int $courseid, array $forumids, int $starttime, int $endtime): int {
    global $DB;

    $timecondition = discoursestats_gettimecondition('fp.created', $starttime, $endtime, '');
    $params        = ['userid' => $userid, 'courseid' => $courseid];

    if (count($forumids) > 0) {
        [$forumsql, $forumparams] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'fid_');
        $forumcondition = "fd.forum $forumsql";
        $params = array_merge($params, $forumparams);
    } else {
        $forumcondition = 'fd.course = :courseid';
    }

    if ($starttime) {
        $params['starttime'] = $starttime;
    }
    if ($endtime) {
        $params['endtime'] = $endtime;
    }

    $sql = "SELECT COUNT(rr.id) reactionsgiven
              FROM {local_reactforum_userreactions} rr
              JOIN {forum_posts} fp ON rr.post = fp.id
              JOIN {forum_discussions} fd ON fp.discussion = fd.id
             WHERE rr.userid = :userid
               AND {$forumcondition}
               {$timecondition}";

    return (int)($DB->get_record_sql($sql, $params)->reactionsgiven ?? 0);
}

/**
 * Count reactions received by a user in selected forums.
 *
 * @param int $userid
 * @param int $courseid
 * @param int[] $forumids
 * @param int $starttime
 * @param int $endtime
 * @return int
 */
function discoursestats_getreactionsreceived(int $userid, int $courseid, array $forumids, int $starttime, int $endtime): int {
    global $DB;

    $timecondition = discoursestats_gettimecondition('fp.created', $starttime, $endtime, '');
    $params        = ['userid' => $userid, 'courseid' => $courseid];

    if (count($forumids) > 0) {
        [$forumsql, $forumparams] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'fid_');
        $forumcondition = "fd.forum $forumsql";
        $params = array_merge($params, $forumparams);
    } else {
        $forumcondition = 'fd.course = :courseid';
    }

    if ($starttime) {
        $params['starttime'] = $starttime;
    }
    if ($endtime) {
        $params['endtime'] = $endtime;
    }

    $sql = "SELECT COUNT(rr.id) received
              FROM {local_reactforum_userreactions} rr
              JOIN {forum_posts} fp ON rr.post = fp.id
              JOIN {forum_discussions} fd ON fp.discussion = fd.id
             WHERE fp.userid = :userid
               AND {$forumcondition}
               {$timecondition}";

    return (int)($DB->get_record_sql($sql, $params)->received ?? 0);
}

/**
 * Get database module entry and comment counts for a user.
 *
 * @param int $userid
 * @param int[] $dbinstanceids  Data module instance IDs
 * @param int $starttime
 * @param int $endtime
 * @return \stdClass  ->dbentries, ->dbcomments
 */
function discoursestats_getdbmodulestats(int $userid, array $dbinstanceids, int $starttime, int $endtime): \stdClass {
    global $DB;

    $result             = new \stdClass();
    $result->dbentries  = 0;
    $result->dbcomments = 0;

    if (!count($dbinstanceids)) {
        return $result;
    }

    // Count database entries.
    [$idsql, $idparams] = $DB->get_in_or_equal($dbinstanceids, SQL_PARAMS_NAMED, 'dat_');
    $entryparams = array_merge($idparams, ['userid' => $userid]);
    $entrycondition = '';
    if ($starttime) {
        $entrycondition  .= ' AND timecreated >= :starttime';
        $entryparams['starttime'] = $starttime;
    }
    if ($endtime) {
        $entrycondition  .= ' AND timecreated <= :endtime';
        $entryparams['endtime'] = $endtime;
    }
    $result->dbentries = (int)$DB->count_records_sql(
        "SELECT COUNT(id) FROM {data_records} WHERE dataid $idsql AND userid = :userid $entrycondition",
        $entryparams
    );

    // Count comments on database entries — via the {comments} table.
    // We need the context IDs for each data module instance.
    $contextids = [];
    foreach ($dbinstanceids as $dataid) {
        $cm = get_coursemodule_from_instance('data', $dataid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $contextids[] = \core\context\module::instance($cm->id)->id;
        }
    }
    if (count($contextids) > 0) {
        [$ctxsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx_');
        $commentparams = array_merge($ctxparams, ['userid' => $userid, 'component' => 'mod_data']);
        $commentcondition = '';
        if ($starttime) {
            $commentcondition  .= ' AND timecreated >= :comstarttime';
            $commentparams['comstarttime'] = $starttime;
        }
        if ($endtime) {
            $commentcondition  .= ' AND timecreated <= :comentime';
            $commentparams['comentime'] = $endtime;
        }
        $result->dbcomments = (int)$DB->count_records_sql(
            "SELECT COUNT(id) FROM {comments}
              WHERE contextid $ctxsql
                AND userid = :userid
                AND component = :component
                $commentcondition",
            $commentparams
        );
    }

    return $result;
}

/**
 * Execute a schedule: calculate and store results.
 *
 * @param \stdClass $schedule
 * @return bool Success
 */
function discoursestats_executeschedule(\stdClass $schedule): bool {
    global $DB;
    try {
        $schedule->status        = DISCOURSESTATS_STATUS_EXECUTING;
        $schedule->processedtime = time();
        $DB->update_record('discoursestats_schedules', $schedule);

        // Remove older finished schedules for the same user (keep only the latest).
        $DB->execute(
            "DELETE FROM {discoursestats_results} WHERE schedule IN (
                SELECT id FROM {discoursestats_schedules} WHERE userid = ? AND createdtime < ?
            )",
            [$schedule->userid, $schedule->createdtime]
        );
        $DB->execute(
            "DELETE FROM {discoursestats_schedules} WHERE userid = ? AND createdtime < ?",
            [$schedule->userid, $schedule->createdtime]
        );

        discoursestats_calculatereport($schedule);

        $schedule->status        = DISCOURSESTATS_STATUS_FINISH;
        $schedule->processedtime = time();
        $DB->update_record('discoursestats_schedules', $schedule);

        // Trigger grading if end date has passed and grading is configured.
        discoursestats_maybe_queue_grading($schedule);

        return true;
    } catch (\Throwable $ex) {
        $schedule->status        = DISCOURSESTATS_STATUS_ERROR;
        $schedule->message       = $ex->getMessage() . "\n" . $ex->getTraceAsString();
        $schedule->processedtime = time();
        $DB->update_record('discoursestats_schedules', $schedule);
        return false;
    }
}

/**
 * Calculate and store report results for a schedule.
 *
 * @param \stdClass $schedule
 */
function discoursestats_calculatereport(\stdClass $schedule) {
    global $DB;

    $forumids      = discoursestats_getforumids($schedule);
    $modcontextlookup = discoursestats_getforummodcontextidlookup($schedule->course, $forumids);
    $stalethreshold = (int)($schedule->stalethreshold ?? 7);
    // Null means disabled; empty JSON array means all course databases.
    if ($schedule->dbinstances === null) {
        $dbinstanceids = null;
    } else {
        $decoded = json_decode($schedule->dbinstances, true);
        if (count($decoded) > 0) {
            $dbinstanceids = array_map('intval', $decoded);
        } else {
            $dbinstanceids = array_keys($DB->get_records('data', ['course' => $schedule->course], '', 'id'));
        }
    }

    // Build engagement calculators for each discussion.
    $engagementcalculators = [];
    $firstposts            = [];

    if (count($forumids) > 0) {
        [$forumsql, $forumparams] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'fid_');
        $discussions = $DB->get_records_sql(
            "SELECT fd.id, fd.firstpost FROM {forum_discussions} fd WHERE fd.forum $forumsql",
            $forumparams
        );
    } else {
        $discussions = $DB->get_records('forum_discussions', ['course' => $schedule->course], '', 'id,firstpost');
    }

    foreach ($discussions as $discussion) {
        $engagementcalculators[] = engagement::getinstancefrommethod(
            $schedule->engagementmethod,
            $discussion->id,
            $schedule->starttime,
            $schedule->endtime,
            $schedule->engagementinternational
        );
        if ($discussion->firstpost) {
            $firstposts[] = $discussion->firstpost;
        }
    }

    // Preload all post data for stale/self-reply and repliestoseed detection.
    // Must include seed posts (parent=0) so that direct replies to seed posts
    // can be looked up by their parent id. Scoped to the current course only.
    $allcourseposts = $DB->get_records_sql(
        'SELECT fp.id, fp.parent, fp.userid, fp.created
           FROM {forum_posts} fp
           JOIN {forum_discussions} fd ON fd.id = fp.discussion
          WHERE fd.course = :course',
        ['course' => $schedule->course]
    );
    $replyuserids    = [];
    $replycreatedmap = [];
    foreach ($allcourseposts as $r) {
        $replyuserids[$r->id]    = (int)$r->userid;
        $replycreatedmap[$r->id] = (int)$r->created;
    }

    $coursecontext = \core\context\course::instance($schedule->course);
    $students      = discoursestats_getbasicreports(
        $schedule->userid,
        $coursecontext,
        $schedule->course,
        $forumids,
        (int)($schedule->groupid ?? 0),
        $schedule->country ?? null,
        (int)($schedule->starttime ?? 0),
        (int)($schedule->endtime ?? 0)
    );

    $results = [];
    foreach ($students as $student) {
        $result               = new \stdClass();
        $result->schedule     = $schedule->id;
        $result->userid       = $student->id;
        $result->username     = $student->username;
        $result->firstname    = $student->firstname;
        $result->lastname     = $student->lastname;
        $result->groups       = $student->groupnames;
        $result->country      = $student->country;
        $result->institution  = $student->institution;
        $result->posts        = (int)($student->posts ?? 0);
        $result->replies      = (int)($student->replies ?? 0);
        $result->uniquedaysactive  = (int)($student->unique_activedays ?? 0);
        $result->views             = (int)($student->viewscount ?? 0);
        $result->uniquedaysviewed  = (int)($student->uniqueviewdays ?? 0);
        $result->firstpost         = $student->firstpost;
        $result->lastpost          = $student->lastpost;

        // Stale reply / self-reply / replies-to-seed counting.
        $stalereply   = 0;
        $selfreply    = 0;
        $repliestoseed = 0;

        if (count($forumids) > 0) {
            [$forumsql, $forumparams] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'pfid_');
            $postparams = array_merge($forumparams, ['userid' => $student->id]);
            $postcondition = "fd.forum $forumsql";
        } else {
            $postparams    = ['userid' => $student->id, 'course' => $schedule->course];
            $postcondition = 'fd.course = :course';
        }

        $timecond = '';
        if ($schedule->starttime) {
            $timecond .= ' AND fp.created >= :startt';
            $postparams['startt'] = $schedule->starttime;
        }
        if ($schedule->endtime) {
            $timecond .= ' AND fp.created <= :endt';
            $postparams['endt'] = $schedule->endtime;
        }

        $userposts = $DB->get_records_sql(
            "SELECT fp.id, fp.parent, fp.created
               FROM {forum_posts} fp
               JOIN {forum_discussions} fd ON fp.discussion = fd.id
              WHERE fp.userid = :userid AND fp.parent > 0
                AND {$postcondition} {$timecond}",
            $postparams
        );

        foreach ($userposts as $post) {
            if (!array_key_exists($post->parent, $replycreatedmap)) {
                continue;
            }
            $parentuserid  = $replyuserids[$post->parent];
            $parentcreated = $replycreatedmap[$post->parent];

            if ($parentuserid == $student->id) {
                $selfreply++;
            } else if (strtotime('-' . $stalethreshold . ' days', $post->created) > $parentcreated) {
                $stalereply++;
            }

            if (in_array($post->parent, $firstposts)) {
                $repliestoseed++;
            }
        }

        $result->stalereply    = $stalereply;
        $result->selfreply     = $selfreply;
        $result->repliestoseed = $repliestoseed;

        // Word count and multimedia.
        $multimedia = discoursestats_countwordmultimedia(
            $modcontextlookup,
            $student->id,
            $schedule->course,
            $forumids,
            (int)($schedule->starttime ?? 0),
            (int)($schedule->endtime ?? 0)
        );
        $result->wordcount        = $multimedia->wordcount ?: null;
        $result->multimedia       = $multimedia->multimedia ?: null;
        $result->images           = $multimedia->multimedia_image ?: null;
        $result->videos           = $multimedia->multimedia_video ?: null;
        $result->audios           = $multimedia->multimedia_audio ?: null;
        $result->links            = $multimedia->multimedia_link ?: null;

        // Engagement levels.
        $engagementresult = new engagementresult();
        foreach ($engagementcalculators as $calc) {
            $engagementresult->add($calc->calculate($student->id));
        }
        $result->engagement1       = $engagementresult->getl1() ?: null;
        $result->engagement2       = $engagementresult->getl2() ?: null;
        $result->engagement3       = $engagementresult->getl3() ?: null;
        $result->engagement4       = $engagementresult->getl4up() ?: null;
        $result->averageengagement = $engagementresult->getaverage();
        $result->maximumengagement = $engagementresult->getmax();

        // Database module stats (only when enabled).
        if ($dbinstanceids !== null && count($dbinstanceids) > 0) {
            $dbstats           = discoursestats_getdbmodulestats(
                $student->id,
                $dbinstanceids,
                (int)($schedule->starttime ?? 0),
                (int)($schedule->endtime ?? 0)
            );
            $result->dbentries  = $dbstats->dbentries ?: null;
            $result->dbcomments = $dbstats->dbcomments ?: null;
        } else {
            $result->dbentries  = null;
            $result->dbcomments = null;
        }

        // React forum reactions (optional plugin).
        if (discoursestats_reactforuminstalled()) {
            $result->reactionsgiven     = discoursestats_getreactionsgiven(
                $student->id,
                $schedule->course,
                $forumids,
                (int)($schedule->starttime ?? 0),
                (int)($schedule->endtime ?? 0)
            );
            $result->reactionsreceived  = discoursestats_getreactionsreceived(
                $student->id,
                $schedule->course,
                $forumids,
                (int)($schedule->starttime ?? 0),
                (int)($schedule->endtime ?? 0)
            );
        }

        $results[] = $result;
    }

    $DB->insert_records('discoursestats_results', $results);
}

/**
 * Queue the grading adhoc task if conditions are met.
 *
 * @param \stdClass $schedule
 */
function discoursestats_maybe_queue_grading(\stdClass $schedule) {
    global $DB;

    if (
        empty($schedule->gradingname)
        || !empty($schedule->gradingtriggered)
        || empty($schedule->endtime)
        || $schedule->endtime > time()
    ) {
        return;
    }

    $task = new \report_discoursestats\task\grading_task();
    $task->set_custom_data(['scheduleid' => $schedule->id]);
    \core\task\manager::queue_adhoc_task($task, true);

    $schedule->gradingtriggered = 1;
    $DB->update_record('discoursestats_schedules', $schedule);
}

/**
 * Get the ordered list of result column headers.
 *
 * @return array  field => label
 */
function discoursestats_getresultsheader(): array {
    return [
        'username'          => get_string('username'),
        'firstname'         => get_string('firstname'),
        'lastname'          => get_string('lastname'),
        'groups'            => get_string('group'),
        'country'           => get_string('country'),
        'institution'       => get_string('institution'),
        'posts'             => get_string('posts'),
        'replies'           => get_string('replies', 'report_discoursestats'),
        'stalereply'        => get_string('stalereply', 'report_discoursestats'),
        'selfreply'         => get_string('selfreply', 'report_discoursestats'),
        'repliestoseed'     => get_string('repliestoseed', 'report_discoursestats'),
        'uniquedaysactive'  => get_string('uniqueactive', 'report_discoursestats'),
        'views'             => get_string('views', 'report_discoursestats'),
        'uniquedaysviewed'  => get_string('uniqueview', 'report_discoursestats'),
        'wordcount'         => get_string('wordcount', 'report_discoursestats'),
        'multimedia'        => get_string('multimedia', 'report_discoursestats'),
        'images'            => get_string('multimedia_image', 'report_discoursestats'),
        'videos'            => get_string('multimedia_video', 'report_discoursestats'),
        'audios'            => get_string('multimedia_audio', 'report_discoursestats'),
        'links'             => get_string('multimedia_link', 'report_discoursestats'),
        'dbentries'         => get_string('dbentries', 'report_discoursestats'),
        'dbcomments'        => get_string('dbcomments', 'report_discoursestats'),
        'engagement1'       => get_string('el1', 'report_discoursestats'),
        'engagement2'       => get_string('el2', 'report_discoursestats'),
        'engagement3'       => get_string('el3', 'report_discoursestats'),
        'engagement4'       => get_string('el4up', 'report_discoursestats'),
        'averageengagement' => get_string('elavg', 'report_discoursestats'),
        'maximumengagement' => get_string('elmax', 'report_discoursestats'),
        'firstpost'         => get_string('firstpost', 'report_discoursestats'),
        'lastpost'          => get_string('lastpost', 'report_discoursestats'),
        'reactionsgiven'    => get_string('reactionsgiven', 'report_discoursestats'),
        'reactionsreceived' => get_string('reactionsreceived', 'report_discoursestats'),
    ];
}

/**
 * Build sortable header context for the results template.
 *
 * @param int $scheduleid
 * @param string|null $sortname
 * @param string $sorttype
 * @return array
 */
function discoursestats_getresultsheadercontext(int $scheduleid, $sortname = null, string $sorttype = 'asc'): array {
    $sorttype    = strtolower($sorttype);
    $fliptype    = $sorttype === 'asc' ? 'desc' : 'asc';
    $items       = [];
    foreach (discoursestats_getresultsheader() as $fieldname => $title) {
        $items[] = [
            'name'    => $title,
            'sorturl' => new \moodle_url(
                '/report/discoursestats/view.php',
                ['id' => $scheduleid, 'sn' => $fieldname, 'sd' => $sortname === $fieldname ? $fliptype : 'asc'],
                'results'
            ),
            'icon' => $sortname === $fieldname ? ($sorttype === 'desc' ? 'fa-caret-down' : 'fa-caret-up') : null,
        ];
    }
    return $items;
}

/**
 * Convert a result record to an ordered array of display values.
 *
 * @param \stdClass $record
 * @return array
 */
function discoursestats_getresultsrow(\stdClass $record): array {
    static $countries = [];
    if (!$countries) {
        $countries = get_string_manager()->get_list_of_countries();
    }
    $dateformat = get_string('strftimedatetimeshortaccurate', 'langconfig');
    return [
        $record->username,
        $record->firstname,
        $record->lastname,
        $record->groups,
        $countries[$record->country] ?? '',
        $record->institution,
        $record->posts,
        $record->replies,
        $record->stalereply,
        $record->selfreply,
        $record->repliestoseed,
        $record->uniquedaysactive,
        $record->views,
        $record->uniquedaysviewed,
        $record->wordcount,
        $record->multimedia,
        $record->images,
        $record->videos,
        $record->audios,
        $record->links,
        $record->dbentries,
        $record->dbcomments,
        $record->engagement1,
        $record->engagement2,
        $record->engagement3,
        $record->engagement4,
        $record->averageengagement,
        $record->maximumengagement,
        $record->firstpost ? userdate($record->firstpost, $dateformat) : '',
        $record->lastpost ? userdate($record->lastpost, $dateformat) : '',
        $record->reactionsgiven ?? '',
        $record->reactionsreceived ?? '',
    ];
}

/**
 * Build a safe ORDER BY clause for the results query.
 *
 * @param string|null $sortname
 * @param string $sorttype
 * @return string
 */
function discoursestats_getsort($sortname, string $sorttype): string {
    $allowed = array_keys(discoursestats_getresultsheader());
    if (!$sortname || !in_array($sortname, $allowed)) {
        return 'userid ASC';
    }
    $sorttype = strtolower($sorttype) === 'desc' ? 'DESC' : 'ASC';
    return "{$sortname} {$sorttype}";
}

/**
 * Allowed field names in formulas.
 *
 * @return string[]
 */
function discoursestats_formula_fields(): array {
    return [
        'posts', 'replies', 'stalereply', 'selfreply', 'repliestoseed',
        'uniquedaysactive', 'views', 'uniquedaysviewed',
        'wordcount', 'multimedia', 'images', 'videos', 'audios', 'links',
        'dbentries', 'dbcomments',
        'engagement1', 'engagement2', 'engagement3', 'engagement4',
        'averageengagement', 'maximumengagement',
        'reactionsgiven', 'reactionsreceived',
    ];
}

/**
 * Evaluate a grade formula against a result record.
 *
 * Field names (with or without {braces}) are substituted with numeric values
 * from the result before parsing. Supports +, -, *, /, parentheses, unary
 * minus, and the functions min(), max(), round(), ceil(), floor(), abs().
 *
 * @param string $formula
 * @param \stdClass $result
 * @return float
 */
function discoursestats_evaluate_formula(string $formula, \stdClass $result): float {
    $expr = $formula;

    // Substitute field names (with or without braces) with numeric values.
    foreach (discoursestats_formula_fields() as $field) {
        $val  = is_numeric($result->$field ?? null) ? (float)$result->$field : 0.0;
        $expr = str_replace('{' . $field . '}', (string)$val, $expr);
        $expr = preg_replace('/\b' . preg_quote($field, '/') . '\b/', (string)$val, $expr);
    }

    try {
        $pos   = 0;
        $value = discoursestats_mathparse_additive($expr, $pos);
        return is_finite($value) ? $value : 0.0;
    } catch (\Throwable $e) {
        return 0.0;
    }
}

/**
 * Skip whitespace in an expression string.
 *
 * @param string $expr
 * @param int $pos Current position (modified in place).
 */
function discoursestats_mathparse_skipspace(string $expr, int &$pos) {
    $len = strlen($expr);
    while ($pos < $len && ctype_space($expr[$pos])) {
        $pos++;
    }
}

/**
 * Parse an additive expression (handles + and -).
 *
 * @param string $expr
 * @param int $pos Current position (modified in place).
 * @return float
 */
function discoursestats_mathparse_additive(string $expr, int &$pos): float {
    $left = discoursestats_mathparse_multiplicative($expr, $pos);
    while (true) {
        discoursestats_mathparse_skipspace($expr, $pos);
        if ($pos >= strlen($expr)) {
            break;
        }
        $ch = $expr[$pos];
        if ($ch !== '+' && $ch !== '-') {
            break;
        }
        $pos++;
        $right = discoursestats_mathparse_multiplicative($expr, $pos);
        $left  = $ch === '+' ? $left + $right : $left - $right;
    }
    return $left;
}

/**
 * Parse a multiplicative expression (handles * and /).
 *
 * @param string $expr
 * @param int $pos Current position (modified in place).
 * @return float
 */
function discoursestats_mathparse_multiplicative(string $expr, int &$pos): float {
    $left = discoursestats_mathparse_unary($expr, $pos);
    while (true) {
        discoursestats_mathparse_skipspace($expr, $pos);
        if ($pos >= strlen($expr)) {
            break;
        }
        $ch = $expr[$pos];
        if ($ch !== '*' && $ch !== '/') {
            break;
        }
        $pos++;
        $right = discoursestats_mathparse_unary($expr, $pos);
        $left  = $ch === '*' ? $left * $right : ($right != 0.0 ? $left / $right : 0.0);
    }
    return $left;
}

/**
 * Parse a unary expression (handles unary minus).
 *
 * @param string $expr
 * @param int $pos Current position (modified in place).
 * @return float
 */
function discoursestats_mathparse_unary(string $expr, int &$pos): float {
    discoursestats_mathparse_skipspace($expr, $pos);
    if ($pos < strlen($expr) && $expr[$pos] === '-') {
        $pos++;
        return -discoursestats_mathparse_primary($expr, $pos);
    }
    return discoursestats_mathparse_primary($expr, $pos);
}

/**
 * Parse a primary expression: number literal, parenthesised sub-expression,
 * or a call to one of the allowed functions (min, max, round, ceil, floor, abs).
 *
 * @param string $expr
 * @param int $pos Current position (modified in place).
 * @return float
 */
function discoursestats_mathparse_primary(string $expr, int &$pos): float {
    discoursestats_mathparse_skipspace($expr, $pos);
    $len = strlen($expr);

    if ($pos >= $len) {
        return 0.0;
    }

    // Number literal (digits and optional decimal point).
    if (ctype_digit($expr[$pos]) || $expr[$pos] === '.') {
        $start = $pos;
        while ($pos < $len && (ctype_digit($expr[$pos]) || $expr[$pos] === '.')) {
            $pos++;
        }
        return (float)substr($expr, $start, $pos - $start);
    }

    // Parenthesised sub-expression.
    if ($expr[$pos] === '(') {
        $pos++;
        $val = discoursestats_mathparse_additive($expr, $pos);
        discoursestats_mathparse_skipspace($expr, $pos);
        if ($pos < $len && $expr[$pos] === ')') {
            $pos++;
        }
        return $val;
    }

    // Named function call (min, max, round, ceil, floor, abs).
    if (ctype_alpha($expr[$pos])) {
        $start = $pos;
        while ($pos < $len && ctype_alpha($expr[$pos])) {
            $pos++;
        }
        $fname = strtolower(substr($expr, $start, $pos - $start));
        discoursestats_mathparse_skipspace($expr, $pos);
        $args = [];
        if ($pos < $len && $expr[$pos] === '(') {
            $pos++;
            while (true) {
                discoursestats_mathparse_skipspace($expr, $pos);
                if ($pos >= $len || $expr[$pos] === ')') {
                    break;
                }
                $args[] = discoursestats_mathparse_additive($expr, $pos);
                discoursestats_mathparse_skipspace($expr, $pos);
                if ($pos < $len && $expr[$pos] === ',') {
                    $pos++;
                }
            }
            if ($pos < $len && $expr[$pos] === ')') {
                $pos++;
            }
        }
        switch ($fname) {
            case 'min':
                return isset($args[1]) ? min($args[0], $args[1]) : ($args[0] ?? 0.0);
            case 'max':
                return isset($args[1]) ? max($args[0], $args[1]) : ($args[0] ?? 0.0);
            case 'round':
                return isset($args[1]) ? (float)round($args[0], (int)$args[1]) : (float)round($args[0] ?? 0.0);
            case 'ceil':
                return (float)ceil($args[0] ?? 0.0);
            case 'floor':
                return (float)floor($args[0] ?? 0.0);
            case 'abs':
                return (float)abs($args[0] ?? 0.0);
        }
    }

    return 0.0;
}

/**
 * Apply a feedback template, substituting {fieldname} placeholders.
 *
 * @param string $template
 * @param \stdClass $result
 * @return string
 */
function discoursestats_apply_feedback_template(string $template, \stdClass $result): string {
    foreach (get_object_vars($result) as $key => $val) {
        $template = str_replace('{' . $key . '}', (string)($val ?? ''), $template);
    }
    return $template;
}

/**
 * Calculate grades and push them to the Moodle gradebook.
 *
 * @param int $scheduleid
 */
function discoursestats_push_grades(int $scheduleid) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $schedule = $DB->get_record('discoursestats_schedules', ['id' => $scheduleid], '*', MUST_EXIST);
    $results  = $DB->get_records('discoursestats_results', ['schedule' => $scheduleid]);

    $grades = [];
    $log    = [];
    foreach ($results as $result) {
        $rawgrade = discoursestats_evaluate_formula($schedule->gradingformula ?? '', $result);
        $rawgrade = max(0.0, min((float)$schedule->gradingmax, $rawgrade));
        $feedback = discoursestats_apply_feedback_template($schedule->gradingfeedback ?? '', $result);

        $grade                 = new \stdClass();
        $grade->userid         = (int)$result->userid;
        $grade->rawgrade       = $rawgrade;
        $grade->feedback       = $feedback;
        $grade->feedbackformat = FORMAT_PLAIN;
        $grades[$result->userid] = $grade;

        $log[] = [
            'scheduleid' => $scheduleid,
            'userid'     => (int)$result->userid,
            'rawgrade'   => $rawgrade,
            'feedback'   => $feedback,
            'timepushed' => time(),
        ];
    }

    $params = [
        'itemname'   => $schedule->gradingname,
        'gradetype'  => GRADE_TYPE_VALUE,
        'grademax'   => (float)$schedule->gradingmax,
    ];
    if (!empty($schedule->gradingcategory)) {
        $params['categoryid'] = (int)$schedule->gradingcategory;
    }
    if (!empty($schedule->gradinghidden)) {
        $params['hidden'] = 1;
    }

    grade_update(
        'report/discoursestats',
        $schedule->course,
        'report',
        'discoursestats',
        $scheduleid,
        0,
        $grades,
        $params
    );

    // Write audit log.
    foreach ($log as $entry) {
        $DB->insert_record('discoursestats_grading_log', (object)$entry);
    }
}
