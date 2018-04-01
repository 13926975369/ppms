<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/2
 * Time: 16:39
 */

namespace app\ppms\exception;


class LossException extends BaseException
{
    public $code = 404;
    public $msg = '未找到页面！';
}