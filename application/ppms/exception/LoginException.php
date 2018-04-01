<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/2
 * Time: 20:40
 */

namespace app\ppms\exception;


class LoginException extends BaseException
{
    public $code = 501;
    public $msg = '未登录！';
}