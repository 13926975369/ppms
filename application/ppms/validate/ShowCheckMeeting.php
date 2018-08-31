<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/5/24
 * Time: 16:56
 */

namespace app\ppms\validate;


use app\ppms\exception\BaseException;

class ShowCheckMeeting extends BaseValidate
{
    protected $rule = [
        'page' => 'require|number',
        'size' => 'require|number',
        'major' => 'require',
    ];

    protected $message = [
        'page.require' => '页号不能为空！',
        'size.require' => '页大小不能为空！',
        'major.require' => '学院不能为空！',
        'page.number' =>  '页号必须为数字！',
        'size.number' => '页大小必须为数字！',
    ];

    protected $field = [
        'page' => '页号',
        'size' => '页大小',
        'major' => '学院',
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