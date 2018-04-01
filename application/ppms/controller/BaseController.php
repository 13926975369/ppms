<?php
/**
 * Created by PhpStorm.
 * User: asus
 * Date: 2018/1/11
 * Time: 22:22
 */

namespace app\ppms\controller;
use app\ppms\exception\BaseException;
use think\Controller;

class BaseController extends Controller
{
    protected function have_key_validate($data = [],$arr){
        foreach ($data as $k => $v){
            if (!array_key_exists($k,$arr)){
                throw new BaseException([
                    'msg' => $v
                ]);
            }
        }
    }
}