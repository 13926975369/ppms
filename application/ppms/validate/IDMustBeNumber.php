<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/13
 * Time: 22:20
 */

namespace app\ppms\validate;


class IDMustBeNumber extends BaseValidate
{
    protected $rule = [
        'id' => 'require|number'
    ];

    protected $message = [
        'id.require' => 'id不能为空！',
        'id.number' => 'id必须为数字！',
    ];

}