<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/1
 * Time: 23:42
 */

namespace app\ppms\model;
use app\ppms\exception\BaseException;
use app\ppms\exception\LoginException;
use app\ppms\exception\PowerException;
use app\ppms\exception\UpdateException;
use app\ppms\validate\NewPasswordValidate;
use think\Cache;
use think\Db;

class User extends BaseModel
{
    //验证请求登录接口的时候是否有用户名和密码
    public function login_exist_validate($token,$data){
        //判断数据data
        if ($token != 'login'){
            throw new BaseException([
                'msg' => '参数并非为登录参数，请重新请求！'
            ]);
        }
        if (!array_key_exists('username',$data)){
            throw new BaseException([
                'msg' => '参数数据缺失第一项！'
            ]);
        }
        if (!array_key_exists('password',$data)){
            throw new BaseException([
                'msg' => '参数数据缺失第二项！'
            ]);
        }
        if (!array_key_exists('code',$data)){
            throw new BaseException([
                'msg' => '无小程序code！'
            ]);
        }
    }

    public function change_psw_validate($data){
        if (!array_key_exists('old_password',$data)){
            throw new BaseException([
                'msg' => '参数数据缺失第一项！'
            ]);
        }
        if (!array_key_exists('password',$data)){
            throw new BaseException([
                'msg' => '参数数据缺失第二项！'
            ]);
        }
        if (!array_key_exists('password_check',$data)){
            throw new BaseException([
                'msg' => '参数数据缺失第三项！'
            ]);
        }
        //validate
        (new NewPasswordValidate())->goToCheck($data);
    }

