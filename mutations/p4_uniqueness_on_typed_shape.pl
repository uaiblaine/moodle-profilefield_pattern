# edit_validate_field(): uniqueness compares the typed shape with stored values.
s~\$normalised->\{\$this->inputname\} = api::normalise\(\$config, \(string\) \$value\);~\$normalised->{\$this->inputname} = (string) \$value;~s;
