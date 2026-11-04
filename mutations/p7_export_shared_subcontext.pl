# export_user_data(): every field is exported under one subcontext, so each overwrites the previous.
s~\$subcontext = \[get_string\(.pluginname., .profilefield_pattern.\), \$record->shortname\];~\$subcontext = [get_string("pluginname", "profilefield_pattern")];~s;
