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
 * Engagement result accumulator for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats;

/**
 * Accumulated engagement result for a single user.
 */
class engagementresult {
    /** @var int[] Post counts keyed by engagement level number. */
    public $levels = [];

    /**
     * Increment the count for a given engagement level.
     *
     * @param int $level
     * @param int $amount
     */
    public function increase(int $level, int $amount = 1) {
        if (!isset($this->levels[$level])) {
            $this->levels[$level] = $amount;
            return;
        }
        $this->levels[$level] += $amount;
    }

    /**
     * Merge another result into this one.
     *
     * @param engagementresult $result
     */
    public function add(engagementresult $result) {
        foreach ($result->levels as $level => $value) {
            $this->increase($level, $value);
        }
    }

    /**
     * Return the count for a given level (0 if not present).
     *
     * @param int $level
     * @return int
     */
    public function getlevel(int $level): int {
        return isset($this->levels[$level]) ? $this->levels[$level] : 0;
    }

    /**
     * Return the level-1 count.
     *
     * @return int
     */
    public function getl1(): int {
        return $this->getlevel(1);
    }

    /**
     * Return the level-2 count.
     *
     * @return int
     */
    public function getl2(): int {
        return $this->getlevel(2);
    }

    /**
     * Return the level-3 count.
     *
     * @return int
     */
    public function getl3(): int {
        return $this->getlevel(3);
    }

    /**
     * Return the combined count for all levels ≥ 4.
     *
     * @return int
     */
    public function getl4up(): int {
        $sum = 0;
        foreach ($this->levels as $level => $value) {
            if ($level >= 4) {
                $sum += $value;
            }
        }
        return $sum;
    }

    /**
     * Return the highest engagement level reached, or null if no engagements.
     *
     * @return int|null
     */
    public function getmax() {
        return count($this->levels) > 0 ? max(array_keys($this->levels)) : null;
    }

    /**
     * Return the weighted average engagement level, or null if no engagements.
     *
     * @return float|null
     */
    public function getaverage() {
        $sum   = 0;
        $count = 0;
        foreach ($this->levels as $level => $value) {
            $sum   += $level * $value;
            $count += $value;
        }
        return $count ? round($sum / $count, 2) : null;
    }
}
