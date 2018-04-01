<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/12
 * Time: 23:39
 */

namespace app\ppms\exception;


class UploadException extends BaseException
{
    public $code = 503;
    public $msg = '上传错误！';
}