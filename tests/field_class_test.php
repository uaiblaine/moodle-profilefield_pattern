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

namespace profilefield_pattern;

use customfield_pattern\form\masked_text_element;
use PHPUnit\Framework\Attributes\CoversClass;
use profilefield_pattern\local\config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/profile/field/pattern/field.class.php');
require_once($CFG->dirroot . '/admin/tool/uploaduser/locallib.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Tests for profile_field_pattern
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\profile_field_pattern::class)]
final class field_class_test extends \advanced_testcase {
    /** @var string A valid CPF as typed with its mask. */
    private const CPF = '529.982.247-25';

    /** @var string The same CPF, typed characters only. */
    private const CPF_RAW = '52998224725';

    /** @var string A CPF with a wrong check digit. */
    private const CPF_BAD = '529.982.247-26';

    /**
     * Create a pattern profile field.
     *
     * @param array $settings pattern settings for param3
     * @param array $record other user_info_field columns
     * @return \stdClass the user_info_field record
     */
    private function create_field(array $settings = ['preset' => 'cpf'], array $record = []): \stdClass {
        return $this->getDataGenerator()->create_custom_profile_field($record + [
            'datatype' => 'pattern',
            'shortname' => 'doc',
            'name' => 'Document',
            'param1' => 20,
            'param2' => 2048,
            'param3' => config::encode($settings),
        ]);
    }

    /**
     * The field instance of a user.
     *
     * @param \stdClass $field
     * @param int $userid
     * @return \profile_field_pattern
     */
    private function instance(\stdClass $field, int $userid = 0): \profile_field_pattern {
        return new \profile_field_pattern((int) $field->id, $userid);
    }

    /**
     * Save a value for a user through profile_save_data(), without validating it first.
     *
     * @param int $userid
     * @param string $value
     * @return void
     */
    private function save(int $userid, string $value): void {
        profile_save_data((object) ['id' => $userid, 'profile_field_doc' => $value]);
    }

    /**
     * The stored value of a user, or false.
     *
     * @param \stdClass $field
     * @param int $userid
     * @return string|false
     */
    private function stored(\stdClass $field, int $userid) {
        global $DB;
        return $DB->get_field('user_info_data', 'data', ['fieldid' => $field->id, 'userid' => $userid]);
    }

    /**
     * A masked field gets the masked element with the mask attributes; one without a mask a text input.
     */
    public function test_edit_field_add_uses_the_masked_input_only_with_a_mask(): void {
        $this->resetAfterTest();

        $mform = new \MoodleQuickForm('test', 'post', '');
        $this->instance($this->create_field())->edit_field_add($mform);
        $element = $mform->getElement('profile_field_doc');
        $this->assertInstanceOf(masked_text_element::class, $element);
        $this->assertSame('999.999.999-99', $element->getAttribute('data-customfield-pattern-mask'));
        $this->assertSame('14', (string) $element->getAttribute('maxlength'));
        $this->assertSame('20', (string) $element->getAttribute('size'));

        $mform = new \MoodleQuickForm('test', 'post', '');
        $this->instance($this->create_field(['preset' => 'none'], ['shortname' => 'free', 'param2' => 40]))->edit_field_add($mform);
        $element = $mform->getElement('profile_field_free');
        $this->assertNotInstanceOf(masked_text_element::class, $element);
        $this->assertNull($element->getAttribute('data-customfield-pattern-mask'));
        $this->assertSame('40', (string) $element->getAttribute('maxlength'));
    }

    /**
     * The profile forms' validation rejects a value the pattern rejects, with the pattern's message.
     */
    public function test_profile_validation_reports_the_pattern_error(): void {
        $this->resetAfterTest();
        $this->create_field();
        $user = $this->getDataGenerator()->create_user();

        $errors = profile_validation((object) ['id' => $user->id, 'profile_field_doc' => self::CPF_BAD], []);
        $this->assertSame(['profile_field_doc' => get_string('errorcpf', 'customfield_pattern')], $errors);

        $this->assertSame([], profile_validation((object) ['id' => $user->id, 'profile_field_doc' => self::CPF], []));
        $this->assertSame([], profile_validation((object) ['id' => $user->id, 'profile_field_doc' => ''], []));
    }

