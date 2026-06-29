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
 * Engaged post data class for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats;

/**
 * A forum post used for engagement calculation.
 */
class engagedpost {
    /** @var int Post ID. */
    public $id;

    /** @var int Discussion ID. */
    public $discussion;

    /** @var int Parent post ID (0 = seed post). */
    public $parent;

    /** @var int Author user ID. */
    public $userid;

    /** @var int Unix timestamp the post was created. */
    public $created;

    /** @var bool True if post satisfies the report time condition. */
    public $satisfiestime;

    /** @var engagedpost[] Direct child posts. */
    public $children;

    /** @var string Comma-separated list of DB fields to select. */
    public const DB_OUT_FIELDS = 'id,discussion,parent,userid,created';
}
