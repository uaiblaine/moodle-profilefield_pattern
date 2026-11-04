# define_save_preprocess(): every save of an existing field queues the rewrite.
s~if \(\$stored !== false && \$stored !== \$data->param3\) \{~if (\$stored !== false) {~s;
