<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/13
 * Time: 22:37
 */

namespace app\ppms\validate;


class NewPasswordValidate extends BaseValidate
{
    protected $rule = [
        'old_password' => 'require|IsCharacter',
        'password' => 'require|IsCharacter',
        'password_check' => 'require',
    ];

    protected $message = [
        'password.require' => '新密码不能为空！',
        'old_password.require' => '旧密码不能为空！',
        'password_check.require' => '新确认密码不能为空！',
    ];

    protected $field = [
        'password' => '新密码',
        'old_password' => '旧密码',
        'password_check' => '新确认密码',
    ];
}