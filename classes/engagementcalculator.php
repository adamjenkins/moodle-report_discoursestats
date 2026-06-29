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
 * Abstract engagement calculator base class for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats;

/**
 * Base class providing common post-loading and traversal infrastructure.
 */
abstract class engagementcalculator {
    /** @var int Discussion ID being analysed. */
    protected $discussionid;

    /** @var engagedpost[] Posts keyed by post ID. */
    protected $postsdict = [];

    /** @var string[] Country codes keyed by user ID. */
    protected $nationalitiesdict = [];

    /** @var int ID of the seed (first) post in the discussion. */
    protected $firstpost;

    /** @var int Report start timestamp (0 = no restriction). */
    protected $starttime = 0;

    /** @var int Report end timestamp (0 = no restriction). */
    protected $endtime = 0;

    /** @var bool When true, only cross-country replies count. */
    protected $international = false;

    /**
     * Constructor — loads posts and prepares internal data structures.
     *
     * @param int $discussionid
     * @param int $starttime
     * @param int $endtime
     * @param bool $international
     */
    public function __construct(int $discussionid, int $starttime = 0, int $endtime = 0, bool $international = false) {
        $this->discussionid  = $discussionid;
        $this->starttime     = $starttime;
        $this->endtime       = $endtime;
        $this->international = $international;
        $this->getposts();
        $this->initchildren();
        $this->checkpoststime();
        $this->getusernationalities();
    }

    /**
     * Return the list of unique participant user IDs in this discussion.
     *
     * @return int[]
     */
    public function getparticipants(): array {
        $results = [];
        foreach ($this->postsdict as $post) {
            if (!in_array($post->userid, $results)) {
                $results[] = $post->userid;
            }
        }
        return $results;
    }

    /**
     * Load all posts for the discussion into $postsdict.
     */
    private function getposts() {
        global $DB;
        $rows = $DB->get_records('forum_posts', ['discussion' => $this->discussionid], '', engagedpost::DB_OUT_FIELDS);
        foreach ($rows as $row) {
            $post             = new engagedpost();
            $post->id         = (int)$row->id;
            $post->discussion = (int)$row->discussion;
            $post->parent     = (int)$row->parent;
            $post->userid     = (int)$row->userid;
            $post->created    = (int)$row->created;
            $this->postsdict[$post->id] = $post;
            if (!$post->parent) {
                $this->firstpost = $post->id;
            }
        }
    }

    /**
     * Populate the children array on every post.
     */
    private function initchildren() {
        foreach ($this->postsdict as $post) {
            $post->children = $this->getchildren($post);
        }
    }

    /**
     * Return direct child posts of the given parent.
     *
     * @param engagedpost $parentpost
     * @return engagedpost[]
     */
    private function getchildren(engagedpost $parentpost): array {
        $results = [];
        foreach ($this->postsdict as $post) {
            if ($post->parent == $parentpost->id) {
                $results[] = $post;
            }
        }
        return $results;
    }

    /**
     * Set the satisfiestime flag on every post.
     */
    private function checkpoststime() {
        foreach ($this->postsdict as $post) {
            $post->satisfiestime = $this->postsatisfiestime($post);
        }
    }

    /**
     * Load country codes for all post authors.
     */
    private function getusernationalities() {
        global $DB;
        $userids = [];
        foreach ($this->postsdict as $post) {
            if (!in_array($post->userid, $userids)) {
                $userids[] = $post->userid;
            }
        }
        if (!count($userids)) {
            return;
        }
        [$sql, $params] = $DB->get_in_or_equal($userids);
        $records = $DB->get_records_sql('SELECT id, country FROM {user} WHERE id ' . $sql, $params);
        foreach ($records as $record) {
            $this->nationalitiesdict[$record->id] = $record->country;
        }
    }

    /**
     * Return true if the post was created within the configured time range.
     *
     * @param engagedpost $post
     * @return bool
     */
    private function postsatisfiestime(engagedpost $post): bool {
        return (!$this->starttime || ($post->created >= $this->starttime))
            && (!$this->endtime || ($post->created <= $this->endtime));
    }

    /**
     * Return true if the reply satisfies the international condition.
     *
     * @param engagedpost $parent
     * @param engagedpost $reply
     * @return bool
     */
    protected function satisfiesinternational(engagedpost $parent, engagedpost $reply): bool {
        if (!$this->international) {
            return true;
        }
        return ($this->nationalitiesdict[$parent->userid] ?? '') !== ($this->nationalitiesdict[$reply->userid] ?? '');
    }

    /**
     * Calculate the engagement result for the given user.
     *
     * @param int $userid
     * @return engagementresult
     */
    abstract public function calculate(int $userid): engagementresult;
}
