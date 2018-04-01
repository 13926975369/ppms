<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/3
 * Time: 8:00
 */

namespace app\ppms\validate;


use app\ppms\exception\BaseException;

class SetMeeting extends BaseValidate
{
    protected $rule = [
        'name' => 'require',
        'date1' => 'require|fourcheck',
        'date2' => 'require|datacheck',
        'date3' => 'require|datacheck',
        'time1' => 'require|datacheck',
        'time2' => 'require|datacheck',
        'position' => 'require',
        'term1' => 'require|fourcheck',
        'term2' => 'require|fourcheck',
        'term3' => 'require|onecheck',
    ];

    protected $message = [
        'name.require' => '会议名称不能为空！',
        'date1.require' => '日期不能为空！',
        'date2.require' => '日期不能为空！',
        'date3.require' => '日期不能为空！',
        'time1.require' => '时间不能为空！',
        'time2.require' => '时间不能为空！',
        'position.require' => '地点不能为空！',
        'term1.require' => '学期不能为空！',
        'term2.require' => '学期不能为空！',
        'term3.require' => '学期不能为空！',
    ];

    protected $field = [
        'name' => '会议名称',
        'date1' => '日期',
        'date2' => '日期',
        'date3' => '日期',
        'time1' => '时间',
        'time2' => '时间',
        'position' => '地点',
        'term1' => '学期',
        'term2' => '学期',
        'term3' => '学期',
    ];

    protected function fourcheck($value,$rule = '',$date = '',$field=''){
        if(preg_match("/^[0-9]{4}$/",$value)){
            return true;
        }else{
            if ($field=='date1'){
                throw new BaseException([
                    'msg' => "日期的第一个空必须由四位数字组成！"
                ]);
            }elseif ($field == 'term1'){
                throw new BaseException([
                    'msg' => "学期的第一个空必须由四位数字组成！"
                ]);
            }elseif ($field == 'term2'){
                throw new BaseException([
                    'msg' => "学期的第二个空必须由四位数字组成！"
                ]);
            }

        }
    }

    protected function onecheck($value,$rule = '',$date = '',$field=''){
        if(preg_match("/^[0-9]{1}$/",$value)){
            return true;
        }else{
            throw new BaseException([
                'msg' => "学期第三个空必须由一位数字组成！"
            ]);
        }
    }

    protected function datacheck($value,$rule = '',$date = '',$field=''){
        if(preg_match("/^[0-9]{2}$/",$value)){
            return true;
        }else{
            if ($field=='date2'){
                throw new BaseException([
                    'msg' => "日期的第二个空必须由两位数字组成！"
                ]);
            }elseif ($field == 'date3'){
                throw new BaseException([
                    'msg' => "日期的第三个空必须由两位数字组成！"
                ]);
            }elseif ($field == 'time1'){
                throw new BaseException([
                    'msg' => "时间的第一个空必须由两位数字组成！"
                ]);
            }elseif ($field == 'time2'){
                throw new BaseException([
                    'msg' => "时间的第二个空必须由两位数字组成！"
                ]);
            }
        }
    }
}