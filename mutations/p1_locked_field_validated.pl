# edit_validate_field(): a field locked for the user is validated anyway.
s~if \(\$this->is_locked\(\) && !has_capability\(.moodle/user:update., context_system::instance\(\)\)\) \{~if (false) {~s;
