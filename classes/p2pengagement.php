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
 * Person-to-Person engagement calculator for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats;

/**
 * Person-to-Person Engagement: level increases each time the user replies to the same person in the same thread.
 */
class p2pengagement extends engagementcalculator {
    /**
     * Calculate P2P engagement for the given user.
     *
     * @param int $userid
     * @return engagementresult
     */
    public function calculate(int $userid): engagementresult {
        $result = new engagementresult();
        if (!isset($this->postsdict[$this->firstpost])) {
            return $result;
        }
        $this->travel($userid, $this->postsdict[$this->firstpost], $result);
        return $result;
    }

    /**
     * Recursively traverse the thread tree, accumulating P2P engagement.
     *
     * @param int $userid
     * @param engagedpost $post
     * @param engagementresult $result
     * @param array $userengagement Running reply-count per target user.
     */
    private function travel(int $userid, engagedpost $post, engagementresult $result, array &$userengagement = []) {
        foreach ($post->children as $childpost) {
            if ($childpost->userid != $post->userid && $childpost->userid == $userid) {
                if (!isset($userengagement[$post->userid])) {
                    $userengagement[$post->userid] = 0;
                }
                $userengagement[$post->userid]++;
                if ($childpost->satisfiestime && $this->satisfiesinternational($post, $childpost)) {
                    $result->increase($userengagement[$post->userid]);
                }
            }
            $this->travel($userid, $childpost, $result, $userengagement);
        }
    }
}
