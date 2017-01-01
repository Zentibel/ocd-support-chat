<?php
chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$GLIPH_TOKEN = '550c2c3f45b4ad7750271683.Yu6grJ3TzhU3w2hJGv0GCw.7Av2DkZTZvfWoZqK3SHBKA';

function downloadGliphMedia($media, $key, $token, $destFile)
{
    if (file_exists($destFile)) {
        return;
    }

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n" .
                         "X-Gliph-Key: {$key}\r\n" .
                         "X-Gliph-Token: {$token}\r\n" ,
            'content' => '{"client":"Ptolemy"}',
            'protocol_version' => '1.1',
        ]
    ];
    $context = stream_context_create($opts);
    $data = file_get_contents('https://gli.ph/api/v2/media/' . $media, false, $context);

    file_put_contents($destFile, $data);

    return $data;
}

$cacheFile = 'data/ocd-gliph-members.json';

$container = require 'config/container.php';

$redis = $container->get('redis');

$opts = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n" .
                     "X-Gliph-Token: {$GLIPH_TOKEN}\r\n",
        'content' => '{"connection_id":"550c2e3d45b4ad77502716b4","action":"read","client":"Ptolemy"}',
        //'content' => '{"connection_id":"58637bba3f47a502266696c3","action":"read","client":"Ptolemy"}',
    ]
];
$context = stream_context_create($opts);

if (file_exists($cacheFile)) {
    $json = file_get_contents($cacheFile);
} else {
    $json = file_get_contents('https://gli.ph/api/v2/connections/members', false, $context);
    file_put_contents($cacheFile, $json);
}


$response = json_decode($json);

$facetsToKeep = [
    'Pseudonym',
    'Username',
    'First Name',
    'Last Name',
    'Email',
    'Phone Number',
    'Facebook Profile',
    'Twitter',
];
$facetsToSkip = ['Gliph', 'URL'];

$ocdChatId = $redis->hget('index:community-paths', 'ocd');
$i = 0;
foreach ($response->members as $member) {

    $values = [
        'gliph_id'    => $member->id,
        'last_update' => microtime(true)
    ];

    $photo = false;

    foreach ($member->facets as $facet) {
        if (in_array($facet->facet_type, $facetsToKeep)) {
            $values['facet:' . $facet->facet_type] = $facet->content->content;
            continue;
        }

        if (in_array($facet->facet_type, $facetsToSkip)) {
            continue;
        }

        if ($facet->facet_type === 'Profile Photo') {

            $small = downloadGliphMedia(
                $facet->content->content[0]->content_id,
                $facet->content->content[0]->key,
                $GLIPH_TOKEN,
                'public/uploads/gliph/user-'.$member->id.'-small.png'
            );

            $large = downloadGliphMedia(
                $facet->content->content[1]->content_id,
                $facet->content->content[1]->key,
                $GLIPH_TOKEN,
                'public/uploads/gliph/user-'.$member->id.'-large.png'
            );

            $photo = true;

        } else {
            var_dump($facet->facet_type);
            die("Unexpected facet type from Gliph...\n");
        }
    }

    if (!$photo) {
        $blankGliph = realpath('public/gliph-user.png');
        copy($blankGliph, realpath('public/uploads/gliph') . '/user-'.$member->id.'-large.png');
        copy($blankGliph, realpath('public/uploads/gliph') . '/user-'.$member->id.'-small.png');
    }


    $rKey = "gliph:user:{$member->id}";
    $redis->hMSet($rKey, $values);


    $redis->sAdd('community:members:' . $ocdChatId, $rKey);

    if ($i > 200) break;
    $i++;
}
