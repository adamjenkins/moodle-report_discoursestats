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
 * Placeholder picker — inserts a clicked placeholder into the target textarea.
 *
 * @module     report_discoursestats/placeholder_picker
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Insert text at the cursor position in a textarea.
 *
 * @param {HTMLTextAreaElement} el
 * @param {string} text
 */
const insertAtCursor = (el, text) => {
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? el.value.length;
    el.value = el.value.substring(0, start) + text + el.value.substring(end);
    el.selectionStart = el.selectionEnd = start + text.length;
    el.focus();
};

/**
 * Attach click handlers to all placeholder picker containers.
 *
 * Each <details> element with data-placeholdertarget="<id>" will have its
 * child <code> elements wired so that clicking inserts the element's text
 * into the textarea identified by that id.
 */
export const init = () => {
    document.querySelectorAll('details[data-placeholdertarget]').forEach(details => {
        const targetId = details.dataset.placeholdertarget;
        const textarea = document.getElementById(targetId);
        if (!textarea) {
            return;
        }
        details.querySelectorAll('code[data-insert]').forEach(code => {
            code.style.cursor = 'pointer';
            code.title = 'Click to insert';
            code.addEventListener('click', () => insertAtCursor(textarea, code.dataset.insert));
        });
    });
};
