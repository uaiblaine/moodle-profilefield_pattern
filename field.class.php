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
 * Pattern profile field
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use customfield_pattern\api;
use profilefield_pattern\local\config;

/**
 * Pattern profile field: a text input with the mask and validation of customfield_pattern
 *
 * Values are stored as {@see api::normalise()} returns them and shown as {@see api::format()}
 * returns them, so a profile field and a course custom field with the same settings accept,
 * store and show the same values.
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_pattern extends profile_field_base {
    /**
     * Add the text input, with the mask for the client-side formatter
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function edit_field_add($mform) {
        $config = $this->get_config();
        $size = (int) $this->field->param1;
        $attributes = ['size' => $size > 0 ? $size : config::DEFAULT_SIZE] + api::input_attributes($config);
        $mform->addElement(api::element_type($config), $this->inputname, format_string($this->field->name), $attributes);
        $mform->setType($this->inputname, PARAM_TEXT);
    }

    /**
     * Show the default value in its displayed (masked) shape
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function edit_field_set_default($mform) {
        $default = (string) ($this->field->defaultdata ?? '');
        if ($default !== '') {
            $mform->setDefault($this->inputname, api::format($this->get_config(), $default));
        }
    }

    /**
     * Put the stored value in the form in its displayed (masked) shape
     *
     * @param stdClass $user
     * @return void
     */
    public function edit_load_user_data($user) {
        if ($this->data !== null) {
            $user->{$this->inputname} = api::format($this->get_config(), (string) $this->data);
        }
    }

    /**
     * Freeze a locked field, showing its value in the displayed (masked) shape
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function edit_field_set_locked($mform) {
        parent::edit_field_set_locked($mform);
        if ($this->data !== null && $mform->elementExists($this->inputname) && $mform->getElement($this->inputname)->isFrozen()) {
            $mform->setConstant($this->inputname, api::format($this->get_config(), (string) $this->data));
        }
    }

    /**
     * Validate the typed value; uniqueness is checked on the value as it would be stored
     *
     * Called by the profile forms, the signup form and the upload users tool. A field locked for
     * this user is not checked: the form froze it ({@see profile_field_base::edit_field_set_locked()},
     * same condition) and submits the stored value, which may predate the current settings and
     * which the user cannot change, so an error there would block every save of the profile.
     *
     * @param stdClass $usernew
     * @return array element name => error message
     */
    public function edit_validate_field($usernew) {
        if ($this->is_locked() && !has_capability('moodle/user:update', context_system::instance())) {
            return [];
        }

        $value = $usernew->{$this->inputname} ?? '';
        if (!is_scalar($value) || trim((string) $value) === '') {
            return parent::edit_validate_field($usernew);
        }

        $config = $this->get_config();
        $errors = [];
        $error = api::validate($config, (string) $value);
        if ($error !== null) {
            $errors[$this->inputname] = $error;
        }

        // The stored values are normalised, so the uniqueness check must compare normalised values.
        $normalised = clone $usernew;
        $normalised->{$this->inputname} = api::normalise($config, (string) $value);
        return $errors + parent::edit_validate_field($normalised);
    }

    /**
     * Save the submitted value, never one the pattern rejects
     *
     * The forms validate before they save, but the user web services (core_user_create_users,
     * core_user_update_users) and the authentication plugins that map external attributes call
     * profile_save_data() without profile_validation(). A value the pattern rejects is then not
     * written and the stored one stays: what is stored has passed {@see api::validate()}. Those
     * callers have no error channel, so the refusal is reported to developers only.
     *
     * @param stdClass $usernew
     * @return void
     */
    public function edit_save_data($usernew) {
        $value = $usernew->{$this->inputname} ?? null;
        if (is_scalar($value) && trim((string) $value) !== '') {
            $error = api::validate($this->get_config(), (string) $value);
            if ($error !== null) {
                debugging("Profile field {$this->field->shortname} of user {$usernew->id} kept its stored value: " .
                    strip_tags($error), DEBUG_DEVELOPER);
                return;
            }
        }
        parent::edit_save_data($usernew);
    }

    /**
     * The value as it is stored: trimmed, fitted into the mask, masked or raw per the settings
     *
     * @param mixed $data the submitted value
     * @param stdClass|null $datarecord the record about to be saved
     * @return mixed the value to store; null and non-scalars are returned unchanged
     */
    public function edit_save_data_preprocess($data, $datarecord) {
        if (!is_scalar($data)) {
            return $data;
        }
        return api::normalise($this->get_config(), (string) $data);
    }

    /**
     * Convert a value from the upload users tool: normalised, or null when the pattern rejects it
     *
     * The tool reports a null as an invalid value before anything is saved.
     *
     * @param string $data the value in the file
     * @return string|null
     */
    public function convert_external_data($data) {
        $value = (string) $data;
        if (trim($value) === '') {
            return '';
        }
        $config = $this->get_config();
        if (api::validate($config, $value) !== null) {
            return null;
        }
        return api::normalise($config, $value);
    }

    /**
     * The stored value as it is shown: the mask restored, escaped
     *
     * Also what the report builder's user profile field columns show.
     *
     * @return string
     */
    public function display_data() {
        if ($this->data === null || (string) $this->data === '') {
            return '';
        }
        $shown = api::format($this->get_config(), (string) $this->data);
        return format_string($shown, true, ['context' => context_system::instance()]);
    }

    /**
     * The parameter type and null property of a submitted value
     *
     * @return array
     */
    public function get_field_properties() {
        return [PARAM_TEXT, NULL_NOT_ALLOWED];
    }

    /**
     * The {@see api} configuration of this field
     *
     * @return array
     */
    private function get_config(): array {
        return $this->field ? config::from_field($this->field) : [];
    }
}
