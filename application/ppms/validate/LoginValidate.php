<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/13
 * Time: 21:44
 */

namespace app\ppms\validate;


class LoginValidate extends BaseValidate
{

    protected $rule = [
        'username' => 'require',
        'password' => 'require|IsCharacter',
    ];

    protected $message = [
        'username.require' => '用户名不能为空！',
        'password.require' => '密码不能为空！',
    ];

    protected $field = [
        'username' => '用户名',
        'password' => '密码',
    ];
}