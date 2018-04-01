<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/14
 * Time: 21:51
 */

namespace app\ppms\exception;


class PowerException extends BaseException
{
    public $code = 403;
    public $msg = '权限不足！';
}