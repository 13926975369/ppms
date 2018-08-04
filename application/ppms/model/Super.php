<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/2
 * Time: 20:04
 */

namespace app\ppms\model;
use app\ppms\exception\BaseException;
use app\ppms\exception\LoginException;
use app\ppms\exception\PowerException;
use app\ppms\exception\UpdateException;
use app\ppms\exception\UploadException;
use app\ppms\exception\UserExistException;
use app\ppms\validate\IDMustBeNumber;
use app\ppms\validate\Search;
use app\ppms\validate\SetMeeting;
use app\ppms\validate\ShowCheckMeeting;
use app\ppms\validate\ShowMeeting;
use think\Db;
use think\Loader;
use think\Request;
use think\Validate;

class Super extends BaseModel
{
    //发布会议
    public function set_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }

        if (!array_key_exists('name',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议名称！'
            ]));
        }
        if (!array_key_exists('date1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！(第一空)！'
            ]));
        }
        if (!array_key_exists('date2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！（第二空）'
            ]));
        }
        if (!array_key_exists('date3',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！（第三空）'
            ]));
        }
        if (!array_key_exists('time1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无时间！(第一空)'
            ]));
        }
        if (!array_key_exists('time2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无时间！(第二空)'
            ]));
        }
        if (!array_key_exists('position',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无地点！'
            ]));
        }
        if (!array_key_exists('term1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第一空)'
            ]));
        }
        if (!array_key_exists('term2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第二空)'
            ]));
        }
        if (!array_key_exists('term3',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第三空)'
            ]));
        }
        $this->have_key_validate([
            'meeting_type' => '无会议类型！',
            'department' => '无开课部门！',
            'enter_begin' => '无报名开始时间！',
            'enter_end' => '无报名结束时间！',
            'description' => '无内容简介！',
            'end_time' => '无会议结束时间！',
            'member' => '无会议学期和年级！',
        ],$data);
        (new SetMeeting())->goToCheck($data);
        //过滤
        $name = filter($data['name']);
        $date1 = filter($data['date1']);
        $date2 = filter($data['date2']);
        $date3 = filter($data['date3']);
        $time1 = filter($data['time1']);
        $time2 = filter($data['time2']);
        $position= filter($data['position']);
        $term1= filter($data['term1']);
        $term2= filter($data['term2']);
        $term3= filter($data['term3']);
        $type = $data['meeting_type'];
        $department = $data['department'];
        $enter_begin = $data['enter_begin'];
        $enter_begin[10] = ' ';
        $enter_begin[13] = ':';
        $enter_end = $data['enter_end'];
        $enter_end[10] = ' ';
        $enter_end[13] = ':';
        $description = $data['description'];
        $period = $data['period'];
        $end_time = $data['end_time'];
        $re = $data['end_time'];
        $member = $data['member'];

        if (!((int)$date1<=(int)$term2&&(int)$date1>=(int)$term1)){
            exit(json_encode([
                'code' => 400,
                'msg' => '输入日期中的年份未在输入的学期之间，请检查后重新输入！'
            ]));
        }
        $end_time = $date1.'-'.$date2.'-'.$date3.' '.$end_time.':00';
        $end_time = (int)strtotime($end_time)+1200;
        $begin_time = $date1.'-'.$date2.'-'.$date3.' '.$time1.':'.$time2.':00';
        $begin_time = (int)strtotime($begin_time)-1200;
        $enter_begin = strtotime($enter_begin);
        $enter_end = strtotime($enter_end);

        //上传图片
        $url = '';
        $photo = Request::instance()->file('photo');
        Db::startTrans();
        if (!$photo){
            $result = Db::table('meeting')->insertGetId([
                'name' => $name,
                'date1' => $date1,
                'date2' => $date2,
                'date3' => $date3,
                'time1' => $time1,
                'time2' => $time2,
                'position' => $position,
                'term1' => $term1,
                'term2' => $term2,
                'term3' => $term3,
                'term' => (int)($term1.$term2.$term3),
                'begin' => $begin_time,
                'end_time' => $end_time,
                'type' => $type,
                'department' => $department,
                'enter_begin' => $enter_begin,
                'description' => $description,
                'enter_end' => $enter_end,
                'period' => $period,
                're_end_time' => $re,
                'publish_id' => $id
            ]);
            if (!$result){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '上传错误'
                ]));
            }
        }else{
            //给定一个目录
            $info = $photo->validate(['ext'=>'jpg,jpeg,png,bmp,gif'])->move('upload');
            if ($info && $info->getPathname()) {
                $url .= $info->getPathname();
            } else {
                exit(json_encode([
                    'code' => 400,
                    'msg' => '请检验上传图片格式（jpg,jpeg,png,bmp,gif）！'
                ]));
            }
            $result = Db::table('meeting')->insertGetId([
                'name' => $name,
                'date1' => $date1,
                'date2' => $date2,
                'date3' => $date3,
                'time1' => $time1,
                'time2' => $time2,
                'position' => $position,
                'term1' => $term1,
                'term2' => $term2,
                'term3' => $term3,
                'term' => (int)($term1.$term2.$term3),
                'begin' => $begin_time,
                'end_time' => $end_time,
                'photo' => $url,
                'type' => $type,
                'department' => $department,
                'enter_begin' => $enter_begin,
                'description' => $description,
                'enter_end' => $enter_end,
                'period' => $period,
                're_end_time' => $re,
                'publish_id' => $id
            ]);
            if (!$result){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '上传错误'
                ]));
            }
        }

