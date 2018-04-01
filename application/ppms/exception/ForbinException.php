<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/1
 * Time: 22:32
 */

namespace app\ppms\exception;


class ForbinException extends BaseException
{
    public $code = 403;
    public $msg = '禁止访问！';
}