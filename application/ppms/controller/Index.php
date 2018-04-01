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
                'code' => 403,
                'msg' => '未传入任何参数！'
            ]));
        }
        if (!array_key_exists('token',$post)){
            exit(json_encode([
                'code' => 403,
                'msg' => '第一项参数缺失，禁止请求！'
            ]));
        }
        $token = $post['token'];
        if (!array_key_exists('type',$post)){
            exit(json_encode([
                'code' => 403,
                'msg' => '第二项参数缺失，禁止请求！'
            ]));
        }
        $type = $post['type'];
        if (!array_key_exists('data',$post)){
            exit(json_encode([
                'code' => 403,
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
            return json_encode([
                'code' => 200,
                'msg' => $tk
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
            if ($is_exist['scope'] != 32){
                exit(json_encode([
                    'code' => 403,
                    'msg' => '权限不足！'
                ]));
            }

            //获得token
            $tk = $admin_token->grantToken($id);
            return json([
                'code' => 200,
                'msg' => $tk
            ]);
        }elseif ($type=='B002'){
            $result = $user->get_admin_name();
            return $result;
        }elseif ($type == 'B003'){
            //发布会议
            $result = $Super->set_meeting($data);
            return $result;
        }elseif ($type == 'B004'){
            $id = $TokenModel->get_id();
            $secret = $TokenModel->checkUser();
            if ($secret != 32){
                exit(json_encode([
                    'code' => 403,
                    'msg' => '权限不足！'
                ]));
            }
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
        }elseif ($type == 'B012'){
            //删除成员
            $result = $Super->delete_member($data);
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
        }elseif ($type == 'B016'){
            //改变会议成员出席情况
            $result = $Super->create_attendance_check($data);
            return $result;
        }elseif ($type == 'B017'){
            //搜索出勤详情导出
            $result = $Super->create_search($data);
            return $result;
        }elseif ($type == 'B018'){
            //搜索出勤详情导出
            $result = $Super->create_code($data);
            return $result;
        }elseif ($type == 'B019'){
            //修改会议
            $result = $Super->change_meeting($data);
            return $result;
        }elseif ($type == 'B020'){
            //生成签退二维码
            $result = $Super->create_sign_out_code($data);
            return $result;
        }elseif ($type == 'B021'){
            $result = $Super->change_single_state($data);
            return $result;
        }elseif ($type == 'B022'){
            $result = $Super->create_single_meeting($data);
            return $result;
        }elseif ($type == 'B023'){
            $result = $Super->be_start($data);
            return $result;
        }elseif ($type == 'B024'){
            $result = $Super->be_end($data);
            return $result;
        }
//        elseif ($type == 'BBBB'){
////            //搜索出勤详情导出
////            $result = $Super->in(COMMON_PATH.'static/member.xlsx');
////            return $result;
////
////            //删除测试账号
////            Db::table('meeting_member')->where('user_id' ,'<',200)->delete();
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