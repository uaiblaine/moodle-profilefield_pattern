# edit_validate_field(): the lock skips validation for administrators too, who can edit the field.
s~if \(\$this->is_locked\(\) && !has_capability\(.moodle/user:update., context_system::instance\(\)\)\) \{~if (\$this->is_locked()) {~s;
