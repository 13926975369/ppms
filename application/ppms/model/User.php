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
use app\ppms\exception\TokenException;
use app\ppms\exception\UpdateException;
use app\ppms\validate\NewPasswordValidate;
use think\Cache;
use think\Db;
use think\Validate;

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


    //小程序管理员登录接口参数检验
    public function wx_admin_login_exist($token,$data){
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

        if (!array_key_exists('code',$data)){
            throw new BaseException([
                'msg' => '无小程序code！'
            ]);
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


        if ($term == 'all'){
            $result['period'] = (string)$this->new_find_period($uid);
        }else{
            //已结束的
            $t = str_replace('-','',$term);
            //把本学期学时找出来
            $result['period'] = (string)$this->new_find_period($uid,$t);
        }




        //出勤率要在已结束的里面找
        $re = $meeting_memebr->where([
            'user_id' => $uid,
            'term' => $t
        ])->where('state','=',2)->order([
            'end_time' => 'desc'
        ])->select();

//        1.0代码
            //出勤率要在已结束的里面找
//        $re = $meeting_memebr->where([
//            'user_id' => $uid,
//            'term' => $t
//        ])->where('end_time','<',$now_time)->order([
//            'end_time' => 'desc'
//        ])->field('meeting_id,attend,sign_out')->select();
        //如果没有任何会议的话就可以直接置零了
        $attend = 0;
        $early = 0;
        $late = 0;
        $ask_leave = 0;
        $absence = 0;
        if ($re){
            $i = 0;
            foreach ($re as $v){
                $meeting_id = $v['meeting_id'];
                $in = $meeting->where([
                    'id' => $meeting_id
                ])->find();
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
                //本次会议的状态
                if ((int)$v['ask_leave'] != 0){
                    $result['meeting'][$i]['state'] = '请假';
                    $ask_leave++;
                }elseif ((int)$v['attend'] == 1 && (int)$v['sign_out'] == 1){
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
        $result['attend'] = $attend;
        $result['early'] = $early;
        $result['late'] = $late;
        $result['absence'] = $absence;
        $result['ask_leave'] = $ask_leave;

        //已报名的
        //1.0代码
//        $sign = Db::table('sign_list sign,meeting meeting')
//            ->where('sign.user_id','=',$uid)
//            ->where('sign.meeting_id=meeting.id')
////            1.0结束判断
////            ->where('meeting.end_time','>=',(string)time())
//            ->where('meeting.state','<=',1)
//            ->order(['meeting.end_time' => 'desc'])
//            ->select();
        $sign = Db::table('meeting_member')
            ->where('user_id','=',$uid)
            ->where('state','<=',1)
            ->order(['end_time' => 'desc'])
            ->select();


        if ($sign){
            $i = 0;
            foreach ($sign as $in){
                $result['sign_meeting'][$i]['meeting_id'] = $in['meeting_id'];
                $sign_name = Db::table('meeting')->where(['id' => $in['meeting_id']])->find();
                $result['sign_meeting'][$i]['name'] = $sign_name['name'];
                $result['sign_meeting'][$i]['position'] = $in['position'];
                $result['sign_meeting'][$i]['period'] = $in['period'];
                $result['sign_meeting'][$i]['year'] = $in['date1'];
                $result['sign_meeting'][$i]['month'] = $in['date2'];
                $result['sign_meeting'][$i]['day'] = $in['date3'];
                $result['sign_meeting'][$i]['time'] = $in['time1'].':'.$in['time2'].'-'.$in['re_end_time'];
//                1.0判断会议状态
//                if ((int)time() >= (int)$in['begin']){
//                    $result['sign_meeting'][$i]['over'] = '已开始';
//                }else{
//                    $result['sign_meeting'][$i]['over'] = '未开始';
//                }

                if ((int)$in['state'] == 1){
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
        //已结束和未开始的里面找
        $re = Db::table('meeting')
            ->where('state','=',0)
            ->whereOr('state','=',1)
            ->field('id,name,position,date1,date2,date3,time1,time2,period,begin,photo,state,sign_number')
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
                $result[$i]['sign_number'] = $in['sign_number'];
//                1.0的代码
//                $sign_list = Db::table('sign_list')
//                    ->where([
//                        'meeting_id' => $meeting_id
//                    ])->select();
//                if (!$sign_list){
//                    $result[$i]['sign_number'] = '0';
//                }else{
//                    $result[$i]['sign_number'] = (string)count($sign_list);
//                }
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
        $re = Db::table('meeting_member')->where([
            'user_id' => $uid
        ])
            ->where('state','=',2)
            ->distinct(true)->order([
            'term' => 'desc'
            ])->field('term')->select();
        //如果没有任何会议的话就可以直接置零了
        if ($re){
            $i = 0;
            foreach ($re as $v) {
                $result[$i]['term'] = substr($v['term'],0,4).'-'.substr($v['term'],4,4).'-'.substr($v['term'],8,1);
                $i++;
            }
        }else{
            //当前时间
            $year = date("Y");
            $month = date("m");
            if ((int)$month >= 8){
                $t = 1;
            }else{
                $t = 2;
                $year = (int)$year-1;
            }
            $term = (string)$year.'-'.(string)($year+1).'-'.(string)$t;
            $result[0]['term'] = $term;
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
//        $end_time = (int)$check['end_time'];
//        $begin = (int)$check['begin'];
//        $time = (int)time();
//        if ($time<$begin){
//            exit(json_encode([
//                'code' => 400,
//                'msg' => '会议未开始'
//            ]));
//        }elseif ($time>$end_time){
//            exit(json_encode([
//                'code' => 400,
//                'msg' => '会议已结束'
//            ]));
//        }elseif ($attend == 1){
//            exit(json_encode([
//                'code' => 400,
//                'msg' => '您已经签过到了'
//            ]));
//        }else{
//            $result = Db::table('meeting_member')
//                ->where([
//                    'user_id' => $uid,
//                    'meeting_id' => $data['meeting_id']
//                ])->update([
//                    'attend' => 1
//                ]);
//            if (!$result){
//                throw new UpdateException();
//            }
//        }
        if ($attend != 1){
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
//        $end_time = (int)$check['end_time'];
//        $begin = (int)$check['begin'];
//        $time = (int)time();
//        if ($time<$begin){
//            exit(json_encode([
//                'code' => 400,
//                'msg' => '会议未开始'
//            ]));
//        }elseif ($time>$end_time){
//            exit(json_encode([
//                'code' => 400,
//                'msg' => '会议已结束'
//            ]));
//        }elseif ($sign_out == 1){
//            exit(json_encode([
//                'code' => 400,
//                'msg' => '您已经签过退了'
//            ]));
//        }else{
//            $result = Db::table('meeting_member')
//                ->where([
//                    'user_id' => $uid,
//                    'meeting_id' => $data['meeting_id']
//                ])->update([
//                    'sign_out' => 1
//                ]);
//            if (!$result){
//                throw new UpdateException();
//            }
//        }
        if ($sign_out != 1){
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
            'msg' => '签退成功'
        ]);
    }

    //扫码签迟到
    public function sign_late($data){
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
        if ($vars != 3){
            exit(json_encode([
                'code' => 400,
                'msg' => '不可用签到码或者签退码'
            ]));
        }
        $check = Db::table('meeting_member')
            ->where([
                'user_id' => $uid,
                'meeting_id' => $data['meeting_id']
            ])->field('late')->find();
        if (!$check){
            exit(json_encode([
                'code' => 400,
                'msg' => '您未参加此次会议'
            ]));
        }
        $late = (int)$check['late'];
        if ($late != 1){
            $result = Db::table('meeting_member')
                ->where([
                    'user_id' => $uid,
                    'meeting_id' => $data['meeting_id']
                ])->update([
                    'late' => 1
                ]);
            if (!$result){
                throw new UpdateException();
            }
        }
        return json_encode([
            'code' => 200,
            'msg' => '签迟到成功'
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
            ->field('id,name,position,date1,date2,date3,time1,time2,period,begin,end_time,enter_begin,enter_end,description,department,type,photo,re_end_time,sign_number,state,people')
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
            $result['people'] = $in['people'];
            $result['photo'] = config('setting.image_root').$in['photo'];
            $result['sign_number'] = $in['sign_number'];
//            1.0代码
//            $sign_list = Db::table('sign_list')
//                ->where([
//                    'meeting_id' => $meeting_id
//                ])->select();
//            if (!$sign_list){
//                $result['sign_number'] = '0';
//            }else{
//                $result['sign_number'] = (string)count($sign_list);
//            }
            $result['time'] = $in['time1'].':'.$in['time2'].'-'.$in['re_end_time'];
            $result['sign_begin_time'] = date("Y年m月d",$in['enter_begin']);
            $result['sign_end_time'] = date("Y年m月d",$in['enter_end']);
            $member = Db::table('meeting_member')->where(['meeting_id' => $meeting_id,'user_id' => $uid])->find();

            //看看会议在什么状态
            $flag = 0;
            if ((int)$in['state'] == 0){
                $flag = 3;    //讲座未开始
                $result['meeting_status'] = '未开始';
            }

            if ($now_time <= (int)$in['enter_end'] && $now_time >= (int)$in['enter_begin']){
                $flag = 4;    //讲座正在报名
                $result['meeting_status'] = '正在报名';
            }

            if ((int)$in['state'] == 1){
                $flag = 1;    //讲座正在进行
                $result['meeting_status'] = '已开始';
            }elseif ((int)$in['state'] == 2){
                $flag = 2;    //讲座已结束
                $result['meeting_status'] = '已结束';
            }
//            1.0代码
            //看看会议在什么状态
//            $flag = 0;
//            if ($now_time < (int)$in['begin']){
//                $flag = 3;    //讲座未开始
//                $result['meeting_status'] = '未开始';
//            }
//
//            if ($now_time <= (int)$in['enter_end'] && $now_time >= (int)$in['enter_begin']){
//                $flag = 4;    //讲座正在报名
//                $result['meeting_status'] = '正在报名';
//            }
//
//            if ($now_time <= (int)$in['end_time'] && $now_time >= (int)$in['begin']){
//                $flag = 1;    //讲座正在进行
//                $result['meeting_status'] = '已开始';
//            }elseif ($now_time > (int)$in['end_time']){
//                $flag = 2;    //讲座已结束
//                $result['meeting_status'] = '已结束';
//            }

            if (!$member){
                $result['user_status'] = '未报名';
                if ($in['sign_number'] == $in['people']) $result['user_status'] = '已报满';
            }else{
                if($flag == 4||$flag == 3){
                    $result['user_status'] = '报名成功';
                    if ((int)$member['ask_leave'] != 0) $result['user_status'] = '已请假';
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
                    if ($member['late'] == 1){
                        $result['user_status'] = '迟到未签退';
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
        $enter_time = Db::table('meeting')
            ->where([
                'id' => $meeting_id
            ])->find();
        if (!$enter_time){
            throw new BaseException([
                'msg' => '未找到会议'
            ]);
        }
        $sign_number = (int)$enter_time['sign_number'];
        $enter_begin = (int)$enter_time['enter_begin'];
        $enter_end = (int)$enter_time['enter_end'];
        if ((int)time()>$enter_end && (int)time() < $enter_begin){
            throw new BaseException([
                'msg' => '未在报名时间'
            ]);
        }

        //检查是否报名
        $check_sign = Db::table('meeting_member')
            ->where([
                'meeting_id' => $meeting_id,
                'user_id' => $uid
            ])->find();
        if ($check_sign){
            //1.0代码
//            Db::startTrans();
//            $result1 = Db::table('sign_list')
//                ->where([
//                    'user_id' => $uid,
//                    'meeting_id' => $meeting_id
//                ])->delete();
//            if (!$result1){
//                Db::rollback();
//                throw new UpdateException([
//                    'msg' => '取消报名失败！'
//                ]);
//            }
            $result3 = Db::table('meeting')
                ->where([
                    'id' => $meeting_id
                ])->update([
                    'sign_number' => $sign_number-1
                ]);
            if (!$result3){
                Db::rollback();
                throw new UpdateException([
                    'msg' => '取消报名失败！'
                ]);
            }
            //找找成员列表，删掉
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
            Db::commit();
        }else{
            //没找到的话就开始报名
            if ($enter_time['people'] == $enter_time['sign_number']){
                throw new BaseException([
                    'msg' => '已满人'
                ]);
            }
            Db::startTrans();
            //1.0代码
//            $result = Db::table('sign_list')
//                ->insert([
//                    'user_id' => $uid,
//                    'meeting_id' => $meeting_id,
//                    'time' => (string)time()
//                ]);
//            if (!$result){
//                Db::rollback();
//                throw new UpdateException([
//                    'msg' => '报名失败'
//                ]);
//            }
            //插入member
            $re = $enter_time;
            $term = $re['term'];
            $end_time = $re['end_time'];
            $begin = $re['begin'];
            $enter_begin = $re['enter_begin'];
            $enter_end = $re['enter_end'];
            $period = $re['period'];
            $in = Db::table('meeting_member')
                ->insert([
                    'meeting_id' => $meeting_id,
                    'user_id' => $uid,
                    'term' => $term,
                    'end_time' => $end_time,
                    'begin' => $begin,
                    'enter_begin' => $enter_begin,
                    'enter_end' => $enter_end,
                    'people' => $re['people'],
                    'state' => $re['state'],
                    'period' => $period,
                    'time' => (string)time()
                ]);
            if (!$in){
                Db::rollback();
                exit(json_encode([
                    'code' => 504,
                    'msg' => '更新出错，请重试！'
                ]));
            }

            $result3 = Db::table('meeting')
                ->where([
                    'id' => $meeting_id
                ])->update([
                    'sign_number' => $sign_number+1
                ]);
            if (!$result3){
                Db::rollback();
                throw new UpdateException([
                    'msg' => '报名失败！'
                ]);
            }

            Db::commit();
        }

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }


    //请假
    public function ask_leave($data){
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
        $enter_time = Db::table('meeting')
            ->where([
                'id' => $meeting_id
            ])->find();
        if (!$enter_time){
            throw new BaseException([
                'msg' => '未找到会议'
            ]);
        }

        if ((int)$enter_time['state'] != 0 || (int)time() > (int)$enter_time['begin']){
            throw new BaseException([
                'msg' => '会议不在未开始状态'
            ]);
        }
        //请假的话会把报名人数减一
        $sign_number = (int)$enter_time['sign_number'];
        $begin = (int)$enter_time['begin'];
        $sign_number -= 1;
        //判断24小时状态（开始前24小时内请假倒扣0.5分,扣分在结束按钮里）
        $begin -= 86400;
        if ((int)time() > $begin){
            //扣0.5
            $flag = 2;
        }else{
            $flag = 1;
        }


        Db::startTrans();

        Db::table('meeting_member')->where([
            'meeting_id' => $data['meeting_id'],
            'user_id' => $uid
        ])->update([
            'ask_leave' => $flag
        ]);

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //签退评论
    public function discussion($data){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }

        $this->have_key_validate([
            'meeting_id' => '无会议标识！',
            'content_discussion' => '无内容评论星级！',
            'holder_discussion' => '无主讲人评论星级！',
            'text' => '无评论内容！'
        ],$data);

        $meeting_id = $data['meeting_id'];
        $content_discussion = $data['content_discussion'];
        $holder_discussion = $data['holder_discussion'];
        $text = $data['text'];
        $enter_time = Db::table('meeting')
            ->where([
                'id' => $meeting_id
            ])->find();
        if (!$enter_time){
            throw new BaseException([
                'msg' => '未找到会议'
            ]);
        }
        //插入评论
        $discussion = Db::table('comment')->insert([
            'user_id' => $uid,
            'meeting_id' => $meeting_id,
            'text' => $text,
            'content' => $content_discussion,
            'holder' => $holder_discussion,
            'time' => time()
        ]);

        if (!$discussion){
            throw new UpdateException([
                'msg' => '评论失败'
            ]);
        }

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }


    //绑定邮箱
    public function bind_email($data){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }

        $this->have_key_validate([
            'email' => '无邮箱！'
        ],$data);

        $email = $data['email'];
        $rule = [
            'email'  => 'require|email'
        ];
        $msg = [
            'email.require' => '邮箱不能为空',
            'email.email' => '邮箱格式不正确'
        ];
        $validate = new Validate($rule,$msg);
        $result = $validate->check($data);
        if(!$result){
            throw new BaseException([
                'msg' => $validate->getError()
            ]);
        }

        $email_check = Db::table('user')->where(['id' => $uid])->find();
        if ($email != $email_check['email']){
            $update_email = Db::table('user')->where(['id' => $uid])->update(['email' => $email]);
            if (!$update_email){
                throw new UpdateException();
            }
        }

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }


    //查看某会议的平均评价星级
    public function show_meeting_average($data){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret < 30){
            throw new PowerException();
        }

        $this->have_key_validate([
            'meeting_id' => '无会议标识！'
        ],$data);

        $meeting_id = $data['meeting_id'];
        //查出会议评论记录
        $comment = Db::table("comment")->where([
            'meeting_id' => $meeting_id
        ])->select();

        $all_content = 0;
        $all_holder = 0;
        $people = 0;
        if (!$comment){
            $result['content'] = 0;
            $result['holder'] = 0;
            return json([
                'code' => 200,
                'msg' => $result
            ]);
        }
        foreach ($comment as $k => $v){
            $all_content += (int)$v['content'];
            $all_holder += (int)$v['holder'];
            $people += 1;
        }
        return json([
            'code' => 200,
            'msg' => [
                'content' => (double)$all_content/$people,
                'holder' => (double)$all_holder/$people
            ]
        ]);
    }


    //查看某个会议的详细评价
    public function show_meeting_comment($data){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret < 30){
            throw new PowerException();
        }

        $this->have_key_validate([
            'meeting_id' => '无会议标识！'
        ],$data);

        $meeting_id = $data['meeting_id'];
        //查出会议评论记录
        $comment = Db::table("comment")->where([
            'meeting_id' => $meeting_id
        ])->field('user_id,content,holder,text')->select();
        foreach ($comment as $k => $v){
            $user_id = $v['user_id'];
            //查用户名和学院
            $user_info = Db::table('user')->where([
                'id' => $user_id
            ])->find();
            $comment[$k]['username'] = $user_info['username'];
            $comment[$k]['major'] = $user_info['major'];
        }

        return json([
            'code' => 200,
            'msg' => $comment
        ]);
    }

    public function collect_formId($data){
        $TokenModel = new Token();

        //通过token获取id并且判断token是否有效
        $uid = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 16){
            throw new PowerException();
        }

        $this->have_key_validate([
            'form_id' => '无form_id！'
        ],$data);

        $form_id = Cache::get($uid);
        if (!$form_id){
            //没有的情况
            $form_id = [0 => $data['form_id']];
        }else{
            $index = count($form_id);
            $form_id[$index] = $data['form_id'];
        }
        //收集form_id并存储7天
        $request = cache($uid, $form_id, 7 * 24 * 60 * 60);
        if (!$request){
            throw new TokenException([
                'msg' => '服务器缓存异常',
            ]);
        }

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }
}