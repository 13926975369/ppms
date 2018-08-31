<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/8/27
 * Time: 16:18
 */

namespace app\ppms\validate;


use app\ppms\exception\BaseException;

class AttendCheck extends BaseValidate
{
    protected $rule = [
        'id' => 'require|number',
        'term' => 'require|termcheck',
    ];

    protected $message = [
        'id.require' => 'id不能为空！',
        'term.require' => '学期不能为空！',
        'id.number' =>  'id必须为数字！',
    ];

    protected $field = [
        'id' => 'id',
        'term' => '学期',
    ];

    protected function termcheck($value,$rule = '',$date = '',$field=''){
        if(preg_match("/^[0-9]{4}-[0-9]{4}-[0-9]{1}$/",$value)||preg_match("/^all$/",$value)){
            return true;
        }else{
            throw new BaseException([
                'msg' => "传入的学期参数格式必须为xxxx-xxxx-x(x为数字)或者传入全部学期！"
            ]);
        }
    }

}