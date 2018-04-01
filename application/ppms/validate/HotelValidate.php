<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/14
 * Time: 21:54
 */

namespace app\ppms\validate;


class HotelValidate extends BaseValidate
{
    protected $rule=[
        'name'=>'require',
        'location'=>'require',
        'time'=>'require',
        'last'=>'require',
        'number'=>'require',
        'introduction'=>'require',
        'requirement'=>'require',
        'work'=>'require',
        'other'=>'require',
    ];

}