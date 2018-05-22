<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/12
 * Time: 23:17
 */

namespace app\ppms\model;
use app\ppms\exception\BaseException;
use think\Db;
use think\Model;

class BaseModel extends Model
{
    protected function have_key_validate($data = [],$arr){
        foreach ($data as $k => $v){
            if (!array_key_exists($k,$arr)){
                exit(json_encode([
                    'code' => 400,
                    'msg' => $v
                ]));
            }
        }
    }
    //查学时的封装器
    protected function find_period($user_id, $term = 'all'){
        if ($term == 'all'){
            $member = Db::table('meeting_member')
                ->where('end_time','<',(int)time())
                ->where([
                    'user_id' => $user_id,
                ])->select();
            if (!$member){
                //没找到就是0学时
                return 0;
            }
            $all_period = 0;
            //早退次数
            $early_time = 0;
            //迟到次数
            $late = 0;
            //缺席次数
            $not = 0;
            foreach ($member as $v){
                $period = (int)$v['period'];
                if ($v['attend'] == 1 && $v['sign_out'] == 1){
                    $all_period+=$period;
                }elseif ($v['attend'] == 1 && $v['sign_out'] == 0){
                    $early_time++;
                    if ($early_time > 2) $all_period-=0.5;
                }elseif ($v['attend'] == 0 && $v['sign_out'] == 1){
                    $late++;
                    if ($late > 2) $all_period-=0.5;
                }elseif ($v['attend'] == 0 && $v['sign_out'] == 0){
                    $not++;
                    if ($not > 2) $all_period-=1;
                }
            }
            unset($member);
            return $all_period;
        }else{
            $member = Db::table('meeting_member')
                ->where('end_time','<',(int)time())
                ->where([
                    'user_id' => $user_id,
                    'term' => $term
                ])->select();
            if (!$member){
                //没找到就是0学时
                return 0;
            }
            $all_period = 0;
            //早退次数
            $early_time = 0;
            //迟到次数
            $late = 0;
            //缺席次数
            $not = 0;
            foreach ($member as $v){
                $period = (int)$v['period'];
                if ($v['attend'] == 1 && $v['sign_out'] == 1){
                    $all_period+=$period;
                }elseif ($v['attend'] == 1 && $v['sign_out'] == 0){
                    $early_time++;
                    if ($early_time > 2) $all_period-=0.5;
                }elseif ($v['attend'] == 0 && $v['sign_out'] == 1){
                    $late++;
                    if ($late > 2) $all_period-=0.5;
                }elseif ($v['attend'] == 0 && $v['sign_out'] == 0){
                    $not++;
                    if ($not > 2) $all_period-=1;
                }
            }
            unset($member);
            return $all_period;
        }
    }
}