//        foreach ($member as $k => $v){
//            $major = Db::table('meeting_major')
//                ->insert([
//                    'major' => $k,
//                    'year' => $v
//                ]);
//            if (!$major){
//                Db::rollback();
//                exit(json_encode([
//                    'code' => 503,
//                    'msg' => '上传错误'
//                ]));
//            }
//        }

        foreach ($member as $k => $item){
            $major = $k;
            foreach ($item as $i){
                $i = (array)$i;
                $in = Db::table('meeting_major')
                    ->insert([
                        'meeting_id' => $result,
                        'major' => $major,
                        'year' => $i['year']
                    ]);
                if (!$in){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '发布失败！'
                    ]));
                }
            }
        }

        Db::commit();
        return json([
            'code' => 200,
            'msg' => $result
        ]);
    }

    //添加可选学院
    public function add_meeting_major($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $this->have_key_validate([
            'meeting_id' => '无会议标识！',
            'major_info' => '无学院信息！'
        ],$data);
        $meeting_info = $data['major_info'];
        $tmp['id'] = $data['meeting_id'];
        (new IDMustBeNumber())->goToCheck($tmp);
        $meeting_id = $data['meeting_id'];
        Db::startTrans();
        foreach ($meeting_info as $k => $v){
            $major = $k;
            foreach ($v as $item){
                $result = Db::table('meeting_major')
                    ->insert([
                        'meeting_id' => $meeting_id,
                        'major' => $major,
                        'year' => $item
                    ]);
                if (!$result){
                    Db::rollback();
                    exit([
                        'code' => 504,
                        'msg' => '插入错误！'
                    ]);
                }
            }
            Db::commit();
        }

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //修改会议
    public function change_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }

        if (!array_key_exists('meeting_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议标识！'
            ]));
        }

        if (!array_key_exists('name',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议名称！'
            ]));
        }
        if (!array_key_exists('date1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！(第一空)！'
            ]));
        }
        if (!array_key_exists('date2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！（第二空）'
            ]));
        }
        if (!array_key_exists('date3',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！（第三空）'
            ]));
        }
        if (!array_key_exists('time1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无时间！(第一空)'
            ]));
        }
        if (!array_key_exists('time2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无时间！(第二空)'
            ]));
        }
        if (!array_key_exists('position',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无地点！'
            ]));
        }
        if (!array_key_exists('term1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第一空)'
            ]));
        }
        if (!array_key_exists('term2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第二空)'
            ]));
        }
        if (!array_key_exists('term3',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第三空)'
            ]));
        }
        if(!is_numeric($data['meeting_id'])){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议标识非数字'
            ]));
        }
        $this->have_key_validate([
            'meeting_type' => '无会议类型！',
            'department' => '无开课部门！',
            'enter_begin' => '无报名开始时间！',
            'enter_end' => '无报名结束时间！',
            'description' => '无内容简介！',
            'end_time' => '无会议结束时间！',
            'member' => '无可选学院年级！'
        ],$data);
        (new SetMeeting())->goToCheck($data);
        //过滤
        $name = filter($data['name']);
        $date1 = filter($data['date1']);
        $date2 = filter($data['date2']);
        $date3 = filter($data['date3']);
        $time1 = filter($data['time1']);
        $time2 = filter($data['time2']);
        $position= filter($data['position']);
        $term1= filter($data['term1']);
        $term2= filter($data['term2']);
        $term3= filter($data['term3']);
        $type = $data['meeting_type'];
        $department = $data['department'];
        $enter_begin = $data['enter_begin'];
        $enter_begin[10] = ' ';
        $enter_begin[13] = ':';
        $enter_end = $data['enter_end'];
        $enter_end[10] = ' ';
        $enter_end[13] = ':';
        $description = $data['description'];
        $period = $data['period'];
        $end_time = $data['end_time'];
        $re = $data['end_time'];
        $member = $data['member'];

        if (!((int)$date1<=(int)$term2&&(int)$date1>=(int)$term1)){
            exit(json_encode([
                'code' => 400,
                'msg' => '输入日期中的年份未在输入的学期之间，请检查后重新输入！'
            ]));
        }
        $end_time = $date1.'-'.$date2.'-'.$date3.' '.$end_time.':00';
        $end_time = (int)strtotime($end_time)+1200;
        $begin_time = $date1.'-'.$date2.'-'.$date3.' '.$time1.':'.$time2.':00';
        $begin_time = (int)strtotime($begin_time)-1200;
        $enter_begin = strtotime($enter_begin);
        $enter_end = strtotime($enter_end);
        //入库
        $meeting = new Meeting();
        $meeting_member = new Meeting_member();
        $check = $meeting->where([
            'id' => $data['meeting_id']
        ])->find();
        if(!$check){
            exit(json_encode([
                'code' => 400,
                'msg' => '该会议不存在'
            ]));
        }

        //上传图片
        $url = '';
        $photo = Request::instance()->file('photo');
        Db::startTrans();
        if ($photo){
            //给定一个目录
            $info = $photo->validate(['ext'=>'jpg,jpeg,png,bmp,gif'])->move('upload');
            if ($info && $info->getPathname()) {
                $url .= $info->getPathname();
            } else {
                exit(json_encode([
                    'code' => 400,
                    'msg' => '请检验上传图片格式（jpg,jpeg,png,bmp,gif）！'
                ]));
            }
            if ($name==$check['name']&&$date1==$check['date1']&&$date2==$check['date2']&&$date3==$check['date3']&&$time1==$check['time1']
                &&$time2==$check['time2']&&$position==$check['position']&&$term1==$check['term1']&&$term2==$check['term2']&&$term3==$check['term3']
                &&$begin_time==$check['begin']&&$end_time==$check['end_time']&&$check['type'] == $type&&$check['department'] == $department&&$check['enter_begin'] = $enter_begin
                &&$enter_end ==$check['enter_end']&&$check['description']==$description&&$check['period']==$period&&$check['photo']==$url){
            }else{
                $result = Db::table('meeting')
                    ->where([
                        'id' => $data['meeting_id']
                    ])
                    ->update([
                        'name' => $name,
                        'date1' => $date1,
                        'date2' => $date2,
                        'date3' => $date3,
                        'time1' => $time1,
                        'time2' => $time2,
                        'position' => $position,
                        'term1' => $term1,
                        'term2' => $term2,
                        'term3' => $term3,
                        'term' => (int)($term1.$term2.$term3),
                        'begin' => $begin_time,
                        'end_time' => $end_time,
                        'photo' => $url,
                        'type' => $type,
                        'department' => $department,
                        'enter_begin' => $enter_begin,
                        'description' => $description,
                        'enter_end' => $enter_end,
                        'period' => $period,
                        're_end_time' => $re
                    ]);
                if (!$result){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '上传错误'
                    ]));
                }
            }
        }else{
            if ($name==$check['name']&&$date1==$check['date1']&&$date2==$check['date2']&&$date3==$check['date3']&&$time1==$check['time1']
                &&$time2==$check['time2']&&$position==$check['position']&&$term1==$check['term1']&&$term2==$check['term2']&&$term3==$check['term3']
                &&$begin_time==$check['begin']&&$end_time==$check['end_time']&&$check['type'] == $type&&$check['department'] == $department&&$check['enter_begin'] = $enter_begin
                &&$enter_end ==$check['enter_end']&&$check['description']==$description&&$check['period']==$period){
            }else{
                $result = Db::table('meeting')
                    ->where([
                        'id' => $data['meeting_id']
                    ])
                    ->update([
                        'name' => $name,
                        'date1' => $date1,
                        'date2' => $date2,
                        'date3' => $date3,
                        'time1' => $time1,
                        'time2' => $time2,
                        'position' => $position,
                        'term1' => $term1,
                        'term2' => $term2,
                        'term3' => $term3,
                        'term' => (int)($term1.$term2.$term3),
                        'begin' => $begin_time,
                        'end_time' => $end_time,
                        'type' => $type,
                        'department' => $department,
                        'enter_begin' => $enter_begin,
                        'description' => $description,
                        'enter_end' => $enter_end,
                        'period' => $period,
                        're_end_time' => $re
                    ]);
                if (!$result){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '上传错误'
                    ]));
                }
            }
        }

        //修改可选学院
        $meeting_major = Db::table('meeting_major')->where([
            'meeting_id' => $data['meeting_id']
        ])->delete();

        if (!$meeting_major){
            Db::rollback();
            exit(json_encode([
                'code' => 503,
                'msg' => '更新学院出错'
            ]));
        }

        foreach ($member as $k => $item){
            $major = $k;
            foreach ($item as $i){
                $i = (array)$i;
                $in = Db::table('meeting_major')
                    ->insert([
                        'meeting_id' => $data['meeting_id'],
                        'major' => $major,
                        'year' => $i['year']
                    ]);
                if (!$in){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '发布失败！'
                    ]));
                }
            }
        }

        //修改参加会议的表
        if ($term1==$check['term1']&&$term2==$check['term2']&&$term3==$check['term3']
            &&$begin_time==$check['begin']&&$end_time==$check['end_time']&&$check['enter_begin'] = $enter_begin&&$enter_end ==$check['enter_end']&&$check['period']==$period){

        }else{
            if (Db::table('meeting_member')
                ->where(['meeting_id' => $data['meeting_id']])
                ->find()){
                $meeting_member = Db::table('meeting_member')
                    ->where(['meeting_id' => $data['meeting_id']])
                    ->update([
                        'term' => (int)($term1.$term2.$term3),
                        'begin' => $begin_time,
                        'end_time' => $end_time,
                        'enter_begin' => $enter_begin,
                        'enter_end' => $enter_end,
                        'period' => $period
                    ]);
                if (!$meeting_member){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '修改失败！'
                    ]));
                }
            }
        }


        Db::commit();

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //显示单个会议
    public function show_single_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 400,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);

        $id = $data['id'];

        $meeting = new Meeting();
        //查询
        $result = $meeting->where([
            'id' => $id
        ])->find();
        if (!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => '或许是id不正确，查找出错！'
            ]));
        }
        $name = $result['name'];
        $date1 = $result['date1'];
        $date2 = $result['date2'];
        $date3 = $result['date3'];
        $time1 = $result['time1'];
        $time2 = $result['time2'];
        $position= $result['position'];
        $term1= $result['term1'];
        $term2= $result['term2'];
        $term3= $result['term3'];
        $department = $result['department'];
        $description = $result['description'];
        $type = $result['type'];
        $period = $result['period'];
        $photo = $result['photo'];
        $re_end_time = $result['re_end_time'];
        $end_time1 = $re_end_time[0].$re_end_time[1];
        $end_time2 = $re_end_time[3].$re_end_time[4];
        $enter_stare = date('Y-m-d-H:i',$result['enter_begin']);
        $enter_end = date('Y-m-d-H:i',$result['enter_end']);
        $publish_id = $result['publish_id'];

        $teach = Db::table('super')->where([
            'id' => $publish_id
        ])->find();
        $teacher = $teach['nickname'];

        //截取当前状态
        $flag = (int)$result['begin'];
        $state = '';
        if ($flag > (int)time()){
            $state .= '未开始';
        }else{
            $state .= '已开始';
        }
        //判断是否结束(会议当天24点结束)
        $f = (int)$result['end_time'];
        if ($f < (int)time()){
            $state = '已结束';
        }
        $member = [];
        $info = Db::table('meeting_major')->where([
            'meeting_id' => $id
        ])->select();
        if ($info){
            foreach ($info as $v){
                if (array_key_exists($v['major'],$member)){
                    $m = count($member[$v['major']]);
                }else{
                    $m = 0;
                }
                $member[$v['major']][$m] = $v['year'];
            }
        }

        return json([
            'code' => 200,
            'msg' => [
                'meeting_id' => $id,
                'name' => $name,
                'date1' => $date1,
                'date2' => $date2,
                'date3' => $date3,
                'time1' => $time1,
                'time2' => $time2,
                'position' => $position,
                'term1' => $term1,
                'term2' => $term2,
                'term3' => $term3,
                'state' => $state,
                'end_time1' => $end_time1,
                'end_time2' => $end_time2,
                'type' => $type,
                'description' => $description,
                'department' => $department,
                'period' => $period,
                'photo' => config('setting.image_root').$photo,
                'member' => $member,
                'enter_begin' => $enter_stare,
                'enter_end' => $enter_end,
                'teacher' => $teacher
            ]
        ]);
    }

    public function create_single_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);
        $id = $data['id'];

        $meeting = new Meeting();
        //查询
        $result = $meeting->where([
            'id' => $id
        ])->find();
        if (!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => '或许是id不正确，查找出错！'
            ]));
        }
        $name = $result['name'];
        $member = [];
        $meeting_member = new Meeting_member();
        $user = new User();
        $info = $meeting_member->where([
            'meeting_id' => $id
        ])->field('user_id,attend,ask_leave,sign_out')->select();
        if ($info){
            $i = 0;
            foreach ($info as $v){
                $re = $user->where([
                    'id' => $v['user_id']
                ])->field(['username,major,number'])->find();
                $member[$i]['user_id'] = $v['user_id'];
                $member[$i]['username'] = $re['username'];
                $member[$i]['major'] = $re['major'];
                $member[$i]['number'] = $re['number'];

                if ((int)$v['ask_leave'] == 1){
                    $member[$i]['status'] = '请假';
                }elseif ((int)$v['attend'] == 1&& (int)$v['sign_out'] == 1){
                    $member[$i]['status'] = '出席';
                }elseif ((int)$v['attend'] == 1&& (int)$v['sign_out'] == 0){
                    $member[$i]['status'] = '早退';
                }elseif ((int)$v['attend'] == 0&& (int)$v['sign_out'] == 1){
                    $member[$i]['status'] = '迟到';
                }else{
                    $member[$i]['status'] = '缺席';
                }

                $i++;
            }
        }

        vendor('PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $styleThinBlackBorderOutline = array(
            'borders' => array (
                'outline' => array (
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
                    'color' => array ('argb' => 'FF000000'),     //设置border颜色
                ),
            ),
        );
        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setTitle("出勤查看");


        $objPHPExcel->getActiveSheet()->mergeCells("A1:D1");
        $objPHPExcel->getActiveSheet()->setCellValue("A1", $name);
        $objPHPExcel->getActiveSheet()->getStyle("A1:D1")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle( "A1")->getFont()->setSize(14);
        $objPHPExcel->getActiveSheet()->getStyle("A1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->setCellValue("A2", "工号");
        $objPHPExcel->getActiveSheet()->getStyle("A2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("B2", "姓名");
        $objPHPExcel->getActiveSheet()->getStyle("B2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('B2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("C2", "单位");
        $objPHPExcel->getActiveSheet()->getStyle("C2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('C2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("D2", "出勤情况");
        $objPHPExcel->getActiveSheet()->getStyle("D2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('D2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->getStyle("A2:D2")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(25);
        $k = 3;
        foreach ($member as $v){
            $objPHPExcel->getActiveSheet()->setCellValue("A".$k, $v['number']);
            $objPHPExcel->getActiveSheet()->getStyle("A".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("B".$k, $v['username']);
            $objPHPExcel->getActiveSheet()->getStyle("B".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('B'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("C".$k, $v['major']);
            $objPHPExcel->getActiveSheet()->getStyle("C".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('C'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("D".$k, $v['status']);
            $objPHPExcel->getActiveSheet()->getStyle("D".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('D'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $k++;
        }

        //设置格子大小
        $objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension()->setWidth(25);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $_savePath = COMMON_PATH.'/static/single_meeting.xlsx';
        $objWriter->save($_savePath);

        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/single_meeting.xlsx'
        ]);

    }

    public function show_term(){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 403,
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

    //显示一个列表的会议
    public function show_all_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }

        if (!array_key_exists('page',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页号！'
            ]));
        }
        if (!array_key_exists('size',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页大小！'
            ]));
        }
        if (!array_key_exists('term',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无排序规则！'
            ]));
        }
        //验证
        (new ShowMeeting())->goToCheck($data);

        //page从1开始
        //limit($page*$size-1,$size)   0除外
        $page = (int)$data['page'];
        $size = (int)$data['size'];
        if ($page<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '页号最小为0！'
            ]));
        }
        if ($size<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '页大小最小为0！'
            ]));
        }
        if ($page*$size == 0 && $page*$size != 0){
            exit(json_encode([
                'code' => 400,
                'msg' => '页号和页大小为零时只有同时为零！'
            ]));
        }

        $term = $data['term'];

        //查询
        // 1  2  3
        // 0  2  5
        $meeting = new Meeting();
        if ($term == 'all'){
            if ($page == 0 && $size == 0){
                $info = $meeting
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }else{
                $start = ($page-1)*$size;
                $info = $meeting->limit($start,$size)
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }
            $msg = [];
            foreach ($info as $k => $v){
                $flag = (int)$v['begin'];
                $state = '';
                if ($flag > (int)time()){
                    $state .= '未开始';
                }else{
                    $state .= '已开始';
                }
                //判断是否结束(会议当天24点结束)
                $f = (int)$v['end_time'];
                if ($f < (int)time()){
                    $state = '已结束';
                }
                $t = $v['term1'].'-'.$v['term2'].'-'.$v['term3'];
                if (!array_key_exists($t,$msg)) $i = 0;
                else $i = count($msg[$t]);
                $msg[$t][$i]['meeting_id'] = $v['id'];
                $msg[$t][$i]['name'] = $v['name'];
                $msg[$t][$i]['time'] = $v['date1'].'/'.$v['date2'].'/'.$v['date3'];
                $msg[$t][$i]['clock'] = $v['time1'].':'.$v['time2'].'-'.$v['re_end_time'];
                $msg[$t][$i]['position'] = $v['position'];
                $msg[$t][$i]['period'] = $v['period'];
                $msg[$t][$i]['photo'] = config('setting.image_root').$v['photo'];
                $msg[$t][$i]['state'] = $state;
            }
            if ($page == 0 && $size == 0){
                $info = Db::table('check_meeting')
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }else{
                $start = ($page-1)*$size;
                $info = Db::table('check_meeting')->limit($start,$size)
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }
            foreach ($info as $k => $v){
                $flag = (int)$v['state'];
                $state = '';
                if ($flag == 0){
                    $state .= '待审核';
                }else{
                    $state .= '未通过';
                }
                $t = $v['term1'].'-'.$v['term2'].'-'.$v['term3'];
                if (!array_key_exists($t,$msg)) $i = 0;
                else $i = count($msg[$t]);
                $msg[$t][$i]['meeting_id'] = $v['id'];
                $msg[$t][$i]['name'] = $v['name'];
                $msg[$t][$i]['time'] = $v['date1'].'/'.$v['date2'].'/'.$v['date3'];
                $msg[$t][$i]['clock'] = $v['time1'].':'.$v['time2'].'-'.$v['re_end_time'];
                $msg[$t][$i]['position'] = $v['position'];
                $msg[$t][$i]['period'] = $v['period'];
                $msg[$t][$i]['photo'] = config('setting.image_root').$v['photo'];
                $msg[$t][$i]['state'] = $state;
            }
        }else{
            $t = str_replace('-','',$term);
            if ($page == 0 && $size == 0){
                $info = $meeting
                    ->where([
                        'term' => $t
                    ])
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }else{
                $start = ($page-1)*$size;
                $info = $meeting->limit($start,$size)
                    ->where([
                        'term' => $t
                    ])
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }
            if (!$info){
                exit(json_encode([
                    'code' => 400,
                    'msg' => '输入的学期有误！查询失败！'
                ]));
            }
            //新开一个数组存放返回的东西
            $msg = [];
            $i = 0;
            foreach ($info as $k => $v){
                $flag = (int)$v['begin'];
                $state = '';
                if ($flag > (int)time()){
                    $state .= '未开始';
                }else{
                    $state .= '已开始';
                }
                //判断是否结束(会议当天24点结束)
                $f = (int)$v['end_time'];
                if ($f < (int)time()){
                    $state = '已结束';
                }
                $msg[$term][$i]['meeting_id'] = $v['id'];
                $msg[$term][$i]['name'] = $v['name'];
                $msg[$term][$i]['time'] = $v['date1'].'/'.$v['date2'].'/'.$v['date3'];
                $msg[$term][$i]['clock'] = $v['time1'].':'.$v['time2'].'-'.$v['re_end_time'];
                $msg[$t][$i]['position'] = $v['position'];
                $msg[$t][$i]['period'] = $v['period'];
                $msg[$t][$i]['photo'] = config('setting.image_root').$v['photo'];
                $msg[$term][$i]['state'] = $state;

                $i++;
            }
            if ($page == 0 && $size == 0){
                $info = Db::table('check_meeting')
                    ->where([
                        'term' => $t
                    ])
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }else{
                $start = ($page-1)*$size;
                $info = Db::table('check_meeting')->limit($start,$size)
                    ->where([
                        'term' => (int)$t
                    ])
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }
            foreach ($info as $k => $v){
                $flag = (int)$v['state'];
                $state = '';
                if ($flag == 0){
                    $state .= '待审核';
                }else{
                    $state .= '未通过';
                }
                $msg[$term][$i]['meeting_id'] = $v['id'];
                $msg[$term][$i]['name'] = $v['name'];
                $msg[$term][$i]['time'] = $v['date1'].'/'.$v['date2'].'/'.$v['date3'];
                $msg[$term][$i]['clock'] = $v['time1'].':'.$v['time2'].'-'.$v['re_end_time'];
                $msg[$t][$i]['position'] = $v['position'];
                $msg[$t][$i]['period'] = $v['period'];
                $msg[$t][$i]['photo'] = config('setting.image_root').$v['photo'];
                $msg[$term][$i]['state'] = $state;
                $i++;
            }
        }

        return json([
            'code' => 200,
            'msg' => $msg
        ]);
    }

    public function get_meeting_number($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }

        if (!array_key_exists('term',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！'
            ]));
        }
        //校验格式
        if(!preg_match("/^[0-9]{4}-[0-9]{4}-[0-9]{1}$/",$data['term'])&&!preg_match("/^all$/",$data['term'])){
            exit(json_encode([
                'code' => 400,
                'msg' => '传入的学期参数格式必须为xxxx-xxxx-x(x为数字)或者传入全部学期！'
            ]));
        }
        $meeting = new Meeting();
        if ($data['term'] == 'all'){
            $info = $meeting->field('name')->select();
            $msg = count($info);
        }else{
            $t = str_replace('-','',$data['term']);
            $info = $meeting->where([
                'term' => $t
            ])->field('name')->select();
            $msg = count($info);
        }
        return json([
            'code' => 200,
            'msg' => $msg
        ]);
    }

    public function show_all_person($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }

        if (!array_key_exists('page',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页号！'
            ]));
        }
        if (!array_key_exists('size',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页大小！'
            ]));
        }
        $rule = [
            'page'  => 'require|number',
            'size'   => 'require|number'
        ];
        $msg = [
            'page.require' => '页号不能为空',
            'page.number'   => '页号必须是数字',
            'size.require' => '页面大小不能为空',
            'size.number'   => '页面大小必须是数字',
        ];
        $validate = new Validate($rule,$msg);
        $result   = $validate->check($data);
        if(!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => $validate->getError()
            ]));
        }
        $page = (int)$data['page'];
        $size = (int)$data['size'];
        if ($page<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '数据参数中的第一项最小为0'
            ]));
        }
        if ($size<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '数据参数中的第二项最小为0'
            ]));
        }
        if ($page*$size == 0 && $page+$size!=0){
            exit(json_encode([
                'code' => 400,
                'msg' => '为0情况只有数据参数中两项同时为零，否则最小从1开始'
            ]));
        }
        if ($page == 0 && $size == 0){
            $user = new User();
            $info = $user
                ->field('id,username,major')
                ->select();
            $result = [];
            $i = 0;
            foreach ($info as $v){
                $result[$i]['id'] = $v['id'];
                $result[$i]['username'] = $v['username'];
                $result[$i]['major'] = $v['major'];

                $i++;
            }
        }else{
            $start = ($page-1)*$size;
            $user = new User();
            $info = $user->limit($start,$size)
                ->field('id,username,major')
                ->select();
            $result = [];
            $i = 0;
            foreach ($info as $v){
                $result[$i]['id'] = $v['id'];
                $result[$i]['username'] = $v['username'];
                $result[$i]['major'] = $v['major'];

                $i++;
            }
        }
        return json([
            'code' => 200,
            'msg' => $result
        ]);
    }

    public function all_person_count(){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $user = new User();
        $info = $user
            ->field('major')
            ->select();
        return json([
            'code' => 200,
            'msg' => count($info)
        ]);
    }
    //删除会议
    public function delete_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('meeting_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议标识！'
            ]));
        }
        $rule = [
            'meeting_id'  => 'require|number',
        ];
        $msg = [
            'meeting_id.require' => '会议标识不能为空',
            'meeting_id.number'   => '会议标识必须是数字',
        ];
        $validate = new Validate($rule,$msg);
        $result   = $validate->check($data);
        if(!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => $validate->getError()
            ]));
        }
        //查看这个会议有没有成员
        $check = Db::table('meeting_member')->where([
            'meeting_id' => $data['meeting_id']
        ])->select();

        $check2 = Db::table('meeting_major')->where([
            'meeting_id' => $data['meeting_id']
        ])->find();

        $check3 = Db::table('sign_list')->where([
            'meeting_id' => $data['meeting_id']
        ])->find();

        $check4 = Db::table('meeting')
            ->where([
                'id' => $data['meeting_id']
            ])->find();
        if (!$check4){
            exit(json_encode([
                'code' => 400,
                'msg' => '没有该会议'
            ]));
        }
        if ($secret == 31){
            $publish_id = $check4['publish_id'];
            if ($id != $publish_id){
                exit(json_encode([
                    'code' => 403,
                    'msg' => '权限不足！不能删除别人发布会议'
                ]));
            }
        }

        //开启事务
        Db::startTrans();
        $rr = Db::table('meeting')->where([
            'id' => $data['meeting_id']
        ])->delete();
        if (!$rr){
            Db::rollback();
            exit(json_encode([
                'code' => 504,
                'msg' => '更新出错，请重试！1'
            ]));
        }
