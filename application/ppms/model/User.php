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
        if ((int)$secret < 30){
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
        ])->find();
        if (!$info){
            throw new BaseException([
                'msg' => '未找到该用户，可能是参数有误'
            ]);
        }
        //结果的数组
        $result = [];
        $result['user_id'] = $info['id'];
        $result['username'] = $info['username'];
        $result['major'] = $info['major'];
        $result['number'] = $info['number'];
        $meeting_memebr = new Meeting_member();
        $meeting = new Meeting();
        //查到用户所在会议的id和这场会议是否出席
        $term = $data['term'];
        $now_time = time();
        $result['meeting'] = [];
        $result['sign_meeting'] = [];


        //已结束的
        $t = str_replace('-','',$term);
        //把本学期学时找出来
        $result['period'] = (string)$this->find_period($uid,$t);

            //出勤率要在已结束的里面找
        $re = $meeting_memebr->where([
            'user_id' => $uid,
            'term' => (int)$t
        ])->where('end_time','<',$now_time)->order([
            'end_time' => 'desc'
        ])->field('meeting_id,attend,sign_out')->select();
        //如果没有任何会议的话就可以直接置零了
        if ($re){
            $i = 0;
            foreach ($re as $v){
                $meeting_id = $v['meeting_id'];
                $in = $meeting->where([
                    'id' => $meeting_id
                ])->field('name,position,period,date1,date2,date3,time1,time2,re_end_time,photo')->find();
                $result['meeting'][$i]['meeting_id'] = $v['meeting_id'];
                $result['meeting'][$i]['name'] = $in['name'];
                $result['meeting'][$i]['position'] = $in['position'];
                $result['meeting'][$i]['period'] = $in['period'];
                $result['meeting'][$i]['year'] = $in['date1'];
                $result['meeting'][$i]['month'] = $in['date2'];
                $result['meeting'][$i]['day'] = $in['date3'];
                $result['meeting'][$i]['time'] = $in['time1'].':'.$in['time2'].'-'.$in['re_end_time'];
                $result['meeting'][$i]['over'] = '已结束';
                $result['meeting'][$i]['photo'] = config('setting.image_root').$in['photo'];
                if ((int)$v['attend'] == 1 && (int)$v['sign_out'] == 1){
                    $result['meeting'][$i]['state'] = '出席';
                }elseif ((int)$v['attend'] == 1 && (int)$v['sign_out'] == 0){
                    $result['meeting'][$i]['state'] = '早退';
                }elseif ((int)$v['attend'] == 0 && (int)$v['sign_out'] == 1){
                    $result['meeting'][$i]['state'] = '迟到';
                }else{
                    //未请假未出席就是缺席
                    $result['meeting'][$i]['state'] = '缺席';
                }
                $i++;
            }
        }

        //已报名的
        $sign = Db::table('sign_list sign,meeting meeting')
            ->where('sign.meeting_id=meeting.id')
            ->where('meeting.end_time','>=',(string)time())
            ->order(['meeting.end_time' => 'desc'])
            ->select();

        if ($sign){
            $i = 0;
            foreach ($sign as $in){
                $result['sign_meeting'][$i]['meeting_id'] = $in['meeting_id'];
                $result['sign_meeting'][$i]['name'] = $in['name'];
                $result['sign_meeting'][$i]['position'] = $in['position'];
                $result['sign_meeting'][$i]['period'] = $in['period'];
                $result['sign_meeting'][$i]['year'] = $in['date1'];
                $result['sign_meeting'][$i]['month'] = $in['date2'];
                $result['sign_meeting'][$i]['day'] = $in['date3'];
                $result['sign_meeting'][$i]['time'] = $in['time1'].':'.$in['time2'].'-'.$in['re_end_time'];
                if ((int)time() >= (int)$in['begin']){
                    $result['sign_meeting'][$i]['over'] = '已开始';
                }else{
                    $result['sign_meeting'][$i]['over'] = '未开始';
                }
                $result['sign_meeting'][$i]['photo'] = config('setting.image_root').$in['photo'];
                $i++;
            }
        }

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
        $re = Db::table('meeting')
            ->where('enter_end','>',$now_time)
            ->field('id,name,position,date1,date2,date3,time1,time2,period,begin,photo')
            ->select();
        //如果没有任何会议的话就可以直接置零了
        if ($re){
            $i = 0;
            foreach ($re as $in){
                $meeting_id = $in['id'];
                $result[$i]['meeting_id'] = $in['id'];
                $result[$i]['name'] = $in['name'];
                $result[$i]['position'] = $in['position'];
                $result[$i]['year'] = $in['date1'];
                $result[$i]['month'] = $in['date2'];
                $result[$i]['day'] = $in['date3'];
                $result[$i]['period'] = $in['period'];
                $result[$i]['photo'] = config('setting.image_root').$in['photo'];
                $sign_list = Db::table('sign_list')
                    ->where([
                        'meeting_id' => $meeting_id
                    ])->select();
                if (!$sign_list){
                    $result[$i]['sign_number'] = '0';
                }else{
                    $result[$i]['sign_number'] = (string)count($sign_list);
                }
                $result[$i]['begin_time'] = $in['time1'].':'.$in['time2'];
//                $result[$i]['end_time'] = $in['re_end_time'];

                $result[$i]['time_distance'] = abs((int)$in['begin'] - (int)time());

                $i++;
            }
            //排个序
            array_multisort(array_column($result,'time_distance'),SORT_ASC,$result);
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
            ])->field('attend,end_time,begin,sign_out,term')->find();
        if (!$check){
            exit(json_encode([
                'code' => 400,
                'msg' => '您未参加此次会议'
            ]));
        }

        $attend = (int)$check['attend'];
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
            ])->field('attend,end_time,begin,sign_out')->find();
        if (!$check){
            exit(json_encode([
                'code' => 400,
                'msg' => '您未参加此次会议'
            ]));
        }
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
        }elseif ($sign_out == 1){
            exit(json_encode([
                'code' => 400,
                'msg' => '您已经签过退了'
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

    public function single_meeting($data){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }

        $this->have_key_validate([
            'meeting_id' => '无会议标识！'
        ],$data);

        $meeting_id = $data['meeting_id'];
        //结果的数组
        $result = [];
        //查到用户所在会议的id和这场会议是否出席
        $now_time = time();
        //出勤率要在已结束的里面找
        $in = Db::table('meeting')
            ->where('id','=',$meeting_id)
            ->field('id,name,position,date1,date2,date3,time1,time2,period,begin,end_time,enter_begin,enter_end,description,department,type,photo,re_end_time')
            ->find();
        //如果没有任何会议的话就可以直接置零了
        if ($in){
            $meeting_id = $in['id'];
            $result['meeting_id'] = $in['id'];
            $result['name'] = $in['name'];
            $result['position'] = $in['position'];
            $result['year'] = $in['date1'];
            $result['month'] = $in['date2'];
            $result['day'] = $in['date3'];
            $result['period'] = $in['period'];
            $result['description'] = $in['description'];
            $result['department'] = $in['department'];
            $result['type'] = $in['type'];
            $result['photo'] = config('setting.image_root').$in['photo'];
            $sign_list = Db::table('sign_list')
                ->where([
                    'meeting_id' => $meeting_id
                ])->select();
            if (!$sign_list){
                $result['sign_number'] = '0';
            }else{
                $result['sign_number'] = (string)count($sign_list);
            }
            $result['time'] = $in['time1'].':'.$in['time2'].'-'.$in['re_end_time'];
            $result['sign_begin_time'] = date("Y年m月d",$in['enter_begin']);
            $result['sign_end_time'] = date("Y年m月d",$in['enter_end']);
            $sign_list = Db::table('sign_list')
                ->where([
                    'meeting_id' => $meeting_id,
                    'user_id' => $uid
                ])->find();
            //看看会议在什么状态
            $flag = 0;
            if ($now_time <= (int)$in['end_time'] && $now_time >= (int)$in['begin']){
                $flag = 1;    //讲座正在进行
                $result['meeting_status'] = '已开始';
            }elseif ($now_time > (int)$in['end_time']){
                $flag = 2;    //讲座已结束
                $result['meeting_status'] = '已结束';
            }elseif ($now_time < (int)$in['begin']){
                $flag = 3;    //讲座未开始
                $result['meeting_status'] = '未开始';
            }
            if ($now_time <= (int)$in['enter_end'] && $now_time >= (int)$in['enter_begin']){
                $flag = 4;    //讲座正在报名
                $result['meeting_status'] = '正在报名';
            }
            if (!$sign_list){
                $result['user_status'] = '未报名';
            }else{
                $member = Db::table('meeting_member')->where(['meeting_id' => $meeting_id,'user_id' => $uid])->find();
                if (!$member){
                    $result['user_status'] = '待审核';
                }elseif($flag == 4||$flag == 3){
                    $result['user_status'] = '报名成功';
                }elseif($flag == 1){
                    if ($member['attend'] == 1 && $member['sign_out'] == 1){
                        $result['user_status'] = '已签到已签退';
                    }elseif ($member['attend'] == 1 && $member['sign_out'] == 0){
                        $result['user_status'] = '已签到未签退';
                    }elseif ($member['attend'] == 0 && $member['sign_out'] == 1){
                        $result['user_status'] = '未签到已签退';
                    }elseif ($member['attend'] == 0 && $member['sign_out'] == 0){
                        $result['user_status'] = '未签到未签退';
                    }
                }elseif($flag == 2){
                    if ($member['attend'] == 1 && $member['sign_out'] == 1){
                        $result['user_status'] = '出席';
                    }elseif ($member['attend'] == 1 && $member['sign_out'] == 0){
                        $result['user_status'] = '早退';
                    }elseif ($member['attend'] == 0 && $member['sign_out'] == 1){
                        $result['user_status'] = '迟到';
                    }elseif ($member['attend'] == 0 && $member['sign_out'] == 0){
                        $result['user_status'] = '缺席';
                    }
                }
            }
//                $result['end_time'] = $in['re_end_time'];

        }else{
            throw new BaseException([
                'msg' => '未找到该会议'
            ]);
        }
        return json_encode([
            'code' => 200,
            'msg' => $result
        ]);
    }

    public function show_term(){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            exit(json_encode([
                'code' => 400,
                'msg' => '权限不足！'
            ]));
        }

        $meeting = new Meeting();
        //查学期
        $result = $meeting->distinct(true)->field('term')->order([
            'term' => 'desc'
        ])->select();
        $i = 0;
        $arr = [];
        foreach ($result as $v){
            $arr[$i] = substr($v['term'],0,4).'-'.substr($v['term'],4,4).'-'.substr($v['term'],8,1);
            $i++;
        }
        return json([
            'code' => 200,
            'msg' => $arr
        ]);
    }

    //报名取消报名
    public function sign_in_out($data){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }

        $this->have_key_validate([
            'meeting_id' => '无会议标识！'
        ],$data);

        $meeting_id = $data['meeting_id'];

        //检查是否报名
        $check_sign = Db::table('sign_list')
            ->where([
                'user_id' => $uid,
                'meeting_id' => $meeting_id
            ])->find();
        if ($check_sign){
            Db::startTrans();
            $result1 = Db::table('sign_list')
                ->where([
                    'user_id' => $uid,
                    'meeting_id' => $meeting_id
                ])->delete();
            if (!$result1){
                Db::rollback();
                throw new UpdateException([
                    'msg' => '取消报名失败！'
                ]);
            }
            //找找成员列表有没有，有的话也删掉
            if (Db::table('meeting_member')
                ->where([
                    'meeting_id' => $meeting_id,
                    'user_id' => $uid
                ])->find()){
                $result2 = Db::table('meeting_member')
                    ->where([
                        'meeting_id' => $meeting_id,
                        'user_id' => $uid
                    ])->delete();
                if (!$result2){
                    Db::rollback();
                    throw new UpdateException([
                        'msg' => '取消报名失败'
                    ]);
                }
            }
            Db::commit();
        }else{
            //没找到的话就开始报名
            $result = Db::table('sign_list')
                ->insert([
                    'user_id' => $uid,
                    'meeting_id' => $meeting_id,
                    'time' => (string)time()
                ]);
            if (!$result){
                throw new UpdateException([
                    'msg' => '报名失败'
                ]);
            }
        }

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }
}