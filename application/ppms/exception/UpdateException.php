<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/29
 * Time: 11:19
 */

namespace app\ppms\exception;


class UpdateException extends BaseException
{
    public $code = 504;
    public $msg = '更新出错，请重试！';
}