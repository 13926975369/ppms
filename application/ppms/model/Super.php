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
use app\ppms\validate\IDMustBeNumber;
use app\ppms\validate\Search;
use app\ppms\validate\SetMeeting;
use app\ppms\validate\ShowMeeting;
use think\Db;
use think\Loader;
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
        if (!array_key_exists('member',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无成员！'
            ]));
        }
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
        $member = $data['member'];

        if (!((int)$date1<=(int)$term2&&(int)$date1>=(int)$term1)){
            exit(json_encode([
                'code' => 400,
                'msg' => '输入日期中的年份未在输入的学期之间，请检查后重新输入！'
            ]));
        }
        $end_time = $date1.'-'.$date2.'-'.$date3.' 23:59:59';
        $end_time = strtotime($end_time);
        $begin_time = $date1.'-'.$date2.'-'.$date3.' '.$time1.':'.$time2.':00';
        $begin_time = strtotime($begin_time);
        //入库
        $meeting = new Meeting();
        $meeting_member = new Meeting_member();
        if ($member == []){
            $meeting->startTrans();
            $meeting_member->startTrans();
            $result = $meeting->insertGetId([
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
                'end_time' => $end_time
            ]);
            if (!$result){
                $meeting->rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '上传错误'
                ]));
            }
        }else{
            $meeting->startTrans();
            $meeting_member->startTrans();
            $result = $meeting->insertGetId([
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
                'end_time' => $end_time
            ]);
            if (!$result){
                $meeting->rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '上传错误'
                ]));
            }

            foreach ($member as $v){
                $rrr = $meeting_member->insert([
                    'meeting_id' => $result,
                    'user_id' => (int)$v,
                    'term' => (int)($term1.$term2.$term3),
                    'end_time' => (int)$end_time,
                    'begin' => (int)$begin_time
                ]);
                if (!$rrr){
                    $meeting_member->rollback();
                    $meeting->rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '更新出错，可能是参数出错'
                    ]));
                }
            }
            $meeting->commit();
            $meeting_member->commit();
        }

        return json([
            'code' => 200,
            'msg' => $result
        ]);
    }

    //修改会议
    public function change_meeting($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
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
        if (!array_key_exists('member',$data)){
            exit(json_encode([
                'code' => 400,
                'msg' => '无成员！'
            ]));
        }
        if(!is_numeric($data['meeting_id'])){
            exit(json_encode([
                'code' => 400,
                'msg' => '会议标识非数字'
            ]));
        }
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

        if (!((int)$date1<=(int)$term2&&(int)$date1>=(int)$term1)){
            exit(json_encode([
                'code' => 400,
                'msg' => '输入日期中的年份未在输入的学期之间，请检查后重新输入！'
            ]));
        }
        $end_time = $date1.'-'.$date2.'-'.$date3.' 23:59:59';
        $end_time = strtotime($end_time);
        $begin_time = $date1.'-'.$date2.'-'.$date3.' '.$time1.':'.$time2.':00';
        $begin_time = strtotime($begin_time);
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
        if ($name==$check['name']&&$date1==$check['date1']&&$date2==$check['date2']&&$date3==$check['date3']&&$time1==$check['time1']
        &&$time2==$check['time2']&&$position==$check['position']&&$term1==$check['term1']&&$term2==$check['term2']&&$term3==$check['term3']
            &&$begin_time==$check['begin']&&$end_time==$check['end_time']){
            $member = $data['member'];
            if (!empty($member)){
                $meeting_member->startTrans();
                $a_check = $meeting_member->where([
                    'meeting_id' => $data['meeting_id']
                ])->delete();
                if (!$a_check){
                    $meeting_member->rollback();
                    $meeting->rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '更新出错，可能是参数出错'
                    ]));
                }
                foreach ($member as $v){
                    $rrr = $meeting_member->insert([
                        'meeting_id' => $data['meeting_id'],
                        'user_id' => (int)$v,
                        'term' => (int)($term1.$term2.$term3),
                        'end_time' => (int)$end_time,
                        'begin' => (int)$begin_time
                    ]);
                    if (!$rrr){
                        $meeting_member->rollback();
                        $meeting->rollback();
                        exit(json_encode([
                            'code' => 503,
                            'msg' => '更新出错，可能是参数出错'
                        ]));
                    }
                }
                $meeting_member->commit();
            }
        }else{
            $meeting->startTrans();
            $meeting_member->startTrans();
            $result = $meeting
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
                'end_time' => $end_time
            ]);
            if (!$result){
                $meeting->rollback();
                exit(json_encode([
                    'code' => 503,
                    'msg' => '上传错误'
                ]));
            }
            $member = $data['member'];
            if (!empty($member)){
                $a_check = $meeting_member->where([
                    'meeting_id' => $data['meeting_id']
                ])->delete();
                if (!$a_check){
                    $meeting_member->rollback();
                    $meeting->rollback();
                    exit(json_encode([
                        'code' => 503,
                        'msg' => '更新出错，可能是参数出错'
                    ]));
                }
                foreach ($member as $v){
                    $rrr = $meeting_member->insert([
                        'meeting_id' => $data['meeting_id'],
                        'user_id' => (int)$v,
                        'term' => (int)($term1.$term2.$term3),
                        'end_time' => (int)$end_time,
                        'begin' => (int)$begin_time
                    ]);
                    if (!$rrr){
                        $meeting_member->rollback();
                        $meeting->rollback();
                        exit(json_encode([
                            'code' => 503,
                            'msg' => '更新出错，可能是参数出错'
                        ]));
                    }
                }
            }

            $meeting->commit();
            $meeting_member->commit();
        }

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
        $date1 = $result['date1'];
        $date2 = $result['date2'];
        $date3 = $result['date3'];
        $time1 = $result['time1'];
        $time2 = $result['time2'];
        $position= $result['position'];
        $term1= $result['term1'];
        $term2= $result['term2'];
        $term3= $result['term3'];

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
                if ($state == '未开始'){
                    $member[$i]['status'] = '未开始';
                }elseif ($state == '已开始'){
//                    $member[$i]['status'] = '已开始';
                    if ((int)$v['ask_leave'] == 1){
                        $member[$i]['status'] = '请假';
                    }elseif ((int)$v['attend'] == 1&& (int)$v['sign_out'] == 1){
                        $member[$i]['status'] = '出席';
                    }elseif ((int)$v['attend'] == 1&& (int)$v['sign_out'] == 0){
                        $member[$i]['status'] = '未签退';
                    }elseif ((int)$v['attend'] == 0&& (int)$v['sign_out'] == 1){
                        $member[$i]['status'] = '迟到';
                    }else{
                        $member[$i]['status'] = '未签到';
                    }
                }else{
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
                }

                $i++;
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
                'member' => $member
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
        if ($secret != 32){
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
                $msg[$t][$i]['clock'] = $v['time1'].':'.$v['time2'];
                $msg[$t][$i]['position'] = $v['position'];
                $msg[$t][$i]['state'] = $state;
            }
        }else{
            $t = str_replace('-','',$term);
            if ($page == 0 && $size == 0){
                $info = $meeting
                    ->where([
                        'term' => (int)$t
                    ])
                    ->order([
                        'term' => 'desc'
                    ])
                    ->select();
            }else{
                $start = ($page-1)*$size;
                $info = $meeting->limit($start,$size)
                    ->where([
                        'term' => (int)$t
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
                $msg[$term][$i]['clock'] = $v['time1'].':'.$v['time2'];
                $msg[$term][$i]['position'] = $v['position'];
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
        if ($secret != 32){
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
        if ($secret != 32){
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

    public function delete_meeting($data){
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
        $check = $member->where([
            'meeting_id' => $data['meeting_id']
        ])->find();
        if ($check){
            $meeting->startTrans();
            $member->startTrans();
            $rr = $meeting->where([
                'id' => $data['meeting_id']
            ])->delete();
            $rrr = $member->where([
                'meeting_id' => $data['meeting_id']
            ])->delete();
            if (!$rr || !$rrr){
                $meeting->rollback();
                $member->rollback();
            }else{
                $meeting->commit();
                $member->commit();
            }
        }else{
            $rr = $meeting->where([
                'id' => $data['meeting_id']
            ])->delete();
            if (!$rr){
                exit(json_encode([
                    'code' => 504,
                    'msg' => '更新出错，请重试！'
                ]));
            }
        }
        return json([
            'code' => 200,
            'msg' => 'success'
        ]);
    }

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
        $time = (int)time();
        if ($page == 0 && $size == 0){
            $i = 0;
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
                    //出席，请假，缺席，早退，迟到
                    $attend = 0;
                    $ask_leave = 0;
                    $absence = 0;
                    $late = 0;
                    $early = 0;
                    $r[$i]['user_id'] = $k['id'];
                    $r[$i]['username'] = $k['username'];
                    $r[$i]['major'] = $k['major'];
                    $r[$i]['number'] = $k['number'];
                    $kk = $member->where([
                        'user_id' => $k['id'],
                        'term' => $t
                    ])->where('end_time','<',$time)->field('attend,ask_leave,sign_out')->select();
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
                    $late = 0;
                    $early = 0;
                    $r[$i]['user_id'] = $k['id'];
                    $r[$i]['username'] = $k['username'];
                    $r[$i]['major'] = $k['major'];
                    $r[$i]['number'] = $k['number'];
                    $kk = $member->where([
                        'user_id' => $k['id']
                    ])->where('end_time','<',$time)->field('attend,ask_leave,sign_out')->select();
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
            $i = 0;
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
                    $late = 0;
                    $early = 0;
                    $r[$i]['user_id'] = $k['id'];
                    $r[$i]['username'] = $k['username'];
                    $r[$i]['major'] = $k['major'];
                    $r[$i]['number'] = $k['number'];
                    $kk = $member->where([
                        'user_id' => $k['id'],
                        'term' => $t
                    ])->where('end_time','<',$time)->field('attend,ask_leave')->select();
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
                    $late = 0;
                    $early = 0;
                    $r[$i]['user_id'] = $k['id'];
                    $r[$i]['username'] = $k['username'];
                    $r[$i]['major'] = $k['major'];
                    $r[$i]['number'] = $k['number'];
                    $kk = $member->where([
                        'user_id' => $k['id']
                    ])->where('end_time','<',$time)->field('attend,ask_leave')->select();
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

        return json([
            'code' => 200,
            'msg' => $r
        ]);
    }


    public function change_state($data){
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
        $rule = [
            'user_id'  => 'require|number',
        ];
        $msg = [
            'user_id.require' => '用户标识不能为空',
            'user_id.number'   => '用户标识必须是数字',
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
        $re = $user->where([
            'id' => $data['user_id']
        ])->field('username')->find();
        if (!$re){
            exit(json_encode([
                'code' => 400,
                'msg' => '没有该用户！'
            ]));
        }
        Db::startTrans();
        foreach ($meet as $vv){
            $rr = Db::table('meeting_member')->where([
                'meeting_id' => $vv['meeting_id'],
                'user_id' => $data['user_id']
            ])->field('attend,ask_leave,sign_out')->find();
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
                        'user_id' => $data['user_id']
                    ])->update([
                        'attend' => 1,
                        'sign_out' => 1,
                        'ask_leave' => 0,
                    ]);
                    if (!$info){
                        Db::rollback();
                        exit(json_encode([
                            'code' => 504,
                            'msg' => '更新出错，请重试！'
                        ]));
                    }
                }
            }elseif ($vv['status'] == '请假'){
                if ($rr['ask_leave'] != 1){
                    //请假
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $vv['meeting_id'],
                        'user_id' => $data['user_id']
                    ])->update([
                        'ask_leave' => 1
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
                        'user_id' => $data['user_id']
                    ])->update([
                        'attend' => 0,
                        'sign_out' => 0,
                        'ask_leave' => 0
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
                        'user_id' => $data['user_id']
                    ])->update([
                        'attend' => 0,
                        'ask_leave' => 0,
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
                        'user_id' => $data['user_id']
                    ])->update([
                        'attend' => 1,
                        'ask_leave' => 0,
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


    public function change_single_state($data){
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
            ])->field('attend,ask_leave,sign_out')->find();
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
                        'ask_leave' => 0,
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
            }elseif ($vv['status'] == '请假'){
                if ($rr['ask_leave'] != 1){
                    //请假
                    $info = Db::table('meeting_member')->where([
                        'meeting_id' => $meet,
                        'user_id' => $vv['user_id']
                    ])->update([
                        'ask_leave' => 1
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
                        'ask_leave' => 0,
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
                        'ask_leave' => 0,
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
                        'ask_leave' => 0,
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
        if ($secret != 32){
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
            //出席，请假，缺席
            $att = 0;
            $ask = 0;
            $absence = 0;
            $early = 0;
            $late = 0;

            if ($data['term'] == 'all'){
                $check = $member->where([
                    'user_id' => $uid
                ])->where('end_time','<',$time)->field('meeting_id,attend,ask_leave,sign_out')->select();
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
                ])->where('end_time','<',$time)->field('meeting_id,attend,ask_leave,sign_out')->select();
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
                $ask_leave = (int)$k['ask_leave'];
                $sign_out = (int)$k['sign_out'];
                $result[$i]['meeting'][$j]['meeting_id'] = $k['meeting_id'];
                $result[$i]['meeting'][$j]['meeting_name'] = $re['name'];
                $result[$i]['meeting'][$j]['meeting_date'] = $re['date1'].'/'.$re['date2'].'/'.$re['date3'];
                $result[$i]['meeting'][$j]['meeting_position'] = $re['position'];
                if ($ask_leave == 1){
                    $result[$i]['meeting'][$j]['status'] = '请假';
                    $ask++;
                }elseif ($attend == 1 && $sign_out == 1){
                    $result[$i]['meeting'][$j]['status'] = '出席';
                    $att++;
                }elseif ($attend == 1 && $sign_out == 0){
                    $result[$i]['meeting'][$j]['status'] = '早退';
                    $early++;
                }elseif ($attend == 0 && $sign_out == 1){
                    $result[$i]['meeting'][$j]['status'] = '迟到';
                    $late++;
                }else{
                    $result[$i]['meeting'][$j]['status'] = '缺席';
                    $absence++;
                }
                $j++;
            }
            $result[$i]['user_id'] = $uid;
            $result[$i]['username'] = $v['username'];
            $result[$i]['major'] = $v['major'];
            $result[$i]['number'] = $v['number'];
            $result[$i]['attend_time'] = $att;
            $result[$i]['ask_leave'] = $ask;
            $result[$i]['absence_time'] = $absence;
            $result[$i]['late'] = $late;
            $result[$i]['early'] = $early;

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


    public function create_search($data){
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
                'msg' => '查无此人'
            ]));
        }
        $i = 0;
        //用来记录表格的行号
        $k = 1;
        $time = (int)time();
        $count = count($info);
        vendor('PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex(0);
        $objPHPExcel->getActiveSheet()->setTitle("出勤详情");
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
        foreach ($info as $v){
            $uid = $v['id'];
            //出席，请假，缺席
            $att = 0;
            $ask = 0;
            $absence = 0;
            $early = 0;
            $late = 0;

            if ($data['term'] == 'all'){
                $check = $member->where([
                    'user_id' => $uid
                ])->where('end_time','<',$time)->field('meeting_id,attend,ask_leave,sign_out')->select();
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
                ])->where('end_time','<',$time)->field('meeting_id,attend,ask_leave,sign_out')->select();
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
            $m = $k;
            $k = $k + 2;
            foreach ($check as $kkkk){
                $re = $meeting->where([
                    'id' => $kkkk['meeting_id']
                ])->field('name,date1,date2,date3,position')->find();
                $attend = (int)$kkkk['attend'];
                $ask_leave = (int)$kkkk['ask_leave'];
                $sign_out = (int)$kkkk['sign_out'];
                if ($ask_leave == 1){
                    $status = '请假';
                    $ask++;
                }elseif ($attend == 1 && $sign_out == 1){
                    $status = '出席';
                    $att++;
                }elseif ($attend == 1 && $sign_out == 0){
                    $status = '早退';
                    $early++;
                }elseif ($attend == 0 && $sign_out == 1){
                    $status = '迟到';
                    $late++;
                }else{
                    $status = '缺席';
                    $absence++;
                }

                $objPHPExcel->getActiveSheet()->setCellValue("A".$k, $v['number']);
                $objPHPExcel->getActiveSheet()->getStyle("A".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('A'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("B".$k, $v['username']);
                $objPHPExcel->getActiveSheet()->getStyle("B".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('B'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("C".$k, $v['major']);
                $objPHPExcel->getActiveSheet()->getStyle("C".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('C'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("D".$k, $re['name']);
                $objPHPExcel->getActiveSheet()->getStyle("D".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('D'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("E".$k, $re['date1'].'/'.$re['date2'].'/'.$re['date3']);
                $objPHPExcel->getActiveSheet()->getStyle("E".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->getAlignment()->setWrapText(true);
                $objPHPExcel->getActiveSheet()->getStyle('E'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("F".$k, $re['position']);
                $objPHPExcel->getActiveSheet()->getStyle("F".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('F'.$k)->getAlignment()->setWrapText(true);
                $objPHPExcel->getActiveSheet()->getStyle('F'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $objPHPExcel->getActiveSheet()->setCellValue("G".$k, $status);
                $objPHPExcel->getActiveSheet()->getStyle("G".$k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('G'.$k)->getAlignment()->setWrapText(true);
                $objPHPExcel->getActiveSheet()->getStyle('G'.$k)->applyFromArray($styleThinBlackBorderOutline);
                $k = $k + 1;
            }
            $objPHPExcel->getActiveSheet()->mergeCells("A".$m.":G".$m);
            $objPHPExcel->getActiveSheet()->setCellValue("A".$m, $v['major'].$v['username'].':出席'.$att.'次，请假'.$ask.'次，缺席'.$absence.'次，迟到'.$late.'次，早退'.$early.'次');
            $objPHPExcel->getActiveSheet()->getStyle("A".$m.":G".$m)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle( 'A'.$m)->getFont()->setSize(14);
            $objPHPExcel->getActiveSheet()->getStyle("A".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $m += 1;
            $objPHPExcel->getActiveSheet()->setCellValue("A".$m, "工号");
            $objPHPExcel->getActiveSheet()->getStyle("A".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A'.$m)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("B".$m, "姓名");
            $objPHPExcel->getActiveSheet()->getStyle("B".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('B'.$m)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("C".$m, "单位");
            $objPHPExcel->getActiveSheet()->getStyle("C".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('C'.$m)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("D".$m, "会议名称");
            $objPHPExcel->getActiveSheet()->getStyle("D".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('D'.$m)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("E".$m, "日期");
            $objPHPExcel->getActiveSheet()->getStyle("E".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('E'.$m)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('E'.$m)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("F".$m, "地点");
            $objPHPExcel->getActiveSheet()->getStyle("F".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('F'.$m)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('F'.$m)->applyFromArray($styleThinBlackBorderOutline);
            $objPHPExcel->getActiveSheet()->setCellValue("G".$m, "出勤情况");
            $objPHPExcel->getActiveSheet()->getStyle("G".$m)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('G'.$m)->getAlignment()->setWrapText(true);
            $objPHPExcel->getActiveSheet()->getStyle('G'.$m)->applyFromArray($styleThinBlackBorderOutline);

            $i++;
        }

        //设置格子大小
        $objPHPExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getDefaultColumnDimension()->setWidth(25);

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $_savePath = COMMON_PATH.'/static/attendance_detail.xlsx';
        $objWriter->save($_savePath);

        return json([
            'code' => 200,
            'msg' => config('setting.image_root').'static/attendance_detail.xlsx'
        ]);
    }

    public function create_code($data){
        $TokenModel = new Token();
        $id = $TokenModel->get_id();
        $secret = $TokenModel->checkUser();
        if ($secret != 32){
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
        if ($secret != 32){
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

    public function in($file='', $sheet=0){
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
            for($_column=0; $_column<=$columnCnt; $_column++){
                $cellId = $cellName[$_column].$_row;
                $cellValue = $currSheet->getCell($cellId)->getValue();
                if ($_column == 0){
                    $cellValue1=preg_replace("/[\r\n\s]/","",$cellValue);
                }elseif ($_column == 1){
                    $cellValue2=preg_replace("/[\r\n\s]/","",$cellValue);
                }else{
                    $cellValue3=preg_replace("/[\r\n\s]/","",$cellValue);
                }
            }
            $result = Db::table('user')
                ->insert([
                    'username' => $cellValue1,
                    'password' => '09951fc3343c63973369b91bdc8e441a',
                    'major' => $cellValue2,
                    'number' => $cellValue3
                ]);
            if (!$result){
                Db::rollback();
                exit(json_encode([
                    'code' => 400,
                    'msg' => '出错了'
                ]));
            }
        }
        Db::commit();

        return 0;
    }
}