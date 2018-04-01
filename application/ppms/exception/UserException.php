<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/12
 * Time: 22:57
 */

namespace app\ppms\exception;


class UserException extends BaseException
{
    public $code = 404;
    public $msg = '用户名或者密码错误，请重新输入！';
}