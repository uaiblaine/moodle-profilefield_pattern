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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use PHPUnit\Framework\Attributes\CoversClass;
use profilefield_pattern\local\config;

/**
 * Tests for the privacy provider
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \stdClass A pattern field storing raw CPFs. */
    private \stdClass $field;

    /** @var \stdClass A core text field, whose rows the provider must leave alone. */
    private \stdClass $textfield;

    /**
     * Create one pattern field and one text field.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->field = $generator->create_custom_profile_field([
            'datatype' => 'pattern', 'shortname' => 'cpf', 'name' => 'CPF',
            'param2' => 14, 'param3' => config::encode(['preset' => 'cpf', 'storeformat' => 'raw']),
        ]);
        $this->textfield = $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'nick', 'name' => 'Nick']);
    }

    /**
     * A user with a value in both fields.
     *
     * @return \stdClass
     */
    private function user_with_values(): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_info_data', ['userid' => $user->id, 'fieldid' => $this->field->id, 'data' => '52998224725']);
        $DB->insert_record('user_info_data', ['userid' => $user->id, 'fieldid' => $this->textfield->id, 'data' => 'Zé']);
        return $user;
    }

    /**
     * The plugin counts as compliant: a metadata provider and a data provider.
     *
     * Core's compliance test is not in the plugin's testsuite, which is all CI runs.
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('profilefield_pattern'));
    }

    /**
     * A user with a value has their user context; one without has none.
     */
    public function test_contexts_and_users(): void {
        $user = $this->user_with_values();
        $other = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);

        $contextids = provider::get_contexts_for_userid((int) $user->id)->get_contextids();
        $this->assertSame([(int) $context->id], array_map('intval', $contextids));
        $this->assertSame([], provider::get_contexts_for_userid((int) $other->id)->get_contextids());

        $userlist = new userlist($context, 'profilefield_pattern');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int) $user->id], array_map('intval', $userlist->get_userids()));
    }

    /**
     * The value is exported as it is shown, the mask restored.
     */
    public function test_export_restores_the_mask(): void {
        $user = $this->user_with_values();
        $context = \context_user::instance($user->id);

        provider::export_user_data(new approved_contextlist($user, 'profilefield_pattern', [$context->id]));

        $exported = writer::with_context($context)->get_data([get_string('pluginname', 'profilefield_pattern')]);
        $this->assertSame('CPF', $exported->name);
        $this->assertSame('529.982.247-25', $exported->data);
    }

    /**
     * Deleting removes the user's pattern values and nothing else: not their text field value
     * (the control that the delete is scoped by datatype), not another user's values.
     */
    public function test_delete_is_scoped_to_pattern_fields_and_the_user(): void {
        global $DB;
        $user = $this->user_with_values();
        $other = $this->user_with_values();

        $contextids = [\context_user::instance($user->id)->id];
        provider::delete_data_for_user(new approved_contextlist($user, 'profilefield_pattern', $contextids));

        $this->assertFalse($DB->record_exists('user_info_data', ['userid' => $user->id, 'fieldid' => $this->field->id]));
        $this->assertTrue($DB->record_exists('user_info_data', ['userid' => $user->id, 'fieldid' => $this->textfield->id]));
        $this->assertTrue($DB->record_exists('user_info_data', ['userid' => $other->id, 'fieldid' => $this->field->id]));

        $userlist = new approved_userlist(\context_user::instance($other->id), 'profilefield_pattern', [$other->id]);
        provider::delete_data_for_users($userlist);
        $this->assertFalse($DB->record_exists('user_info_data', ['userid' => $other->id, 'fieldid' => $this->field->id]));

        // A user list that does not name the context's user deletes nothing.
        $fourth = $this->user_with_values();
        provider::delete_data_for_users(new approved_userlist(\context_user::instance($fourth->id), 'profilefield_pattern', []));
        $this->assertTrue($DB->record_exists('user_info_data', ['userid' => $fourth->id, 'fieldid' => $this->field->id]));

        $third = $this->user_with_values();
        provider::delete_data_for_all_users_in_context(\context_user::instance($third->id));
        $this->assertFalse($DB->record_exists('user_info_data', ['userid' => $third->id, 'fieldid' => $this->field->id]));
        $this->assertTrue($DB->record_exists('user_info_data', ['userid' => $third->id, 'fieldid' => $this->textfield->id]));
    }
}
