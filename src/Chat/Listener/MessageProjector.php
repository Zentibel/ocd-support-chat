<?php
declare(strict_types=1);

namespace Chat\Listener;

use Predis\Client;
use Chat\Command\SendMessage as MessageSent;
use Auth\Finder\UserFinder;

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
        $now = microtime(true);

        $rKey = 'chat:messages:' . $e->roomId;
        $this->redis->zAdd($rKey, $now, 'message:'.$e->messageId);

        $rKey = 'message:' . $e->messageId;
        $data = [
            'id'        => $e->messageId,
            'sender'    => $e->userId,
            'roomId'    => $e->roomId,
            'message'   => $e->message,
            'timestamp' => $now,
        ];

        if (is_array($e->media)) {
            $data['media'] = implode('#', $e->media);
        }

        $this->redis->hMSet($rKey, $data);

        $this->redis->publish('new-message', $e->roomId);

        $username = $this->userFinder->findUsernameByUserId($e->userId);
        $gliphMsg = "{$username} says:\n\n{$e->message}";

        if (is_array($e->media)) {
            $imgCount = count($e->media);
            $gliphMsg .= "\n\n({$imgCount} image(s) attached. Sign into the new chat to view.)";
        }

        $this->redis->publish('message-to-gliph-' . $e->roomId, $gliphMsg);

        return $e->messageId;
    }
}

