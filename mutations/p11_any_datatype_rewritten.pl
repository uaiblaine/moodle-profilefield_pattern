# renormalise_values: the field is read without checking it is still a pattern field.
s~\[.id. => \$fieldid, .datatype. => .pattern.\]~["id" => \$fieldid]~s;
