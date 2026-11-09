# edit_save_data(): a value the pattern rejects is written on paths without validation.
s~(DEBUG_DEVELOPER\);\n\s*\}\n)\s*return;\n~$1~s;
