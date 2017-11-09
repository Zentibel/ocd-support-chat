<?php
namespace Chat\Finder;

use Predis\Client;
use Auth\AuthService;

class MessageFinder
{
    private $redis;
    private $authService;

    public function __construct(Client $redis, AuthService $authService)
    {
        $this->redis = $redis;
        $this->authService = $authService;
    }

    public function chatMessages($roomId, $limit = 50, $max = '+inf')
    {
        $limitInc = $limit;
        do {
            $messageKeys = $this->redis->zrevrangebyscore('chat:messages:'.$roomId, $max, '-inf', 'LIMIT', 0, $limitInc);
            $messages = [];
            foreach ($messageKeys as $messageKey) {
                if (strpos($messageKey, 'gliph') !== false) {
                    $message = $this->processGliphMessage($messageKey);
                } else {
                    $message = $this->processMessage($messageKey);
                }
                if ($message) {
                    $message['limit'] = $limitInc;
                    $messages[] = $message;
                    if (count($messages) >= $limit) break;
                }
            }
            $limitInc += 50;
        } while (count($messages) < $limit && $limitInc <= 500);
        if ($max === '+inf') {
            $messages = array_reverse($messages);
        }
        return $messages;
    }

    public function latestMessages($roomId, $limit = 50)
    {
        $messageKeys = $this->redis->zrange('chat:messages:'.$roomId, '-'.$limit, '-1');

        $messages = [];
        foreach ($messageKeys as $messageKey) {
            if (strpos($messageKey, 'gliph') !== false) {
                $message = $this->processGliphMessage($messageKey);
            } else {
                $message = $this->processMessage($messageKey);
            }
            $messages[] = $message;
        }
        return $messages;
    }

    private function processMessage($messageKey)
    {
        $message = $this->redis->hgetall($messageKey);

        if (!isset($message['sender'])) {
            return false; // TODO how/why does this happen? development quirk?
        }

        if (!isset($message['ip'])) {
            $message['ip'] = 'unknown';
        }

        $sender = $this->redis->hgetall('user:'.$message['sender']);
        $receiver = $this->redis->hGetAll('user:' . $this->authService->getIdentity()->id);

        $senderIsMod = $this->redis->sIsMember('mod-users', $message['sender']);
        $receiverIsMod = $this->redis->sIsMember('mod-users', $this->authService->getIdentity()->id);

        $senderIsBanned = $this->redis->sIsMember('banned-users', $message['sender']);
        $receiverIsBanned = $this->redis->sIsMember('banned-users', $this->authService->getIdentity()->id);

        $senderIpIsBanned = $this->redis->sIsMember('banned-ips', $message['ip']);
        $receiverIpIsBanned = $this->redis->sIsMember('banned-ips', $_SERVER['REMOTE_ADDR']);

        $senderIpIsProxy = (bool) $this->redis->sIsMember('proxy-ips', $message['ip']);
        $receiverIpIsProxy = (bool) $this->redis->sIsMember('proxy-ips', $_SERVER['REMOTE_ADDR']);
        $messageShouldBeHidden = (!$receiverIsBanned && !$receiverIpIsBanned && !$receiverIpIsProxy)
                                && ($senderIsBanned || $senderIpIsBanned || $senderIpIsProxy);
        //$messageShouldBeHidden = !$receiverIsBanned && !$receiverIpIsBanned && ($senderIsBanned || $senderIpIsBanned);
        //$messageShouldBeHiddenProxy = !$receiverIsBanned && !$receiverIpIsBanned && !$receiverIpIsProxy && ($senderIsBanned || $senderIpIsBanned || $senderIpIsProxy);
        //$messageFromProxyUser = $senderIpIsProxy && !$receiverIsBanned && !$receiverIpIsBanned && !$receiverIpIsProxy;

        if ($messageShouldBeHidden && !$receiverIsMod) {
            //return array('hide' => $messageShouldBeHidden, 'proxy' => $senderIsProxy, 'message' => $message['message']);
            return false;
            //$messageShouldBeHidden = false;
        }

        //if (!$senderIsMod && $this->redis->sIsMember('banned-ips', $message['ip'])) {
        //    $sender['banned'] = 1;
        //}

        //if (!isset($receiver['mod']) && $this->redis->sIsMember('banned-ips', $_SERVER['REMOTE_ADDR'])) {
        //    $receiver['banned'] = 1;
        //}

        //$forceShow = strpos($message['roomId'], ':') !== false && isset($sender['mod']);

        //if (isset($receiver['banned']) && !isset($sender['banned']) && !$forceShow) {
        //    return false;
        //}

        $messageCount = $this->redis->hget('messageCounts', $message['sender']);

        $senderName = $sender['username'];

        $response = [
            'source' => 'native',
            'id' => $message['id'],
            'key' => $messageKey,
            'newUser' => ($messageCount < 100) ? true : false ,
            // if sender and receiver banned: false
            // if not sender and not receiver: false
            // if sender and not receiver: true
            // if receiver and not sender: true
            // 'banned' => (!isset($sender['banned']) && isset($receiver['banned']) && !$forceShow) || (isset($sender['banned']) && !isset($receiver['banned'])),
            'banned' => $messageShouldBeHidden,
            'proxy' => $receiverIsMod && $senderIpIsProxy,
            'senderName' => $senderName,
            'senderId' => $message['sender'],
            'senderAvatar' => $sender['avatar'] ?? '/no-avatar.png',
            'message' => $message['message'],
            'timestamp' => $message['timestamp'],
        ];

        if (isset($message['media']) && $message['media']) {
            $media = explode('#', $message['media']);
            foreach ($media as $item) {
                $item = explode('|', $item);
                $response['media'][] = [
                    'thumbnail' => $item[0],
                    'fullsize' => $item[1] ?? false,
                ];
            }
        }

        return $response;

    }

    private function processGliphMessage($messageKey)
    {
        $message = $this->redis->hgetall($messageKey);
        $sender = $this->redis->hgetall('gliph:user:'.$message['sender']);

        $senderName = $sender['facet:First Name'] ?? $sender['facet:Pseudonym'] ?? $sender['facet:Username'];

        $response = [
            'source' => 'gliph',
            'key' => $messageKey,
            'id' => $message['id'],
            'senderName' => $senderName,
            'newUser' => false,
            'senderId' => $message['sender'],
            'senderAvatar' => '/gliph-media/' . ($sender['photo:thumbnail'] ?? 'nopic') . '.png',
            'message' => $message['text'],
            'timestamp' => $message['timestamp'],
        ];

        if (isset($message['media'])) {
            $response['media'] = [];

            $media = explode('#', $message['media']);
            foreach ($media as $item) {
                $item = explode('|', $item);
                $response['media'][] = [
                    'thumbnail' => '/gliph-media/' . $item[0] . '.png',
                    'fullsize' => '/gliph-media/' . $item[1] . '.png',
                ];
            }
        }

        return $response;
    }




}
