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

        if (strtolower(substr($e->message, 0, 5)) == '/roll') {
            if (preg_match('/\/roll (?P<diecount>\d+)/', $e->message, $matches)) {
                $dieCount = (int) $matches['diecount'];
            } else {
                $dieCount = 6;
            }

            $rollResult = rand(1, $dieCount);

            $currentTime = time();
            $lastRoll = $this->redis->hGet('dice', $e->userId);
            $secondsSinceLastRoll = $currentTime - $lastRoll;
            if ($secondsSinceLastRoll < 120) {
                //$dog = ['Chloe-dog', 'Zap', 'Leela'];
                //$eatenBy = $dog[array_rand($dog)];
                //$message = "{$e->message}\n\n🎲 *The die rolled off the table and was eaten by {$eatenBy}.*";
                $remainingWait = 120 - $secondsSinceLastRoll;
                $message = "{$e->message}\n\n🎲 *You're out of dice! You'll be given another die in {$remainingWait} seconds.*";
            } else {
                $this->redis->hSet('dice', $e->userId, time());
                $message = "{$e->message}\n\n🎲 *Rolled a **{$rollResult}**.*";
            }
        } else {
            $message = $e->message;
        }

        $rKey = 'message:' . $e->messageId;
        $data = [
            'id'        => $e->messageId,
            'sender'    => $e->userId,
            'roomId'    => $e->roomId,
            'message'   => str_replace('¯\_(ツ)_/¯', '¯\\\_(ツ)\_/¯', $message),
            'timestamp' => $now,
        ];

        if (is_array($e->media)) {
            $mediaFiles = array_map(function($media) {
                return implode('|', $media);
            }, $e->media);

            $data['media'] = implode('#', $mediaFiles);
        }

        $this->redis->hMSet($rKey, $data);

        $this->redis->publish('new-message', $e->roomId);

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
}
