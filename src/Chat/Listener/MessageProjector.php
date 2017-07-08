<?php
declare(strict_types=1);

namespace Chat\Listener;

use Predis\Client;
use Chat\Command\SendMessage as MessageSent;
use Auth\Finder\UserFinder;
use Ramsey\Uuid\Uuid;

class MessageProjector
{
    private $redis;

    private $userFinder;

    public function __construct(Client $redis, UserFinder $userFinder)
    {
        $this->redis      = $redis;
        $this->userFinder = $userFinder;
    }

    public function __invoke($event)
    {
        switch(get_class($event)) {
            case MessageSent::class:
                return $this->onMessageSent($event);
                break;
        }
    }

    public function onMessageSent(MessageSent $e)
    {

        if (preg_match('/^\/(?P<username>[^\s]+)\+\+/', $e->message, $matches) && (strpos($e->roomId, ':') === false)) {
            $userId = $this->redis->hGet('index:usernames', strtolower($matches['username']));
            if(!$userId) {
                $message = "{$e->message}\n\n⛔️ *{$matches['username']} is not a valid username.*";
            } elseif ($userId == $e->userId) {
                $message = "{$e->message}\n\n⛔️ *You can't give yourself karma!!!*";
            } else {
                $this->redis->hIncrBy('karmaCounts', $userId, 1);
                $username = $this->redis->hGet('user:' . $userId, 'username');
                $kCount = $this->redis->hGet('karmaCounts', $userId);
                $message = "{$e->message}\n\n📈 *{$username} now has {$kCount} karma.*";
            }
        } elseif (preg_match('/^\/(?P<username>[^\s]+)\-\-/', $e->message, $matches) && (strpos($e->roomId, ':') === false)) {
            $userId = $this->redis->hGet('index:usernames', strtolower($matches['username']));
            if(!$userId) {
                $message = "{$e->message}\n\n⛔️ *{$matches['username']} is not a valid username.*";
            } elseif ($userId == $e->userId) {
                $message = "{$e->message}\n\n⛔️ *You can't take away your own karma!!!*";
            } else {
                $this->redis->hIncrBy('karmaCounts', $userId, -1);
                $username = $this->redis->hGet('user:' . $userId, 'username');
                $kCount = $this->redis->hGet('karmaCounts', $userId);
                $message = "{$e->message}\n\n📉 *{$username} now has {$kCount} karma.*";
            }
        } elseif (strtolower(substr($e->message, 0, 5)) == '/help') {
            $help = <<<help
##### OChatD Command Reference Guide

| **Command** | **Description** |
| ----------- | --------------- |
| `/karma` | view your own karma |
| `/karma {username}` | view someone else's karma |
| `/{username}++` | give {username} 1 karma |
| `/{username}--` | reduce {username}'s karma by 1 |
| `/roll` | roll a 6-sided die |
| `/roll N` | (N=number) roll an N-sided die |
| `/count` | see how many messages you have sent |
| `/count {username}` | see how many messages {username} has sent |
| `/help` | This. |
help;
            $message = "{$e->message}\n\n---\n\n{$help}";
        } elseif (strtolower(substr($e->message, 0, 6)) == '/karma') {
            if (preg_match('/^\/karma (?P<username>[^\s]+)/', $e->message, $matches)) {
                $userId = $this->redis->hGet('index:usernames', strtolower($matches['username']));
            } else {
                $userId = $e->userId;
            }
            if ($userId) {
                $username = $this->redis->hGet('user:' . $userId, 'username');
                $kCount = $this->redis->hGet('karmaCounts', $userId) ?: '0';
                $message = "{$e->message}\n\n🔢 *{$username} has {$kCount} karma.*";
            } else {
                $message = "{$e->message}\n\n🔢 *{$matches['username']} is not a valid username.*";
            }
        } elseif (strtolower(substr($e->message, 0, 6)) == '/count') {
            if (preg_match('/^\/count (?P<username>[^\s]+)/', $e->message, $matches)) {
                $userId = $this->redis->hGet('index:usernames', strtolower($matches['username']));
            } else {
                $userId = $e->userId;
            }
            if ($userId) {
                $username = $this->redis->hGet('user:' . $userId, 'username');
                $msgCount = $this->redis->hGet('messageCounts', $userId);
                $message = "{$e->message}\n\n🔢 *{$username} has sent {$msgCount} messages since counting began on July 1st, 2017.*";
            } else {
                $message = "{$e->message}\n\n🔢 *{$matches['username']} is not a valid username.*";
            }
        } else if (strtolower(substr($e->message, 0, 5)) == '/roll') {
            if (preg_match('/^\/roll (?P<diecount>\d+)/', $e->message, $matches)) {
                $dieCount = (int) $matches['diecount'];
            } else {
                $dieCount = 6;
            }

            $rollResult = rand(1, $dieCount);

            $currentTime = time();
            $lastRoll = $this->redis->hGet('dice', $e->userId);
            $secondsSinceLastRoll = $currentTime - $lastRoll;
            if ($secondsSinceLastRoll < 120) {
                $remainingWait = 120 - $secondsSinceLastRoll;
                $message = "{$e->message}\n\n🎲 *You're out of dice! You'll be given another die in {$remainingWait} seconds.*";
            } else {
                $this->redis->hSet('dice', $e->userId, time());
                $message = "{$e->message}\n\n🎲 *Rolled a **{$rollResult}**.*";
            }
        } else {
            $message = $e->message;
        }
        if (strpos($e->roomId, ':') === false) {
            $this->redis->hIncrBy('messageCounts', $e->userId, 1);
        }

        $message = str_replace('¯\_(ツ)_/¯', '¯\\\_(ツ)\_/¯', $message);

        $now = microtime(true);

        $msgKey = 'message:' . $e->messageId;
        $data = [
            'id'        => $e->messageId,
            'sender'    => $e->userId,
            'roomId'    => $e->roomId,
            'message'   => $message,
            'timestamp' => $now,
        ];


        $chatKey = 'chat:messages:' . $e->roomId;

        if (is_array($e->media)) {
            $mediaFiles = array_map(function($media) {
                return implode('|', $media);
            }, $e->media);

            $data['media'] = implode('#', $mediaFiles);
        }

        if ($e->roomId === 'dd0c62bd-c4f2-4286-affa-256bfcc93955') {
            $lastMsgKey = $this->redis->zRevRangeByScore($chatKey, '+inf', '-inf', ['limit' => [0, 1]])[0];
            $lastMsgTimestamp = $this->redis->hGet($lastMsgKey, 'timestamp');
            $timeSinceLastMsg = $now - $lastMsgTimestamp;
            if ($timeSinceLastMsg > 300) {
                $copy = $data;
                $copy['id'] = Uuid::uuid4()->toString();
                $copy['message'] = "{$copy['message']}\n\n💬 ***This is an automated cross-post of a new message in the [support chat.](/c/ocd) Don't respond to it here.***";
                $copyKey = "message:{$copy['id']}";
                $this->redis->hMSet($copyKey, $copy);
                $this->redis->zAdd('chat:messages:85d5bd7f-9374-4553-98de-84f234e3dba1', $now, $copyKey);
                $this->redis->publish('new-message', '85d5bd7f-9374-4553-98de-84f234e3dba1');
            }
        }

        $this->redis->hMSet($msgKey, $data);
        $this->redis->zAdd($chatKey, $now, $msgKey);
        $this->redis->publish('new-message', $e->roomId);

        if (strpos($e->roomId, ':') !== false) {
            $recipientUserId = str_replace($e->userId, '', $e->roomId, $count);
            $recipientUserId = $count == 2 ? $e->userId : str_replace(':', '', $recipientUserId);
            $response = $this->sendPushNotification($e->userId, $recipientUserId);
            var_dump($response);die();
        }
        //$username = $this->userFinder->findUsernameByUserId($e->userId);
        //$gliphMsg = "{$username} says:\n\n{$e->message}";

        //if (is_array($e->media)) {
        //    $gliphMsg .= "\n\n";
        //    foreach ($e->media as $media) {
        //        $gliphMsg .= "https://chat.ocd.community/uploads/default/{$media}\n";

        //    }
        //}

        //$rKey = 'message:28969b6b-002b-4379-bf49-35570802c423';
        //$this->redis->publish('message-to-gliph-' . $e->roomId, $rKey);

        return $e->messageId;
    }

    public function sendPushNotification($userIdFrom, $userIdTo = false)
    {
            $senderUsername = $this->userFinder->findUsernameByUserId($userIdFrom);
            $url = "https://chat.ocd.community/p/{$senderUsername}";
            $messageContent = "New PM from {$senderUsername}";
            $content = ['en' => $messageContent];
            $headings = ['en' => "OChatD — {$senderUsername}"];
            $fields = [
                'app_id' => '71c72165-618c-45c2-bb81-8268524f1806',
                'headings' => $headings,
                'contents' => $content,
                'url' => $url,
                'android_group' => $userIdFrom,
                'android_group_message' => "$[notif_count] new PMs from {$senderUsername}",
            ];

            if ($userIdTo) {
                $fields['filters'] = [['field' => 'tag', 'key' => 'userID', 'relation' => '=', 'value' => $userIdTo]];
            }

            $fields = json_encode($fields);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                                                       'Authorization: Basic YTJmODRjZTEtOGI3MC00NWMxLWI1ZDEtMDlmMDhmNjk1YmQz'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }
}
