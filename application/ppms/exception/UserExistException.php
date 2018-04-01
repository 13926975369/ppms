<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/17
 * Time: 9:49
 */

namespace app\ppms\exception;


class UserExistException extends BaseException
{
    public $code = 505;
    public $msg = '用户名已存在！';
}