    /**
     * Uniqueness compares stored values: the masked and the raw spelling are the same CPF.
     */
    public function test_uniqueness_compares_normalised_values(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['preset' => 'cpf', 'storeformat' => 'raw'], ['forceunique' => 1]);
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->save((int) $owner->id, self::CPF);
        $this->assertSame(self::CPF_RAW, $this->stored($field, (int) $owner->id));

        $instance = $this->instance($field, (int) $other->id);
        $this->assertSame(
            ['profile_field_doc' => get_string('valuealreadyused')],
            $instance->edit_validate_field((object) ['id' => $other->id, 'profile_field_doc' => self::CPF])
        );
        // The owner keeps their own value.
        $this->assertSame([], $this->instance($field, (int) $owner->id)
            ->edit_validate_field((object) ['id' => $owner->id, 'profile_field_doc' => self::CPF]));
    }

    /**
     * Uniqueness the other way round: a masked stored value against the raw spelling.
     */
    public function test_uniqueness_finds_a_masked_value_typed_raw(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['preset' => 'cpf'], ['forceunique' => 1]);
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->save((int) $owner->id, self::CPF_RAW);
        $this->assertSame(self::CPF, $this->stored($field, (int) $owner->id));

        $usernew = (object) ['id' => $other->id, 'profile_field_doc' => self::CPF_RAW];
        $this->assertSame(
            ['profile_field_doc' => get_string('valuealreadyused')],
            $this->instance($field, (int) $other->id)->edit_validate_field($usernew)
        );
    }

    /**
     * The signup form's validation reports a value the pattern rejects.
     */
    public function test_signup_validation_reports_the_pattern_error(): void {
        $this->resetAfterTest();
        set_config('registerauth', 'email');
        $this->create_field(['preset' => 'cpf'], ['signup' => 1]);
        $data = [
            'username' => 'newcomer', 'password' => 'Signup-Passw0rd!', 'email' => 'newcomer@example.com',
            'email2' => 'newcomer@example.com', 'firstname' => 'New', 'lastname' => 'Comer',
        ];

        $errors = signup_validate_data($data + ['profile_field_doc' => self::CPF_BAD], []);
        $this->assertSame(get_string('errorcpf', 'customfield_pattern'), $errors['profile_field_doc'] ?? null);

        // Control: the same form with a valid CPF raises nothing on the field.
        $this->assertArrayNotHasKey('profile_field_doc', signup_validate_data($data + ['profile_field_doc' => self::CPF], []));
    }

    /**
     * A field locked for the user is not checked: a value stored under earlier settings, which they
     * cannot change, must not block the save of their profile.
     */
    public function test_locked_field_is_not_validated_for_the_user(): void {
        global $DB;
        $this->resetAfterTest();
        $field = $this->create_field(['preset' => 'cpf'], ['locked' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id, 'data' => self::CPF_BAD]);
        $usernew = (object) ['id' => $user->id, 'profile_field_doc' => self::CPF_BAD];

        $this->setUser($user);
        $this->assertSame([], profile_validation($usernew, []));

        // Controls: the same value is checked when the field is not locked, and for an administrator.
        $DB->set_field('user_info_field', 'locked', 0, ['id' => $field->id]);
        $this->assertArrayHasKey('profile_field_doc', profile_validation($usernew, []));
        $DB->set_field('user_info_field', 'locked', 1, ['id' => $field->id]);
        $this->setAdminUser();
        $this->assertArrayHasKey('profile_field_doc', profile_validation($usernew, []));
    }

    /**
     * A locked field is frozen showing its stored value with the mask, not the raw stored string.
     */
    public function test_locked_field_shows_the_formatted_value(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['preset' => 'cpf', 'storeformat' => 'raw'], ['locked' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->save((int) $user->id, self::CPF);
        $this->setUser($user);

        $mform = new \MoodleQuickForm('test', 'post', '');
        $instance = $this->instance($field, (int) $user->id);
        $instance->edit_field_add($mform);
        $instance->edit_field_set_locked($mform);

        $this->assertTrue($mform->getElement('profile_field_doc')->isFrozen());
        $this->assertSame(self::CPF, $mform->getElementValue('profile_field_doc'));
    }

    /**
     * A saved value is stored per "Store as" and shown with its mask, escaped.
     */
    public function test_save_stores_normalised_and_display_restores_the_mask(): void {
        $this->resetAfterTest();
        $raw = $this->create_field(['preset' => 'cpf', 'storeformat' => 'raw']);
        $masked = $this->create_field(['preset' => 'cpf'], ['shortname' => 'masked']);
        $user = $this->getDataGenerator()->create_user();

        profile_save_data((object) ['id' => $user->id, 'profile_field_doc' => self::CPF, 'profile_field_masked' => self::CPF_RAW]);

        $this->assertSame(self::CPF_RAW, $this->stored($raw, (int) $user->id));
        $this->assertSame(self::CPF, $this->stored($masked, (int) $user->id));
        $this->assertSame(self::CPF, $this->instance($raw, (int) $user->id)->display_data());
        $this->assertSame(self::CPF, $this->instance($masked, (int) $user->id)->display_data());
    }

    /**
     * A free-text value is shown through format_string(), as core's text field does: escaped once.
     */
    public function test_display_escapes(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['preset' => 'none']);
        $user = $this->getDataGenerator()->create_user();
        $this->save((int) $user->id, 'A & B');

        $this->assertSame('A &amp; B', $this->instance($field, (int) $user->id)->display_data());
    }

    /**
     * The edit form shows a raw-stored value with its mask.
     */
    public function test_edit_load_user_data_formats_the_stored_value(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['preset' => 'cpf', 'storeformat' => 'raw']);
        $user = $this->getDataGenerator()->create_user();
        $this->save((int) $user->id, self::CPF);

        $loaded = new \stdClass();
        $this->instance($field, (int) $user->id)->edit_load_user_data($loaded);
        $this->assertSame(self::CPF, $loaded->profile_field_doc);
    }

    /**
     * A path that skips validation never stores a rejected value; the stored one stays.
     *
     * The first save is the control: the same call with a valid value does write.
     */
    public function test_save_without_validation_keeps_the_stored_value(): void {
        $this->resetAfterTest();
        $field = $this->create_field();
        $user = $this->getDataGenerator()->create_user();

        $this->save((int) $user->id, self::CPF);
        $this->assertSame(self::CPF, $this->stored($field, (int) $user->id));

        $this->save((int) $user->id, self::CPF_BAD);
        $this->assertSame(self::CPF, $this->stored($field, (int) $user->id));
        $this->assertDebuggingCalled("Profile field doc of user {$user->id} kept its stored value: " .
            get_string('errorcpf', 'customfield_pattern'));
    }

    /**
     * The refusal on paths without validation covers every rule, not only the check digit.
     */
    public function test_save_without_validation_refuses_mask_and_length_errors(): void {
        $this->resetAfterTest();
        $masked = $this->create_field(['preset' => 'custom', 'mask' => '999-AA']);
        $limited = $this->create_field(['preset' => 'none'], ['shortname' => 'short', 'param2' => 5]);
        $user = $this->getDataGenerator()->create_user();

        profile_save_data((object) ['id' => $user->id, 'profile_field_doc' => '123AB', 'profile_field_short' => 'abcde']);
        $this->assertSame('123-AB', $this->stored($masked, (int) $user->id));
        $this->assertSame('abcde', $this->stored($limited, (int) $user->id));

        profile_save_data((object) ['id' => $user->id, 'profile_field_doc' => 'ab123', 'profile_field_short' => 'abcdef']);
        $this->assertSame('123-AB', $this->stored($masked, (int) $user->id));
        $this->assertSame('abcde', $this->stored($limited, (int) $user->id));
        $this->assertDebuggingCalledCount(2);
    }

    /**
     * core_user_update_users stores a valid value and keeps the stored one over a rejected value.
     */
    public function test_update_users_web_service_refuses_a_rejected_value(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();
        $field = $this->create_field();
        $user = $this->getDataGenerator()->create_user();
        $call = fn(string $value) => \core_external\external_api::call_external_function('core_user_update_users', [
            'users' => [['id' => $user->id, 'customfields' => [['type' => 'doc', 'value' => $value]]]],
        ]);

        $call(self::CPF_RAW);
        $this->assertSame(self::CPF, $this->stored($field, (int) $user->id));

        $call(self::CPF_BAD);
        $this->assertSame(self::CPF, $this->stored($field, (int) $user->id));
        $this->assertDebuggingCalled();
    }

    /**
     * The upload users tool marks a rejected value invalid and converts an accepted one.
     */
    public function test_upload_users_checks_the_value(): void {
        $this->resetAfterTest();
        $this->create_field(['preset' => 'cpf', 'storeformat' => 'raw']);

        $row = ['username' => 'new', 'profile_field_doc' => self::CPF];
        $this->assertTrue(uu_check_custom_profile_data($row));

        $row = ['username' => 'new', 'profile_field_doc' => self::CPF_BAD];
        $this->assertFalse(uu_check_custom_profile_data($row));
        $this->assertContains(get_string('errorcpf', 'customfield_pattern') . ' (profile_field_doc)', $row['status']);

        $converted = uu_pre_process_custom_profile_data((object) ['profile_field_doc' => self::CPF]);
        $this->assertSame(self::CPF_RAW, $converted->profile_field_doc);

        // A rejected value converts to null, which edit_save_data() skips, so it is not saved even
        // where the tool converts without checking first.
        $converted = uu_pre_process_custom_profile_data((object) ['profile_field_doc' => self::CPF_BAD]);
        $this->assertNull($converted->profile_field_doc);

        $field = $this->create_field(['preset' => 'cpf'], ['shortname' => 'saved']);
        $user = $this->getDataGenerator()->create_user();
        profile_save_data((object) ['id' => $user->id, 'profile_field_saved' => self::CPF]);
        profile_save_data((object) ['id' => $user->id, 'profile_field_saved' => null]);
        $this->assertSame(self::CPF, $this->stored($field, (int) $user->id));
    }

    /**
     * The default value is shown with its mask in the edit form.
     */
    public function test_default_is_shown_formatted(): void {
        $this->resetAfterTest();
        $field = $this->create_field(['preset' => 'cpf', 'storeformat' => 'raw'], ['defaultdata' => self::CPF_RAW]);

        $mform = new \MoodleQuickForm('test', 'post', '');
        $instance = $this->instance($field);
        $instance->edit_field_add($mform);
        $instance->edit_field_set_default($mform);
        $this->assertSame(self::CPF, $mform->getElementValue('profile_field_doc'));
    }

    /**
     * A field whose param3 is damaged behaves as a field with no mask instead of failing.
     */
    public function test_damaged_param3_reads_as_no_mask(): void {
        $this->resetAfterTest();
        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'pattern', 'shortname' => 'doc', 'name' => 'Document', 'param2' => 2048, 'param3' => '{oops',
        ]);
        $user = $this->getDataGenerator()->create_user();

        $this->assertSame([], profile_validation((object) ['id' => $user->id, 'profile_field_doc' => 'anything'], []));
        $this->save((int) $user->id, ' anything ');
        $this->assertSame('anything', $this->stored($field, (int) $user->id));
    }
}
