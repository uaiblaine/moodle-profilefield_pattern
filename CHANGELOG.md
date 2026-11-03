# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- The "Pattern" user profile field type: a text input with the mask and validation of
  `customfield_pattern` (CPF, CNPJ, CEP, Brazilian phone, custom masks, an optional regular
  expression), storing values formatted or raw and showing them formatted. Uniqueness compares
  stored values; the upload users tool reports rejected values; paths that skip validation
  never store a rejected value.
- Privacy provider for the field's values in `user_info_data`.
