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

use PHPUnit\Framework\Attributes\CoversClass;
use profilefield_pattern\local\config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/profile/definelib.php');
require_once($CFG->dirroot . '/user/profile/field/pattern/define.class.php');

/**
 * Tests for the task that rewrites stored values after a settings change, and its queueing
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(renormalise_values::class)]
#[CoversClass(\profile_define_pattern::class)]
final class renormalise_values_test extends \advanced_testcase {
    /**
     * Create a pattern field.
     *
     * @param array $settings pattern settings for param3
     * @param string $shortname
     * @return \stdClass
     */
    private function create_field(array $settings, string $shortname = 'cpf'): \stdClass {
        return $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'pattern', 'shortname' => $shortname, 'name' => $shortname,
            'param2' => 2048, 'param3' => config::encode($settings),
        ]);
    }

    /**
     * Store a value as it is, without the field type.
     *
     * @param \stdClass $field
     * @param string $value
     * @return int the user id
     */
    private function store(\stdClass $field, string $value): int {
        global $DB;
        $userid = (int) $this->getDataGenerator()->create_user()->id;
        $DB->insert_record('user_info_data', ['userid' => $userid, 'fieldid' => $field->id, 'data' => $value]);
        return $userid;
    }

    /**
     * The definition form data that saves a field with some pattern settings.
     *
     * @param \stdClass $field
     * @param array $pattern
     * @return \stdClass
     */
    private function definition(\stdClass $field, array $pattern): \stdClass {
        return (object) ['id' => $field->id, 'defaultdata' => '', 'param1' => 30, 'param2' => 2048, 'pattern' => $pattern];
    }

    /**
     * The adhoc tasks of this class waiting to run.
     *
     * @return array
     */
    private function queued(): array {
        return \core\task\manager::get_adhoc_tasks(renormalise_values::class);
    }

    /**
     * Saving an existing field with changed pattern settings queues the task; unchanged settings
     * and a new field do not.
     */
    public function test_a_settings_change_queues_the_task(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['preset' => 'cpf']);
        $define = new \profile_define_pattern();

        $define->define_save_preprocess($this->definition($field, ['preset' => 'cpf']));
        $this->assertSame([], $this->queued());
        $new = $this->definition($field, ['preset' => 'cpf', 'storeformat' => 'raw']);
        $new->id = 0;
        $define->define_save_preprocess($new);
        $this->assertSame([], $this->queued());

        $define->define_save_preprocess($this->definition($field, ['preset' => 'cpf', 'storeformat' => 'raw']));
        $tasks = $this->queued();
        $this->assertCount(1, $tasks);
        $this->assertSame((int) $field->id, (int) reset($tasks)->get_custom_data()->fieldid);
    }

    /**
     * The task rewrites the values the current settings accept, leaves a rejected one and the
     * other fields' values as they are.
     */
    public function test_execute_rewrites_accepted_values_only(): void {
        global $DB;
        $this->resetAfterTest();
        $field = $this->create_field(['preset' => 'cpf', 'storeformat' => 'raw']);
        $other = $this->create_field(['preset' => 'cpf'], 'other');
        $masked = $this->store($field, '529.982.247-25');
        $raw = $this->store($field, '11144477735');
        $rejected = $this->store($field, '529.982.247-26');
        $empty = $this->store($field, '');
        $untouched = $this->store($other, '111.444.777-35');

        $task = new renormalise_values();
        $task->set_custom_data((object) ['fieldid' => $field->id]);
        $this->expectOutputRegex('/cpf: 1 values rewritten, 1 left as stored/');
        $task->execute();

        $stored = fn(\stdClass $f, int $userid) =>
            $DB->get_field('user_info_data', 'data', ['fieldid' => $f->id, 'userid' => $userid]);
        $this->assertSame('52998224725', $stored($field, $masked));
        $this->assertSame('11144477735', $stored($field, $raw));
        $this->assertSame('529.982.247-26', $stored($field, $rejected));
        $this->assertSame('', $stored($field, $empty));
        $this->assertSame('111.444.777-35', $stored($other, $untouched));
    }

    /**
     * A field deleted, or no longer a pattern field, before the task runs is skipped.
     */
    public function test_execute_skips_a_missing_field(): void {
        global $DB;
        $this->resetAfterTest();
        $text = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'nick', 'name' => 'Nick',
        ]);
        $DB->insert_record('user_info_data', ['userid' => 2, 'fieldid' => $text->id, 'data' => '529.982.247-25']);

        $task = new renormalise_values();
        $task->set_custom_data((object) ['fieldid' => $text->id]);
        $task->execute();

        $this->assertSame('529.982.247-25', $DB->get_field('user_info_data', 'data', ['fieldid' => $text->id]));
        $this->expectOutputString('');
    }
}