//        if ($check){
//            foreach ($check as $item){
//                //出席
//                if ($item['attend'] == 1&& $item['sign_out'] == 1){
//                    $score_check = Db::table('period')
//                        ->where([
//                            'term' => $term,
//                            'user_id' => $item['user_id']
//                        ])->find();
//                    if (!$score_check){
//                        $score = Db::table('period')
//                            ->insert([
//                                'term' => $term,
//                                'user_id' => $item['user_id'],
//                                'period' => 0
//                            ]);
//                        if (!$score){
//                            Db::rollback();
//                            exit(json_encode([
//                                'code' => 504,
//                                'msg' => '更新出错，请重试！'
//                            ]));
//                        }
//                    }else{
//                        $score = Db::table('period')
//                            ->where([
//                                'term' => $term,
//                                'user_id' => $item['user_id']
//                            ])->update([
//                                'period' => ['exp','period-'.$meeting_period]
//                            ]);
//                        if (!$score){
//                            Db::rollback();
//                            exit(json_encode([
//                                'code' => 504,
//                                'msg' => '更新出错，请重试！'
//                            ]));
//                        }
//                    }
//
//                    $all_score = Db::table('user')
//                        ->where([
//                            'id' => $item['user_id']
//                        ])->update([
//                            'period' => ['exp','period-'.$meeting_period]
//                        ]);
//                    if (!$all_score){
//                        Db::rollback();
//                        exit(json_encode([
//                            'code' => 504,
//                            'msg' => '更新出错，请重试！'
//                        ]));
//                    }
//                }elseif(($item['attend'] == 1&& $item['sign_out'] == 0)||($item['attend'] == 0&& $item['sign_out'] == 1)){
//                    //早退或迟到
//                    if (count(Db::table('meeting_member')->where(['attend' => 1,'sign_out' => 0,'user_id' => $item['user_id']])->select()) >= 2
//                    || count(Db::table('meeting_member')->where(['attend' => 0,'sign_out' => 1,'user_id' => $item['user_id']])->select()) >= 2){
//                        $score_check = Db::table('period')
//                            ->where([
//                                'term' => $term,
//                                'user_id' => $item['user_id']
//                            ])->find();
//                        if (!$score_check){
//                            $score = Db::table('period')
//                                ->insert([
//                                    'term' => $term,
//                                    'user_id' => $item['user_id'],
//                                    'period' => 0
//                                ]);
//                            if (!$score){
//                                Db::rollback();
//                                exit(json_encode([
//                                    'code' => 504,
//                                    'msg' => '更新出错，请重试！'
//                                ]));
//                            }
//                        }else{
//                            $score = Db::table('period')
//                                ->where([
//                                    'term' => $term,
//                                    'user_id' => $item['user_id']
//                                ])->update([
//                                    'period' => ['exp','period+0.5']
//                                ]);
//                            if (!$score){
//                                Db::rollback();
//                                exit(json_encode([
//                                    'code' => 504,
//                                    'msg' => '更新出错，请重试！'
//                                ]));
//                            }
//                        }
//                        $all_score = Db::table('user')
//                            ->where([
//                                'id' => $item['user_id']
//                            ])->update([
//                                'period' => ['exp','period+0.5']
//                            ]);
//                        if (!$all_score){
//                            Db::rollback();
//                            exit(json_encode([
//                                'code' => 504,
//                                'msg' => '更新出错，请重试！'
//                            ]));
//                        }
//                    }
//                }else{
//                    if (count(Db::table('meeting_member')->where(['attend' => 0,'sign_out' => 0,'user_id' => $item['user_id']])->select()) >= 2){
//                        $score_check = Db::table('period')
//                            ->where([
//                                'term' => $term,
//                                'user_id' => $item['user_id']
//                            ])->find();
//                        if (!$score_check){
//                            $score = Db::table('period')
//                                ->insert([
//                                    'term' => $term,
//                                    'user_id' => $item['user_id'],
//                                    'period' => 0
//                                ]);
//                            if (!$score){
//                                Db::rollback();
//                                exit(json_encode([
//                                    'code' => 504,
//                                    'msg' => '更新出错，请重试！'
//                                ]));
//                            }
//                        }else{
//                            $score = Db::table('period')
//                                ->where([
//                                    'term' => $term,
//                                    'user_id' => $item['user_id']
//                                ])->update([
//                                    'period' => ['exp','period+1']
//                                ]);
//                            if (!$score){
//                                Db::rollback();
//                                exit(json_encode([
//                                    'code' => 504,
//                                    'msg' => '更新出错，请重试！'
//                                ]));
//                            }
//                        }
//                        $all_score = Db::table('user')
//                            ->where([
//                                'id' => $item['user_id']
//                            ])->update([
//                                'period' => ['exp','period+1']
//                            ]);
//                        if (!$all_score){
//                            Db::rollback();
//                            exit(json_encode([
//                                'code' => 504,
//                                'msg' => '更新出错，请重试！'
//                            ]));
//                        }
//                    }
//                }
//            }
//            $rrr = Db::table('meeting_member')->where([
//                'meeting_id' => $data['meeting_id']
//            ])->delete();
//            if (!$rrr){
//                Db::rollback();
//                exit(json_encode([
//                    'code' => 504,
//                    'msg' => '更新出错，请重试！'
//                ]));
//            }
//        }
        if ($check){
            $rr = Db::table('meeting_member')->where([
                'meeting_id' => $data['meeting_id']
            ])->delete();
            if (!$rr){
                Db::rollback();
                exit(json_encode([
                    'code' => 504,
                    'msg' => '更新出错，请重试！2'
                ]));
            }
        }
        if ($check2){
            $rr = Db::table('meeting_major')->where([
                'meeting_id' => $data['meeting_id']
            ])->delete();
            if (!$rr){
                Db::rollback();
                exit(json_encode([
                    'code' => 504,
                    'msg' => '更新出错，请重试！3'
                ]));
            }
        }
        if ($check3){
            $rr = Db::table('sign_list')->where([
                'meeting_id' => $data['meeting_id']
            ])->delete();
            if (!$rr){
                Db::rollback();
                exit(json_encode([
                    'code' => 504,
                    'msg' => '更新出错，请重试！4'
                ]));
            }
        }
        Db::commit();
        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }
    //删除成员
    public function delete_member($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $member = new Meeting_member();
        if (!array_key_exists('meeting_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议标识！'
            ]));
        }
        if (!array_key_exists('member',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无成员输入！'
            ]));
        }
        if (!is_array($data['member'])){
            exit(json_encode([
                'code' => 400,
                'msg' => '输入成员并非数组！'
            ]));
        }
        $member_array = $data['member'];
        $rule = [
            'meeting_id'  => 'require|number',
        ];
        $msg = [
            'meeting_id.require' => '会议标识不能为空',
            'meeting_id.number'   => '会议标识必须是数字',
        ];
        $validate = new Validate($rule,$msg);
        $result   = $validate->check($data);
        if(!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => $validate->getError()
            ]));
        }
        $member->startTrans();
        foreach ($member_array as $v){
            if (!is_numeric($v)){
                $member->rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '传入的成员并非数字'
                ]));
            }
            $re = $member->where([
                'meeting_id' => $data['meeting_id'],
                'user_id' => $v
            ])->delete();
            if (!$re){
                $member->rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '参数错误'
                ]));
            }
        }
        $member->commit();

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    public function attendance_check($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $user = new User();
        if (!array_key_exists('page',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页号！'
            ]));
        }
        if (!array_key_exists('size',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页大小！'
            ]));
        }
        if (!array_key_exists('term',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！'
            ]));
        }
        if (!array_key_exists('major',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学院！'
            ]));
        }
        if (!array_key_exists('grade',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无年级！'
            ]));
        }
        //验证
        (new ShowMeeting())->goToCheck($data);
        $page = (int)$data['page'];
        $size = (int)$data['size'];
        if ($page<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '数据参数中的第一项最小为0'
            ]));
        }
        if ($size<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '数据参数中的第二项最小为0'
            ]));
        }
        if ($page*$size == 0 && $page+$size!=0){
            exit(json_encode([
                'code' => 400,
                'msg' => '为0情况只有数据参数中两项同时为零，否则最小从1开始'
            ]));
        }
        if ($data['term'] == 'all'){
            exit(json_encode([
                'code' => 400,
                'msg' => '传入学期没有全部的情况'
            ]));
        }
        $major = $data['major'];
        $grade = $data['grade'];
        if ($page == 0 && $size == 0){
            $i = 0;
            $r = [];
            $t = str_replace('-','',$data['term']);

            if ($major == 'all'){
                if ($grade == 'all'){
                    $info = $user->field('id,username,major,number,period')
                        ->order([
                            'number' => 'asc'
                        ])
                        ->select();
                    if (!$info){
                        exit(json_encode([
                            'code' => 400,
                            'msg' => '未查到用户'
                        ]));
                    }
                }else{
                    $info = $user->field('id,username,major,number,period')
                        ->where('number','like',$grade.'%')
                        ->order([
                            'number' => 'asc'
                        ])
                        ->select();
                    if (!$info){
                        exit(json_encode([
                            'code' => 400,
                            'msg' => '未查到用户'
                        ]));
                    }
                }
            }else{
                if ($grade == 'all'){
                    $info = $user->field('id,username,major,number,period')
                        ->where([
                            'major' => $major
                        ])
                        ->order([
                            'number' => 'asc'
                        ])
                        ->select();
                    if (!$info){
                        exit(json_encode([
                            'code' => 400,
                            'msg' => '未查到用户'
                        ]));
                    }
                }else{
                    $info = $user->field('id,username,major,number,period')
                        ->where([
                            'major' => $major
                        ])
                        ->where('number','like',$grade.'%')
                        ->order([
                            'number' => 'asc'
                        ])
                        ->select();
                    if (!$info){
                        exit(json_encode([
                            'code' => 400,
                            'msg' => '未查到用户'
                        ]));
                    }
                }
            }

            foreach ($info as $k){
                $r[$i]['user_id'] = $k['id'];
                $r[$i]['username'] = $k['username'];
                $r[$i]['major'] = $k['major'];
                $r[$i]['number'] = $k['number'];
                $r[$i]['all_period'] = (string)$this->find_period($k['id']);
                $r[$i]['term_period'] = (string)$this->find_period($k['id'],$t);
                $i++;
            }
        }else{
            $start = ($page-1)*$size;
            $r = [];
            $i = 0;
            if ($data['term'] != 'all'){
                $t = str_replace('-','',$data['term']);
                if ($major == 'all'){
                    if ($grade == 'all'){
                        $info = $user->limit($start,$size)->field('id,username,major,number,period')
                            ->order([
                                'number' => 'asc'
                            ])
                            ->select();
                        if (!$info){
                            exit(json_encode([
                                'code' => 400,
                                'msg' => '未查到用户'
                            ]));
                        }
                    }else{
                        $info = $user->limit($start,$size)->field('id,username,major,number,period')
                            ->order([
                                'number' => 'asc'
                            ])
                            ->where('number','like',$grade.'%')
                            ->select();
                        if (!$info){
                            exit(json_encode([
                                'code' => 400,
                                'msg' => '未查到用户'
                            ]));
                        }
                    }
                }else{
                    if ($grade == 'all'){
                        $info = $user->limit($start,$size)->field('id,username,major,number,period')
                            ->where([
                                'major' => $major
                            ])
                            ->order([
                                'number' => 'asc'
                            ])
                            ->select();
                        if (!$info){
                            exit(json_encode([
                                'code' => 400,
                                'msg' => '未查到用户'
                            ]));
                        }
                    }else{
                        $info = $user->limit($start,$size)->field('id,username,major,number,period')
                            ->where([
                                'major' => $major
                            ])
                            ->where('number','like',$grade.'%')
                            ->order([
                                'number' => 'asc'
                            ])
                            ->select();
                        if (!$info){
                            exit(json_encode([
                                'code' => 400,
                                'msg' => '未查到用户'
                            ]));
                        }
                    }
                }

                foreach ($info as $k){
                    $r[$i]['user_id'] = $k['id'];
                    $r[$i]['username'] = $k['username'];
                    $r[$i]['major'] = $k['major'];
                    $r[$i]['number'] = $k['number'];
                    $r[$i]['all_period'] = (string)$this->find_period($k['id']);
                    $r[$i]['term_period'] = (string)$this->find_period($k['id'],$t);
                    $i++;
                }
            }
        }

        return json([
            'code' => 200,
            'msg' => $r
        ]);
    }


    public function change_state($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $meeting = new Meeting();
        $user = new User();
        Db::startTrans();
        foreach ($data as $vv){
            $rr = Db::table('meeting_member')->where([
                'meeting_id' => $vv['meeting_id'],
                'user_id' => $vv['user_id']
            ])->field('attend,sign_out')->find();
            if (!$rr){
                Db::rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '没有该会议！'
                ]));
            }
            if ($vv['status'] == '出席'){
                if ($rr['attend'] != 1||$rr['sign_out'] != 1){
                    //出席
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $vv['meeting_id'],
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 1,
                        'sign_out' => 1,
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['status'] == '缺席'){
                if ($rr['sign_out'] == 1||$rr['attend'] == 1){
                    //缺席
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $vv['meeting_id'],
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 0,
                        'sign_out' => 0,
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['status'] == '迟到'){
                if ($rr['attend'] == 1||$rr['sign_out'] != 1){
                    //迟到
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $vv['meeting_id'],
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 0,
                        'sign_out' => 1
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['status'] == '早退'){
                if ($rr['attend'] != 1||$rr['sign_out'] == 1){
                    //早退
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $vv['meeting_id'],
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 1,
                        'sign_out' => 0
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }else{
                Db::rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '状态只能为出席,请假或缺席'
                ]));
            }
        }
        Db::commit();

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //修改已开始会议状态
    public function change_start_single_state($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 400,
                'msg' => '权限不足！'
            ]));
        }
        $meeting = new Meeting();
        $user = new User();
        if (!array_key_exists('meeting',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无meeting！'
            ]));
        }
        if (!array_key_exists('user_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无用户标识！'
            ]));
        }
        $meet = $data['meeting'];
        $usr = $data['user_id'];
        $rule = [
            'meeting'  => 'require|number',
        ];
        $msg = [
            'meeting.require' => '会议标识不能为空',
            'meeting.number'   => '会议标识必须是数字',
        ];
        $validate = new Validate($rule,$msg);
        $result   = $validate->check($data);
        if(!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => $validate->getError()
            ]));
        }

        //检查是否有用户
        $re = $meeting->where([
            'id' => $meet
        ])->field('id')->find();
        if (!$re){
            exit(json_encode([
                'code' => 400,
                'msg' => '没有该会议！'
            ]));
        }
        Db::startTrans();
        foreach ($usr as $vv){
            $rr = Db::table('meeting_member')->where([
                'meeting_id' => $meet,
                'user_id' => $vv['user_id']
            ])->field('attend,sign_out')->find();
            if (!$rr){
                Db::rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '没有该用户成员！'
                ]));
            }
            if ($vv['sign_in'] == '已签到'){
                if ($rr['attend'] != 1){
                    //出席
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 1,
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['sign_in'] == '未签到'){
                if ($rr['attend'] != 0){
                    //缺席
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 0,
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }
            if ($vv['sign_out'] == '已签退'){
                if ($rr['sign_out'] == 0){
                    //迟到
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'sign_out' => 1
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['sign_out'] == '未签退'){
                if ($rr['sign_out'] == 1){
                    //早退
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'sign_out' => 0
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }else{
                Db::rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '没有这种状态'
                ]));
            }
        }
        Db::commit();

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }


    public function change_single_state($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 400,
                'msg' => '权限不足！'
            ]));
        }
        $meeting = new Meeting();
        $user = new User();
        if (!array_key_exists('meeting',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无meeting！'
            ]));
        }
        if (!array_key_exists('user_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无用户标识！'
            ]));
        }
        $meet = $data['meeting'];
        $usr = $data['user_id'];
        $rule = [
            'meeting'  => 'require|number',
        ];
        $msg = [
            'meeting.require' => '会议标识不能为空',
            'meeting.number'   => '会议标识必须是数字',
        ];
        $validate = new Validate($rule,$msg);
        $result   = $validate->check($data);
        if(!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => $validate->getError()
            ]));
        }

        //检查是否有用户
        $re = $meeting->where([
            'id' => $meet
        ])->field('id')->find();
        if (!$re){
            exit(json_encode([
                'code' => 400,
                'msg' => '没有该会议！'
            ]));
        }
        Db::startTrans();
        foreach ($usr as $vv){
            $rr = Db::table('meeting_member')->where([
                'meeting_id' => $meet,
                'user_id' => $vv['user_id']
            ])->field('attend,sign_out')->find();
            if (!$rr){
                Db::rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '没有该用户成员！'
                ]));
            }
            if ($vv['status'] == '出席'){
                if (!($rr['attend'] == 1&&$rr['sign_out'] == 1)){
                    //出席
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 1,
                        'sign_out' => 1
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['status'] == '未签到'||$vv['status'] == '缺席'){
                if ($rr['sign_out'] == 1||$rr['attend'] == 1){
                    //缺席
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 0,
                        'sign_out' => 0
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['status'] == '迟到'){
                if ($rr['attend'] == 1||$rr['sign_out'] == 0){
                    //迟到
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 0,
                        'sign_out' => 1
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['status'] == '未签退'||$vv['status'] == '早退'){
                if ($rr['attend'] == 0||$rr['sign_out'] == 1){
                    //早退
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'attend' => 1,
                        'sign_out' => 0
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }else{
                Db::rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '没有这种状态'
                ]));
            }
        }
        Db::commit();

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    public function search($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $meeting = new Meeting();
        $user = new User();
        $member = new Meeting_member();
        if (!array_key_exists('search_key',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无搜索关键词！'
            ]));
        }
        if (!array_key_exists('term',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！'
            ]));
        }
        //验证
        (new Search())->goToCheck($data);
        $search_key = filter($data['search_key']);
        $info = $user->where([
            'number|username' => $search_key
        ])->field('id,username,major,number')->select();
        if (!$info){
            exit(json_encode([
                'code' => 400,
                'msg' => '查无此人！'
            ]));
        }
        $i = 0;
        $result = [];
        $time = (int)time();
        $count = count($info);
        foreach ($info as $v){
            $uid = $v['id'];

            if ($data['term'] == 'all'){
                $check = $member
                    ->order(['begin' => 'desc'])
                    ->where([
                    'user_id' => $uid
                ])->where('end_time','<',$time)->field('meeting_id,attend,sign_out')->select();
                if (!$check){
                    if ($i+1 == $count){
                        exit(json_encode([
                            'code' => 557,
                            'msg' => '该用户没有已结束的会议'
                        ]));
                    }else{
                        continue;
                    }
                }
            }else{
                $t = str_replace('-','',$data['term']);
                $check = $member->where([
                    'user_id' => $uid,
                    'term' => $t
                ])
                ->order(['begin' => 'desc'])
                ->where('end_time','<',$time)->field('meeting_id,attend,sign_out')->select();
                if (!$check){
                    if ($i+1 == $count){
                        exit(json_encode([
                            'code' => 557,
                            'msg' => '该用户在该学期没有已结束的会议'
                        ]));
                    }else{
                        continue;
                    }
                }
            }

            $j = 0;
            foreach ($check as $k){
                $re = $meeting->where([
                    'id' => $k['meeting_id']
                ])->field('name,date1,date2,date3,position')->find();
                $attend = (int)$k['attend'];
                $sign_out = (int)$k['sign_out'];
                $result[$i]['meeting'][$j]['meeting_id'] = $k['meeting_id'];
                $result[$i]['meeting'][$j]['meeting_name'] = $re['name'];
                $result[$i]['meeting'][$j]['meeting_date'] = $re['date1'].'/'.$re['date2'].'/'.$re['date3'];
                $result[$i]['meeting'][$j]['meeting_position'] = $re['position'];
                if ($attend == 1 && $sign_out == 1){
                    $result[$i]['meeting'][$j]['status'] = '出席';
                }elseif ($attend == 1 && $sign_out == 0){
                    $result[$i]['meeting'][$j]['status'] = '早退';
                }elseif ($attend == 0 && $sign_out == 1){
                    $result[$i]['meeting'][$j]['status'] = '迟到';
                }else{
                    $result[$i]['meeting'][$j]['status'] = '缺席';
                }
                $j++;
            }
            $result[$i]['user_id'] = $uid;
            $result[$i]['username'] = $v['username'];
            $result[$i]['major'] = $v['major'];
            $result[$i]['number'] = $v['number'];

            $i++;
        }
        return json([
            'code' => 200,
            'msg' => $result
        ]);
    }

    public function create_attendance_check($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $meeting = new Meeting();
        $member = new Meeting_member();
        $user = new User();
        if (!array_key_exists('page',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页号！'
            ]));
        }
        if (!array_key_exists('size',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页大小！'
            ]));
        }
        if (!array_key_exists('term',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！'
            ]));
        }
        //验证
        (new ShowMeeting())->goToCheck($data);
        $page = (int)$data['page'];
        $size = (int)$data['size'];
        if ($page<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '数据参数中的第一项最小为0！'
            ]));
        }
        if ($size<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '数据参数中的第二项最小为0！'
            ]));
        }
        if ($page*$size == 0 && $page+$size!=0){
            exit(json_encode([
                'code' => 400,
                'msg' => '为0情况只有数据参数中两项同时为零，否则最小从1开始'
            ]));
        }
        vendor('PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $styleThinBlackBorderOutline = array(
            'borders' => array (
                'outline' => array (
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
                    'color' => array ('argb' => 'FF000000'),     //设置border颜色
                ),
            ),
        );
        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setTitle("出勤查看");
        $objPHPExcel->getActiveSheet()->setCellValue("A1", "序号");
        $objPHPExcel->getActiveSheet()->getStyle("A1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A1')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("B1", "工号");
        $objPHPExcel->getActiveSheet()->getStyle("B1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('B1')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("C1", "姓名");
        $objPHPExcel->getActiveSheet()->getStyle("C1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('C1')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("D1", "单位");
        $objPHPExcel->getActiveSheet()->getStyle("D1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('D1')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("E1", "出席次数");
        $objPHPExcel->getActiveSheet()->getStyle("E1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('E1')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('E1')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("F1", "请假次数");
        $objPHPExcel->getActiveSheet()->getStyle("F1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('F1')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('F1')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("G1", "缺席次数");
        $objPHPExcel->getActiveSheet()->getStyle("G1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('G1')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('G1')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("H1", "迟到次数");
        $objPHPExcel->getActiveSheet()->getStyle("H1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('H1')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('H1')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("I1", "早退次数");
        $objPHPExcel->getActiveSheet()->getStyle("I1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('I1')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('I1')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->getStyle("A1:I1")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(25);

        if ($page == 0 && $size == 0){
            $i = 2;
            $r = [];
            if ($data['term'] != 'all'){
                $t = str_replace('-','',$data['term']);
                $info = $user->field('id,username,major,number')
                    ->order([
                        'number' => 'asc'
                    ])
                    ->select();
                if (!$info){
                    exit(json_encode([
                        'code' => 400,
                        'msg' => '未查到用户'
                    ]));
                }
                foreach ($info as $k){
                    //出席，请假，缺席
                    $attend = 0;
                    $ask_leave = 0;
                    $absence = 0;
                    $early = 0;
                    $late = 0;
                    $r[$i]['user_id'] = $k['id'];
                    $r[$i]['username'] = $k['username'];
                    $r[$i]['major'] = $k['major'];
                    $r[$i]['number'] = $k['number'];
                    $kk = $member->where([
                        'user_id' => $k['id'],
                        'term' => $t
                    ])->field('attend,ask_leave,sign_out')->select();
                    //如果没查道的话这个用户就是没参加过会议
                    if ($kk){
                        foreach ($kk as $kkk){
                            if ((int)$kkk['ask_leave'] == 1){
                                $ask_leave++;
                            }elseif ((int)$kkk['attend'] == 1 && (int)$kkk['sign_out'] == 1){
                                $attend++;
                            }elseif ((int)$kkk['attend'] == 1&& (int)$kkk['sign_out'] == 0){
                                $early++;
                            }elseif ((int)$kkk['attend'] == 0&& (int)$kkk['sign_out'] == 1){
                                $late++;
                            }else{
                                $absence++;
                            }
                        }
                    }
                    $r[$i]['attend'] = $attend;
                    $r[$i]['ask_leave'] = $ask_leave;
                    $r[$i]['absence'] = $absence;
                    $r[$i]['early'] = $early;
                    $r[$i]['late'] = $late;
                    $i++;
                }
            }else{
                $info = $user->field('id,username,major,number')
                    ->order([
                        'number' => 'asc'
                    ])
                    ->select();
                if (!$info){
                    exit(json_encode([
                        'code' => 400,
                        'msg' => '未查到用户'
                    ]));
                }
                foreach ($info as $k){
                    //出席，请假，缺席
                    $attend = 0;
                    $ask_leave = 0;
                    $absence = 0;
                    $early = 0;
                    $late = 0;
                    $r[$i]['user_id'] = $k['id'];
                    $r[$i]['username'] = $k['username'];
                    $r[$i]['major'] = $k['major'];
                    $r[$i]['number'] = $k['number'];
                    $kk = $member->where([
                        'user_id' => $k['id']
                    ])->field('attend,ask_leave,sign_out')->select();
                    //如果没查道的话这个用户就是没参加过会议
                    if ($kk){
                        foreach ($kk as $kkk){
                            if ((int)$kkk['ask_leave'] == 1){
                                $ask_leave++;
                            }elseif ((int)$kkk['attend'] == 1 && (int)$kkk['sign_out'] == 1){
                                $attend++;
                            }elseif ((int)$kkk['attend'] == 1&& (int)$kkk['sign_out'] == 0){
                                $early++;
                            }elseif ((int)$kkk['attend'] == 0&& (int)$kkk['sign_out'] == 1){
                                $late++;
                            }else{
                                $absence++;
                            }
                        }
                    }
                    $r[$i]['attend'] = $attend;
                    $r[$i]['ask_leave'] = $ask_leave;
                    $r[$i]['absence'] = $absence;
                    $r[$i]['early'] = $early;
                    $r[$i]['late'] = $late;
                    $i++;
                }
            }
        }else{
            $start = ($page-1)*$size;
            $r = [];
            $i = 2;
            if ($data['term'] != 'all'){
                $t = str_replace('-','',$data['term']);
                $info = $user->limit($start,$size)->field('id,username,major,number')
                    ->order([
                        'number' => 'asc'
                    ])
                    ->select();
                if (!$info){
                    exit(json_encode([
                        'code' => 400,
                        'msg' => '未查到用户'
                    ]));
                }
                foreach ($info as $k){
                    //出席，请假，缺席
                    $attend = 0;
                    $ask_leave = 0;
                    $absence = 0;
                    $early = 0;
                    $late = 0;
                    $r[$i]['user_id'] = $k['id'];
                    $r[$i]['username'] = $k['username'];
                    $r[$i]['major'] = $k['major'];
                    $r[$i]['number'] = $k['number'];
                    $kk = $member->where([
                        'user_id' => $k['id'],
                        'term' => $t
                    ])->field('attend,ask_leave')->select();
                    //如果没查道的话这个用户就是没参加过会议
                    if ($kk){
                        foreach ($kk as $kkk){
                            if ((int)$kkk['ask_leave'] == 1){
                                $ask_leave++;
                            }elseif ((int)$kkk['attend'] == 1 && (int)$kkk['sign_out'] == 1){
                                $attend++;
                            }elseif ((int)$kkk['attend'] == 1&& (int)$kkk['sign_out'] == 0){
                                $early++;
                            }elseif ((int)$kkk['attend'] == 0&& (int)$kkk['sign_out'] == 1){
                                $late++;
                            }else{
                                $absence++;
                            }
                        }
                    }
                    $r[$i]['attend'] = $attend;
                    $r[$i]['ask_leave'] = $ask_leave;
                    $r[$i]['absence'] = $absence;
                    $r[$i]['early'] = $early;
                    $r[$i]['late'] = $late;
                    $i++;
                }
            }else{
                $info = $user->field('id,username,major,number')
                    ->order([
                        'number' => 'asc'
                    ])
                    ->limit($start,$size)
                    ->select();
                if (!$info){
                    exit(json_encode([
                        'code' => 400,
                        'msg' => '未查到用户'
                    ]));
                }
                foreach ($info as $k){
                    //出席，请假，缺席
                    $attend = 0;
                    $ask_leave = 0;
                    $absence = 0;
                    $early = 0;
                    $late = 0;
                    $r[$i]['user_id'] = $k['id'];
                    $r[$i]['username'] = $k['username'];
                    $r[$i]['major'] = $k['major'];
                    $r[$i]['number'] = $k['number'];
                    $kk = $member->where([
                        'user_id' => $k['id']
                    ])->field('attend,ask_leave')->select();
                    //如果没查道的话这个用户就是没参加过会议
                    if ($kk){
                        foreach ($kk as $kkk){
                            if ((int)$kkk['ask_leave'] == 1){
                                $ask_leave++;
                            }elseif ((int)$kkk['attend'] == 1 && (int)$kkk['sign_out'] == 1){
                                $attend++;
                            }elseif ((int)$kkk['attend'] == 1&& (int)$kkk['sign_out'] == 0){
                                $early++;
                            }elseif ((int)$kkk['attend'] == 0&& (int)$kkk['sign_out'] == 1){
                                $late++;
                            }else{
                                $absence++;
                            }
                        }
                    }
                    $r[$i]['attend'] = $attend;
                    $r[$i]['ask_leave'] = $ask_leave;
                    $r[$i]['absence'] = $absence;
                    $r[$i]['early'] = $early;
                    $r[$i]['late'] = $late;
                    $i++;
                }
            }

        }
        $i = 2;
        $u = 1;
        foreach ($r as $item) {
            $objPHPExcel->getActiveSheet()->setCellValue("A".$i, $u);
            $objPHPExcel->getActiveSheet()->getStyle("A".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A'.$i)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("B".$i, $item['number']);
            $objPHPExcel->getActiveSheet()->getStyle("B".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('B'.$i)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("C".$i, $item['username']);
            $objPHPExcel->getActiveSheet()->getStyle("C".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('C'.$i)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("D".$i, $item['major']);
            $objPHPExcel->getActiveSheet()->getStyle("D".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('D'.$i)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("E".$i, $item['attend']);
            $objPHPExcel->getActiveSheet()->getStyle("E".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('E'.$i)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('E'.$i)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("F".$i, $item['ask_leave']);
            $objPHPExcel->getActiveSheet()->getStyle("F".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('F'.$i)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('F'.$i)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("G".$i, $item['absence']);
            $objPHPExcel->getActiveSheet()->getStyle("G".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('G'.$i)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('G'.$i)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("H".$i, $item['late']);
            $objPHPExcel->getActiveSheet()->getStyle("H".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('H'.$i)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('H'.$i)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("I".$i, $item['early']);
            $objPHPExcel->getActiveSheet()->getStyle("I".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('I'.$i)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('I'.$i)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->getRowDimension($i)->setRowHeight(25);
            $i++;
            $u++;
        }

        //设置格子大小
        $objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension()->setWidth(25);
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(8);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $_savePath = COMMON_PATH.'/static/attendance_check.xlsx';
        $objWriter->save($_savePath);
        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/attendance_check.xlsx'
        ]);
    }


//    public function create_search($data){
//        $TokenModel = new Token();
//        $id = $TokenModel->get_id();
//        $secret = $TokenModel->checkUser();
//        if ($secret != 32){
//            exit(json_encode([
//                'code' => 403,
//                'msg' => '权限不足！'
//            ]));
//        }
//        $meeting = new Meeting();
//        $user = new User();
//        $member = new Meeting_member();
//        if (!array_key_exists('search_key',$data)){
//            exit(json_encode([
//                'code' => 400,
//                'msg' => '无搜索关键词！'
//            ]));
//        }
//        if (!array_key_exists('term',$data)){
//            exit(json_encode([
//                'code' => 400,
//                'msg' => '无学期！'
//            ]));
//        }
//        //验证
//        (new Search())->goToCheck($data);
//        $search_key = filter($data['search_key']);
//        $info = $user->where([
//            'number|username' => $search_key
//        ])->field('id,username,major,number')->select();
//        if (!$info){
//            exit(json_encode([
//                'code' => 400,
//                'msg' => '查无此人'
//            ]));
//        }
//        $i = 0;
//        //用来记录表格的行号
//        $k = 1;
//        $time = (int)time();
//        $count = count($info);
//        vendor('PHPExcel');
//        $objPHPExcel = new \PHPExcel();
//        $objPHPExcel->createSheet();
//        $objPHPExcel->setActiveSheetIndex(0);
//        $objPHPExcel->getActiveSheet()->setTitle("出勤详情");
//        $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
//        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod);
//        $styleThinBlackBorderOutline = array(
//            'borders' => array (
//                'outline' => array (
//                    'style' => \PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
//                    'color' => array ('argb' => 'FF000000'),     //设置border颜色
//                ),
//            ),
//        );
//        foreach ($info as $v){
//            $uid = $v['id'];
//            //出席，请假，缺席
//            $att = 0;
//            $ask = 0;
//            $absence = 0;
//            $early = 0;
//            $late = 0;
//
//            if ($data['term'] == 'all'){
//                $check = $member->where([
//                    'user_id' => $uid
//                ])->where('end_time','<',$time)->field('meeting_id,attend,ask_leave,sign_out')->select();
//                if (!$check){
//                    if ($i+1 == $count){
//                        exit(json_encode([
//                            'code' => 557,
//                            'msg' => '该用户没有已结束的会议'
//                        ]));
//                    }else{
//                        continue;
//                    }
//                }
//            }else{
//                $t = str_replace('-','',$data['term']);
//                $check = $member->where([
//                    'user_id' => $uid,
//                    'term' => $t
//                ])->where('end_time','<',$time)->field('meeting_id,attend,ask_leave,sign_out')->select();
//                if (!$check){
//                    if ($i+1 == $count){
//                        exit(json_encode([
//                            'code' => 557,
//                            'msg' => '该用户在该学期没有已结束的会议'
//                        ]));
//                    }else{
//                        continue;
//                    }
//                }
//            }
//            $m = $k;
//            $k = $k + 2;
//            foreach ($check as $kkkk){
//                $re = $meeting->where([
//                    'id' => $kkkk['meeting_id']
//                ])->field('name,date1,date2,date3,position')->find();
//                $attend = (int)$kkkk['attend'];
//                $ask_leave = (int)$kkkk['ask_leave'];
//                $sign_out = (int)$kkkk['sign_out'];
//                if ($ask_leave == 1){
//                    $status = '请假';
//                    $ask++;
//                }elseif ($attend == 1 && $sign_out == 1){
//                    $status = '出席';
//                    $att++;
//                }elseif ($attend == 1 && $sign_out == 0){
//                    $status = '早退';
//                    $early++;
//                }elseif ($attend == 0 && $sign_out == 1){
//                    $status = '迟到';
//                    $late++;
//                }else{
//                    $status = '缺席';
//                    $absence++;
//                }
//
//                $objPHPExcel->getActiveSheet()->setCellValue("A".$k, $v['number']);
//                $objPHPExcel->getActiveSheet()->getStyle("A".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('A'.$k)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("B".$k, $v['username']);
//                $objPHPExcel->getActiveSheet()->getStyle("B".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('B'.$k)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("C".$k, $v['major']);
//                $objPHPExcel->getActiveSheet()->getStyle("C".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('C'.$k)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("D".$k, $re['name']);
//                $objPHPExcel->getActiveSheet()->getStyle("D".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('D'.$k)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("E".$k, $re['date1'].'/'.$re['date2'].'/'.$re['date3']);
//                $objPHPExcel->getActiveSheet()->getStyle("E".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->getAlignment()->setWrapText(true);
//                $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("F".$k, $re['position']);
//                $objPHPExcel->getActiveSheet()->getStyle("F".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('F'.$k)->getAlignment()->setWrapText(true);
//                $objPHPExcel->getActiveSheet()->getStyle('F'.$k)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("G".$k, $status);
//                $objPHPExcel->getActiveSheet()->getStyle("G".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('G'.$k)->getAlignment()->setWrapText(true);
//                $objPHPExcel->getActiveSheet()->getStyle('G'.$k)->applyFromArray($styleThinBlackBorderOutline);
//                $k = $k + 1;
//            }
//            $objPHPExcel->getActiveSheet()->mergeCells("A".$m.":G".$m);
//            $objPHPExcel->getActiveSheet()->setCellValue("A".$m, $v['major'].$v['username'].':出席'.$att.'次，请假'.$ask.'次，缺席'.$absence.'次，迟到'.$late.'次，早退'.$early.'次');
//            $objPHPExcel->getActiveSheet()->getStyle("A".$m.":G".$m)->getFont()->setBold(true);
//            $objPHPExcel->getActiveSheet()->getStyle( 'A'.$m)->getFont()->setSize(14);
//            $objPHPExcel->getActiveSheet()->getStyle("A".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//            $m += 1;
//            $objPHPExcel->getActiveSheet()->setCellValue("A".$m, "工号");
//            $objPHPExcel->getActiveSheet()->getStyle("A".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//            $objPHPExcel->getActiveSheet()->getStyle('A'.$m)->applyFromArray($styleThinBlackBorderOutline);
//            $objPHPExcel->getActiveSheet()->setCellValue("B".$m, "姓名");
//            $objPHPExcel->getActiveSheet()->getStyle("B".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//            $objPHPExcel->getActiveSheet()->getStyle('B'.$m)->applyFromArray($styleThinBlackBorderOutline);
//            $objPHPExcel->getActiveSheet()->setCellValue("C".$m, "单位");
//            $objPHPExcel->getActiveSheet()->getStyle("C".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//            $objPHPExcel->getActiveSheet()->getStyle('C'.$m)->applyFromArray($styleThinBlackBorderOutline);
//            $objPHPExcel->getActiveSheet()->setCellValue("D".$m, "会议名称");
//            $objPHPExcel->getActiveSheet()->getStyle("D".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//            $objPHPExcel->getActiveSheet()->getStyle('D'.$m)->applyFromArray($styleThinBlackBorderOutline);
//            $objPHPExcel->getActiveSheet()->setCellValue("E".$m, "日期");
//            $objPHPExcel->getActiveSheet()->getStyle("E".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//            $objPHPExcel->getActiveSheet()->getStyle('E'.$m)->getAlignment()->setWrapText(true);
//            $objPHPExcel->getActiveSheet()->getStyle('E'.$m)->applyFromArray($styleThinBlackBorderOutline);
//            $objPHPExcel->getActiveSheet()->setCellValue("F".$m, "地点");
//            $objPHPExcel->getActiveSheet()->getStyle("F".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//            $objPHPExcel->getActiveSheet()->getStyle('F'.$m)->getAlignment()->setWrapText(true);
//            $objPHPExcel->getActiveSheet()->getStyle('F'.$m)->applyFromArray($styleThinBlackBorderOutline);
//            $objPHPExcel->getActiveSheet()->setCellValue("G".$m, "出勤情况");
//            $objPHPExcel->getActiveSheet()->getStyle("G".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//            $objPHPExcel->getActiveSheet()->getStyle('G'.$m)->getAlignment()->setWrapText(true);
//            $objPHPExcel->getActiveSheet()->getStyle('G'.$m)->applyFromArray($styleThinBlackBorderOutline);
//
//            $i++;
//        }
//
//        //设置格子大小
//        $objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(25);
//        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension()->setWidth(25);
//
//        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
//        $_savePath = COMMON_PATH.'/static/attendance_detail.xlsx';
//        $objWriter->save($_savePath);
//
//        return json([
//            'code' => 200,
//            'msg' => config('setting.image_root').'static/attendance_detail.xlsx'
//        ]);
//    }

    public function create_code($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('meeting_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议标识！'
            ]));
        }
        if (!is_numeric($data['meeting_id'])){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议标识需为数字'
            ]));
        }
        //用三组字符串md5加密
        //32个字符组成一组随机字符串
        $randChars = getRandChars(32);
        //时间戳
        $timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
        //salt 盐
        $salt = 'Quanta';

        $key = md5($randChars.$timestamp.$salt);
        vendor('phpqrcode.phpqrcode');
        $url = json_encode([
            'meeting_id' => $data['meeting_id'],
            'code_id' => $key
        ]);
        //存起来
        cache($key,1,15);
        $errorCorrectionLevel = 'L';//容错级别
        $matrixPointSize = 6;//生成图片大小
        $new_image = COMMON_PATH.'static/code.png';
        //生成二维码图片
        \QRcode::png($url, $new_image, $errorCorrectionLevel, $matrixPointSize, 2);
        //输出图片
        header("Content-type: image/png");
        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/code.png'
        ]);
    }

    public function create_sign_out_code($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('meeting_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议标识！'
            ]));
        }
        if (!is_numeric($data['meeting_id'])){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议标识需为数字'
            ]));
        }
        //用三组字符串md5加密
        //32个字符组成一组随机字符串
        $randChars = getRandChars(32);
        //时间戳
        $timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
        //salt 盐
        $salt = 'Qt';

        $key = md5($randChars.$timestamp.$salt);
        vendor('phpqrcode.phpqrcode');
        $url = json_encode([
            'meeting_id' => $data['meeting_id'],
            'code_id' => $key
        ]);
        //存起来
        cache($key,2,15);
        $errorCorrectionLevel = 'L';//容错级别
        $matrixPointSize = 6;//生成图片大小
        $new_image = COMMON_PATH.'static/sign_out.png';
        //生成二维码图片
        \QRcode::png($url, $new_image, $errorCorrectionLevel, $matrixPointSize, 2);
        //输出图片
        header("Content-type: image/png");
        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/sign_out.png'
        ]);
    }

    public function be_start($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);
        $id = $data['id'];

        $check = Db::table('meeting')->where([
            'id' => $id
        ])->field('begin,end_time')->find();

        $begin = $check['begin'];
        $end = $check['end_time'];
        $time = (int)time();
        if ($time>=$end){
            exit(json_encode([
                'code' => 400,
                'msg' => '该会议已结束'
            ]));
        }
        if ($begin != $time){
            Db::startTrans();
            $result = Db::table('meeting')
                ->where([
                    'id' => $id
                ])
                ->update([
                    'begin' => $time
                ]);

            if (!$result){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '更新出错，可能是参数出错'
                ]));
            }
            $re = Db::table('meeting_member')
                ->where([
                    'meeting_id' => $id
                ])
                ->update([
                    'begin' => $time
                ]);
            if (!$re){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '更新出错，可能是参数出错'
                ]));
            }
            Db::commit();
        }
        return json([
            'code' => 200,
            'msg' => '修改成功'
        ]);
    }

    public function be_end($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);
        $id = $data['id'];

        $check = Db::table('meeting')->where([
            'id' => $id
        ])->field('begin,end_time')->find();

        $begin = $check['begin'];
        $end = $check['end_time'];
        $time = (int)time();
        if ($time<=$begin){
            exit(json_encode([
                'code' => 400,
                'msg' => '该会议未开始'
            ]));
        }
        if ($end != $time){
            Db::startTrans();
            $result = Db::table('meeting')
                ->where([
                    'id' => $id
                ])
                ->update([
                    'end_time' => $time
                ]);

            if (!$result){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '更新出错，可能是参数出错'
                ]));
            }
            $re = Db::table('meeting_member')
                ->where([
                    'meeting_id' => $id
                ])
                ->update([
                    'end_time' => $time
                ]);
            if (!$re){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '更新出错，可能是参数出错'
                ]));
            }
            Db::commit();
        }

        return json([
            'code' => 200,
            'msg' => '修改成功'
        ]);
    }

    public function in($file='', $sheet=0,$username_colum = 0,$number_colum = 1,$major_colum = 2){
        $file = iconv("utf-8", "gb2312", $file);   //转码
        if(empty($file) OR !file_exists($file)) {
            die('file not exists!');
        }
        vendor('PHPExcel');
        $objRead = new \PHPExcel_Reader_Excel2007();   //建立reader对象
        if(!$objRead->canRead($file)){
            $objRead = new \PHPExcel_Reader_Excel5();
            if(!$objRead->canRead($file)){
                die('No Excel!');
            }
        }

        $cellName = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ');

        $obj = $objRead->load($file);  //建立excel对象
        $currSheet = $obj->getSheet($sheet);   //获取指定的sheet表
        $columnH = $currSheet->getHighestColumn();   //取得最大的列号
        $columnCnt = array_search($columnH, $cellName);
        $rowCnt = $currSheet->getHighestRow();   //获取总行数
        Db::startTrans();
        for($_row=2; $_row<=$rowCnt; $_row++){  //读取内容
            for($_column=0; $_column<$columnCnt; $_column++){
                $cellId = $cellName[$_column].$_row;
                $cellValue = $currSheet->getCell($cellId)->getValue();
                if ($_column == $username_colum){
                    $cellValue1=preg_replace("/[\r\n\s]/","",$cellValue);
                }elseif ($_column == $number_colum){
                    $cellValue2=preg_replace("/[\r\n\s]/","",$cellValue);
                }elseif ($_column == $major_colum){
                    $cellValue3=preg_replace("/[\r\n\s]/","",$cellValue);
                }
            }

//            var_dump("名字：".$cellValue1."学号：".$cellValue2."学院：".$cellValue3."<br>");

            if (!Db::table('user')->where(['username'=>$cellValue1,'number' => $cellValue2,'major' => $cellValue3])->find()){
                $result = Db::table('user')
                    ->insert([
                        'username' => $cellValue1,
                        'password' => '09951fc3343c63973369b91bdc8e441a',
                        'number' => $cellValue2,
                        'major' => $cellValue3
                    ]);

                if (!$result){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 400,
                        'msg' => '出错了'
                    ]));
                }
            }
        }
        Db::commit();

        return 0;
    }

    public function show_meeting_sign($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);

        $id = $data['id'];

        $meeting = new Meeting();
        //查询
        $result = $meeting->where([
            'id' => $id
        ])->find();
        if (!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => '或许是id不正确，没有该会议！'
            ]));
        }

        $term = $result['term'];
        $select = Db::table('sign_list')
            ->where([
                'meeting_id' => $id
            ])->select();
        if (!$select){
            return json_encode([
                'code' => 200,
                'msg' => []
            ]);
        }
        $r = [];
        $i = 0;
        foreach ($select as $v){
            $user_id = $v['user_id'];
            $user = Db::table('user')
                ->where([
                    'id' => $user_id
                ])->find();
            $r[$i]['user_id'] = $user_id;
            $r[$i]['name'] = $user['username'];
            $r[$i]['number'] = $user['number'];
            $period = Db::table('period')
                ->where([
                    'user_id' => $user_id,
                    'term' => $term
                ])->find();
            if (!$period){
                $r[$i]['period'] = 0;
            }else{
                $r[$i]['period'] = $period['period'];
            }
            $r[$i]['state'] = $v['state'];
            $i++;
        }

        return json_encode([
            'code' => 200,
            'msg' => $r
        ]);
    }

    //让所有人通过审核
    public function change_check($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $meeting = new Meeting();
        $user = new User();
        if (!array_key_exists('meeting',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '修改状态的数组！'
            ]));
        }
        if (!array_key_exists('user_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无用户标识！'
            ]));
        }
        $meet = $data['meeting'];
        $usr = $data['user_id'];
        $rule = [
            'meeting'  => 'require|number',
        ];
        $msg = [
            'meeting.require' => '会议标识不能为空',
            'meeting.number'   => '会议标识必须是数字',
        ];
        $validate = new Validate($rule,$msg);
        $result   = $validate->check($data);
        if(!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => $validate->getError()
            ]));
        }

        //检查是否有用户
        $re = $meeting->where([
            'id' => $meet
        ])->find();
        if (!$re){
            exit(json_encode([
                'code' => 400,
                'msg' => '没有该会议！'
            ]));
        }
        $term = $re['term'];
        $end_time = $re['end_time'];
        $begin = $re['begin'];
        $enter_begin = $re['enter_begin'];
        $enter_end = $re['enter_end'];
        $period = $re['period'];
        Db::startTrans();
        foreach ($usr as $vv){
            $user_id = $vv['user_id'];
            $rr = Db::table('sign_list')->where([
                'meeting_id' => $meet,
                'user_id' => $vv['user_id']
            ])->find();
            if (!$rr){
                Db::rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '没有该报名成员！'
                ]));
            }
            if ($vv['status'] == '同意'){
                if ($rr['state'] != '已通过审核'){
                    //出席
                    $info = Db::table('sign_list')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'state' => '已通过审核'
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }

                    $in = Db::table('meeting_member')
                        ->insert([
                            'meeting_id' => $meet,
                            'user_id' => $vv['user_id'],
                            'term' => $term,
                            'end_time' => $end_time,
                            'begin' => $begin,
                            'enter_begin' => $enter_begin,
                            'enter_end' => $enter_end,
                            'period' => $period
                        ]);
                    if (!$in){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['status'] == '取消'){
                if ($rr['state'] != '未通过审核'){
                    //出席
                    $info = Db::table('sign_list')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'state' => '未通过审核'
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                    $out = Db::table('meeting_member')
                        ->where([
                            'meeting_id' => $meet,
                            'user_id' => $vv['user_id']
                        ])->delete();
                    if (!$out){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }else{
                Db::rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '没有这种状态'
                ]));
            }
        }
        Db::commit();

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //展示单个会议成员信息接口
    public function single_meeting_member($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);

        $id = $data['id'];

        $meeting = Db::table('meeting')
            ->where([
                'id' => $id
            ])->find();
        if (!$meeting){
            throw new BaseException([
                'msg' => '未找到该会议'
            ]);
        }

