<?php
pake_import('pear', false);

pake_task('default');

pake_alias('init_mvc', 'default');
pake_alias('reinit_db', 'default');


// TASKS
function run_default($task, $args)
{
    pake_echo_error('Installer is a separate tool now. Please use that');

    try {
        pake_which('mvc_install');
        pake_echo_comment('It is already installed on your system. Type: mvc_install');
    } catch (pakeException $e) {
        pake_echo_comment('I will install it for you…');
        pakePearTask::install_pear_package('midgardmvc_installer', 'pear.indeyets.pp.ru');

        pake_echo_comment('Done. To use it, type: mvc_install');
    }
}
