<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/25
 * Time: 11:41
 */

namespace app\ppms\validate;


use app\ppms\exception\BaseException;

class Search extends BaseValidate
{
    protected $rule = [
        'search_key' => 'require',
        'term' => 'require|termcheck',
    ];

    protected $message = [
        'search_key.require' => '搜索词不能为空！',
        'term.require' => '学期不能为空！',
    ];

    protected $field = [
        'search_key' => '搜索词',
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