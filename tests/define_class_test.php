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

use core_user\form\profile_field_form;
use PHPUnit\Framework\Attributes\CoversClass;
use profilefield_pattern\local\config;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/profile/definelib.php');
require_once($CFG->dirroot . '/user/profile/field/pattern/define.class.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Tests for profile_define_pattern
 *
 * The rules themselves are customfield_pattern's (its api_test); this covers how the
 * definition form reads, reports and stores them.
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\profile_define_pattern::class)]
final class define_class_test extends \advanced_testcase {
    /**
     * Submitted definition data with valid defaults, overridden by $overrides.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function data(array $overrides = []): \stdClass {
        return (object) array_replace_recursive([
            'id' => 0,
            'shortname' => 'cpf',
            'defaultdata' => '',
            'param1' => 30,
            'param2' => 2048,
            'pattern' => ['preset' => 'cpf', 'mask' => '', 'regex' => '', 'regexerror' => '', 'storeformat' => 'masked'],
        ], $overrides);
    }

    /**
     * Valid settings give no error.
     */
    public function test_valid_settings_pass(): void {
        $this->resetAfterTest();
        $this->assertSame([], (new \profile_define_pattern())->define_validate_specific($this->data(), []));
    }

    /**
     * Each broken setting is reported under its own element.
     */
    public function test_errors_land_under_their_elements(): void {
        $this->resetAfterTest();
        $define = new \profile_define_pattern();

        $this->assertSame(
            ['param1' => get_string('errorconfigdisplaysize', 'customfield_pattern')],
            $define->define_validate_specific($this->data(['param1' => 0]), [])
        );
        $this->assertSame(
            ['param2' => get_string('errorconfigmaxlength', 'profilefield_pattern')],
            $define->define_validate_specific($this->data(['param2' => 0]), [])
        );
        $this->assertSame(
            ['pattern[mask]' => get_string('errorconfigmaskrequired', 'customfield_pattern')],
            $define->define_validate_specific($this->data(['pattern' => ['preset' => 'custom']]), [])
        );
        $this->assertSame(
            ['pattern[preset]' => get_string('errorconfigpreset', 'customfield_pattern')],
            $define->define_validate_specific($this->data(['pattern' => ['preset' => 'rg']]), [])
        );
        $this->assertSame(
            ['pattern[regex]' => get_string('errorconfigregex', 'customfield_pattern')],
            $define->define_validate_specific($this->data(['pattern' => ['regex' => '[0-9']]), [])
        );
    }

    /**
     * The character limit must hold the longest value the mask stores: 14 masked, 11 raw.
     */
    public function test_character_limit_below_the_mask_goes_under_param2(): void {
        $this->resetAfterTest();
        $define = new \profile_define_pattern();

        $this->assertSame(
            ['param2' => get_string('errorconfigmaxlenmask', 'customfield_pattern', 14)],
            $define->define_validate_specific($this->data(['param2' => 13]), [])
        );
        $this->assertSame([], $define->define_validate_specific($this->data(['param2' => 14]), []));
        $this->assertSame([], $define->define_validate_specific(
            $this->data(['param2' => 11, 'pattern' => ['storeformat' => 'raw']]),
            []
        ));
    }

    /**
     * The default value obeys the field's rules.
     */
    public function test_default_value_is_validated(): void {
        $this->resetAfterTest();
        $define = new \profile_define_pattern();

        $this->assertSame(
            ['defaultdata' => get_string('errorcpf', 'customfield_pattern')],
            $define->define_validate_specific($this->data(['defaultdata' => '529.982.247-26']), [])
        );
        $this->assertSame([], $define->define_validate_specific($this->data(['defaultdata' => '52998224725']), []));
    }

    /**
     * Saving packs the pattern settings into param3 and normalises the default value.
     */
    public function test_save_preprocess_packs_param3(): void {
        $this->resetAfterTest();
        $data = (new \profile_define_pattern())->define_save_preprocess(
            $this->data(['defaultdata' => '529.982.247-25', 'pattern' => ['storeformat' => 'raw']])
        );

        $this->assertObjectNotHasProperty('pattern', $data);
        $this->assertSame(['preset' => 'cpf', 'storeformat' => 'raw'], config::decode($data->param3));
        $this->assertSame('52998224725', $data->defaultdata);
    }

    /**
     * The stored param3 is spread over the pattern elements when the form is shown.
     */
    public function test_after_data_shows_the_stored_settings(): void {
        $this->resetAfterTest();
        $define = new \profile_define_pattern();
        $mform = new \MoodleQuickForm('test', 'post', '');
        $define->define_form_specific($mform);

        // Control: before param3 is loaded the elements show the defaults.
        $this->assertSame(['none'], $mform->getElementValue('pattern[preset]'));

        $mform->setDefaults(['param3' => config::encode(['preset' => 'custom', 'mask' => '999-AA', 'storeformat' => 'raw'])]);
        $define->define_after_data($mform);

        $this->assertSame(['custom'], $mform->getElementValue('pattern[preset]'));
        $this->assertSame('999-AA', $mform->getElementValue('pattern[mask]'));
        $this->assertSame(['raw'], $mform->getElementValue('pattern[storeformat]'));
    }

    /**
     * The definition form, submitted as the modal submits it, stores the field.
     */
    public function test_definition_form_round_trip(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_custom_profile_field_category(['name' => 'Documents']);

        $formdata = [
            'id' => 0,
            'action' => 'editfield',
            'datatype' => 'pattern',
            'categoryid' => $category->id,
            'shortname' => 'cpf',
            'name' => 'CPF',
            'description' => ['text' => '', 'format' => FORMAT_HTML],
            'required' => 0,
            'locked' => 0,
            'forceunique' => 1,
            'signup' => 0,
            'visible' => PROFILE_VISIBLE_ALL,
            'defaultdata' => '',
            'param1' => 20,
            'param2' => 14,
            'param3' => '',
            'pattern' => ['preset' => 'cpf', 'mask' => '', 'regex' => '', 'regexerror' => '', 'storeformat' => 'raw'],
        ];
        $formdata = profile_field_form::mock_ajax_submit($formdata);
        $form = new profile_field_form(null, null, 'post', '', null, true, $formdata, true);
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();

        $field = $DB->get_record('user_info_field', ['shortname' => 'cpf'], '*', MUST_EXIST);
        $this->assertSame('pattern', $field->datatype);
        $this->assertSame('20', $field->param1);
        $this->assertSame('14', $field->param2);
        $this->assertSame(['preset' => 'cpf', 'storeformat' => 'raw'], config::decode($field->param3));
    }
}
