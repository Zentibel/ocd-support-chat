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
        $sender = $this->userFinder->findByUserId($e->userId);
        $this->redis->sAdd("ips:{$e->userId}", $_SERVER['REMOTE_ADDR']);

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
##### OChatD Reference Guide

| **Command** | **Description** |
| ----------- | --------------- |
| `/karma` | view your own karma |
| `/karma username` | view Username's karma |
| `/leaderboard` | view karma leaderboard |
| `/username++` | give Username 1 karma |
| `/username--` | reduce Username's karma by 1 |
| `/roll` | roll a 6-sided die |
| `/roll N` | (N=number) roll an N-sided die |
| `/count` | see how many messages you have sent |
| `/jfckatz` | see how many times katz has said "jfc" |
| `/help` | This. |

##### 

| **Shortcut** | **Description** |
| ------------- | --------------- |
| `alt+m` | Mute / unmute notification sounds. |
| `alt+v` | Toggle text-to-speech (beta) |
| `alt+u` | Increase notification volume by 10% |
| `alt+d` | Decrease notification volume by 10% |
help;
            if ($e->roomId === 'e6ddc009-a7c0-4bf9-8637-8a3da4d65825') {
                $help .= <<<help

##### 

**Mod Commands**

* `/ban username`
* `/unban username`
* `/mod username`
* `/unmod username`
* `/alias some-psuedonym`
* `/msg username A mod message to username`
help;
            }
            $message = "{$e->message}\n\n---\n\n{$help}";
        } elseif (strtolower(substr($e->message, 0, 12)) == '/leaderboard') {
            $leaderboard = $this->leaderboard();
            $message = "{$e->message}\n\n---\n\n{$leaderboard}";
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
                if ($userId === $e->userId || $e->userId == '6d03e32d-1537-4348-8448-cd2066c20c27') {
                    $username = $this->redis->hGet('user:' . $userId, 'username');
                    $msgCount = $this->redis->hGet('messageCounts', $userId);
                    $message = "{$e->message}\n\n🔢 *{$username} has sent {$msgCount} messages since counting began on July 1st, 2017.*";
                } else {
                    $message = "{$e->message}\n\n🔢 *You can only check your own message count.*";
                }
            } else {
                $message = "{$e->message}\n\n🔢 *{$matches['username']} is not a valid username.*";
            }
        } elseif (strtolower(substr($e->message, 0, 8)) == '/jfckatz') {
            $start = 1505241000;
            $now = time();
            $days = ($now - $start) / 86400;
            $msgCount = $this->redis->hGet('jfcCounts', 'f555daac-5720-4af6-bc8d-c6562a45c9b4') ?: '0';
            $average = round($msgCount / $days);
            $message = "{$e->message}\n\n🔢 *ersatzkatz has said \"jfc\" {$msgCount} times since September 13th, 2017 at 18:30 UTC (Average: {$average} jfc's per day).*";
        } elseif (strtolower(substr($e->message, 0, 5)) == '/roll') {
            if (preg_match('/^\/roll (?P<diecount>\d+)/', $e->message, $matches)) {
                $dieCount = (int) $matches['diecount'];
            } else {
                $dieCount = 6;
            }

            //if ($e->userId == '6d03e32d-1537-4348-8448-cd2066c20c27') {
            //    $rollResult = 27;
            //} else {
                $rollResult = rand(1, $dieCount);
            //}

            $currentTime = time();
            $lastRoll = $this->redis->hGet('dice', $e->userId);
            $secondsSinceLastRoll = $currentTime - $lastRoll;
            if ($secondsSinceLastRoll < 120 && $e->userId != '6d03e32d-1537-4348-8448-cd2066c20c27') {
                $remainingWait = 120 - $secondsSinceLastRoll;
                $message = "{$e->message}\n\n🎲 *You're out of dice! You'll be given another die in {$remainingWait} seconds.*";
            } else {
                $this->redis->hSet('dice', $e->userId, time());
                $message = "{$e->message}\n\n🎲 *Rolled a **{$rollResult}**.*";
            }
        } elseif (preg_match('/^\/ban (?P<username>[^\s]+)/', $e->message, $matches) && $e->roomId === 'e6ddc009-a7c0-4bf9-8637-8a3da4d65825' && isset($sender->mod)) {
            $userId = $this->redis->hGet('index:usernames', strtolower($matches['username']));
            if(!$userId) {
                $message = "{$e->message}\n\n⛔️ *{$matches['username']} is not a valid username.*";
            } else {
                $targetUser = $this->userFinder->findByUserId($userId);
                if (!isset($targetUser->banned)) {
                    $this->redis->hSet(
                        'user:' . $userId,
                        'banned',
                        1
                    );
                    $ips = $this->redis->sMembers("ips:{$userId}");
                    foreach ($ips as $ip) {
                        $this->redis->sAdd('banned-ips', $ip);
                    }
                    $ipCount = count($ips);
                    $message = "{$e->message}\n\n🚫 *{$matches['username']} has been banned. {$ipCount} IP(s) banned.*";
                } else {
                    $message = "{$e->message}\n\n⛔️ *{$matches['username']} is already banned.*";
                }
            }
        } elseif (preg_match('/^\/unban (?P<username>[^\s]+)/', $e->message, $matches) && $e->roomId === 'e6ddc009-a7c0-4bf9-8637-8a3da4d65825' && isset($sender->mod)) {
            $userId = $this->redis->hGet('index:usernames', strtolower($matches['username']));
            if(!$userId) {
                $message = "{$e->message}\n\n⛔️ *{$matches['username']} is not a valid username.*";
            } else {
                $targetUser = $this->userFinder->findByUserId($userId);
                if (isset($targetUser->banned)) {
                    $this->redis->hDel(
                        'user:' . $userId,
                        'banned'
                    );
                    $ips = $this->redis->sMembers("ips:{$userId}");
                    foreach ($ips as $ip) {
                        $this->redis->sRem('banned-ips', $ip);
                    }
                    $ipCount = count($ips);
                    $message = "{$e->message}\n\n👌 *{$matches['username']} has been unbanned. {$ipCount} IP(s) unbanned.*";
                } else {
                    $message = "{$e->message}\n\n⛔️ *{$matches['username']} is not banned.*";
                }
            }
        } elseif (preg_match('/^\/mod (?P<username>[^\s]+)/', $e->message, $matches) && $e->roomId === 'e6ddc009-a7c0-4bf9-8637-8a3da4d65825' && isset($sender->mod)) {
            $userId = $this->redis->hGet('index:usernames', strtolower($matches['username']));
            if(!$userId) {
                $message = "{$e->message}\n\n⛔️ *{$matches['username']} is not a valid username.*";
            } else {
                $targetUser = $this->userFinder->findByUserId($userId);
                if (!isset($targetUser->mod)) {
                    $this->redis->hSet(
                        'user:' . $userId,
                        'mod',
                        1
                    );
                    $message = "{$e->message}\n\n👮 *{$matches['username']} has been modded.*";
                } else {
                    $message = "{$e->message}\n\n⛔️ *{$matches['username']} is already a mod.*";
                }
            }
        } elseif (preg_match('/^\/unmod (?P<username>[^\s]+)/', $e->message, $matches) && $e->roomId === 'e6ddc009-a7c0-4bf9-8637-8a3da4d65825' && isset($sender->mod)) {
            $userId = $this->redis->hGet('index:usernames', strtolower($matches['username']));
            if(!$userId) {
                $message = "{$e->message}\n\n⛔️ *{$matches['username']} is not a valid username.*";
            } else {
                $targetUser = $this->userFinder->findByUserId($userId);
                if (isset($targetUser->mod)) {
                    $this->redis->hDel(
                        'user:' . $userId,
                        'mod'
                    );
                    $message = "{$e->message}\n\n👋 *{$matches['username']} is no longer a mod.*";
                } else {
                    $message = "{$e->message}\n\n⛔️ *{$matches['username']} is not a mod.*";
                }
            }
        } elseif (preg_match('/^\/alias (?P<username>[^\s]+)/', $e->message, $matches) && $e->roomId === 'e6ddc009-a7c0-4bf9-8637-8a3da4d65825' && isset($sender->mod)) {
                $this->redis->hSet(
                    'user:' . $sender->id,
                    'modAlias',
                    $matches['username']
                );
                $message = "{$e->message}\n\n🛡 *You will now be known as {$matches['username']} when responding as a mod.*";
        } else {
            $message = $e->message;
        }
        if (strpos($e->roomId, ':') === false) {
            $this->redis->hIncrBy('messageCounts', $e->userId, 1);
            if ($e->userId == 'f555daac-5720-4af6-bc8d-c6562a45c9b4' && preg_match('/jfc/i', $e->message, $matches)) {
                $this->redis->hIncrBy('jfcCounts', $e->userId, 1);
            }
        }

        $message = str_replace('¯\_(ツ)_/¯', '¯\\\_(ツ)\_/¯', $message);

        $now = microtime(true);
        $bannedUserIds = array(
            '3a1edd40-7250-4da3-a33d-f41a0eda73b2',
        );
        if (in_array($e->userId, $bannedUserIds)) {
            $message = '⛔ *This account is trying to send a message but has been banned.*';
        }

        $msgKey = 'message:' . $e->messageId;
        $data = [
            'id'        => $e->messageId,
            'sender'    => $e->userId,
            'roomId'    => $e->roomId,
            'message'   => $message,
            'timestamp' => $now,
            'ip'        => $_SERVER['REMOTE_ADDR'],
        ];


        $chatKey = 'chat:messages:' . $e->roomId;

        if (is_array($e->media)) {
            $mediaFiles = array_map(function($media) {
                return implode('|', $media);
            }, $e->media);

            $data['media'] = implode('#', $mediaFiles);
        }

        if (strpos($e->roomId, 'b3dd9e79-de3b-4d55-8c94-b9b5df5d7769') !== false && strpos($e->roomId, ':') !== false) {
                $copy = $data;
                $copy['id'] = Uuid::uuid4()->toString();
                $copy['message'] = "{$copy['message']}\n\n💬 ***This user will only see replies that begin with `/msg username`***";
                $copyKey = "message:{$copy['id']}";
                $this->redis->hMSet($copyKey, $copy);
                $this->redis->zAdd('chat:messages:e6ddc009-a7c0-4bf9-8637-8a3da4d65825', $now, $copyKey);
                $this->redis->publish('new-message', 'e6ddc009-a7c0-4bf9-8637-8a3da4d65825');
        }

        if (preg_match('/^\/msg (?P<username>[^\s]+)/', $e->message, $matches) && $e->roomId === 'e6ddc009-a7c0-4bf9-8637-8a3da4d65825' && isset($sender->mod)) {
            $userId = $this->redis->hGet('index:usernames', strtolower($matches['username']));
            if(!$userId) {
                $message = "{$e->message}\n\n⛔️ *{$matches['username']} is not a valid username.*";
            } else {
                $targetUser = $this->userFinder->findByUserId($userId);
                $modPmRoomId = $this->pmChatKey($targetUser->id, 'b3dd9e79-de3b-4d55-8c94-b9b5df5d7769');
                $modAlias = $this->redis->hGet(
                    'user:' . $e->userId,
                    'modAlias'
                ) ?: 'Anonymous Moderator';
                $copy = $data;
                $copy['id'] = Uuid::uuid4()->toString();
                $copy['sender'] = 'b3dd9e79-de3b-4d55-8c94-b9b5df5d7769';
                $copy['roomId'] = $modPmRoomId;
                $copy['message'] = trim(str_replace("/msg {$matches['username']}", '', $copy['message']));
                $copy['message'] = "{$copy['message']}\n\n***— {$modAlias}***";

                $data['sender'] = $copy['sender'];
                $data['message'] = "/msg {$matches['username']} ".$copy['message'];

                $copyKey = "message:{$copy['id']}";
                $this->redis->hMSet($copyKey, $copy);
                $this->redis->zAdd('chat:messages:'.$modPmRoomId, $now, $copyKey);
                $this->redis->publish('new-message', $modPmRoomId);
                $this->sendPushNotification('b3dd9e79-de3b-4d55-8c94-b9b5df5d7769', $targetUser->id);
            }
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

    protected function pmChatKey($id1, $id2)
    {
        if ($id1 > $id2) {
            return "{$id1}:{$id2}";
        }

        return "{$id2}:{$id1}";
    }
    protected function leaderboard()
    {
        $kCounts = $this->redis->hGetAll('karmaCounts');
        array_map(function($n) {
            return (int) $n;
        }, $kCounts);
        asort($kCounts);
        $kCounts = array_reverse($kCounts, true);

        $leaders = <<<msg
##### OChatD Karma Leaderboard

| **Rank** | **Karma** | **User** |
| --- | ----------- | --------------- |
msg;
        $i=0;
        foreach ($kCounts as $uid => $karma) {
            if (!$uid) continue;
            if (++$i == 11) break;
            $username = $this->redis->hGet('user:' . $uid, 'username');
            $leaders .= "\n| {$i}. | {$karma} | {$username} |";
        }
        //end($kCounts);
        //$uid = key($kCounts);
        //$username = $this->redis->hGet('user:' . $uid, 'username');
        //$leaders .= "\n| Last: | {$kCounts[$uid]} | {$username} |";
        return $leaders;
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
