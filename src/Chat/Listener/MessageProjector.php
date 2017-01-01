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
        $this->redis->hMSet($rKey, [
            'id'        => $e->messageId,
            'sender'    => $e->userId,
            'roomId'    => $e->roomId,
            'message'   => $e->message,
            'timestamp' => $now,
        ]);

        $this->redis->publish('new-message', $e->roomId);
        $username = $this->userFinder->findUsernameByUserId($e->userId);
        $this->redis->publish('message-to-gliph-' . $e->roomId, "{$username}: {$e->message}");

        return $e->messageId;
    }
}

