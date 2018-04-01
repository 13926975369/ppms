<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/25
 * Time: 16:44
 */

namespace app\ppms\validate;

class SendUserMsg extends BaseValidate
{
    protected $rule = [
        'gender' => 'require',
        'realname' => 'require',
        'idcard' => 'require|number|length:18',
        'birth' => 'require',
        'area' => 'require',
        'introduction' => 'require',
    ];
}