<?php
namespace Common;

class CheckIp
{
    public static function isAnon()
    {
        return self::isTorExitPoint() || self::isProxy();
    }

    public static function isProxy()
    {
        $proxyports = array(80,8080,6588,8000,3128,3127,3124,1080,553,554);
        for ($i=0; $i <= count($proxyports); $i++) {
            if(@fsockopen($_SERVER['REMOTE_ADDR'], $proxyports[$i], $errstr, $errno, 0.5)){
                $sockport=true;
            }
        }
        if($_SERVER['HTTP_FORWARDED']
        || $_SERVER['HTTP_X_FORWARDED_FOR']
        || $_SERVER['HTTP_CLIENT_IP']
        || $_SERVER['HTTP_VIA']
        || $_SERVER['HTTP_XROXY_CONNECTION']
        || $_SERVER['HTTP_PROXY_CONNECTION']
        || $sockport == true
        )
        {
            return true;
        } else {
            return false;
        }
    }

    public static function isTorExitPoint() {
        if (gethostbyname(self::reverseIpOctets($_SERVER['REMOTE_ADDR']).".".$_SERVER['SERVER_PORT'].".".self::reverseIpOctets($_SERVER['SERVER_ADDR']).".ip-port.exitlist.torproject.org")=="127.0.0.2") {
            return true;
        } else {
           return false;
        }
    }

    public static function reverseIpOctets($inputIp) {
        $ipoc = explode('.', $inputIp);
        return "{$ipoc[3]}.{$ipoc[2]}.{$ipoc[1]}.{$ipoc[0]}";
    }
}
