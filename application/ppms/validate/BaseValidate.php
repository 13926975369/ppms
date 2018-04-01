<?php
/**
 * Created by PhpStorm.
 * User: asus
 * Date: 2018/1/11
 * Time: 21:40
 */

namespace app\ppms\validate;


use app\ppms\exception\BaseException;
use think\Request;
use think\Validate;

class BaseValidate extends Validate
{
    public function goCheck(){
        $request = Request::instance();
        $params = $request->param();

        $result = $this->batch()->check($params);
        if(!$result){
            //隐藏掉属性（如username等）并只输出一条
            $arr = $this->error;
            $str='';
            foreach ($arr as $k => $v){
                $str .= $v;
                break;
            }
            exit(json_encode([
                'code' => 400,
                'msg' => $str
            ]));
        }else{
            return true;
        }
    }
    public function goToCheck($data){
        $result = $this->batch()->check($data);
        if(!$result){
            //隐藏掉属性（如username等）并只输出一条
            $arr = $this->error;
            $str='';
            foreach ($arr as $k => $v){
                $str .= $v;
                break;
            }
            exit(json_encode([
                'code' => 400,
                'msg' => $str
            ]));
        }else{
            return true;
        }
    }
    //密码和用户名的检测规则
    protected function IsCharacter($value,$rule = '',$date = '',$field=''){
        if(preg_match("/^[a-z0-9A-Z_]+$/",$value)){
            return true;
        }else{
            if ($field=='username'){
                exit(json_encode([
                    'code' => 400,
                    'msg' => "密码必须由字母、数字和下划线组成！"
                ]));
            }elseif ($field == 'password'){
                exit(json_encode([
                    'code' => 400,
                    'msg' => "密码必须由字母、数字和下划线组成！"
                ]));
            }
        }
    }

}