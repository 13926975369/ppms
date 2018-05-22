<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件


function getRandChars($length){
    //不能把位数写死了，根据length来确定多少位数随机字符串
    $str = null;
    $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
    $max = strlen($strPol) - 1;

    //从中间抽出字符串加length次
    for ($i = 0; $i < $length; $i++){
        $str .= $strPol[rand(0, $max)];
    }

    return $str;
}

function filter($str){
    //防XSS
    $s = strip_tags($str);
    $s = htmlspecialchars($s);

    return $str;
}

//当前超过了传入时间也就是时间已经到了会议开始的时间为开始状态返回false，当前时间未超过传入时间就还没到开会时间返回true
function calculate_state($year,$month,$day,$hour,$minute){
    //获取当前时间
    $y=date("Y",time());
    $m=date("m",time());
    $d=date("d",time());
    $H=date("H",time());
    $I=date("i",time());

    if ((int)$y>(int)$year){
        return false;
    }elseif ((int)$y<(int)$year){
        return true;
    }else{
        if ((int)$m>(int)$month){
            return false;
        }elseif ((int)$m<(int)$month){
            return true;
        }else{
            if ((int)$d>(int)$day){
                return false;
            }elseif ((int)$d<(int)$day){
                return true;
            }else{
                if ((int)$H>(int)$hour){
                    return false;
                }elseif ((int)$H<(int)$hour){
                    return true;
                }else{
                    if ((int)$I>=(int)$minute){
                        return false;
                    }elseif ((int)$I<(int)$minute){
                        return true;
                    }
                }
            }
        }
    }
}

function curl_get($url, $httpCode = 0) {
//    初始化
    $ch = curl_init();
//    爬取url地址
    curl_setopt($ch, CURLOPT_URL, $url);
//    不将爬取内容直接输出而保存到变量中
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    //部署在Linux环境下改为true
//    模拟一个浏览器访问https网站
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//    设定连接时间
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    //执行获取内容
    $file_contents = curl_exec($ch);
    $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $file_contents;
}