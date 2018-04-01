<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/12
 * Time: 22:35
 */

namespace app\ppms\exception;
use think\exception\Handle;
use think\exception\HttpException;

class ExceptionHandler extends Handle
{
    //状态码
    private $code;
    //错误信息
    private $msg;

    public function render(\Exception $e){
        if ($e instanceof BaseException){
            $this->code = $e->code;
            $this->msg  = $e->msg;
        }else{
            //系统出错
            $this->code = 500;
            $this->msg = '服务器内部错误';

            //调试
            if ($e instanceof HttpException) {
                return $this->renderHttpException($e);
            } else {
                return $this->convertExceptionToResponse($e);
            }
        }
        return json([
            'code' => $this->code,
            'msg' => $this->msg
        ],$this->code);
    }
}