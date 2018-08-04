<?php
/**
 * Created by PhpStorm.
 * User: 63254
 * Date: 2018/2/16
 * Time: 1:35
 */

namespace app\ppms\model;


use app\ppms\exception\LoginException;
use app\ppms\exception\TokenException;
use app\ppms\exception\WeChatException;
use app\ppms\validate\IDMustBeNumber;
use think\Db;
use think\Exception;

class AdminToken extends Token
{
    protected $secret;
    protected $uid;
    protected $code;
    protected $wxAppID;
    protected $wxAppSecret;
    protected $wxLoginurl;

    //web端后台管理系统管理员登录
    public function grantToken($id,$scope){
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
        $cachedValue['secret'] = (int)$scope;
        $this->secret  = (int)$scope;
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

    //小程序端后台管理系统管理员登录
    public function wx_grantToken($id,$scope,$code){
        $this->code = $code;
        $this->wxAppID = config('wx.app_id');
        $this->wxAppSecret = config('wx.app_secret');
        $this->wxLoginurl = sprintf(config('wx.login_url'),
            $this->wxAppID,$this->wxAppSecret,$this->code);
        $result = curl_get($this->wxLoginurl);

        $wxResult = json_decode($result, true);
        if (empty($wxResult)){
            throw new Exception('获取session_key及openID时异常，微信内部错误');
        }
        else{
            $loginFail = array_key_exists('errcode',$wxResult);
            if ($loginFail){
                throw new WeChatException([
                    'msg' => $wxResult['errmsg']
                ]);
            }else{
                //检测没有报错的话就去取token
                //检验id在的时候，这里的openid等于它
                $openid = $wxResult['openid'];
                $user = Db::table('super')->where([
                    'id' => $id
                ])->field('openid')->find();
                $user_openid = $user['openid'];
                if ($user_openid == NULL){
                    Db::table('super')->where([
                        'id' => $id
                    ])->update([
                        'openid' => $openid
                    ]);
                }else{
                    if ($user_openid != $openid){
                        throw new LoginException([
                            'msg' => '微信号与用户账号不匹配！'
                        ]);
                    }
                }
                $this->uid = $id;
                //这是一个拼接token的函数，32随机+时间戳+salt
                //key就是token，value包含uid，scope
                //拿到钥匙
                $key = $this->gettoken();
                $cachedValue['id'] = $id;
                //scope为权限
                $cachedValue['secret'] = (int)$scope;
                $this->secret  = (int)$scope;
                $value = json_encode($cachedValue);
                //设置存活时间
                $expire_in = config('setting.token_expire_in');
                //存入缓存
                $request = cache($key, $value, $expire_in);
                if (!$request){
                    exit(json_encode([
                        'code' => 401,
                        'msg' => '服务器缓存异常！'
                    ]));
                }
                return $key;
            }
        }



    }
}