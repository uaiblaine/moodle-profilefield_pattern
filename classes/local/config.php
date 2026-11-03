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

namespace profilefield_pattern\local;

use customfield_pattern\api;

/**
 * Where a pattern field keeps its settings in user_info_field, and how they are read back
 *
 * `user_info_field` has five free columns and a pattern field has seven settings, so:
 *
 * - `param1`: the form input size, as profilefield_text;
 * - `param2`: the maximum number of characters, as profilefield_text, and the `maxlength` of
 *   the {@see api} configuration;
 * - `param3`: the rest of the {@see api} configuration (`preset`, `mask`, `regex`,
 *   `regexerror`, `storeformat`) as a JSON object, with `version` = {@see api::CONFIG_VERSION};
 * - `param4`, `param5`: unused.
 *
 * Size and maximum length keep profilefield_text's columns so a reader of `user_info_field` that
 * knows text fields (an external function, a report) finds them where it expects them.
 *
 * Static helpers live here, not in define.class.php or field.class.php: the plugin validator
 * of the 5.2 CI leg parses those files and fatals on a member with two modifiers.
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config {
    /** @var array The {@see api} settings param3 holds, in the order the form shows them. */
    public const PATTERN_KEYS = ['preset', 'mask', 'regex', 'regexerror', 'storeformat'];

    /** @var int Default form input size, as profilefield_text. */
    public const DEFAULT_SIZE = 30;

    /** @var int Largest form input size. */
    public const MAX_SIZE = 200;

    /** @var int Default maximum number of characters, as profilefield_text. */
    public const DEFAULT_MAXLENGTH = 2048;

    /**
     * The {@see api} configuration of a field record
     *
     * @param \stdClass $field a user_info_field record (param2 and param3 are read)
     * @return array the configuration (see {@see api}); empty settings are left out
     */
    public static function from_field(\stdClass $field): array {
        $config = self::decode((string) ($field->param3 ?? ''));
        $maxlength = (int) ($field->param2 ?? 0);
        if ($maxlength > 0) {
            $config['maxlength'] = (string) $maxlength;
        }
        return $config;
    }

    /**
     * The pattern settings param3 holds
     *
     * Anything but a JSON object gives no settings, so a damaged value reads as a field with
     * no mask instead of failing every profile page.
     *
     * @param string $json the value of param3
     * @return array setting => string value, only the {@see self::PATTERN_KEYS} present
     */
    public static function decode(string $json): array {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $settings = [];
        foreach (self::PATTERN_KEYS as $key) {
            if (isset($decoded[$key]) && is_scalar($decoded[$key]) && (string) $decoded[$key] !== '') {
                $settings[$key] = (string) $decoded[$key];
            }
        }
        return $settings;
    }

    /**
     * The value of param3 for some pattern settings
     *
     * @param array $settings setting => value; keys other than {@see self::PATTERN_KEYS},
     *      non-scalar values and empty values are left out
     * @return string a JSON object
     */
    public static function encode(array $settings): string {
        $stored = ['version' => api::CONFIG_VERSION];
        foreach (self::PATTERN_KEYS as $key) {
            $value = $settings[$key] ?? '';
            if (is_scalar($value) && trim((string) $value) !== '') {
                $stored[$key] = trim((string) $value);
            }
        }
        return json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
