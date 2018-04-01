<?php
/**
 * Created by PhpStorm.
 * User: asus
 * Date: 2018/1/11
 * Time: 22:10
 */

namespace app\ppms\validate;


use app\ppms\exception\BaseException;

class RegisterValidate extends BaseValidate
{
    protected $rule=[
        'username'=>'require',
        'password'=>'require|IsCharacter',
        'password2'=>'require|IsCharacter',
        'phonenumber'=>'require|number',
        'usertype'=>'require',
        'select' => 'require'
    ];

    protected function IsPostiveInteger($value,$rule = '',$date = '',$field=''){
        if(is_numeric($value)&&is_int($value+0)&&($value+0) > 0){
            return true;
        }else{
            throw new BaseException([
                'msg' => $field."必须是正整数"
            ]);
        }
    }
}