//        $end_time = $meeting['end_time'];
//        $time = (int)time();
//        if ($time <= $end_time){
//            throw new BaseException([
//                'msg' => '会议还未结束，不能看成员的出勤情况'
//            ]);
//        }
        $meeting_name = $meeting['name'];
        $term = $meeting['term'];

        //找成员
        $member = Db::table('meeting_member')
            ->where([
                'meeting_id' => $id
            ])->select();
        $r = [];
        $i = 0;
        $r['meeting_name'] = $meeting_name;
        if ($member){
            foreach ($member as $v){
                $r['member'][$i]['user_id'] = $v['user_id'];
                $user = Db::table('user')->where(['id' => $v['user_id']])->find();
                $r['member'][$i]['user_name'] = $user['username'];
                $r['member'][$i]['user_number'] = $user['number'];
                $r['member'][$i]['period'] = (string)$this->find_period($v['user_id'],$term);
                if ((int)$v['attend'] == 1){
                    $r['member'][$i]['sign_in'] = '已签到';
                    if ((int)$v['sign_out'] == 1){
                        $r['member'][$i]['status'] = '出席';
                    }else{
                        $r['member'][$i]['status'] = '早退';
                    }
                }else{
                    $r['member'][$i]['sign_in'] = '未签到';
                    if ((int)$v['sign_out'] == 1){
                        $r['member'][$i]['status'] = '迟到';
                    }else{
                        $r['member'][$i]['status'] = '缺席';
                    }
                }
                if ((int)$v['sign_out'] == 1){
                    $r['member'][$i]['sign_out'] = '已签退';
                }else{
                    $r['member'][$i]['sign_out'] = '未签退';
                }
            }
        }

        return json([
            'code' => 200,
            'msg' => $r
        ]);
    }

    public function create_single_meeting_member($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);

        $id = $data['id'];

        $meeting = Db::table('meeting')
            ->where([
                'id' => $id
            ])->find();
        if (!$meeting){
            throw new BaseException([
                'msg' => '未找到该会议'
            ]);
        }

        $end_time = $meeting['end_time'];
        $time = (int)time();
        if ($time <= $end_time){
            throw new BaseException([
                'msg' => '会议还未结束，不能看成员的出勤情况'
            ]);
        }
        $meeting_name = $meeting['name'];
        $term = $meeting['term'];

        //找成员
        $member = Db::table('meeting_member')
            ->where([
                'meeting_id' => $id
            ])->select();
        $r = [];
        $i = 0;
        $r['meeting_name'] = $meeting_name;
        if ($member){
            foreach ($member as $v){
                $r['member'][$i]['user_id'] = $v['user_id'];
                $user = Db::table('user')->where(['id' => $v['user_id']])->find();
                $r['member'][$i]['user_name'] = $user['username'];
                $r['member'][$i]['user_number'] = $user['number'];
                $r['member'][$i]['period'] = (string)$this->find_period($v['user_id'],$term);
                if ((int)$v['attend'] == 1){
                    $r['member'][$i]['sign_in'] = '已签到';
                    if ((int)$v['sign_out'] == 1){
                        $r['member'][$i]['status'] = '出席';
                    }else{
                        $r['member'][$i]['status'] = '早退';
                    }
                }else{
                    $r['member'][$i]['sign_in'] = '未签到';
                    if ((int)$v['sign_out'] == 1){
                        $r['member'][$i]['status'] = '迟到';
                    }else{
                        $r['member'][$i]['status'] = '缺席';
                    }
                }
                if ((int)$v['sign_out'] == 1){
                    $r['member'][$i]['sign_out'] = '已签退';
                }else{
                    $r['member'][$i]['sign_out'] = '未签退';
                }
            }
        }

        //用来记录表格的行号
        $k = 3;

        vendor('PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod);
        $styleThinBlackBorderOutline = array(
            'borders' => array (
                'outline' => array (
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
                    'color' => array ('argb' => 'FF000000'),     //设置border颜色
                ),
            ),
        );

        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setTitle("讲座成员信息");

        $objPHPExcel->getActiveSheet()->mergeCells("A1:F1");
        $objPHPExcel->getActiveSheet()->setCellValue("A1", "讲座名称：".$meeting_name);
        $objPHPExcel->getActiveSheet()->getStyle("A1:F1")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle( 'A1')->getFont()->setSize(14);
        $objPHPExcel->getActiveSheet()->getStyle("A1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

        $objPHPExcel->getActiveSheet()->setCellValue("A2", "姓名");
        $objPHPExcel->getActiveSheet()->getStyle("A2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("B2", "学号");
        $objPHPExcel->getActiveSheet()->getStyle("B2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('B2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("C2", "该学期学时");
        $objPHPExcel->getActiveSheet()->getStyle("C2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('C2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("D2", "签到状态");
        $objPHPExcel->getActiveSheet()->getStyle("D2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('D2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("E2", "签退状态");
        $objPHPExcel->getActiveSheet()->getStyle("E2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("F2", "出勤情况");
        $objPHPExcel->getActiveSheet()->getStyle('F2')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('F2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->getStyle("A2:F2")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(25);

        foreach ($r['member'] as $v){
            $objPHPExcel->getActiveSheet()->setCellValue("A".$k, $v['user_name']);
            $objPHPExcel->getActiveSheet()->getStyle("A".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("B".$k, $v['user_number']);
            $objPHPExcel->getActiveSheet()->getStyle("B".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('B'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("C".$k, $v['period']);
            $objPHPExcel->getActiveSheet()->getStyle("C".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('C'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("D".$k, $v['sign_in']);
            $objPHPExcel->getActiveSheet()->getStyle("D".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('D'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("E".$k, $v['sign_out']);
            $objPHPExcel->getActiveSheet()->getStyle("E".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("F".$k, $v['status']);
            $objPHPExcel->getActiveSheet()->getStyle("F".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('F'.$k)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('F'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $k = $k + 1;
        }

        //设置格子大小
        $objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension()->setWidth(25);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $_savePath = COMMON_PATH.'/static/single_meeting_info.xlsx';
        $objWriter->save($_savePath);

        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/single_meeting_info.xlsx'
        ]);
    }

    public function create_show_checked($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);

        $id = $data['id'];

        $meeting = Db::table('meeting')
            ->where([
                'id' => $id
            ])->find();
        if (!$meeting){
            throw new BaseException([
                'msg' => '未找到该会议'
            ]);
        }

        $end_time = $meeting['end_time'];
        $time = (int)time();
        if ($time <= $end_time){
            throw new BaseException([
                'msg' => '会议还未结束，不能看成员的出勤情况'
            ]);
        }
        $meeting_name = $meeting['name'];
        $term = $meeting['term'];

        //找成员
        $member = Db::table('meeting_member')
            ->where([
                'meeting_id' => $id
            ])->select();
        $r = [];
        $i = 0;
        $r['meeting_name'] = $meeting_name;
        if ($member){
            foreach ($member as $v){
                $r['member'][$i]['user_id'] = $v['user_id'];
                $user = Db::table('user')->where(['id' => $v['user_id']])->find();
                $r['member'][$i]['user_name'] = $user['username'];
                $r['member'][$i]['user_number'] = $user['number'];
                $r['member'][$i]['period'] = (string)$this->find_period($v['user_id'],$term);
                if ((int)$v['attend'] == 1){
                    $r['member'][$i]['sign_in'] = '已签到';
                    if ((int)$v['sign_out'] == 1){
                        $r['member'][$i]['status'] = '出席';
                    }else{
                        $r['member'][$i]['status'] = '早退';
                    }
                }else{
                    $r['member'][$i]['sign_in'] = '未签到';
                    if ((int)$v['sign_out'] == 1){
                        $r['member'][$i]['status'] = '迟到';
                    }else{
                        $r['member'][$i]['status'] = '缺席';
                    }
                }
                if ((int)$v['sign_out'] == 1){
                    $r['member'][$i]['sign_out'] = '已签退';
                }else{
                    $r['member'][$i]['sign_out'] = '未签退';
                }
            }
        }

        //用来记录表格的行号
        $k = 3;

        vendor('PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod);
        $styleThinBlackBorderOutline = array(
            'borders' => array (
                'outline' => array (
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
                    'color' => array ('argb' => 'FF000000'),     //设置border颜色
                ),
            ),
        );

        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setTitle("已审核学生");

        $objPHPExcel->getActiveSheet()->mergeCells("A1:F1");
        $objPHPExcel->getActiveSheet()->setCellValue("A1", "讲座名称：".$meeting_name);
        $objPHPExcel->getActiveSheet()->getStyle("A1:F1")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle( 'A1')->getFont()->setSize(14);
        $objPHPExcel->getActiveSheet()->getStyle("A1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

        $objPHPExcel->getActiveSheet()->setCellValue("A2", "姓名");
        $objPHPExcel->getActiveSheet()->getStyle("A2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("B2", "学号");
        $objPHPExcel->getActiveSheet()->getStyle("B2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('B2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("C2", "该学期学时");
        $objPHPExcel->getActiveSheet()->getStyle("C2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('C2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("D2", "签到状态");
        $objPHPExcel->getActiveSheet()->getStyle("D2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('D2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("E2", "签退状态");
        $objPHPExcel->getActiveSheet()->getStyle("E2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->getStyle("A2:E2")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(25);

        foreach ($r['member'] as $v){
            $objPHPExcel->getActiveSheet()->setCellValue("A".$k, $v['user_name']);
            $objPHPExcel->getActiveSheet()->getStyle("A".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("B".$k, $v['user_number']);
            $objPHPExcel->getActiveSheet()->getStyle("B".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('B'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("C".$k, $v['period']);
            $objPHPExcel->getActiveSheet()->getStyle("C".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('C'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("D".$k, $v['sign_in']);
            $objPHPExcel->getActiveSheet()->getStyle("D".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('D'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("E".$k, $v['sign_out']);
            $objPHPExcel->getActiveSheet()->getStyle("E".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $k = $k + 1;
        }

        //设置格子大小
        $objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension()->setWidth(25);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $_savePath = COMMON_PATH.'/static/show_checked.xlsx';
        $objWriter->save($_savePath);

        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/show_checked.xlsx'
        ]);
    }

    public function create_show_meeting_sign($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);

        $id = $data['id'];

        $meeting = new Meeting();
        //查询
        $result = $meeting->where([
            'id' => $id
        ])->find();
        if (!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => '或许是id不正确，没有该会议！'
            ]));
        }

        $meeting_name = $result['name'];
        $term = $result['term'];
        $select = Db::table('sign_list')
            ->where([
                'meeting_id' => $id
            ])->select();
        if (!$select){
            $r = [];
        }else{
            $r = [];
            $i = 0;
            foreach ($select as $v){
                $user_id = $v['user_id'];
                $user = Db::table('user')
                    ->where([
                        'id' => $user_id
                    ])->find();
                $r[$i]['user_id'] = $user_id;
                $r[$i]['name'] = $user['username'];
                $r[$i]['number'] = $user['number'];
                $period = Db::table('period')
                    ->where([
                        'user_id' => $user_id,
                        'term' => $term
                    ])->find();
                if (!$period){
                    $r[$i]['period'] = 0;
                }else{
                    $r[$i]['period'] = $period['period'];
                }
                $r[$i]['state'] = $v['state'];
                $i++;
            }
        }


        //用来记录表格的行号
        $k = 3;

        vendor('PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod);
        $styleThinBlackBorderOutline = array(
            'borders' => array (
                'outline' => array (
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
                    'color' => array ('argb' => 'FF000000'),     //设置border颜色
                ),
            ),
        );

        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setTitle("已报名学生");

        $objPHPExcel->getActiveSheet()->mergeCells("A1:D1");
        $objPHPExcel->getActiveSheet()->setCellValue("A1", "讲座名称：".$meeting_name);
        $objPHPExcel->getActiveSheet()->getStyle("A1:D1")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle( 'A1')->getFont()->setSize(14);
        $objPHPExcel->getActiveSheet()->getStyle("A1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

        $objPHPExcel->getActiveSheet()->setCellValue("A2", "姓名");
        $objPHPExcel->getActiveSheet()->getStyle("A2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("B2", "学号");
        $objPHPExcel->getActiveSheet()->getStyle("B2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('B2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("C2", "该学期学时");
        $objPHPExcel->getActiveSheet()->getStyle("C2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('C2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("D2", "状态");
        $objPHPExcel->getActiveSheet()->getStyle("D2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('D2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(25);

        foreach ($r as $v){
            $objPHPExcel->getActiveSheet()->setCellValue("A".$k, $v['name']);
            $objPHPExcel->getActiveSheet()->getStyle("A".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("B".$k, $v['number']);
            $objPHPExcel->getActiveSheet()->getStyle("B".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('B'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("C".$k, $v['period']);
            $objPHPExcel->getActiveSheet()->getStyle("C".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('C'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("D".$k, $v['state']);
            $objPHPExcel->getActiveSheet()->getStyle("D".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('D'.$k)->applyFromArray($styleThinBlackBorderOutline);
            $k = $k + 1;
        }

        //设置格子大小
        $objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension()->setWidth(25);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $_savePath = COMMON_PATH.'/static/show_meeting_sign.xlsx';
        $objWriter->save($_savePath);

        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/show_meeting_sign.xlsx'
        ]);
    }

    public function create_show_student($data){
        ini_set('memory_limit','100M');
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $user = new User();
        if (!array_key_exists('page',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页号！'
            ]));
        }
        if (!array_key_exists('size',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页大小！'
            ]));
        }
        if (!array_key_exists('term',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！'
            ]));
        }
        //验证
        (new ShowMeeting())->goToCheck($data);
        $page = (int)$data['page'];
        $size = (int)$data['size'];
        if ($page<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '数据参数中的第一项最小为0'
            ]));
        }
        if ($size<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '数据参数中的第二项最小为0'
            ]));
        }
        if ($page*$size == 0 && $page+$size!=0){
            exit(json_encode([
                'code' => 400,
                'msg' => '为0情况只有数据参数中两项同时为零，否则最小从1开始'
            ]));
        }
        if ($data['term'] == 'all'){
            exit(json_encode([
                'code' => 400,
                'msg' => '传入学期没有全部的情况'
            ]));
        }

        //用来记录表格的行号
        $i = 3;

        vendor('PHPExcel');
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod);
//        $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
//        $cacheSettings = array( 'memoryCacheSize' => '512MB');
//        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod,$cacheSettings);
        $objPHPExcel = new \PHPExcel();
        $styleThinBlackBorderOutline = array(
            'borders' => array (
                'outline' => array (
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
                    'color' => array ('argb' => 'FF000000'),     //设置border颜色
                ),
            ),
        );

        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setTitle("已审核学生");

        $objPHPExcel->getActiveSheet()->mergeCells("A1:F1");
        $objPHPExcel->getActiveSheet()->setCellValue("A1", "学生查看");
        $objPHPExcel->getActiveSheet()->getStyle("A1:F1")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle( 'A1')->getFont()->setSize(14);
        $objPHPExcel->getActiveSheet()->getStyle("A1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

        $objPHPExcel->getActiveSheet()->setCellValue("A2", "姓名");
        $objPHPExcel->getActiveSheet()->getStyle("A2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("B2", "学号");
        $objPHPExcel->getActiveSheet()->getStyle("B2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('B2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("C2", "学院");
        $objPHPExcel->getActiveSheet()->getStyle("C2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('C2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("D2", "该学期学时");
        $objPHPExcel->getActiveSheet()->getStyle("D2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('D2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("E2", "总学时");
        $objPHPExcel->getActiveSheet()->getStyle("E2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->getStyle("A2:E2")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(25);

//        if ($page == 0 && $size == 0){
//            $t = str_replace('-','',$data['term']);
//            $info = $user->field('id,username,major,number,period')
//                ->order([
//                    'number' => 'asc'
//                ])
//                ->select();
//            if (!$info){
//                exit(json_encode([
//                    'code' => 400,
//                    'msg' => '未查到用户'
//                ]));
//            }
//            foreach ($info as $k){
//                $objPHPExcel->getActiveSheet()->setCellValue("A".$i, $k['username']);
//                $objPHPExcel->getActiveSheet()->getStyle("A".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('A'.$i)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("B".$i, $k['number']);
//                $objPHPExcel->getActiveSheet()->getStyle("B".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('B'.$i)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("C".$i, $k['major']);
//                $objPHPExcel->getActiveSheet()->getStyle("C".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('C'.$i)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("D".$i, (string)$this->find_period($k['id'],$t));
//                $objPHPExcel->getActiveSheet()->getStyle("D".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('D'.$i)->applyFromArray($styleThinBlackBorderOutline);
//                $objPHPExcel->getActiveSheet()->setCellValue("E".$i, (string)$this->find_period($k['id']));
//                $objPHPExcel->getActiveSheet()->getStyle("E".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
//                $objPHPExcel->getActiveSheet()->getStyle('E'.$i)->getAlignment()->setWrapText(true);
//                $objPHPExcel->getActiveSheet()->getStyle('E'.$i)->applyFromArray($styleThinBlackBorderOutline);
//                $i = $i + 1;
//            }
//        }
        if ($page == 0&& $size == 0){
            exit(json_encode([
                'code' => 400,
                'msg' => 'page和size不能同时为0'
            ]));
        }
        $start = ($page-1)*$size;
        if ($data['term'] != 'all'){
            $t = str_replace('-','',$data['term']);
            $info = $user->limit($start,$size)->field('id,username,major,number,period')
                ->order([
                    'number' => 'asc'
                ])
                ->select();
            if (!$info){
                exit(json_encode([
                    'code' => 400,
                    'msg' => '未查到用户'
                ]));
            }
            foreach ($info as $k){
                $objPHPExcel->getActiveSheet()->setCellValue("A".$i, $k['username']);
                $objPHPExcel->getActiveSheet()->getStyle("A".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('A'.$i)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("B".$i, $k['number']);
                $objPHPExcel->getActiveSheet()->getStyle("B".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('B'.$i)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("C".$i, $k['major']);
                $objPHPExcel->getActiveSheet()->getStyle("C".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('C'.$i)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("D".$i, (string)$this->find_period($k['id'],$t));
                $objPHPExcel->getActiveSheet()->getStyle("D".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('D'.$i)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("E".$i, (string)$this->find_period($k['id']));
                $objPHPExcel->getActiveSheet()->getStyle("E".$i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('E'.$i)->getAlignment()->setWrapText(true);
                $objPHPExcel->getActiveSheet()->getStyle('E'.$i)->applyFromArray($styleThinBlackBorderOutline);
                $i = $i + 1;
            }
        }
        //设置格子大小
        $objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension()->setWidth(25);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $_savePath = COMMON_PATH.'/static/show_student.xlsx';
        $objWriter->save($_savePath);

        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/show_student.xlsx'
        ]);
    }

    public function create_search($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $meeting = new Meeting();
        $user = new User();
        $member = new Meeting_member();
        if (!array_key_exists('search_key',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无搜索关键词！'
            ]));
        }
        if (!array_key_exists('term',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！'
            ]));
        }
        //验证
        (new Search())->goToCheck($data);
        $search_key = filter($data['search_key']);
        $info = $user->where([
            'number|username' => $search_key
        ])->field('id,username,major,number')->select();
        if (!$info){
            exit(json_encode([
                'code' => 400,
                'msg' => '查无此人！'
            ]));
        }
        $i = 0;
        $result = [];
        $time = (int)time();
        $count = count($info);
        if ($count>500){
            exit(json_encode([
                'code' => 400,
                'msg' => '查到的数据过多，请输入更精准的搜索词！'
            ]));
        }
        foreach ($info as $v){
            $uid = $v['id'];

            if ($data['term'] == 'all'){
                $check = $member
                    ->order(['begin' => 'desc'])
                    ->where([
                        'user_id' => $uid
                    ])->where('end_time','<',$time)->field('meeting_id,attend,sign_out')->select();
                if (!$check){
                    if ($i+1 == $count){
                        exit(json_encode([
                            'code' => 557,
                            'msg' => '该用户没有已结束的会议'
                        ]));
                    }else{
                        continue;
                    }
                }
            }else{
                $t = str_replace('-','',$data['term']);
                $check = $member->where([
                    'user_id' => $uid,
                    'term' => $t
                ])
                    ->order(['begin' => 'desc'])
                    ->where('end_time','<',$time)->field('meeting_id,attend,sign_out')->select();
                if (!$check){
                    if ($i+1 == $count){
                        exit(json_encode([
                            'code' => 557,
                            'msg' => '该用户在该学期没有已结束的会议'
                        ]));
                    }else{
                        continue;
                    }
                }
            }

            $j = 0;
            foreach ($check as $k){
                $re = $meeting->where([
                    'id' => $k['meeting_id']
                ])->field('name,date1,date2,date3,position')->find();
                $attend = (int)$k['attend'];
                $sign_out = (int)$k['sign_out'];
                $result[$i]['meeting'][$j]['meeting_id'] = $k['meeting_id'];
                $result[$i]['meeting'][$j]['meeting_name'] = $re['name'];
                $result[$i]['meeting'][$j]['meeting_date'] = $re['date1'].'/'.$re['date2'].'/'.$re['date3'];
                $result[$i]['meeting'][$j]['meeting_position'] = $re['position'];
                if ($attend == 1 && $sign_out == 1){
                    $result[$i]['meeting'][$j]['status'] = '出席';
                }elseif ($attend == 1 && $sign_out == 0){
                    $result[$i]['meeting'][$j]['status'] = '早退';
                }elseif ($attend == 0 && $sign_out == 1){
                    $result[$i]['meeting'][$j]['status'] = '迟到';
                }else{
                    $result[$i]['meeting'][$j]['status'] = '缺席';
                }
                $j++;
            }
            $result[$i]['user_id'] = $uid;
            $result[$i]['username'] = $v['username'];
            $result[$i]['major'] = $v['major'];
            $result[$i]['number'] = $v['number'];

            $i++;
        }

        //用来记录表格的行号
        $k = 3;

        vendor('PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod);
        $styleThinBlackBorderOutline = array(
            'borders' => array (
                'outline' => array (
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,  //设置border样式
                    'color' => array ('argb' => 'FF000000'),     //设置border颜色
                ),
            ),
        );

        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setTitle("出勤查看");

        $objPHPExcel->getActiveSheet()->mergeCells("A1:G1");
        $objPHPExcel->getActiveSheet()->setCellValue("A1", "出勤查看");
        $objPHPExcel->getActiveSheet()->getStyle("A1:F1")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle( 'A1')->getFont()->setSize(14);
        $objPHPExcel->getActiveSheet()->getStyle("A1")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

        $objPHPExcel->getActiveSheet()->setCellValue("A2", "学号");
        $objPHPExcel->getActiveSheet()->getStyle("A2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("B2", "姓名");
        $objPHPExcel->getActiveSheet()->getStyle("B2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('B2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("C2", "学院");
        $objPHPExcel->getActiveSheet()->getStyle("C2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('C2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("D2", "讲座名称");
        $objPHPExcel->getActiveSheet()->getStyle("D2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('D2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("E2", "日期");
        $objPHPExcel->getActiveSheet()->getStyle("E2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('E2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("F2", "地点");
        $objPHPExcel->getActiveSheet()->getStyle("F2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('F2')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('F2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->setCellValue("G2", "出勤情况");
        $objPHPExcel->getActiveSheet()->getStyle("G2")->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('G2')->getAlignment()->setWrapText(true);
        $objPHPExcel->getActiveSheet()->getStyle('G2')->applyFromArray($styleThinBlackBorderOutline);
        $objPHPExcel->getActiveSheet()->getStyle("A2:G2")->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(25);

        foreach ($result as $v){
            foreach ($v['meeting'] as $vv){
                $objPHPExcel->getActiveSheet()->setCellValue("A".$k, $v['number']);
                $objPHPExcel->getActiveSheet()->getStyle("A".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('A'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("B".$k, $v['username']);
                $objPHPExcel->getActiveSheet()->getStyle("B".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('B'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("C".$k, $v['major']);
                $objPHPExcel->getActiveSheet()->getStyle("C".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('C'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("D".$k, $vv['meeting_name']);
                $objPHPExcel->getActiveSheet()->getStyle("D".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('D'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("E".$k, $vv['meeting_date']);
                $objPHPExcel->getActiveSheet()->getStyle("E".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->getAlignment()->setWrapText(true);
                $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("F".$k, $vv['meeting_position']);
                $objPHPExcel->getActiveSheet()->getStyle("F".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('F'.$k)->getAlignment()->setWrapText(true);
                $objPHPExcel->getActiveSheet()->getStyle('F'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("G".$k, $vv['status']);
                $objPHPExcel->getActiveSheet()->getStyle("G".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('G'.$k)->getAlignment()->setWrapText(true);
                $objPHPExcel->getActiveSheet()->getStyle('G'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $k = $k + 1;
            }
        }

        //设置格子大小
        $objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension()->setWidth(25);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $_savePath = COMMON_PATH.'/static/create_search.xlsx';
        $objWriter->save($_savePath);

        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/create_search.xlsx'
        ]);
    }

    //发布审核会议
    public function apply_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }

        if (!array_key_exists('name',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议名称！'
            ]));
        }
        if (!array_key_exists('date1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！(第一空)！'
            ]));
        }
        if (!array_key_exists('date2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！（第二空）'
            ]));
        }
        if (!array_key_exists('date3',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！（第三空）'
            ]));
        }
        if (!array_key_exists('time1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无时间！(第一空)'
            ]));
        }
        if (!array_key_exists('time2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无时间！(第二空)'
            ]));
        }
        if (!array_key_exists('position',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无地点！'
            ]));
        }
        if (!array_key_exists('term1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第一空)'
            ]));
        }
        if (!array_key_exists('term2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第二空)'
            ]));
        }
        if (!array_key_exists('term3',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第三空)'
            ]));
        }
        $this->have_key_validate([
            'meeting_type' => '无会议类型！',
            'department' => '无开课部门！',
            'enter_begin' => '无报名开始时间！',
            'enter_end' => '无报名结束时间！',
            'description' => '无内容简介！',
            'end_time' => '无会议结束时间！',
            'member' => '无会议学期和年级！'
        ],$data);
        (new SetMeeting())->goToCheck($data);
        //过滤
        $name = filter($data['name']);
        $date1 = filter($data['date1']);
        $date2 = filter($data['date2']);
        $date3 = filter($data['date3']);
        $time1 = filter($data['time1']);
        $time2 = filter($data['time2']);
        $position= filter($data['position']);
        $term1= filter($data['term1']);
        $term2= filter($data['term2']);
        $term3= filter($data['term3']);
        $type = $data['meeting_type'];
        $department = $data['department'];
        $enter_begin = $data['enter_begin'];
        $enter_begin[10] = ' ';
        $enter_begin[13] = ':';
        $enter_end = $data['enter_end'];
        $enter_end[10] = ' ';
        $enter_end[13] = ':';
        $description = $data['description'];
        $period = $data['period'];
        $end_time = $data['end_time'];
        $re = $data['end_time'];
        $member = $data['member'];

        if (!((int)$date1<=(int)$term2&&(int)$date1>=(int)$term1)){
            exit(json_encode([
                'code' => 400,
                'msg' => '输入日期中的年份未在输入的学期之间，请检查后重新输入！'
            ]));
        }
        $end_time = $date1.'-'.$date2.'-'.$date3.' '.$end_time.':00';
        $end_time = (int)strtotime($end_time)+1200;
        $begin_time = $date1.'-'.$date2.'-'.$date3.' '.$time1.':'.$time2.':00';
        $begin_time = (int)strtotime($begin_time)-1200;
        $enter_begin = strtotime($enter_begin);
        $enter_end = strtotime($enter_end);

        //上传图片
        $url = '';
        $photo = Request::instance()->file('photo');
        Db::startTrans();
        if (!$photo){
            $result = Db::table('check_meeting')->insertGetId([
                'name' => $name,
                'date1' => $date1,
                'date2' => $date2,
                'date3' => $date3,
                'time1' => $time1,
                'time2' => $time2,
                'position' => $position,
                'term1' => $term1,
                'term2' => $term2,
                'term3' => $term3,
                'term' => (int)($term1.$term2.$term3),
                'begin' => $begin_time,
                'end_time' => $end_time,
                'type' => $type,
                'department' => $department,
                'enter_begin' => $enter_begin,
                'description' => $description,
                'enter_end' => $enter_end,
                'period' => $period,
                're_end_time' => $re,
                'publish_id' => $id
            ]);
            if (!$result){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '上传错误'
                ]));
            }
        }else{
            //给定一个目录
            $info = $photo->validate(['ext'=>'jpg,jpeg,png,bmp,gif'])->move('upload');
            if ($info && $info->getPathname()) {
                $url .= $info->getPathname();
            } else {
                exit(json_encode([
                    'code' => 400,
                    'msg' => '请检验上传图片格式（jpg,jpeg,png,bmp,gif）！'
                ]));
            }
            $result = Db::table('check_meeting')->insertGetId([
                'name' => $name,
                'date1' => $date1,
                'date2' => $date2,
                'date3' => $date3,
                'time1' => $time1,
                'time2' => $time2,
                'position' => $position,
                'term1' => $term1,
                'term2' => $term2,
                'term3' => $term3,
                'term' => (int)($term1.$term2.$term3),
                'begin' => $begin_time,
                'end_time' => $end_time,
                'photo' => $url,
                'type' => $type,
                'department' => $department,
                'enter_begin' => $enter_begin,
                'description' => $description,
                'enter_end' => $enter_end,
                'period' => $period,
                're_end_time' => $re,
                'publish_id' => $id
            ]);
            if (!$result){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '上传错误'
                ]));
            }
        }

        foreach ($member as $k => $item){
            $major = $k;
            foreach ($item as $i){
                $i = (array)$i;
                $in = Db::table('check_major')
                    ->insert([
                        'meeting_id' => $result,
                        'major' => $major,
                        'year' => $i['year']
                    ]);
                if (!$in){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '发布失败！'
                    ]));
                }
            }
        }

        Db::commit();
        return json([
            'code' => 200,
            'msg' => $result
        ]);
    }

    //添加申请会议可选学院
    public function add_checked_major($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }
        $this->have_key_validate([
            'meeting_id' => '无会议标识！',
            'major_info' => '无学院信息！'
        ],$data);
        $meeting_info = $data['major_info'];
        $tmp['id'] = $data['meeting_id'];
        (new IDMustBeNumber())->goToCheck($tmp);
        $meeting_id = $data['meeting_id'];
        Db::startTrans();
        foreach ($meeting_info as $k => $v){
            $major = $k;
            foreach ($v as $item){
                $result = Db::table('check_major')
                    ->insert([
                        'meeting_id' => $meeting_id,
                        'major' => $major,
                        'year' => $item
                    ]);
                if (!$result){
                    Db::rollback();
                    exit([
                        'code' => 504,
                        'msg' => '插入错误！'
                    ]);
                }
            }
            Db::commit();
        }

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //显示一个列表的审核会议
    public function show_check_all_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }

        if (!array_key_exists('page',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页号！'
            ]));
        }
        if (!array_key_exists('size',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无页大小！'
            ]));
        }
        if (!array_key_exists('major',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无排序规则！'
            ]));
        }
        //验证
        (new ShowCheckMeeting())->goToCheck($data);

        //page从1开始
        //limit($page*$size-1,$size)   0除外
        $page = (int)$data['page'];
        $size = (int)$data['size'];
        if ($page<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '页号最小为0！'
            ]));
        }
        if ($size<0){
            exit(json_encode([
                'code' => 400,
                'msg' => '页大小最小为0！'
            ]));
        }
        if ($page*$size == 0 && $page*$size != 0){
            exit(json_encode([
                'code' => 400,
                'msg' => '页号和页大小为零时只有同时为零！'
            ]));
        }

        $major = $data['major'];

        //查询
        // 1  2  3
        // 0  2  5
        $meeting = new Check_meeting();
        if ($major == 'all'){
            if ($page == 0 && $size == 0){
                $info = $meeting
                    ->order([
                        'term' => 'desc'
                    ])
                    ->where(['state' => 0])
                    ->select();
            }else{
                $start = ($page-1)*$size;
                $info = $meeting->limit($start,$size)
                    ->order([
                        'term' => 'desc'
                    ])
                    ->where(['state' => 0])
                    ->select();
            }
            $msg = [];
            foreach ($info as $k => $v){
                $t = $v['term1'].'-'.$v['term2'].'-'.$v['term3'];
                $major = $v['department'];
                if (!array_key_exists($major,$msg)) $i = 0;
                else $i = count($msg[$major]);
                $msg[$major][$i]['meeting_id'] = $v['id'];
                $msg[$major][$i]['name'] = $v['name'];
                $msg[$major][$i]['time'] = $v['date1'].'/'.$v['date2'].'/'.$v['date3'];
                $msg[$major][$i]['clock'] = $v['time1'].':'.$v['time2'].'-'.$v['re_end_time'];
                $msg[$major][$i]['position'] = $v['position'];
                $msg[$major][$i]['period'] = $v['period'];
                $msg[$major][$i]['term'] = $t;
                $msg[$major][$i]['photo'] = config('setting.image_root').$v['photo'];
            }
        }else{
            if ($page == 0 && $size == 0){
                $info = $meeting
                    ->where([
                        'department' => $major,
                        'state' => 0
                    ])
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }else{
                $start = ($page-1)*$size;
                $info = $meeting->limit($start,$size)
                    ->where([
                        'department' => $major,
                        'state' => 0
                    ])
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }
            if (!$info){
                exit(json_encode([
                    'code' => 400,
                    'msg' => '输入的学期有误！查询失败！'
                ]));
            }
            //新开一个数组存放返回的东西
            $msg = [];
            foreach ($info as $k => $v){
                $t = $v['term1'].'-'.$v['term2'].'-'.$v['term3'];
                $major = $v['department'];
                if (!array_key_exists($major,$msg)) $i = 0;
                else $i = count($msg[$major]);
                $msg[$major][$i]['meeting_id'] = $v['id'];
                $msg[$major][$i]['name'] = $v['name'];
                $msg[$major][$i]['time'] = $v['date1'].'/'.$v['date2'].'/'.$v['date3'];
                $msg[$major][$i]['clock'] = $v['time1'].':'.$v['time2'].'-'.$v['re_end_time'];
                $msg[$major][$i]['position'] = $v['position'];
                $msg[$major][$i]['period'] = $v['period'];
                $msg[$major][$i]['term'] = $t;
                $msg[$major][$i]['photo'] = config('setting.image_root').$v['photo'];
            }
        }

        return json([
            'code' => 200,
            'msg' => $msg
        ]);
    }


    public function show_check_term(){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }

        $meeting = new Check_meeting();
        //查学期
        $result = $meeting->distinct(true)->field('department')->select();
        $i = 0;
        $arr = [];
        foreach ($result as $v){
            $arr[$i] = $v['department'];
            $i++;
        }
        return json([
            'code' => 200,
            'msg' => $arr
        ]);
    }

    //显示单个会议
    public function show_single_check_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 30){
            exit(json_encode([
                'code' => 400,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);

        $id = $data['id'];

        $meeting = new Check_meeting();
        //查询
        $result = $meeting->where([
            'id' => $id
        ])->find();
        if (!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => '或许是id不正确，查找出错！'
            ]));
        }
        $name = $result['name'];
        $date1 = $result['date1'];
        $date2 = $result['date2'];
        $date3 = $result['date3'];
        $time1 = $result['time1'];
        $time2 = $result['time2'];
        $position= $result['position'];
        $term1= $result['term1'];
        $term2= $result['term2'];
        $term3= $result['term3'];
        $department = $result['department'];
        $description = $result['description'];
        $type = $result['type'];
        $period = $result['period'];
        $photo = $result['photo'];
        $re_end_time = $result['re_end_time'];
        $end_time1 = $re_end_time[0].$re_end_time[1];
        $end_time2 = $re_end_time[3].$re_end_time[4];
        $enter_stare = date('Y-m-d-H:i',$result['enter_begin']);
        $enter_end = date('Y-m-d-H:i',$result['enter_end']);
        $publish_id = $result['publish_id'];

        $teach = Db::table('super')
            ->where([
                'id' => $publish_id
            ])->find();
        $teacher = $teach['nickname'];

        //截取当前状态
        if ($result['state'] == 0){
            $state = '待审核';
        }else{
            $state = '未通过';
        }


        $member = [];
        $info = Db::table('check_major')->where([
            'meeting_id' => $id
        ])->select();
        if ($info){
            foreach ($info as $v){
                if (array_key_exists($v['major'],$member)){
                    $m = count($member[$v['major']]);
                }else{
                    $m = 0;
                }
                $member[$v['major']][$m] = $v['year'];
            }
        }

        return json([
            'code' => 200,
            'msg' => [
                'meeting_id' => $id,
                'name' => $name,
                'date1' => $date1,
                'date2' => $date2,
                'date3' => $date3,
                'time1' => $time1,
                'time2' => $time2,
                'position' => $position,
                'term1' => $term1,
                'term2' => $term2,
                'term3' => $term3,
                'state' => $state,
                'end_time1' => $end_time1,
                'end_time2' => $end_time2,
                'type' => $type,
                'description' => $description,
                'department' => $department,
                'period' => $period,
                'photo' => config('setting.image_root').$photo,
                'member' => $member,
                'enter_begin' => $enter_stare,
                'enter_end' => $enter_end,
                'teacher' => $teacher
            ]
        ]);
    }

    //同意审核
    public function agree_apply($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 32){
            exit(json_encode([
                'code' => 400,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入审核会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);

        $meeting_id = $data['id'];
        $meeting = new Check_meeting();
        //查询
        $result = $meeting->where([
            'id' => $meeting_id
        ])->find();
        if (!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => '或许是id不正确，查找出错！'
            ]));
        }

        $major_result = Db::table('check_major')
            ->where([
                'meeting_id' => $meeting_id
            ])->select();

        Db::startTrans();

        //插入正式会议的表
        $insert = Db::table('meeting')->insertGetId([
            'name' => $result['name'],
            'date1' => $result['date1'],
            'date2' => $result['date2'],
            'date3' => $result['date3'],
            'time1' => $result['time1'],
            'time2' => $result['time2'],
            'position' => $result['position'],
            'term1' => $result['term1'],
            'term2' => $result['term2'],
            'term3' => $result['term3'],
            'term' => $result['term'],
            'begin' => $result['begin'],
            'end_time' => $result['end_time'],
            'photo' => $result['photo'],
            'type' => $result['type'],
            'department' => $result['department'],
            'enter_begin' => $result['enter_begin'],
            'description' => $result['description'],
            'enter_end' => $result['enter_end'],
            'period' => $result['period'],
            're_end_time' => $result['re_end_time'],
            'publish_id' => $result['publish_id']
        ]);
        if (!$insert){
            Db::rollback();
            exit(json_encode([
                'code' => 503,
                'msg' => '更新出错！'
            ]));
        }

        foreach ($major_result as $item){
            $major = Db::table('meeting_major')
                ->insert([
                    'meeting_id' => $insert,
                    'major' => $item['major'],
                    'year' => $item['year'],
                ]);
            if (!$major){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '更新出错！'
                ]));
            }
        }
        if ($major_result){
            $major_delete = Db::table('check_major')->where(['meeting_id' => $meeting_id])->delete();
            if (!$major_delete){
                Db::rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '更新出错！'
                ]));
            }
        }
        $meeting_delete = Db::table('check_meeting')->where(['id' => $meeting_id])->delete();
        if (!$meeting_delete){
            Db::rollback();
            exit(json_encode([
                'code' => 503,
                'msg' => '更新出错！'
            ]));
        }
        Db::commit();

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //不同意审核
    public function disagree_apply($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 32){
            exit(json_encode([
                'code' => 400,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入审核会议标识！'
            ]));
        }
        (new IDMustBeNumber())->goToCheck($data);

        $meeting_id = $data['id'];
        $meeting = new Check_meeting();
        //查询
        $result = $meeting->where([
            'id' => $meeting_id
        ])->find();
        if (!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => '或许是id不正确，查找出错！'
            ]));
        }
        if ($result['state'] == 0){
            $update_result = Db::table('check_meeting')->where(['id' => $meeting_id])->update(['state' => 1]);
            if (!$update_result) {
                exit(json_encode([
                    'code' => 503,
                    'msg' => '更新出错！'
                ]));
            }
        }

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //修改审核会议
    public function change_check_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ((int)$secret < 31){
            exit(json_encode([
                'code' => 403,
                'msg' => '权限不足！'
            ]));
        }

        if (!array_key_exists('meeting_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议标识！'
            ]));
        }

        if (!array_key_exists('name',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议名称！'
            ]));
        }
        if (!array_key_exists('date1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！(第一空)！'
            ]));
        }
        if (!array_key_exists('date2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！（第二空）'
            ]));
        }
        if (!array_key_exists('date3',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无日期！（第三空）'
            ]));
        }
        if (!array_key_exists('time1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无时间！(第一空)'
            ]));
        }
        if (!array_key_exists('time2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无时间！(第二空)'
            ]));
        }
        if (!array_key_exists('position',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无地点！'
            ]));
        }
        if (!array_key_exists('term1',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第一空)'
            ]));
        }
        if (!array_key_exists('term2',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第二空)'
            ]));
        }
        if (!array_key_exists('term3',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无学期！(第三空)'
            ]));
        }
        if(!is_numeric($data['meeting_id'])){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议标识非数字'
            ]));
        }
        $this->have_key_validate([
            'meeting_type' => '无会议类型！',
            'department' => '无开课部门！',
            'enter_begin' => '无报名开始时间！',
            'enter_end' => '无报名结束时间！',
            'description' => '无内容简介！',
            'end_time' => '无会议结束时间！',
            'member' => '无可选学院年级！'
        ],$data);
        (new SetMeeting())->goToCheck($data);
        //过滤
        $name = filter($data['name']);
        $date1 = filter($data['date1']);
        $date2 = filter($data['date2']);
        $date3 = filter($data['date3']);
        $time1 = filter($data['time1']);
        $time2 = filter($data['time2']);
        $position= filter($data['position']);
        $term1= filter($data['term1']);
        $term2= filter($data['term2']);
        $term3= filter($data['term3']);
        $type = $data['meeting_type'];
        $department = $data['department'];
        $enter_begin = $data['enter_begin'];
        $enter_begin[10] = ' ';
        $enter_begin[13] = ':';
        $enter_end = $data['enter_end'];
        $enter_end[10] = ' ';
        $enter_end[13] = ':';
        $description = $data['description'];
        $period = $data['period'];
        $end_time = $data['end_time'];
        $re = $data['end_time'];
        $member = $data['member'];

        if (!((int)$date1<=(int)$term2&&(int)$date1>=(int)$term1)){
            exit(json_encode([
                'code' => 400,
                'msg' => '输入日期中的年份未在输入的学期之间，请检查后重新输入！'
            ]));
        }
        $end_time = $date1.'-'.$date2.'-'.$date3.' '.$end_time.':00';
        $end_time = (int)strtotime($end_time)+1200;
        $begin_time = $date1.'-'.$date2.'-'.$date3.' '.$time1.':'.$time2.':00';
        $begin_time = (int)strtotime($begin_time)-1200;
        $enter_begin = strtotime($enter_begin);
        $enter_end = strtotime($enter_end);
        //入库
        $meeting = new Check_meeting();
        $check = $meeting->where([
            'id' => $data['meeting_id']
        ])->find();
        if(!$check){
            exit(json_encode([
                'code' => 400,
                'msg' => '该会议不存在'
            ]));
        }

        //上传图片
        $url = '';
        $photo = Request::instance()->file('photo');
        Db::startTrans();
        if ($photo){
            //给定一个目录
            $info = $photo->validate(['ext'=>'jpg,jpeg,png,bmp,gif'])->move('upload');
            if ($info && $info->getPathname()) {
                $url .= $info->getPathname();
            } else {
                exit(json_encode([
                    'code' => 400,
                    'msg' => '请检验上传图片格式（jpg,jpeg,png,bmp,gif）！'
                ]));
            }
            if ($name==$check['name']&&$date1==$check['date1']&&$date2==$check['date2']&&$date3==$check['date3']&&$time1==$check['time1']
                &&$time2==$check['time2']&&$position==$check['position']&&$term1==$check['term1']&&$term2==$check['term2']&&$term3==$check['term3']
                &&$begin_time==$check['begin']&&$end_time==$check['end_time']&&$check['type'] == $type&&$check['department'] == $department&&$check['enter_begin'] = $enter_begin
                    &&$enter_end ==$check['enter_end']&&$check['description']==$description&&$check['period']==$period&&$check['photo']==$url){
            }else{
                $result = Db::table('check_meeting')
                    ->where([
                        'id' => $data['meeting_id']
                    ])
                    ->update([
                        'name' => $name,
                        'date1' => $date1,
                        'date2' => $date2,
                        'date3' => $date3,
                        'time1' => $time1,
                        'time2' => $time2,
                        'position' => $position,
                        'term1' => $term1,
                        'term2' => $term2,
                        'term3' => $term3,
                        'term' => (int)($term1.$term2.$term3),
                        'begin' => $begin_time,
                        'end_time' => $end_time,
                        'photo' => $url,
                        'type' => $type,
                        'department' => $department,
                        'enter_begin' => $enter_begin,
                        'description' => $description,
                        'enter_end' => $enter_end,
                        'period' => $period,
                        're_end_time' => $re,
                        'state' => 0
                    ]);
                if (!$result){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '上传错误'
                    ]));
                }
            }
        }else{
            if ($name==$check['name']&&$date1==$check['date1']&&$date2==$check['date2']&&$date3==$check['date3']&&$time1==$check['time1']
                &&$time2==$check['time2']&&$position==$check['position']&&$term1==$check['term1']&&$term2==$check['term2']&&$term3==$check['term3']
                &&$begin_time==$check['begin']&&$end_time==$check['end_time']&&$check['type'] == $type&&$check['department'] == $department&&$check['enter_begin'] = $enter_begin
                    &&$enter_end ==$check['enter_end']&&$check['description']==$description&&$check['period']==$period){
            }else{
                $result = Db::table('check_meeting')
                    ->where([
                        'id' => $data['meeting_id']
                    ])
                    ->update([
                        'name' => $name,
                        'date1' => $date1,
                        'date2' => $date2,
                        'date3' => $date3,
                        'time1' => $time1,
                        'time2' => $time2,
                        'position' => $position,
                        'term1' => $term1,
                        'term2' => $term2,
                        'term3' => $term3,
                        'term' => (int)($term1.$term2.$term3),
                        'begin' => $begin_time,
                        'end_time' => $end_time,
                        'type' => $type,
                        'department' => $department,
                        'enter_begin' => $enter_begin,
                        'description' => $description,
                        'enter_end' => $enter_end,
                        'period' => $period,
                        're_end_time' => $re,
                        'state' => 0
                    ]);
                if (!$result){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '上传错误'
                    ]));
                }
            }
        }

        //修改可选学院
        $meeting_major = Db::table('check_major')->where([
            'meeting_id' => $data['meeting_id']
        ])->delete();

        if (!$meeting_major){
            Db::rollback();
            exit(json_encode([
                'code' => 503,
                'msg' => '更新学院出错'
            ]));
        }

        foreach ($member as $k => $item){
            $major = $k;
            foreach ($item as $i){
                $i = (array)$i;
                $in = Db::table('check_major')
                    ->insert([
                        'meeting_id' => $data['meeting_id'],
                        'major' => $major,
                        'year' => $i['year']
                    ]);
                if (!$in){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '发布失败！'
                    ]));
                }
            }
        }

        Db::commit();

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //删除带审核会议
    public function delete_check_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret < 31){
            exit(json_encode([
                'code' => 400,
                'msg' => '权限不足！'
            ]));
        }
        if (!array_key_exists('meeting_id',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无会议标识！'
            ]));
        }
        $rule = [
            'meeting_id'  => 'require|number',
        ];
        $msg = [
            'meeting_id.require' => '会议标识不能为空',
            'meeting_id.number'   => '会议标识必须是数字',
        ];
        $validate = new Validate($rule,$msg);
        $result   = $validate->check($data);
        if(!$result){
            exit(json_encode([
                'code' => 400,
                'msg' => $validate->getError()
            ]));
        }
        //查看这个会议有没有成员
        $check2 = Db::table('check_major')->where([
            'meeting_id' => $data['meeting_id']
        ])->find();

        $check4 = Db::table('check_meeting')
            ->where([
                'id' => $data['meeting_id']
            ])->find();

        if(!$check4){
            exit(json_encode([
                'code' => 400,
                'msg' => '未找到该会议'
            ]));
        }
        $publish_id = $check4['publish_id'];
        if ($secret == 31){
            if ($publish_id != $id){
                exit(json_encode([
                    'code' => 400,
                    'msg' => '权限不足！不能删除别人发布的会议'
                ]));
            }
        }


        //开启事务
        Db::startTrans();
        if ($check4){
            $rr = Db::table('check_meeting')->where([
                'id' => $data['meeting_id']
            ])->delete();
            if (!$rr){
                Db::rollback();
                exit(json_encode([
                    'code' => 504,
                    'msg' => '更新出错，请重试！1'
                ]));
            }
        }

        if ($check2){
            $rr = Db::table('check_major')->where([
                'meeting_id' => $data['meeting_id']
            ])->delete();
            if (!$rr){
                Db::rollback();
                exit(json_encode([
                    'code' => 504,
                    'msg' => '更新出错，请重试！3'
                ]));
            }
        }
        Db::commit();
        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    //删除用户
    public function delete_user($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret < 31){
            exit(json_encode([
                'code' => 400,
                'msg' => '权限不足！'
            ]));
        }

        foreach ($data as $item){
            $check1 = Db::table('user')->where([
                'id' => $item['user_id']
            ])->find();
            if (!$check1){
                exit(json_encode([
                    'code' => 400,
                    'msg' => '未找到该用户！'
                ]));
            }

            $check2 = Db::table('sign_list')
                ->where([
                    'user_id' => $item['user_id']
                ])->find();

            $check3 = Db::table('meeting_member')
                ->where([
                    'user_id' => $item['user_id']
                ])->find();

            $check4 = Db::table('period')
                ->where([
                    'user_id' => $item['user_id']
                ])->find();


            //开启事务
            Db::startTrans();
            if ($check1){
                $rr = Db::table('user')->where([
                    'id' => $item['user_id']
                ])->delete();
                if (!$rr){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 504,
                        'msg' => '更新出错，请重试！1'
                    ]));
                }
            }

            if ($check2){
                $rr = Db::table('sign_list')->where([
                    'user_id' => $item['user_id']
                ])->delete();
                if (!$rr){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 504,
                        'msg' => '更新出错，请重试！2'
                    ]));
                }
            }

            if ($check3){
                $rr = Db::table('meeting_member')->where([
                    'user_id' => $item['user_id']
                ])->delete();
                if (!$rr){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 504,
                        'msg' => '更新出错，请重试！4'
                    ]));
                }
            }
            if ($check4){
                $rr = Db::table('period')->where([
                    'user_id' => $item['user_id']
                ])->delete();
                if (!$rr){
                    Db::rollback();
                    exit(json_encode([
                        'code' => 504,
                        'msg' => '更新出错，请重试！4'
                    ]));
                }
            }
            Db::commit();
        }

        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

    public function return_grade(){
            $TokenModel = new Token();
            $id = $TokenModel->get_id();
            $secret = $TokenModel->checkUser();
            if ((int)$secret < 30){
                exit(json_encode([
                    'code' => 403,
                    'msg' => '权限不足！'
                ]));
            }

            //查学期
            $result = Db::table('user')->field('number')->select();
            $i = 0;
            $arr = [];
            foreach ($result as $v){
                $arr[$i] = substr($v['number'],0,4);
                $i++;
            }
            $a = array_unique($arr);
            $i = 0;
            $b = [];
            foreach ($a as $item) {
                $b[$i] = $item;
                $i++;
            }
            return json([
                'code' => 200,
                'msg' => $b
            ]);
    }

    public function show_attend(){

    }
}