    public function admin_login_exist($token,$data){
        //判断数据data
        if ($token != 'super_login'){
            exit(json_encode([
                'code' => 400,
                'msg' => "参数并非为登录参数，请重新请求！"
            ]));
        }
        if (!array_key_exists('username',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => "参数数据缺失第一项！"
            ]));
        }
        if (!array_key_exists('password',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => "参数数据缺失第二项！"
            ]));
        }
    }

    public function get_admin_name(){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $super = new Super();
        $info = $super->where([
            'id' => $id
        ])->field('nickname')->find();
        return json([
            'code' => 200,
            'msg' => $info['nickname']
        ]);
    }

    public function get_user_info($data){
        $user = new User();
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }
        $info = $user->where([
            'id' => $uid
        ])->field('username,major')->find();
        if (!$info){
            throw new BaseException([
                'msg' => '未找到该用户，可能是参数有误'
            ]);
        }
        //结果的数组
        $result = [];
        $result['username'] = $info['username'];
        $result['major'] = $info['major'];
        //出席，请假，缺席
        $attend = 0;
        $ask_leave = 0;
        $absence = 0;
        $early = 0;
        $late = 0;
        $meeting_memebr = new Meeting_member();
        $meeting = new Meeting();
        //查到用户所在会议的id和这场会议是否出席
        $term = $data['term'];
        $now_time = time();
        $result['meeting'] = [];
        if ($term == 'all'){
            //出勤率要在已结束的里面找
            $re = $meeting_memebr->where([
                'user_id' => $uid
            ])->where('end_time','<',$now_time)->order([
                'end_time' => 'desc'
            ])->field('meeting_id,ask_leave,attend,sign_out')->select();
            //如果没有任何会议的话就可以直接置零了
            if ($re){
                $i = 0;
                foreach ($re as $v){
                    $meeting_id = $v['meeting_id'];
                    $in = $meeting->where([
                        'id' => $meeting_id
                    ])->field('name,position,date1,date2,date3,time1,time2')->find();
                    $result['meeting'][$i]['meeting_id'] = $v['meeting_id'];
                    $result['meeting'][$i]['name'] = $in['name'];
                    $result['meeting'][$i]['position'] = $in['position'];
                    $result['meeting'][$i]['year'] = $in['date1'];
                    $result['meeting'][$i]['month'] = $in['date2'];
                    $result['meeting'][$i]['day'] = $in['date3'];
                    $result['meeting'][$i]['hour'] = $in['time1'];
                    $result['meeting'][$i]['minute'] = $in['time2'];
                    $result['meeting'][$i]['over'] = '已结束';
                    //先看是否请假
                    if ((int)$v['ask_leave'] == 1){
                        $ask_leave++;
                        $result['meeting'][$i]['state'] = '请假';
                        $i++;
                        continue;
                    }
                    if ((int)$v['attend'] == 1 && (int)$v['sign_out'] == 1){
                        $result['meeting'][$i]['state'] = '出席';
                        $attend++;
                    }elseif ((int)$v['attend'] == 1 && (int)$v['sign_out'] == 0){
                        $result['meeting'][$i]['state'] = '早退';
                        $early++;
                    }elseif ((int)$v['attend'] == 0 && (int)$v['sign_out'] == 1){
                        $result['meeting'][$i]['state'] = '迟到';
                        $late++;
                    }else{
                        //未请假未出席就是缺席
                        $result['meeting'][$i]['state'] = '缺席';
                        $absence++;
                    }
                    $i++;
                }
            }
        }else{
            $t = str_replace('-','',$term);
            //出勤率要在已结束的里面找
            $re = $meeting_memebr->where([
                'user_id' => $uid,
                'term' => (int)$t
            ])->where('end_time','<',$now_time)->order([
                'end_time' => 'desc'
            ])->field('meeting_id,ask_leave,attend,sign_out')->select();
            //如果没有任何会议的话就可以直接置零了
            if ($re){
                $i = 0;
                foreach ($re as $v){
                    $meeting_id = $v['meeting_id'];
                    $in = $meeting->where([
                        'id' => $meeting_id
                    ])->field('name,position,date1,date2,date3,time1,time2')->find();
                    $result['meeting'][$i]['meeting_id'] = $v['meeting_id'];
                    $result['meeting'][$i]['name'] = $in['name'];
                    $result['meeting'][$i]['position'] = $in['position'];
                    $result['meeting'][$i]['year'] = $in['date1'];
                    $result['meeting'][$i]['month'] = $in['date2'];
                    $result['meeting'][$i]['day'] = $in['date3'];
                    $result['meeting'][$i]['hour'] = $in['time1'];
                    $result['meeting'][$i]['minute'] = $in['time2'];
                    $result['meeting'][$i]['over'] = '已结束';
                    //先看是否请假
                    if ((int)$v['ask_leave'] == 1){
                        $ask_leave++;
                        $result['meeting'][$i]['state'] = '请假';
                        $i++;
                        continue;
                    }
                    if ((int)$v['attend'] == 1 && (int)$v['sign_out'] == 1){
                        $result['meeting'][$i]['state'] = '出席';
                        $attend++;
                    }elseif ((int)$v['attend'] == 1 && (int)$v['sign_out'] == 0){
                        $result['meeting'][$i]['state'] = '早退';
                        $early++;
                    }elseif ((int)$v['attend'] == 0 && (int)$v['sign_out'] == 1){
                        $result['meeting'][$i]['state'] = '迟到';
                        $late++;
                    }else{
                        //未请假未出席就是缺席
                        $result['meeting'][$i]['state'] = '缺席';
                        $absence++;
                    }
                    $i++;
                }
            }
        }
        $result['attend'] = $attend;
        $result['ask_leave'] = $ask_leave;
        $result['absence'] = $absence;
        $result['early'] = $early;
        $result['late'] = $late;
        return json_encode([
            'code' => 200,
            'msg' => $result
        ]);
    }

    public function get_top_info(){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }

        //结果的数组
        $result = [];
        $meeting_memebr = new Meeting_member();
        $meeting = new Meeting();
        //查到用户所在会议的id和这场会议是否出席
        $now_time = time();
        //出勤率要在已结束的里面找
        $re = $meeting_memebr->where([
            'user_id' => $uid
        ])->where('end_time','>',$now_time)->order([
            'begin' => 'desc'
        ])->field('meeting_id,ask_leave,attend,begin,sign_out')->select();
        //如果没有任何会议的话就可以直接置零了
        if ($re){
            $i = 0;
            foreach ($re as $v){
                $meeting_id = $v['meeting_id'];
                $in = $meeting->where([
                    'id' => $meeting_id
                ])->field('name,position,date1,date2,date3,time1,time2')->find();
                $result[$i]['meeting_id'] = $v['meeting_id'];
                $result[$i]['name'] = $in['name'];
                $result[$i]['position'] = $in['position'];
                $result[$i]['year'] = $in['date1'];
                $result[$i]['month'] = $in['date2'];
                $result[$i]['day'] = $in['date3'];
                $result[$i]['hour'] = $in['time1'];
                $result[$i]['minute'] = $in['time2'];
                if ((int)$now_time < (int)$v['begin']){
                    $result[$i]['over'] = '未开始';
                    $result[$i]['state'] = '未签到';
                }else{
                    $result[$i]['over'] = '已开始';
                    //先看是否请假
                    if ((int)$v['ask_leave'] == 1){
                        $result[$i]['state'] = '请假';
                        $i++;
                        continue;
                    }
                    if ((int)$v['attend'] == 1 && (int)$v['sign_out'] == 1){
                        $result[$i]['state'] = '已签到';
                    }elseif ((int)$v['attend'] == 1 && (int)$v['sign_out'] == 0){
                        $result[$i]['state'] = '未签退';
                    }elseif ((int)$v['attend'] == 0 && (int)$v['sign_out'] == 1){
                        $result[$i]['state'] = '迟到';
                    }else{
                        $result[$i]['state'] = '未签到';
                    }
                }

                $i++;
            }
        }
        return json_encode([
            'code' => 200,
            'msg' => $result
        ]);
    }


    public function get_user_term(){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }
        //结果的数组
        $result = [];
        $meeting_memebr = new Meeting_member();
        $meeting = new Meeting();
        //查到用户所在会议的id和这场会议是否出席
        $now_time = time();
        //查已结束会议学期
        $re = $meeting_memebr->where([
            'user_id' => $uid
        ])->where('end_time','<',$now_time)->distinct(true)->order([
            'term' => 'desc'
        ])->field('term')->select();
        //如果没有任何会议的话就可以直接置零了
        if ($re){
            $i = 0;
            foreach ($re as $v) {
                $result[$i]['term'] = substr($v['term'],0,4).'-'.substr($v['term'],4,4).'-'.substr($v['term'],8,1);
                $i++;
            }
        }
        return json_encode([
            'code' => 200,
            'msg' => $result
        ]);
    }

    public function sign_up($data){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }
        if (!array_key_exists('meeting_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议标识！'
            ]));
        }
        if (!array_key_exists('code_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无二维码标识！'
            ]));
        }
        if (!is_numeric($data['meeting_id'])){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议标识不为数字！'
            ]));
        }
        $code_id = $data['code_id'];
        $vars = Cache::get($code_id);
        if (!$vars){
            exit(json_encode([
                'code' => 400,
                'msg' => '二维码失效'
            ]));
        }
        if ($vars != 1){
            exit(json_encode([
                'code' => 400,
                'msg' => '不可用签到码签退会议'
            ]));
        }
        $check = Db::table('meeting_member')
            ->where([
                'user_id' => $uid,
                'meeting_id' => $data['meeting_id']
            ])->field('attend,ask_leave,end_time,begin,sign_out')->find();
        if (!$check){
            exit(json_encode([
                'code' => 400,
                'msg' => '您未参加此次会议'
            ]));
        }
        $attend = (int)$check['attend'];
        $ask_leave = (int)$check['ask_leave'];
        $sign_out = (int)$check['sign_out'];
        $end_time = (int)$check['end_time'];
        $begin = (int)$check['begin'];
        $time = (int)time();
        if ($time<$begin){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议未开始'
            ]));
        }elseif ($time>$end_time){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议已结束'
            ]));
        }elseif ($attend == 1){
            exit(json_encode([
                'code' => 400,
                'msg' => '您已经签过到了'
            ]));
        }elseif ($ask_leave == 1){
            exit(json_encode([
                'code' => 400,
                'msg' => '您已经请假，不可签到'
            ]));
        }else{
            $result = Db::table('meeting_member')
                ->where([
                    'user_id' => $uid,
                    'meeting_id' => $data['meeting_id']
                ])->update([
                    'attend' => 1
                ]);
            if (!$result){
                throw new UpdateException();
            }
        }
        return json_encode([
            'code' => 200,
            'msg' => '签到成功'
        ]);
    }

    public function sign_out($data){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }
        if (!array_key_exists('meeting_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议标识！'
            ]));
        }
        if (!array_key_exists('code',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无二维码标识！'
            ]));
        }
        if (!is_numeric($data['meeting_id'])){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议标识不为数字！'
            ]));
        }
        $code_id = $data['code'];
        $vars = Cache::get($code_id);
        if (!$vars){
            exit(json_encode([
                'code' => 400,
                'msg' => '二维码失效'
            ]));
        }
        if ($vars != 2){
            exit(json_encode([
                'code' => 400,
                'msg' => '不可用签到码签退会议'
            ]));
        }
        $check = Db::table('meeting_member')
            ->where([
                'user_id' => $uid,
                'meeting_id' => $data['meeting_id']
            ])->field('attend,ask_leave,end_time,begin,sign_out')->find();
        if (!$check){
            exit(json_encode([
                'code' => 400,
                'msg' => '您未参加此次会议'
            ]));
        }
        $sign_out = (int)$check['sign_out'];
        $ask_leave = (int)$check['ask_leave'];
        $end_time = (int)$check['end_time'];
        $begin = (int)$check['begin'];
        $time = (int)time();
        if ($time<$begin){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议未开始'
            ]));
        }elseif ($time>$end_time){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议已结束'
            ]));
        }elseif ($sign_out == 1){
            exit(json_encode([
                'code' => 400,
                'msg' => '您已经签过退了'
            ]));
        }elseif ($ask_leave == 1){
            exit(json_encode([
                'code' => 400,
                'msg' => '您已经请假，不可签到'
            ]));
        }else{
            $result = Db::table('meeting_member')
                ->where([
                    'user_id' => $uid,
                    'meeting_id' => $data['meeting_id']
                ])->update([
                    'sign_out' => 1
                ]);
            if (!$result){
                throw new UpdateException();
            }
        }
        return json_encode([
            'code' => 200,
            'msg' => '签到成功'
        ]);
    }
}