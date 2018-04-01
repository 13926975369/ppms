<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/16
 * Time: 1:35
 */

namespace app\ppms\model;


use app\ppms\exception\TokenException;
use app\ppms\validate\IDMustBeNumber;

class AdminToken extends Token
{
    protected $secret;
    protected $uid;

    public function grantToken($id){
        //验证id
        (new IDMustBeNumber())->goToCheck([
            'id' => $id
        ]);
        $this->uid = $id;
        //这是一个拼接token的函数，32随机+时间戳+salt
        //key就是token，value包含uid，scope
        //拿到钥匙
        $key = $this->gettoken();
        $cachedValue['id'] = $id;
        //scope为权限
        $cachedValue['secret'] = 32;
        $this->secret  = 32;
        $value = json_encode($cachedValue);
        //设置存活时间
        $expire_in = config('setting.token_expire_in');
        //存入缓存
        $request = cache($key, $value, $expire_in);
        if (!$request){
            exit(json_encode([
                'code' => 401,
                'msg' => '密码错误！'
            ]));
        }
        return $key;
    }
}