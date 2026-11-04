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

namespace profilefield_pattern\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use customfield_pattern\api;
use profilefield_pattern\local\config;

/**
 * Privacy provider: the values of pattern profile fields, in core's user_info_data
 *
 * The same shape as core's profilefield_text provider, for the rows of pattern fields. Values
 * are exported as they are shown, the mask restored.
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data the plugin keeps
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        return $collection->add_database_table('user_info_data', [
            'userid' => 'privacy:metadata:profilefield_pattern:userid',
            'fieldid' => 'privacy:metadata:profilefield_pattern:fieldid',
            'data' => 'privacy:metadata:profilefield_pattern:data',
            'dataformat' => 'privacy:metadata:profilefield_pattern:dataformat',
        ], 'privacy:metadata:profilefield_pattern:tableexplanation');
    }

    /**
     * The user context of a user who has a value in a pattern field
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {user_info_data} uda
                  JOIN {user_info_field} uif ON uda.fieldid = uif.id
                  JOIN {context} ctx ON ctx.instanceid = uda.userid
                       AND ctx.contextlevel = :contextlevel
                 WHERE uda.userid = :userid
                       AND uif.datatype = :datatype";
        $params = [
            'userid' => $userid,
            'contextlevel' => CONTEXT_USER,
            'datatype' => 'pattern',
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * The user of a user context, when they have a value in a pattern field
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }

        $sql = "SELECT uda.userid
                  FROM {user_info_data} uda
                  JOIN {user_info_field} uif ON uda.fieldid = uif.id
                 WHERE uda.userid = :userid
                       AND uif.datatype = :datatype";
        $params = [
            'userid' => $context->instanceid,
            'datatype' => 'pattern',
        ];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export the user's values, one record per field, under the plugin's name and the field's shortname
     *
     * Each field gets its own subcontext: export_data() writes one data.json per subcontext, so a
     * shared one would keep only the last field.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_USER || $context->instanceid != $user->id) {
                continue;
            }
            foreach (self::get_records((int) $user->id) as $record) {
                $data = (object) [
                    'name' => $record->name,
                    'description' => $record->description,
                    'data' => api::format(config::from_field($record), (string) $record->data),
                ];
                $subcontext = [get_string('pluginname', 'profilefield_pattern'), $record->shortname];
                writer::with_context($context)->export_data($subcontext, $data);
            }
        }
    }

    /**
     * Delete the values of the user a user context belongs to
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel == CONTEXT_USER) {
            self::delete_data((int) $context->instanceid);
        }
    }

    /**
     * Delete the values of the user of an approved user context
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_user && in_array((int) $context->instanceid, $userlist->get_userids())) {
            self::delete_data((int) $context->instanceid);
        }
    }

    /**
     * Delete the user's values
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $user->id) {
                self::delete_data((int) $context->instanceid);
            }
        }
    }

    /**
     * Delete one user's values of every pattern field
     *
     * @param int $userid
     * @return void
     */
    protected static function delete_data(int $userid): void {
        global $DB;
        $DB->delete_records_select(
            'user_info_data',
            'fieldid IN (SELECT id FROM {user_info_field} WHERE datatype = :datatype) AND userid = :userid',
            ['datatype' => 'pattern', 'userid' => $userid]
        );
    }

    /**
     * One user's values of every pattern field, with the field's name and settings
     *
     * @param int $userid
     * @return array records with shortname, name, description, param2, param3 and data
     */
    protected static function get_records(int $userid): array {
        global $DB;
        $sql = "SELECT uda.id, uif.shortname, uif.name, uif.description, uif.param2, uif.param3, uda.data
                  FROM {user_info_data} uda
                  JOIN {user_info_field} uif ON uda.fieldid = uif.id
                 WHERE uda.userid = :userid
                       AND uif.datatype = :datatype
              ORDER BY uif.sortorder, uda.id";
        return $DB->get_records_sql($sql, ['userid' => $userid, 'datatype' => 'pattern']);
    }
}
