<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/14
 * Time: 21:46
 */

namespace app\ppms\exception;


class TokenException extends BaseException
{
    public $code = 401;
    public $msg = 'Token已经过期或无效Token';
}