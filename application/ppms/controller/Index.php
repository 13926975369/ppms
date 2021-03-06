<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/1
 * Time: 22:26
 */

namespace app\ppms\controller;
use app\ppms\exception\BaseException;
use app\ppms\exception\UpdateException;
use app\ppms\exception\UserException;
use app\ppms\model\AdminToken;
use app\ppms\model\Super;
use app\ppms\model\Token;
use app\ppms\model\User;
use app\ppms\model\UserToken;
use app\ppms\validate\LoginValidate;
use think\Cache;
use think\Collection;
use think\Db;
use think\Request;
use think\Validate;

class Index extends Collection
{
    /**
     *  $token
     *  $type
     *  $data
     */

    public function index(){
        //跨域
        header('content-type:application:json;charset=utf8');
        header('Access-Control-Allow-Origin:*');
        header('Access-Control-Allow-Methods:POST');
        header('Access-Control-Allow-Headers:x-requested-with,content-type');

        $post = input('post.');


        if (!$post){
            exit(json_encode([
                'code' => 400,
                'msg' => '未传入任何参数！'
            ]));
        }
        if (!array_key_exists('token',$post)){
            exit(json_encode([
                'code' => 400,
                'msg' => '第一项参数缺失，禁止请求！'
            ]));
        }
        $token = $post['token'];
        if (!array_key_exists('type',$post)){
            exit(json_encode([
                'code' => 400,
                'msg' => '第二项参数缺失，禁止请求！'
            ]));
        }
        $type = $post['type'];
        if (!array_key_exists('data',$post)){
            exit(json_encode([
                'code' => 400,
                'msg' => '第三项参数缺失，禁止请求！'
            ]));
        }
        $data = $post['data'];

        //实例化
        $user = new User();
        $user_token = new UserToken();
        $admin_token = new AdminToken();
        $TokenModel = new Token();
        $Super = new Super();

        //判断类型
        if ($type=='A001'){
            //登录
            //验证是否有传参
            $user->login_exist_validate($token,$data);
            //验证传参的格式是否正确(白名单)
            (new LoginValidate())->goToCheck($data);
            //校验
            $username = $data['username'];
            $is = $user->where([
                'number' => $username
            ])->field('id')->find();
            if (!$is){
                throw new UserException([
                    'msg' => '用户不存在！'
                ]);
            }
            $password = $data['password'];
            $is_exist = $user->where([
                'number' => $username,
                'password' => md5(config('setting.user_salt').$password)
            ])->field('id')->find();
            if (!$is_exist){
                throw new UserException([
                    'code' => 405,
                    'msg' => '密码错误！'
                ]);
            }
            //查id
            $id = $is_exist['id'];
            //获得token
            $tk = $TokenModel->get_token($data['code'],$id);

            //查一下用户是否填入邮箱，没有的话，填入邮箱
            $email = Db::table('user')
                ->where(['id' => $id])
                ->find();
            //0代表还没绑定邮箱
            $state = 0;
            if ($email['email'] != null){
                //1代表绑定邮箱了
                $state = 1;
            }

            return json_encode([
                'code' => 200,
                'msg' => $tk,
                'state' => $state
            ]);
        }elseif ($type == 'A002'){
            //修改密码
            //通过token获取id并且判断token是否有效
            $uid = $TokenModel->get_id();
            //检查这个用户是否存在和验证传参的格式是否正确(白名单)
            $user->change_psw_validate($data);
            $is_exist = $user->where([
                'id' => $uid
            ])->field('password')->find();
            if (!$is_exist){
                throw new UserException([
                    'msg' => '用户不存在！'
                ]);
            }
            if (md5(config('setting.user_salt').$data['old_password']) != $is_exist['password']){
                throw new BaseException([
                    'msg' => '旧密码错误'
                ]);
            }
            //判断两次是否一致
            $psw = $data['password'];
            $psw_check = $data['password_check'];
            if ($psw != $psw_check){
                throw new BaseException([
                    'msg' => '输入的两次密码不一致！'
                ]);
            }
            //检验新密码和原密码是否一致
            $old_psw = $is_exist['password'];
            if (md5(config('setting.user_salt').$psw)==$old_psw){
                throw new UpdateException([
                    'msg' => '新密码不可与旧密码一样！'
                ]);
            }
            //修改
            $result = $user->where([
                'id' => $uid
            ])->update([
                'password' => md5(config('setting.user_salt').$psw)
            ]);
            if (!$result){
                throw new UpdateException();
            }
            return json_encode([
                'code' => 200,
                'msg' => '修改成功！'
            ]);
        }elseif ($type == 'A003'){
            //退出登录接口
            $id = $TokenModel->get_id();
            cache($token, NULL);
            return json_encode([
                'code' => 200,
                'msg' => '退出成功！'
            ]);
        }elseif ($type == 'A004'){
            //返回用户信息接口
            $id = $TokenModel->get_id();
            $result = $user->get_user_info($data);
            return $result;
        }elseif ($type == 'A005'){
            //返回用户信息接口
            $id = $TokenModel->get_id();
            $result = $user->get_user_term();
            return $result;
        }elseif ($type == 'A006'){
            //保存前端传过来的id并且设置存活时间
            $id = $TokenModel->get_id();
            if (!array_key_exists('id',$data)){
                throw new BaseException([
                    'msg' => '未传入二维码标识'
                ]);
            }
            $rule = [
                'id'  => 'require'
            ];
            $msg = [
                'id.require' => '二维码标识不能为空'
            ];
            $validate = new Validate($rule,$msg);
            $result   = $validate->check($data);
            if(!$result){
                throw new BaseException([
                    'msg' => $validate->getError()
                ]);
            }
            cache($data['id'],1,config('setting.code_time'));
            return json_encode([
                'code' => 200,
                'msg' => 'success'
            ]);
        }elseif ($type == 'A007'){
            $result = $user->sign_up($data);
            return $result;
        }elseif ($type == 'A008'){
            //展示首页
            $id = $TokenModel->get_id();
            $result = $user->get_top_info();
            return $result;
        }elseif ($type == 'A009'){
            $result = $user->sign_out($data);
            return $result;
        }elseif ($type == 'A010'){
            $result = $user->single_meeting($data);
            return $result;
        }elseif ($type == 'A012'){
            $result = $user->sign_in_out($data);
            return $result;
        }elseif ($type == 'A013'){
            $result = $user->sign_late($data);
            return $result;
        }elseif ($type == 'A014'){
            $result = $user->discussion($data);
            return $result;
        }elseif ($type == 'A015'){
            $result = $Super->show_advance_notice();
            return $result;
        }elseif ($type == 'A016'){
            $result = $user->bind_email($data);
            return $result;
        }elseif ($type == 'A017'){
            $result = $user->collect_formId($data);
            return $result;
        }elseif ($type == 'B001'){
            //管理员登录
            //登录
            //验证是否有传参
            $user->admin_login_exist($token,$data);
            //验证传参的格式是否正确(白名单)
            (new LoginValidate())->goToCheck($data);
            //校验
            $username = $data['username'];
            $password = $data['password'];
            //查用户
            $exist_user = $Super->where([
                'admin' => $username,
            ])->find();
            if (!$exist_user){
                exit(json_encode([
                    'code' => 404,
                    'msg' => '用户不存在！'
                ]));
            }
            $is_exist = $Super->where([
                'admin' => $username,
                'psw' => md5('Quanta'.$password)
            ])->field('id,scope')->find();
            if (!$is_exist){
                exit(json_encode([
                    'code' => 405,
                    'msg' => '密码错误！'
                ]));
            }
            //查id
            $id = $is_exist['id'];

            //获得token
            $tk = $admin_token->grantToken($id,$is_exist['scope']);
            if ($is_exist['scope'] == '32'){
                $rank = 1;
            }elseif ($is_exist['scope'] == '31'){
                $rank = 2;
            }elseif ($is_exist['scope'] == '30'){
                $rank = 3;
            }else{
                exit(json_encode([
                    'code' => 400,
                    'msg' => '无该管理员！'
                ]));
            }
            return json([
                'code' => 200,
                'msg' => [
                    'rank' => $rank,
                    'token' => $tk
                ]
            ]);
        }elseif ($type == 'B045'){
            //小程序端管理员登录
            //登录
            //验证是否有传参
            $user->wx_admin_login_exist($token,$data);
            //验证传参的格式是否正确(白名单)
            (new LoginValidate())->goToCheck($data);
            //校验
            $username = $data['username'];
            $password = $data['password'];
            //查用户
            $exist_user = $Super->where([
                'admin' => $username,
            ])->find();
            if (!$exist_user){
                exit(json_encode([
                    'code' => 404,
                    'msg' => '用户不存在！'
                ]));
            }
            $is_exist = $Super->where([
                'admin' => $username,
                'psw' => md5('Quanta'.$password)
            ])->field('id,scope')->find();
            if (!$is_exist){
                exit(json_encode([
                    'code' => 405,
                    'msg' => '密码错误！'
                ]));
            }
            //查id
            $id = $is_exist['id'];

            //获得token
            $tk = $admin_token->wx_grantToken($id,$is_exist['scope'],$data['code']);
            if ($is_exist['scope'] == '32'){
                $rank = 1;
            }elseif ($is_exist['scope'] == '31'){
                $rank = 2;
            }elseif ($is_exist['scope'] == '30'){
                $rank = 3;
            }else{
                exit(json_encode([
                    'code' => 400,
                    'msg' => '无该管理员！'
                ]));
            }
            return json([
                'code' => 200,
                'msg' => [
                    'rank' => $rank,
                    'token' => $tk
                ]
            ]);
        }elseif ($type=='B002'){
            $result = $user->get_admin_name();
            return $result;
        }elseif ($type == 'B003'){
            //发布会议
            $result = $Super->set_meeting(json_decode($data,true));
            return $result;
        }elseif ($type == 'B004'){
            $id = $TokenModel->get_id();
            cache($token, NULL);
            return json_encode([
                'code' => 200,
                'msg' => '退出成功！'
            ]);
        }elseif ($type == 'B005'){
            //查看单个会议情况
            $result = $Super->show_single_meeting($data);
            return $result;
        }elseif ($type == 'B006'){
            //显示学期
            $result = $Super->show_term();
            return $result;
        }elseif ($type == 'B007'){
            //一次查看一个列表的会议
            $result = $Super->show_all_meeting($data);
            return $result;
        }elseif ($type == 'B008'){
            //查看会议条数
            $result = $Super->get_meeting_number($data);
            return $result;
        }elseif ($type == 'B009'){
            //在发布会议下面的展示会议的成员
            $result = $Super->show_all_person($data);
            return $result;
        }elseif ($type == 'B010'){
            //获取用户的数目
            $result = $Super->all_person_count();
            return $result;
        }elseif ($type == 'B011'){
            //删除会议
            $result = $Super->delete_meeting($data);
            return $result;
        }elseif ($type == 'B013'){
            //展示每个会议每条成员情况（出勤查看）
            $result = $Super->attendance_check($data);
            return $result;
        }elseif ($type == 'B014'){
            //改变会议成员出席情况
            $result = $Super->change_state($data);
            return $result;
        }elseif ($type == 'B015'){
            //改变会议成员出席情况
            $result = $Super->search($data);
            return $result;
        }elseif ($type == 'B017'){
            //搜索出勤详情导出
            $result = $Super->create_search($data);
            return $result;
        }elseif ($type == 'B018'){
            //生成签到二维码
            $result = $Super->create_code($data);
            return $result;
        }elseif ($type == 'B019'){
            //修改会议
            $result = $Super->change_meeting(json_decode($data,true));
            return $result;
        }elseif ($type == 'B020'){
            //生成签退二维码
            $result = $Super->create_sign_out_code($data);
            return $result;
        }elseif ($type == 'B021'){
            $result = $Super->change_single_state($data);
            return $result;
        }
//          elseif ($type == 'B022'){
//            $result = $Super->create_single_meeting($data);
//            return $result;
//        }
        elseif ($type == 'B023'){
            $result = $Super->be_start($data);
            return $result;
        }elseif ($type == 'B024'){
            $result = $Super->be_end($data);
            return $result;
        }
        elseif($type == 'B025'){
            $id = $TokenModel->get_id();
            $secret = $TokenModel->checkUser();
            if ((int)$secret < 31){
                exit(json_encode([
                    'code' => 403,
                    'msg' => '权限不足！'
                ]));
            }
            $select = Db::table('user')
                ->field('major,number')
                ->select();
            if (!$select){
                $result = json_encode([
                    'code' => 200,
                    'msg' => []
                ]);
            }else{
                //当前的专业
                $now_major = "";
                $flag = -1;
                //信息数组
                $info = [];
                foreach ($select as $v){
                    $now_year = substr($v['number'],0,4);
                    if (array_key_exists($v['major'],$info)){
                        if (!in_array($now_year,$info[$v['major']])){
                            $num = count($info[$v['major']]);
                            $info[$v['major']][$num] = $now_year;
                        }
                    }else{
                        $info[$v['major']][0] = $now_year;
                    }
//                    if ($v['major'] != $now_major){
//                        $now_major = $v['major'];
//                        $flag++;
//                    }
//                    $info[$flag]['major'] = $now_major;
//                    if (!array_key_exists('year',$info[$flag])){
//                        $info[$flag]['year'] = [];
//                    }
//                    $year = $info[$flag]['year'];
//                    $now_year = substr($v['number'],0,4);
//                    if (!in_array($now_year,$year)){
//                        array_push($info[$flag]['year'],$now_year);
//                    }
                }
                $info_final = [];
                foreach ($info as $m => $n){
                    //外数组长度
                    $a_num = count($info_final);
                    $info_final[$a_num]['major'] = $m;
                    $info_final[$a_num]['year'] = $n;
                }

                $result = json_encode([
                    'code' => 200,
                    'msg' => $info_final
                ]);
            }
            return $result;
        }elseif($type == 'B026'){
            $id = $TokenModel->get_id();
            $result = $Super->show_meeting_sign($data);
            return $result;
        }elseif($type == 'B027'){
            $id = $TokenModel->get_id();
            $result = $Super->change_check($data);
            return $result;
        }elseif($type == 'B028'){
            $id = $TokenModel->get_id();
            $result = $Super->single_meeting_member($data);
            return $result;
        }elseif($type == 'B029'){
            $id = $TokenModel->get_id();
            $result = $Super->create_single_meeting_member($data);
            return $result;
        }elseif($type == 'B030'){
            $id = $TokenModel->get_id();
            $result = $Super->create_show_meeting_sign($data);
            return $result;
        }elseif($type == 'B031'){
            $id = $TokenModel->get_id();
            $result = $Super->create_show_checked($data);
            return $result;
        }elseif($type == 'B032'){
            $id = $TokenModel->get_id();
            $result = $Super->create_show_student($data);
            return $result;
        }elseif($type == 'B033'){
            //审核会议
            $result = $Super->apply_meeting(json_decode($data,true));
            return $result;
        }elseif($type == 'B034'){
            //展示所有审核会议
            $result = $Super->show_check_all_meeting($data);
            return $result;
        }elseif($type == 'B035'){
            //展示审核会议的学期
            $result = $Super->show_check_term();
            return $result;
        }elseif($type == 'B036'){
            //展示单个审核会议
            $result = $Super->show_single_check_meeting($data);
            return $result;
        }elseif($type == 'B037'){
            //同意审核
            $result = $Super->agree_apply($data);
            return $result;
        }elseif($type == 'B038'){
            //不同意审核
            $result = $Super->disagree_apply($data);
            return $result;
        }elseif($type == 'B039'){
            //修改会议信息
            $result = $Super->change_check_meeting(json_decode($data,true));
            return $result;
        }elseif($type == 'B040'){
            //删除待会议信息
            $result = $Super->delete_check_meeting($data);
            return $result;
        }elseif($type == 'B041'){
            $secret = $TokenModel->checkUser();
            if ((int)$secret < 31){
                exit(json_encode([
                    'code' => 403,
                    'msg' => '权限不足！'
                ]));
            }
            $select = Db::table('user')
                ->distinct(true)
                ->field('major')
                ->select();
            if (!$select){
                $result = json_encode([
                    'code' => 200,
                    'msg' => []
                ]);
            }else{
                $info = [];
                $i = 0;
                foreach ($select as $item){
                    $info[$i] = $item['major'];
                    $i++;
                }
                $result = json_encode([
                    'code' => 200,
                    'msg' => $info
                ]);
            }
            return $result;
        }elseif($type == 'B042'){
            //删除用户
            $result = $Super->delete_user($data);
            return $result;
        }elseif($type == 'B043'){
            //返回年级
            $result = $Super->return_grade();
            return $result;
        }elseif ($type == 'B044'){
            $result = $Super->change_start_single_state($data);
            return $result;
        }elseif ($type == 'B050'){
            //生成迟到二维码
            $result = $Super->create_late_code($data);
            return $result;
        }elseif ($type == 'B046'){
            //新增预告
            $result = $Super->set_advance_notice($data);
            return $result;
        }elseif ($type == 'B047'){
            //修改预告
            $result = $Super->edit_advance_notice($data);
            return $result;
        }elseif ($type == 'B048'){
            //展示预告
            $result = $Super->show_advance_notice();
            return $result;
        }elseif ($type == 'B049'){
            //学院查看
            $result = $Super->show_major_period($data);
            return $result;
        }elseif ($type == 'B051'){
            //返回学院查看上面的学期种类
            $result = $Super->get_major_period_term();
            return $result;
        }elseif ($type == 'B052'){
            //发布会议
            $result = $Super->set_meeting_wx(json_decode($data,true));
            return $result;
        }elseif ($type == 'B053'){
            //修改会议
            $result = $Super->change_meeting_wx(json_decode($data,true));
            return $result;
        }elseif($type == 'B054'){
            //审核会议
            $result = $Super->apply_meeting_wx(json_decode($data,true));
            return $result;
        }elseif($type == 'B055'){
            //修改会议信息
            $result = $Super->change_check_meeting_wx(json_decode($data,true));
            return $result;
        }elseif ($type == 'B056'){
            $result = $user->show_meeting_average($data);
            return $result;
        }elseif ($type == 'B057'){
            $result = $user->show_meeting_comment($data);
            return $result;
        }elseif ($type == 'B058'){
            $result = $Super->delete_advance_notice($data);
            return $result;
        }elseif ($type == 'B059'){
            $result = $Super->init_student_pwd($data);
            return $result;
        }elseif($type == 'B060'){
            //展示所有审核会议小程序
            $result = $Super->show_check_all_meeting_wx($data);
            return $result;
        }elseif($type == 'B061'){
            //展示单个审核会议
            $result = $Super->show_single_check_meeting_wx($data);
            return $result;
        }elseif($type == 'B062'){
            //新的出勤查看
            $result = $Super->attendance_check_new($data);
            return $result;
        }elseif($type == 'B063'){
            $id = $TokenModel->get_id();
            $result = $Super->show_meeting_sign_wx($data);
            return $result;
        }
//        elseif ($type == 'update_major_period'){
//            //更新学院查看
//            $result = Db::table('meeting_major')->distinct('major')->field('major')->select();
//            Db::startTrans();
//            foreach ($result as $k => $v){
//                if (!Db::table('major_period')->where(['term'=>'all','major'=>$v['major']])->find()){
//                    $deal = Db::table('major_period')->insert([
//                        'term' => 'all',
//                        'major' => $v['major']
//                    ]);
//                    if (!$deal){
//                        exit(json_encode([
//                            'code' => 400,
//                            'msg' => 'error'
//                        ]));
//                    }
//                }
//
//            }
//            Db::commit();
//            return json("成功");
//        }
        elseif ($type == 'BBBB'){
//            //搜索出勤详情导出
//            $result = $Super->in(COMMON_PATH.'static/member.xls',0,2,0,4);
//            return $result;

//            //二级权限导入
//            $result = $Super->second_power_in(COMMON_PATH.'static/second_power_member.xls',0,0,2,1);
//            return $result;

//            //删除奇奇怪怪的学院
//            $result = $Super->delete_special_major(COMMON_PATH.'static/member.xls',0,2,0,4);
//            return $result;
//
//            //更新学院的行政顺序
//            $result = $Super->update_major_order(COMMON_PATH.'static/order.xls',0,1,0);
//            return $result;
//            Cache::clear();
//
//            //删除测试账号
//            Db::table('user')->where('id' ,'>',267)->delete();
        }
//        elseif ($type == 'BAAA'){
//            $form_id = Cache::get(3);
//            if (!$form_id){
//                //没有的情况
//                $form_id = [0 => $data['form_id']];
//            }else{
//                $index = count($form_id);
//                $form_id[$index] = $data['form_id'];
//            }
//            //收集form_id并存储7天
//            $request = cache(3, $form_id, 7 * 24 * 60 * 60);
//            if (!$request){
//                var_dump('缓存错误');
//            }
//        }elseif ($type == 'BAAB'){
//            var_dump(Cache::get(3));
//        }
        else{
            exit(json_encode([
                'code' => 404,
                'msg' => '未找到此类型！'
            ]));
        }
    }

    private function attackfilter($data,$msg='用户名错误'){//登录过滤
        $user_name = $data;
        $filter = "/`|'|\||and|union|select|from|regexp|like|=|information_schema|where|union|join|sleep|benchmark|,|\(|\)/is";
        if (preg_match($filter,$user_name)==1){
            throw new BaseException([
                'msg' => $msg
            ]);
        }
    }

}