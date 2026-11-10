# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Changed

- One branch per Moodle version: `MOODLE_501_STABLE`, `MOODLE_502_STABLE` and `MOODLE_503_STABLE`
  (`main`). This branch supports Moodle 5.2 only and numbers its versions from 2026042000; a site that
  installed a development build (2026110301) must set the plugin's version row below 2026042000 before
  upgrading.

### Added

- The "Pattern" user profile field type: a text input with the mask and validation of
  `customfield_pattern` (CPF, CNPJ, CEP, Brazilian phone, custom masks, an optional regular
  expression), storing values formatted or raw and showing them formatted. Uniqueness compares
  stored values; the upload users tool reports rejected values; paths that skip validation
  never store a rejected value.
- Privacy provider for the field's values in `user_info_data`, one subcontext per field (a
  shared subcontext kept only the last field of a user with several).
- Changing a field's pattern settings queues an adhoc task that rewrites the stored values in the
  new shape, so uniqueness, report filters and the bulk download keep seeing one shape.
- A field locked for the user is not validated when they save their profile, so a value stored
  under earlier settings cannot block the save.
- A value refused on a path without validation (user web services, authentication sync) is
  reported through `debugging()` at developer level, unless it is the stored value: a locked
  field's frozen form submits the stored value, which may predate the current settings, and saving
  that profile no longer leaves a developer trace.
- Requires `customfield_pattern` 2026042000, its 5.2 branch, whose `api::element_type()` names the input
  element.
