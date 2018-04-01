<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/1/13
 * Time: 22:08
 */

namespace app\ppms\model;
use app\ppms\exception\TokenException;
use think\Cache;
use think\Exception;

class Token extends BaseModel
{
    public function gettoken(){
        //用三组字符串md5加密
        //32个字符组成一组随机字符串
        $randChars = getRandChars(32);
        //时间戳
        $timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
        //salt 盐
        $salt = config('setting.token_salt');

        $key = md5($randChars.$timestamp.$salt);

        return $key;
    }

    //获取token中的权限值并且判断token是否过期
    public function checkUser(){
        $token = input('post.token');
        $vars = Cache::get($token);
        if (!$vars){
            exit(json_encode([
                'code' => 401,
                'msg' => 'Token已经过期或无效Token！'
            ]));
        }else{
            if (!is_array($vars)){
                $vars = json_decode($vars,true);
            }
            if (array_key_exists('secret',$vars)) {
                return $vars['secret'];
            }else{
                exit(json_encode([
                    'code' => 444,
                    'msg' => '尝试获取的Token变量并不存在！'
                ]));
            }
        }
    }

    public function get_id(){
        $token = input('post.token');
        $vars = Cache::get($token);
        if (!$vars){
            exit(json_encode([
                'code' => 401,
                'msg' => 'Token已经过期或无效Token！'
            ]));
        }else{
            if (!is_array($vars)){
                $vars = json_decode($vars,true);
            }
            if (array_key_exists('id',$vars)) {
                return $vars['id'];
            }else{
                exit(json_encode([
                    'code' => 444,
                    'msg' => '尝试获取的Token变量并不存在！'
                ]));
            }
        }
    }

    /*
     * @ 注册或者登录获取token令牌
     * @ $code  小程序获取的code码
     */
    public function get_token($code,$id) {
        $ut = new UserToken($code);
        $token = $ut->gett($code,$id);
        return $token;
    }
}