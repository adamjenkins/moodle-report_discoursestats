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
 * Engagement factory class for report_discoursestats.
 *
 * @package    report_discoursestats
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_discoursestats;

/**
 * Factory and registry for engagement calculation methods.
 */
class engagement {
    /** @var string Plugin component name. */
    private const COMPONENT = 'report_discoursestats';

    /** @var int Person-to-Person Engagement method identifier. */
    public const PERSON_TO_PERSON = 1;

    /** @var int Thread Total Count Engagement method identifier. */
    public const THREAD_TOTAL_COUNT = 2;

    /** @var int Thread Engagement method identifier. */
    public const THREAD_ENGAGEMENT = 3;

    /**
     * Return a localised string for the given engagement method.
     *
     * @param int $method
     * @param string $suffix Optional string key suffix (e.g. '_description').
     * @return string
     */
    private static function getstring(int $method, string $suffix = ''): string {
        switch ($method) {
            case static::PERSON_TO_PERSON:
                return get_string('engagement_persontoperson' . $suffix, static::COMPONENT);
            case static::THREAD_TOTAL_COUNT:
                return get_string('engagement_threadtotalcount' . $suffix, static::COMPONENT);
            case static::THREAD_ENGAGEMENT:
                return get_string('engagement_threadengagement' . $suffix, static::COMPONENT);
        }
        throw new \moodle_exception('invalidentgmethod', static::COMPONENT);
    }

    /**
     * Instantiate a concrete engagement calculator for the given method.
     *
     * @param int $method
     * @param int $discussionid
     * @param int $starttime
     * @param int $endtime
     * @param bool $international
     * @return engagementcalculator
     */
    public static function getinstancefrommethod(
        int $method,
        int $discussionid,
        int $starttime = 0,
        int $endtime = 0,
        bool $international = false
    ): engagementcalculator {
        switch ($method) {
            case static::PERSON_TO_PERSON:
                return new p2pengagement($discussionid, $starttime, $endtime, $international);
            case static::THREAD_TOTAL_COUNT:
                return new threadcountengagement($discussionid, $starttime, $endtime, $international);
            case static::THREAD_ENGAGEMENT:
                return new threadengagement($discussionid, $starttime, $endtime, $international);
        }
        throw new \moodle_exception('invalidentgmethod', static::COMPONENT);
    }

    /**
     * Return the localised name of an engagement method.
     *
     * @param int $method
     * @return string
     */
    public static function getname(int $method): string {
        return static::getstring($method);
    }

    /**
     * Return the localised description of an engagement method.
     *
     * @param int $method
     * @return string
     */
    public static function getdescription(int $method): string {
        return static::getstring($method, '_description');
    }

    /**
     * Return all available engagement method identifiers.
     *
     * @return int[]
     */
    public static function getallmethods(): array {
        return [static::PERSON_TO_PERSON, static::THREAD_TOTAL_COUNT, static::THREAD_ENGAGEMENT];
    }

    /**
     * Return an associative array suitable for a Moodle select element.
     *
     * @return array<int,string>
     */
    public static function getselectoptions(): array {
        $options = [];
        foreach (static::getallmethods() as $option) {
            $options[$option] = static::getname($option);
        }
        return $options;
    }

    /**
     * Add the engagement method and international checkbox to a form.
     *
     * @param \MoodleQuickForm $mform
     * @param string $elementname
     * @param int|null $defaultvalue
     * @param string $internationalname
     * @param bool $defaultinternational
     */
    public static function addtoform(
        $mform,
        string $elementname = 'engagementmethod',
        $defaultvalue = null,
        string $internationalname = 'engagementinternational',
        bool $defaultinternational = false
    ) {
        $mform->addElement('select', $elementname, get_string('engagement_method', static::COMPONENT), self::getselectoptions());
        $mform->addHelpButton($elementname, 'engagement_method', static::COMPONENT);
        if (is_null($defaultvalue)) {
            $defaultvalue = get_config(static::COMPONENT, 'defaultengagementmethod');
        }
        $mform->setDefault($elementname, $defaultvalue);

        $mform->addElement('checkbox', $internationalname, get_string('engagement_international', static::COMPONENT));
        $mform->setDefault($internationalname, $defaultinternational);
    }
}
