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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the param3 encoding
 *
 * @package    profilefield_pattern
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(config::class)]
final class config_test extends \basic_testcase {
    /**
     * Encoding keeps the pattern settings, trimmed and non-empty, and stamps the API version.
     */
    public function test_encode_keeps_only_the_pattern_settings(): void {
        $json = config::encode([
            'preset' => 'custom',
            'mask' => ' 999-AA ',
            'regex' => '',
            'regexerror' => ['not', 'scalar'],
            'storeformat' => 'raw',
            'maxlength' => '20',
            'other' => 'x',
        ]);

        $this->assertSame(
            ['version' => api::CONFIG_VERSION, 'preset' => 'custom', 'mask' => '999-AA', 'storeformat' => 'raw'],
            json_decode($json, true)
        );
    }

    /**
     * Decoding returns what encoding kept, without the version stamp.
     */
    public function test_decode_reverses_encode(): void {
        $settings = ['preset' => 'custom', 'mask' => '(99) 9999-9999|(99) 99999-9999', 'regex' => '[0-9() -]+',
            'regexerror' => 'Só números', 'storeformat' => 'masked'];

        $this->assertSame($settings, config::decode(config::encode($settings)));
    }

    /**
     * A param3 that is not a JSON object reads as no settings instead of failing.
     */
    public function test_decode_tolerates_damaged_values(): void {
        $this->assertSame([], config::decode(''));
        $this->assertSame([], config::decode('not json'));
        $this->assertSame([], config::decode('"cpf"'));
        $this->assertSame(['preset' => 'cpf'], config::decode('{"preset": "cpf", "mask": {"a": 1}, "unknown": "x"}'));
    }

    /**
     * The configuration of a field joins param3 with param2 as maxlength.
     */
    public function test_from_field_adds_the_character_limit(): void {
        $field = (object) ['param1' => '30', 'param2' => '14', 'param3' => config::encode(['preset' => 'cpf'])];
        $this->assertSame(['preset' => 'cpf', 'maxlength' => '14'], config::from_field($field));

        $field->param2 = '';
        $this->assertSame(['preset' => 'cpf'], config::from_field($field));
        $this->assertSame([], config::from_field((object) []));
    }
}
