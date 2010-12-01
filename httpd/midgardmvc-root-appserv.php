<?php

require 'AppServer/autoload.php';
require __DIR__.'/appserv_runner_app.php';

try
{
    $handler = new \MFS\AppServer\DaemonicHandler('tcp://127.0.0.1:8001', 'HTTP');
    $handler->serve(new midgardmvc_appserv_runner_app());
}
catch (Exception $e)
{
    echo $e;
}
