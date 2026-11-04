moodle-profilefield_pattern
===========================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-profilefield_pattern/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-profilefield_pattern/actions/workflows/ci.yml?query=branch%3Amain)

A user profile field with an input mask and validation: CPF, CNPJ, CEP, Brazilian phone
numbers, or a mask and regular expression of your own.

The field behaves like core's "Text input" profile field, adds a mask that formats the value
while the user types, and validates the value on the server before it is stored. The rules are
those of the [Pattern custom field](https://github.com/uaiblaine/moodle-customfield_pattern)
(`customfield_pattern`), which this plugin requires: a profile field and a course custom field
with the same settings accept, store and show the same values. It owns no tables: values live in
core's `user_info_data`.


Requirements
------------

| Moodle | Supported |
|---|---|
| 5.1 | yes |
| 5.2 | yes |
| 5.3 | yes |

- `$plugin->requires` is Moodle 5.1 (2025100600).
- [customfield_pattern](https://github.com/uaiblaine/moodle-customfield_pattern) 0.1.0
  (2026102300) or later, installed at `/customfield/field/pattern`.


Installation
------------

Install `customfield_pattern` first, then this plugin to folder `/user/profile/field/pattern`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.

Nothing needs configuring after installation: the type "Pattern" appears in the "Create a new
profile field" menu of Site administration > Users > User profile fields.


Usage
-----

### Settings of a field

Besides the settings every profile field has (short name, name, required, locked, unique,
signup, visibility):

- **Default value**: obeys the same rules as typed input, and is stored normalised.
- **Display size** and **Character limit**: as in "Text input". With a mask, the character limit
  must hold the longest value the mask stores.
- **Format**, **Custom mask**, **Regular expression**, **Regular expression error message** and
  **Store as**: as in the Pattern custom field; see its README for the presets, the mask syntax
  and how the regular expression is bounded.

### What users see

On their profile, on the signup form and on "Add a new user" / "Edit profile", the field is a
text input that formats the value as it is typed. On save the value is checked (mask, CPF/CNPJ
check digits, character limit, regular expression) and stored formatted or as the typed
characters only, per "Store as". It is shown formatted on the profile page, in report builder's
user profile field columns, in the `displayvalue` of the user web services and in a data export.
Places that read the stored value show it as stored, which with "Typed characters only" means
without the mask: the bulk user download, identity fields (participants, grader report), report
builder's filters and sorting, and `$USER->profile`.

A field locked for a user ("Is this field locked?") is not checked when that user saves the
profile: they cannot change it, and a value stored under earlier settings must not block the save.

"Should the data be unique?" compares values as they are stored, so `529.982.247-25` and
`52998224725` are the same CPF.

### Where values are and are not checked

| Path | Checked |
|---|---|
| Profile forms, "Add a new user", signup (including `core_auth_signup_user`) | yes, with the error under the field |
| Upload users (`tool_uploaduser`) | yes: a value the pattern rejects marks the row invalid in the preview and is not saved |
| User web services (`core_user_create_users`, `core_user_update_users`) and authentication plugins that map external attributes (`auth_db`, `auth_ldap`, `auth_oauth2`) | not reported: core calls no validation there, so the field refuses to store a value the pattern rejects and keeps the one it had; with developer debugging on, the refusal is reported through `debugging()`. Uniqueness is not checked on these paths, as for every core field type |
| `profile_save_custom_fields()` (authentication plugins' field mapping on `update_user_record()`) and direct writes to `user_info_data` | no: they bypass the field type entirely |


Capabilities
------------

The plugin defines no capabilities. Who may create and edit profile fields, and who may edit a
user's profile, is decided by core (`moodle/site:config`, `moodle/user:update`,
`moodle/user:editownprofile`).


Settings
--------

The plugin has no site settings. Each field is configured on its own definition form.


Data stored
-----------

The plugin owns no database tables. It uses two core tables:

- `user_info_field`: the field's settings, kept until an administrator deletes the field.
  `param1` holds the display size, `param2` the character limit (as for "Text input") and
  `param3` the pattern settings as a JSON object (format, mask, regular expression and its
  message, storage format); `param4` and `param5` are unused.
- `user_info_data`: one value per user and field, in `data`, as stored by "Store as". Core
  removes a user's values when the user is deleted, and every value of a field when the field is
  deleted.

The privacy provider declares `user_info_data`. A data request exports each value of a pattern
field, under the field's short name, with the field's name and description, as it is shown (mask
restored); a deletion request deletes the user's values of every pattern field.

Changing a field's format, mask or storage format queues an adhoc task that rewrites the field's
stored values in the new shape on the next cron run. A stored value the new settings reject is
left as it is.


Troubleshooting
---------------

- **"Pattern" is missing from the profile field menu**: `customfield_pattern` is not installed
  or is older than 2026110300.
- **The mask does not format while typing**: the JavaScript comes from `customfield_pattern`;
  rebuild or reinstall it. The server still normalises and validates the value on save.
- **A value stored under an earlier mask shows unformatted, or is refused when the user saves the
  profile again**: after a change of settings the adhoc task rewrites every value the new settings
  accept (run cron, or `admin/cli/adhoc_task.php --execute`); a value they reject is shown as
  stored and must be corrected on the next save of that profile, unless the field is locked.


Translations
------------

The English strings ship in the package. The Brazilian Portuguese translation
is maintained in this repository (`lang/pt_br`) and reaches sites through
[AMOS](https://lang.moodle.org/), as the plugins directory requires; it is left
out of the release package. The settings labels come from `customfield_pattern`.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).
The full text ships in `LICENSE` and `COPYING.txt`.

Copyright: 2026 Anderson Blaine
