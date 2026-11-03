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
 * Definition form of a pattern profile field
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use customfield_pattern\api;
use profilefield_pattern\local\config;

/**
 * Definition form of a pattern profile field
 *
 * The pattern settings are edited as `pattern[<setting>]` elements and stored together in
 * param3 ({@see config}). core_user\form\profile_field_form::set_data_for_dynamic_submission()
 * loads the field record as it is, so param3 travels as a hidden element and
 * {@see self::define_after_data()} spreads it over the pattern elements.
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_define_pattern extends profile_define_base {
    /**
     * Add the elements of a pattern field to the definition form
     *
     * @param MoodleQuickForm $form
     * @return void
     */
    public function define_form_specific($form) {
        $form->addElement('text', 'defaultdata', get_string('profiledefaultdata', 'admin'), ['size' => 50]);
        $form->setType('defaultdata', PARAM_TEXT);

        $form->addElement('text', 'param1', get_string('profilefieldsize', 'admin'), ['size' => 6]);
        $form->setDefault('param1', config::DEFAULT_SIZE);
        $form->setType('param1', PARAM_INT);

        $form->addElement('text', 'param2', get_string('profilefieldmaxlength', 'admin'), ['size' => 6]);
        $form->setDefault('param2', config::DEFAULT_MAXLENGTH);
        $form->setType('param2', PARAM_INT);
        $form->addHelpButton('param2', 'profilefieldmaxlength', 'admin');

        // Only carries the stored settings into define_after_data(); define_save_preprocess() rewrites it.
        $form->addElement('hidden', 'param3');
        $form->setType('param3', PARAM_RAW);

        $form->addElement('select', 'pattern[preset]', get_string('preset', 'customfield_pattern'), api::presets());
        $form->addHelpButton('pattern[preset]', 'preset', 'customfield_pattern');
        $form->setDefault('pattern[preset]', 'none');

        $form->addElement('text', 'pattern[mask]', get_string('mask', 'customfield_pattern'), ['size' => 50]);
        $form->setType('pattern[mask]', PARAM_TEXT);
        $form->addHelpButton('pattern[mask]', 'mask', 'customfield_pattern');
        $form->hideIf('pattern[mask]', 'pattern[preset]', 'neq', 'custom');

        // Raw, not PARAM_TEXT: tag stripping would mangle a body such as [^<>]+.
        $form->addElement('text', 'pattern[regex]', get_string('regex', 'customfield_pattern'), ['size' => 50]);
        $form->setType('pattern[regex]', PARAM_RAW_TRIMMED);
        $form->addHelpButton('pattern[regex]', 'regex', 'customfield_pattern');

        $form->addElement('text', 'pattern[regexerror]', get_string('regexerror', 'customfield_pattern'), ['size' => 50]);
        $form->setType('pattern[regexerror]', PARAM_TEXT);
        $form->addHelpButton('pattern[regexerror]', 'regexerror', 'customfield_pattern');
        $form->hideIf('pattern[regexerror]', 'pattern[regex]', 'eq', '');

        $storeformats = [
            'masked' => get_string('storeformatmasked', 'customfield_pattern'),
            'raw' => get_string('storeformatraw', 'customfield_pattern'),
        ];
        $form->addElement('select', 'pattern[storeformat]', get_string('storeformat', 'customfield_pattern'), $storeformats);
        $form->addHelpButton('pattern[storeformat]', 'storeformat', 'customfield_pattern');
        $form->setDefault('pattern[storeformat]', 'masked');
        $form->hideIf('pattern[storeformat]', 'pattern[preset]', 'eq', 'none');
    }

    /**
     * Show the stored pattern settings in their elements
     *
     * A default set here yields to a submitted value, so this is harmless on submission.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function define_after_data(&$mform) {
        foreach (config::decode((string) $mform->getElementValue('param3')) as $key => $value) {
            $mform->setDefault('pattern[' . $key . ']', $value);
        }
    }

    /**
     * Validate the settings and the default value
     *
     * @param stdClass $data from the definition form
     * @param array $files
     * @return array element name => error message
     */
    public function define_validate_specific($data, $files) {
        $errors = [];

        $size = (int) ($data->param1 ?? 0);
        if ($size < 1 || $size > config::MAX_SIZE) {
            $errors['param1'] = get_string('errorconfigdisplaysize', 'customfield_pattern');
        }

        $maxlength = (int) ($data->param2 ?? 0);
        if ($maxlength < 1) {
            $errors['param2'] = get_string('errorconfigmaxlength', 'profilefield_pattern');
        }

        $config = $this->submitted_config($data);
        foreach (api::config_errors($config) as $setting => $error) {
            $element = $setting === 'maxlength' ? 'param2' : 'pattern[' . $setting . ']';
            if (!isset($errors[$element])) {
                $errors[$element] = $error;
            }
        }

        // The default value obeys the same rules as typed input, once the rules themselves are valid.
        $default = (string) ($data->defaultdata ?? '');
        if (!$errors && trim($default) !== '') {
            $error = api::validate($config, $default);
            if ($error !== null) {
                $errors['defaultdata'] = $error;
            }
        }

        return $errors;
    }

    /**
     * Pack the pattern settings into param3 and store the default value normalised
     *
     * @param stdClass $data from the definition form
     * @return stdClass the record to save
     */
    public function define_save_preprocess($data) {
        $config = $this->submitted_config($data);
        $data->param3 = config::encode($config);
        unset($data->pattern);
        if (isset($data->defaultdata)) {
            $data->defaultdata = api::normalise($config, (string) $data->defaultdata);
        }
        return $data;
    }

    /**
     * The {@see api} configuration the submitted form describes
     *
     * @param stdClass $data from the definition form
     * @return array
     */
    private function submitted_config(stdClass $data): array {
        $config = array_intersect_key((array) ($data->pattern ?? []), array_flip(config::PATTERN_KEYS));
        $config['maxlength'] = (string) (int) ($data->param2 ?? 0);
        return $config;
    }
}
