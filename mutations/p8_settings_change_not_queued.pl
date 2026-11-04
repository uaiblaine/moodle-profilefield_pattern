# define_save_preprocess(): a change of pattern settings never queues the rewrite.
s~if \(\$stored !== false && \$stored !== \$data->param3\) \{~if (false) {~s;
