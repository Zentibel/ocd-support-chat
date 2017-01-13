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
            $gliphMsg .= "\n\n";
            foreach ($e->media as $media) {
                $gliphMsg .= "https://chat.ocd.community/uploads/default/{$media}\n";

            }
        }

        $this->redis->publish('message-to-gliph-' . $e->roomId, $gliphMsg);

        return $e->messageId;
    }

    private function convertTextToFakeBold($string)
    {
        $letterMap = [
            'A' => 'Ａ',
            'B' => 'Ｂ',
            'C' => 'Ｃ',
            'D' => 'Ｄ',
            'E' => 'Ｅ',
            'F' => 'Ｆ',
            'G' => 'Ｇ',
            'H' => 'Ｈ',
            'I' => 'Ｉ',
            'J' => 'Ｊ',
            'K' => 'Ｋ',
            'L' => 'Ｌ',
            'M' => 'Ｍ',
            'N' => 'Ｎ',
            'O' => 'Ｏ',
            'P' => 'Ｐ',
            'Q' => 'Ｑ',
            'R' => 'Ｒ',
            'S' => 'Ｓ',
            'T' => 'Ｔ',
            'U' => 'Ｕ',
            'V' => 'Ｖ',
            'W' => 'Ｗ',
            'X' => 'Ｘ',
            'Y' => 'Ｙ',
            'Z' => '𝗭',
            'a' => '𝗮',
            'b' => '𝗯',
            'c' => '𝗰',
            'd' => '𝗱',
            'e' => '𝗲',
            'f' => '𝗳',
            'g' => '𝗴',
            'h' => '𝗵',
            'i' => '𝗶',
            'j' => '𝗷',
            'k' => '𝗸',
            'l' => '𝗹',
            'm' => '𝗺',
            'n' => '𝗻',
            'o' => '𝗼',
            'p' => '𝗽',
            'q' => '𝗾',
            'r' => '𝗿',
            's' => '𝘀',
            't' => '𝘁',
            'u' => '𝘂',
            'v' => '𝘃',
            'w' => '𝘄',
            'x' => '𝘅',
            'y' => '𝘆',
            'z' => '𝘇',
            '0' => '𝟬',
            '1' => '𝟭',
            '2' => '𝟮',
            '3' => '𝟯',
            '4' => '𝟰',
            '5' => '𝟱',
            '6' => '𝟲',
            '7' => '𝟳',
            '8' => '𝟴',
            '9' => '𝟵',
        ];

    }
}

