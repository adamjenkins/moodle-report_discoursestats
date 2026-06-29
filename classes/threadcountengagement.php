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
 * Thread Total Count engagement calculator for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats;

/**
 * Thread Total Count Engagement: level increases each time the user participates in the same thread.
 */
class threadcountengagement extends engagementcalculator {
    /**
     * Calculate thread-count engagement for the given user.
     *
     * @param int $userid
     * @return engagementresult
     */
    public function calculate(int $userid): engagementresult {
        $result = new engagementresult();
        if (!isset($this->postsdict[$this->firstpost])) {
            return $result;
        }
        $threads = $this->postsdict[$this->firstpost]->children;
        foreach ($threads as $post) {
            $countinthread = 0;
            if ($post->userid == $userid && $post->userid != $this->postsdict[$this->firstpost]->userid) {
                $countinthread++;
                if ($post->satisfiestime) {
                    $result->increase(1);
                }
            }
            $this->travel($userid, $post, $result, $countinthread);
        }
        return $result;
    }

    /**
     * Recursively traverse a sub-thread, accumulating thread-count engagement.
     *
     * @param int $userid
     * @param engagedpost $post
     * @param engagementresult $result
     * @param int $count Running participation count in the current thread.
     */
    public function travel(int $userid, engagedpost $post, engagementresult $result, int &$count) {
        foreach ($post->children as $childpost) {
            if (
                $childpost->userid != $post->userid
                && $childpost->userid == $userid
                && $childpost->satisfiestime
                && $this->satisfiesinternational($post, $childpost)
            ) {
                $count++;
                $result->increase($count);
            }
            $this->travel($userid, $childpost, $result, $count);
        }
    }
}
