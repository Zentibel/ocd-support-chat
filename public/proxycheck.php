<?php
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
var_dump(\Common\CheckIp::isAnon());
