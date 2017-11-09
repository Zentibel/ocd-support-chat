<?php
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$container = require 'config/container.php';

$redis = $container->get('redis');

$users = $redis->keys('user:*');

foreach ($users as $userKey) {
    $user = $redis->hgetall($userKey);
    if (isset($user['banned'])) {
        echo "BANNED: {$user['username']}\n";
        $redis->sAdd('banned-users', $user['id']);
        $redis->hDel($userKey, 'banned');
    }

    if (isset($user['mod'])) {
        echo "MOD: {$user['username']}\n";
        $redis->sAdd('mod-users', $user['id']);
        $redis->hDel($userKey, 'mod');
    }
}
