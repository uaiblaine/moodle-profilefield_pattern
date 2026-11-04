# Claude instructions for `profilefield_pattern`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin, as rules
with a one-line reason each; keep it under ~250 lines, because every session
and every subagent pays for it on every cache write. Measurements, incidents
and the long version of a gotcha go to `docs/` (export-ignored) with a pointer
from the rule.

Plugin context: a Moodle **user profile field type** plugin ("Pattern") that adds a single-line
text field with the input mask and validation of `customfield_pattern` (CPF, CNPJ numeric and
alphanumeric, CEP, Brazilian phone, custom masks, an optional anchored regex), shaped like core's
`profilefield_text`. It owns no tables: settings live in `user_info_field` (param1 size, param2
character limit, param3 the pattern settings as JSON) and values in `user_info_data.data`. Every
rule comes from `\customfield_pattern\api` (hard dependency in `version.php`); this plugin is the
adapter. Supports Moodle **5.1 through 5.3** (`$plugin->requires = 2025100600`,
`$plugin->supported = [501, 503]`), one branch, `main`, like its dependency.
CI is the reusable workflow of `uaiblaine/moodle-workflows` (the fork of MAH's), one job per supported
branch in `.github/workflows/ci.yml` — **update those jobs when `supported`
changes**. Development happens on the m503 stack; this repo is mounted at
`user/profile/field/pattern` into the stacks `~/dev/moodle-dev/plugins.conf` names.

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-profilefield_pattern                      # full CI locally before any push
mdl phpunit m503 profilefield_pattern           # targeted tests
mdl behat m503 @profilefield_pattern            # Behat smoke tests
mdl purge m503                           # after PHP changes that affect output
```

In a Claude Code cloud session none of `mdl`, the stacks or `~/dev/CLAUDE.md` exist. The cloud
environment's setup script (`moodle-dev/cloud/setup.sh`) installs `moodle-plugin-ci` and the `mpci`
runner instead: `mpci` runs every gate on the highest branch in `ci.yml`, `mpci --branch <branch>`
another one, `mpci --reuse --only phpunit --filter <test>` iterates. It cannot run Behat, PHP 8.4 or
MariaDB, so the owner runs the gate the change's tier asks (fleet `CLAUDE.md` section 4; the
matrix, `mdl ci moodle-profilefield_pattern --matrix --behat`, for a tier-3 change) before merging: push a branch,
open the pull request, leave the merge to them. If `which mpci` prints nothing, the session was resumed on
a container that skipped the setup script: attach `moodle-dev` and run `bash cloud/setup.sh` from it.
See `moodle-dev/cloud/README.md`.

## Code layout

```
define.class.php        definition form: pattern[<setting>] elements, packed into param3 on save
field.class.php         the field: masked input, validation, normalised save, formatted display
classes/local/config.php  where the settings live in user_info_field, param3 encode/decode
classes/privacy/        user_info_data rows of pattern fields, as profilefield_text does
```

## Versions

`$plugin->version` is a date-based number (`YYYYMMDDXX`) while the plugin ships one branch for
every Moodle version it supports. When it splits into one branch per Moodle version, each branch
numbers itself in the namespace of its Moodle version (`20241007XX` for 4.5, `20251006XX` for 5.1,
`20260420XX` for 5.2), starting at its `$plugin->requires` value and counting up on that branch
alone, because moodle.org needs a different number for each Moodle-specific release. The rule and
its consequences for upgrade steps and cherry-picks are in the fleet file (`~/dev/CLAUDE.md`,
"XMLDB, upgrades, privacy, versioning").

## Architecture gotchas

- **No rule of its own.** Validation, normalisation, display and the input's attributes all go
  through `\customfield_pattern\api`, never `customfield_pattern\local\*` (another plugin's
  internals): a course custom field and a profile field with the same settings must agree.
- **param3 reaches the form as a hidden element.** `core_user\form\profile_field_form` loads the
  field record with `set_data()` and gives the define class no hook before it, so
  `define_after_data()` spreads the hidden `param3` over the `pattern[...]` elements and
  `define_save_preprocess()` rewrites it from them. A `hideIf`-hidden element is not submitted
  (5.3), so a setting hidden at save time is dropped from param3, never given a `required` rule.
- **Uniqueness compares stored values.** Core's `edit_validate_field()` compares the submitted
  value with `user_info_data.data`; the override hands it the normalised value.
- **Not every write is validated.** `profile_save_data()` without `profile_validation()` (user web
  services, auth plugins' attribute mapping) reaches `edit_save_data()` unchecked: the override
  refuses to write a value the pattern rejects. `profile_save_custom_fields()` bypasses the field
  class entirely. The README's "Where values are and are not checked" table is the contract.
- **Shared constants and static helpers live in `classes/local/config.php`**, autoloaded, so the
  define and field classes (loaded by core through `require_once`) and the tests share one copy.

## Testing notes

- Fields are created with `create_custom_profile_field(['datatype' => 'pattern', 'param3' =>
  config::encode([...])])`; the definition form is exercised through
  `core_user\form\profile_field_form::mock_ajax_submit()`.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.
