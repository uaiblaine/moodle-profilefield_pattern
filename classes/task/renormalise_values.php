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

namespace profilefield_pattern\task;

use customfield_pattern\api;
use profilefield_pattern\local\config;

/**
 * Rewrite the stored values of one pattern field in the shape its current settings store
 *
 * Queued when a field's pattern settings change ({@see \profile_define_pattern::define_save_preprocess()}),
 * so that uniqueness, report filters and the bulk download, which read the stored value, keep
 * seeing one shape. The settings are read when the task runs, not when it was queued. A value the
 * current settings reject is left as it is: it cannot be rewritten without guessing.
 *
 * Custom data: `fieldid`.
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renormalise_values extends \core\task\adhoc_task {
    /**
     * Queue the task for a field, once
     *
     * @param int $fieldid user_info_field id
     * @return void
     */
    public static function queue(int $fieldid): void {
        $task = new self();
        $task->set_custom_data((object) ['fieldid' => $fieldid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Rewrite the field's values that the current settings accept but would store differently
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $fieldid = (int) ($this->get_custom_data()->fieldid ?? 0);
        $field = $DB->get_record('user_info_field', ['id' => $fieldid, 'datatype' => 'pattern']);
        if (!$field) {
            return;
        }

        $config = config::from_field($field);
        $rewritten = 0;
        $rejected = 0;
        $rows = $DB->get_recordset('user_info_data', ['fieldid' => $fieldid], 'id', 'id, data');
        foreach ($rows as $row) {
            $stored = (string) $row->data;
            if (trim($stored) === '') {
                continue;
            }
            if (api::validate($config, $stored) !== null) {
                $rejected++;
                continue;
            }
            $normalised = api::normalise($config, $stored);
            if ($normalised !== $stored) {
                $DB->set_field('user_info_data', 'data', $normalised, ['id' => $row->id]);
                $rewritten++;
            }
        }
        $rows->close();

        mtrace("Profile field {$field->shortname}: {$rewritten} values rewritten, {$rejected} left as stored " .
            "because the current settings reject them.");
    }
}
