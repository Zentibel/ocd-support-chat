<?php
namespace Auth\Validator;

use Respect\Validation\Validator as v;

class Username
{
    public static function validator()
    {
        return v::alnum('-')->noWhitespace()->length(3, 15);
    }
}
