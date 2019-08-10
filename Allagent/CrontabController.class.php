<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/2/20
 * Time: 15:04
 */

namespace Admin\Controller;

use Think\Controller;
use Think\Db;
use Think\Log;

class CrontabController extends Controller
{
    protected $db;
    protected $date;

    public function _initialize()
    {
        set_time_limit(0);
        $this->db = M();
        $now = time();
        $currtime = $now - 60 * 60;
        $this->date = date('Y-m-d', $currtime);
    }

    /**
     * 代理
     */
    public function agent_statistics_script()
    {
        Log::write('agent_statistics_script runtime:' . date('Y-m-d H:i:s', time()));
        $agent = $this->db->table('tb_agent')->field('IDX')->select();
        foreach ($agent as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_agentstatistics')->where(['AgentID' => $val['idx'], 'CreateDate' => $this->date])->find();
            if (!$res) {
                $ins_data = ['AgentID' => $val['idx'],'CreateDate' => $this->date];
                $this->db->table('gamelogdb.tb_agentstatistics')->add($ins_data);
            }
            $sale_where = [
                'PayMethod' => 1,
                'Status' => 1,
                'PayChannelID' => $val['idx'],
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            ];
            $sale = $this->db->table('tb_recharge')->where($sale_where)->sum('Gold');
            $purchase_where = [
                'Status' => 1,
                'AgentID' => $val['idx'],
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            ];
            $purchase = $this->db->table('tb_agentorder')->where($purchase_where)->sum('Gold');
            $update_where = [
                'AgentID' => $val['idx'],
                'CreateDate' => $this->date
            ];
            $update_data = [
                'Sale' => $sale,
                'Purchase' => $purchase,
            ];
            $this->db->table('gamelogdb.tb_agentstatistics')->where($update_where)->save($update_data);
        }
        echo 'success';
        die;
    }

    /**
     * 渠道
     */
    public function channel_statistics_script()
    {
        set_time_limit(0);
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        $channel = $this->db->table('tb_channel')->field('ChannelID')->select();
        foreach ($channel as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_channelstatistics')->where(['ChannelID' => $val['channelid'], 'CreateDate' => $this->date])->find();
            if (!$res) {
                $ins_data = [
                    'ChannelID' => $val['channelid'],
                    'CreateDate' => $this->date
                ];
                $this->db->table('gamelogdb.tb_channelstatistics')->add($ins_data);
            }
            $registernum = $this->db->table('tb_userinfo')
                ->where([
                    'ChannelID' => $val['channelid'],
                    //'DATE(RegTime)' => $this->date]
                    'RegTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                    ]
                )
                ->count();
            //SELECT RegMachine,RegTime,ChannelID FROM gamedb.tb_userinfo  ORDER BY RegTime
            $resultSQL = $this->db->table('tb_userinfo')
                ->field('RegMachine,RegTime,ChannelID')
                ->where(['ChannelID' => $val['channelid'], 'RegTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]])
                ->order('RegTime')
                ->fetchSql(true)->select();

            $sql = "( SELECT COUNT(RegMachine) num,ChannelID,RegTime FROM (".$resultSQL.") as a GROUP BY RegMachine ) as b";
            $autocephalynum = $this->db->table($sql)->where(['num' => 1])->count();
            $activenum = $this->db->table('tb_userinfo')
                ->where([
                    'ChannelID' => $val['channelid'],
                    //'DATE(LastLoginTime)' => $this->date
                    'LastLoginTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])
                ->count();

            $registerrnum = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) gold,COUNT(DISTINCT a.UserID) num')
                ->where([
                    'b.Status' => 1,
                    'a.ChannelID' => $val['channelid'],
                    'a.RegTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'b.CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])
                ->find();
            echo 'registerrum :'.var_export($registerrnum,true).
            //'RegisterRGlod' => $registerrnum['gold'], 'RechargeGlod' => $rechargenum['gold'],
            $rechargenum = $this->db->table('tb_recharge a')
                ->join('tb_userinfo as b ON a.UserID = b.UserID')
                ->field('SUM(a.Gold) gold,COUNT(DISTINCT a.UserID) num')
                ->where([
                    'a.Status' => 1,
                    'a.CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'b.ChannelID' => $val['channelid']
                ])->find();
            $officialrecharge = $this->db->table('tb_recharge a')
                ->join('tb_userinfo as b ON a.UserID = b.UserID')
                ->where([
                    'a.Status' => 1,
                    //'DATE(a.CreateTime)' => $this->date,
                    'a.CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'b.ChannelID' => $val['channelid'],
                    'a.PayMethod' => ['GT', 1]
                ])->sum('a.Gold');
            $agentrecharge = $this->db->table('tb_recharge a')
                ->join('tb_userinfo as b ON a.UserID = b.UserID')
                ->where([
                    'a.Status' => 1,
                    //'DATE(a.CreateTime)' => $this->date,
                    'a.CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'b.ChannelID' => $val['channelid'],
                    'a.PayMethod' => 1
                ])->sum('a.Gold');
            $exchangeglod = $this->db->table('tb_exchange a')
                ->join('tb_userinfo as b ON a.UserID = b.UserID')
                ->where([
                    'a.Status' => 1,
                    //'DATE(a.CreateTime)' => $this->date,
                    'a.CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'b.ChannelID' => $val['channelid']
                ])->sum('a.Gold');
            $systemtax = $this->db->table('gamelogdb.tb_loguser a')
                ->join('tb_userinfo as b ON a.UserID = b.UserID')
                ->where([
                    //'DATE(a.CreateTime)' => $this->date,
                    'a.CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'b.ChannelID' => $val['channelid']
                ])->sum('a.tax');
            $update_data = [
                'RegisterNum' => $registernum,
                'AutocephalyNum' => $autocephalynum,
                'ActiveNum' => $activenum,
                'RegisterRNum' => $registerrnum['num'],
                'RechargeNum' => $rechargenum['num'],
                'RegisterRGlod' => $registerrnum['gold'],
                'RechargeGlod' => $rechargenum['gold'],
                'OfficialRecharge' => $officialrecharge,
                'AgentRecharge' => $agentrecharge,
                'ExchangeGlod' => $exchangeglod,
                'SystemTax' => $systemtax,
            ];
            $update_where = [
                'ChannelID' => $val['channelid'],
                'CreateDate' => $this->date
            ];
            $this->db->table('gamelogdb.tb_channelstatistics')->where($update_where)->save($update_data);
        }
        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }

    /**
     * 留存
     */
    public function channel_retain_script()
    {
        Log::write('channel_retain_script runtime:' . date('Y-m-d H:i:s', time()));
        $channel = $this->db->table('tb_channel')->field('ChannelID')->select();
        foreach ($channel as $key => $val) {
            //次日留存
            /*$yesterdaynumsql = $this->db->table('tb_userinfo')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => date("Y-m-d", time() - 25 * 60 * 60)])
                ->fetchSql(true)->count();
            $yesterdaynum = $this->db->table('tb_userinfo')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => date("Y-m-d", time() - 25 * 60 * 60)])
                ->count();
            $todaynumsql = $this->db->table('tb_userinfo')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => date("Y-m-d", time() - 25 * 60 * 60), 'DATE(LastLoginTime)' => $this->date])
                ->fetchSql(true)->count();
            $todaynum = $this->db->table('tb_userinfo')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => date("Y-m-d", time() - 25 * 60 * 60), 'DATE(LastLoginTime)' => $this->date])
                ->count();
            Log::write('$yesterdaynum sql :' . $yesterdaynumsql);
            Log::write('$yesterdaynum date :' . date("Y-m-d", time() - 25 * 60 * 60));
            Log::write('$threedaynum date :' . date("Y-m-d", time() - 73 * 60 * 60));
            Log::write('$sevendaynum date :' . date("Y-m-d", time() - 169 * 60 * 60));
            Log::write('$yesterdaynum :' . $yesterdaynum);
            Log::write('$todaynumsql  :' . $todaynumsql);
            Log::write('$todaynum :' . $todaynum);
            if ($yesterdaynum) {
                $retain = round(($todaynum / $yesterdaynum) * 100, 2);
            } else {
                $retain = 0;
            }
            $update_data = [
                'DayRetain' => $retain,
            ];
            $update_where = [
                'ChannelID' => $val['channelid'],
                'CreateDate' => date("Y-m-d", time() - 25 * 60 * 60)
            ];
            $this->db->table('gamelogdb.tb_channelstatistics')->where($update_where)->save($update_data);
            */
            //3日留存
            $threedaynum = $this->db->table('tb_userinfo')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => date("Y-m-d", time() - 73 * 60 * 60)])
                ->count();
            $threenum = $this->db->table('tb_userinfo')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => date("Y-m-d", time() - 73 * 60 * 60), 'DATE(LastLoginTime)' => $this->date])
                ->count();
            if ($threedaynum) {
                $three = round(($threenum / $threedaynum) * 100, 2);
            } else {
                $three = 0;
            }
            $update_three_data = [
                'HighNum' => $three,
            ];
            $update_three_where = [
                'ChannelID' => $val['channelid'],
                'CreateDate' => date("Y-m-d")
            ];
            $this->db->table('gamelogdb.tb_channelstatistics')->where($update_three_where)->save($update_three_data);
            //7日留存
            $sevendaynum = $this->db->table('tb_userinfo')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => date("Y-m-d", time() - 169 * 60 * 60)])
                ->count();
            $sevennum = $this->db->table('tb_userinfo')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => date("Y-m-d", time() - 169 * 60 * 60), 'DATE(LastLoginTime)' => $this->date])
                ->count();
            if ($sevendaynum) {
                $seven = round(($sevennum / $sevendaynum) * 100, 2);
            } else {
                $seven = 0;
            }
            $update_seven_data = [
                'AvgNum' => $seven,
            ];
            $update_seven_where = [
                'ChannelID' => $val['channelid'],
                'CreateDate' => date("Y-m-d")
            ];
            $this->db->table('gamelogdb.tb_channelstatistics')->where($update_seven_where)->save($update_seven_data);
        }
        echo 'success';
        die;
    }

    /**
     * 渠道LTV　ＡＲＰＰＵ
     */
    public function channel_new_statistics_script()
    {
        set_time_limit(0);
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        $channel = $this->db->table('tb_channel')->field('ChannelID')->select();
        // 初始化 所用到的时间区间
        $yesterday_time = date('Y-m-d', strtotime($this->date) - 24 * 60 * 60);
        $three_time = date('Y-m-d', strtotime($this->date) - 2 * 24 * 60 * 60);
        $seven_time = date('Y-m-d', strtotime($this->date) - 6 * 24 * 60 * 60);
        $fifteen_time = date('Y-m-d', strtotime($this->date) - 14 * 24 * 60 * 60);
        $thirty_time = date('Y-m-d', strtotime($this->date) - 29 * 24 * 60 * 60);
        foreach ($channel as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_newchannelstatistics')->where(['channel_id' => $val['channelid'], 'create_date' => $this->date])->find();
            if (!$res) { //如果没有记录 就插入新数据
                $ins_data = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $this->date
                ];
                $this->db->table('gamelogdb.tb_newchannelstatistics')->add($ins_data);
            }
            $register_num = $this->db->table('tb_userinfo')->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => $this->date])->count(); //新增用户数
            $this->db->table('gamelogdb.tb_newchannelstatistics')->where(['channel_id' => $val['channelid'], 'create_date' => $this->date])->save(['register_num' => $register_num]);
            //当前渠道 新增用户当日充值金额跟  当日 还有多少在的充值人数
            $today_arppu_recharge = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) gold,COUNT(DISTINCT a.UserID) num')
                ->where([
                    'b.Status' => 1,
                    'a.ChannelID' => $val['channelid'],
                    //'DATE(a.RegTime)' => $this->date,
                    'a.RegTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    //'DATE(b.PayTime)' => $this->date
                    'b.PayTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])
                ->find();
            if ($today_arppu_recharge && ($today_arppu_recharge['gold'] || $today_arppu_recharge['num'])) {
                $update_data = [
                    'today_recharge_glod' => $today_arppu_recharge['gold'],
                    'today_recharge_num' => $today_arppu_recharge['num']
                ];
                $update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $this->date
                ];
                $today_arppu_recharge_update = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($update_where)->save($update_data);
                Log::write('channel_new_statistics arppu  update_wehre--' . var_export($update_where, true) . '----update_data---' . var_export($update_data, true) . '---update_result--' . $today_arppu_recharge_update, 'INFO');
            }

            //当前渠道 新增用户第3日充值金额跟  第3日 还有多少在的充值人数
            $three_arppu_recharge = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) gold,COUNT(DISTINCT a.UserID) num')
                ->where([
                    'b.Status' => 1,
                    'a.ChannelID' => $val['channelid'],
                    'DATE(a.RegTime)' => $three_time,
                    //'DATE(b.PayTime)' => $this->date
                    'b.PayTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])
                ->find();
            if ($three_arppu_recharge && ($three_arppu_recharge['gold'] || $three_arppu_recharge['num'])) {
                $update_data = [
                    'today_recharge_glod' => $three_arppu_recharge['gold'],
                    'today_recharge_num' => $three_arppu_recharge['num']
                ];
                $update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $three_time
                ];
                $three_arppu_recharge_update = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($update_where)->save($update_data);
                Log::write('channel_new_statistics arppu  update_wehre--' . var_export($update_where, true) . '----update_data---' . var_export($update_data, true) . '---update_result--' . $three_arppu_recharge_update, 'INFO');
            }
            //当前渠道 新增用户第7日充值金额跟  第7日 还有多少在的充值人数
            $seven_arppu_recharge = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) gold,COUNT(DISTINCT a.UserID) num')
                ->where([
                    'b.Status' => 1,
                    'a.ChannelID' => $val['channelid'],
                    'DATE(a.RegTime)' => $seven_time,
                    //'DATE(b.PayTime)' => $this->date
                    'b.PayTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])
                ->find();
            if ($seven_arppu_recharge && ($seven_arppu_recharge['gold'] || $seven_arppu_recharge['num'])) {
                $update_data = [
                    'today_recharge_glod' => $seven_arppu_recharge['gold'],
                    'today_recharge_num' => $seven_arppu_recharge['num']
                ];
                $update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $seven_time
                ];
                $seven_arppu_recharge_update = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($update_where)->save($update_data);
                Log::write('channel_new_statistics arppu  update_wehre--' . var_export($update_where, true) . '----update_data---' . var_export($update_data, true) . '---update_result--' . $seven_arppu_recharge_update, 'INFO');
            }
            //当前渠道 新增用户第15日充值金额跟  第15日 还有多少在的充值人数
            $fifteen_arppu_recharge = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) gold,COUNT(DISTINCT a.UserID) num')
                ->where([
                    'b.Status' => 1,
                    'a.ChannelID' => $val['channelid'],
                    'DATE(a.RegTime)' => $fifteen_time,
                    //'DATE(b.PayTime)' => $this->date
                    'b.PayTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])
                ->find();
            if ($fifteen_arppu_recharge && ($fifteen_arppu_recharge['gold'] || $fifteen_arppu_recharge['num'])) {
                $update_data = [
                    'today_recharge_glod' => $fifteen_arppu_recharge['gold'],
                    'today_recharge_num' => $fifteen_arppu_recharge['num']
                ];
                $update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $fifteen_time
                ];
                $fifteen_arppu_recharge_update = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($update_where)->save($update_data);
                Log::write('channel_new_statistics arppu  update_wehre--' . var_export($update_where, true) . '----update_data---' . var_export($update_data, true) . '---update_result--' . $fifteen_arppu_recharge_update, 'INFO');
            }
            //当前渠道 新增用户第30日充值金额跟  第30日 还有多少在的充值人数
            $thirty_arppu_recharge = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) gold,COUNT(DISTINCT a.UserID) num')
                ->where([
                    'b.Status' => 1,
                    'a.ChannelID' => $val['channelid'],
                    'DATE(a.RegTime)' => $thirty_time,
                    //'DATE(b.PayTime)' => $this->date
                    'b.PayTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])
                ->find();
            if ($thirty_arppu_recharge && ($thirty_arppu_recharge['gold'] || $thirty_arppu_recharge['num'])) {
                $update_data = [
                    'today_recharge_glod' => $thirty_arppu_recharge['gold'],
                    'today_recharge_num' => $thirty_arppu_recharge['num']
                ];
                $update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $thirty_time
                ];
                $thirty_arppu_recharge_update = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($update_where)->save($update_data);
                Log::write('channel_new_statistics arppu  update_wehre--' . var_export($update_where, true) . '----update_data---' . var_export($update_data, true) . '---update_result--' . $thirty_arppu_recharge_update, 'INFO');
            }

        }
        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }

    public function channel_new_statistics_ltv()
    {
        set_time_limit(0);
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        $channel = $this->db->table('tb_channel')->field('ChannelID')->select();
        // 初始化 所用到的时间区间
        $yesterday_time = date('Y-m-d', strtotime($this->date) - 24 * 60 * 60);
        $three_time = date('Y-m-d', strtotime($this->date) - 2 * 24 * 60 * 60);
        $seven_time = date('Y-m-d', strtotime($this->date) - 6 * 24 * 60 * 60);
        $fifteen_time = date('Y-m-d', strtotime($this->date) - 13 * 24 * 60 * 60); //14日 ltv
        $thirty_time = date('Y-m-d', strtotime($this->date) - 29 * 24 * 60 * 60);
        foreach ($channel as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_newchannelstatistics')->where(['channel_id' => $val['channelid'], 'create_date' => $this->date])->find();
            if (!$res) { //如果没有记录 就插入新数据
                $ins_data = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $this->date
                ];
                $this->db->table('gamelogdb.tb_newchannelstatistics')->add($ins_data);
            }
            // 3日充值总数 7 日充值总数，15日充值总数，30日充值总数
            //3日充值总数
            $three_recharge_total = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) total_gold')
                ->where(['b.Status' => 1, 'a.ChannelID' => $val['channelid'], 'DATE(a.RegTime)' => $three_time, 'DATE(b.PayTime)' => ['EGT', $three_time]])
                ->find();

            if ($three_recharge_total && $three_recharge_total['total_gold']) {
                $three_update_data = [
                    'three_day_recharge_total' => $three_recharge_total['total_gold']
                ];
                $three_update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $three_time
                ];
                $arppu_update_info = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($three_update_where)->save($three_update_data);
                Log::write('channel_new_statistics LTV  three_where--' . var_export($three_update_where, true) . '----update_data---' . var_export($three_update_data, true) . '---update_result--' . $arppu_update_info, 'INFO');
            }
            //7日充值总数
            $seven_recharge_total = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) total_gold')
                ->where(['b.Status' => 1, 'a.ChannelID' => $val['channelid'], 'DATE(a.RegTime)' => $seven_time, 'DATE(b.PayTime)' => ['EGT', $seven_time]])
                ->find();
            if ($seven_recharge_total && $seven_recharge_total['total_gold']) {
                $seven_update_data = [
                    'seven_day_recharge_total' => $seven_recharge_total['total_gold']
                ];
                $seven_update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $seven_time
                ];
                $arppu_update_info = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($seven_update_where)->save($seven_update_data);
                Log::write('channel_new_statistics LTV  seven_where--' . var_export($seven_update_where, true) . '----update_data---' . var_export($seven_update_data, true) . '---update_result--' . $arppu_update_info, 'INFO');
            }
            //15日充值总数
            $fifteen_recharge_total = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) total_gold')
                ->where(['b.Status' => 1, 'a.ChannelID' => $val['channelid'], 'DATE(a.RegTime)' => $fifteen_time, 'DATE(b.PayTime)' => ['EGT', $fifteen_time]])
                ->find();
            if ($fifteen_recharge_total && $fifteen_recharge_total['total_gold']) {
                $fifteen_update_data = [
                    'fifteen_day_recharge_total' => $fifteen_recharge_total['total_gold']
                ];
                $fifteen_update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $fifteen_time
                ];
                $arppu_update_info = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($fifteen_update_where)->save($fifteen_update_data);
                Log::write('channel_new_statistics LTV  fifteen_where--' . var_export($fifteen_update_where, true) . '----update_data---' . var_export($fifteen_update_data, true) . '---update_result--' . $arppu_update_info, 'INFO');
            }
            //30日充值总数
            $thirty_recharge_total = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) total_gold')
                ->where(['b.Status' => 1, 'a.ChannelID' => $val['channelid'], 'DATE(a.RegTime)' => $thirty_time, 'DATE(b.PayTime)' => ['EGT', $thirty_time]])
                ->find();
            if ($thirty_recharge_total && $thirty_recharge_total['total_gold']) {
                $thirty_update_data = [
                    'thirty_day_recharge_total' => $thirty_recharge_total['total_gold']
                ];
                $thirty_update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $thirty_time
                ];
                $arppu_update_info = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($thirty_update_where)->save($thirty_update_data);
                Log::write('channel_new_statistics LTV  where--' . var_export($thirty_update_where, true) . '----update_data---' . var_export($thirty_update_data, true) . '---update_result--' . $arppu_update_info, 'INFO');
            }
        }
        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }

    public function channel_new_statistics_keep()
    {
        set_time_limit(0);
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        $channel = $this->db->table('tb_channel')->field('ChannelID')->select();
        // 初始化 所用到的时间区间
        $yesterday_time = date('Y-m-d', strtotime($this->date) - 24 * 60 * 60);
        $three_time = date('Y-m-d', strtotime($this->date) - 3 * 24 * 60 * 60);
        $seven_time = date('Y-m-d', strtotime($this->date) - 7 * 24 * 60 * 60);
        $fifteen_time = date('Y-m-d', strtotime($this->date) - 15 * 24 * 60 * 60);
        $thirty_time = date('Y-m-d', strtotime($this->date) - 30 * 24 * 60 * 60);
        foreach ($channel as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_newchannelstatistics')->where(['channel_id' => $val['channelid'], 'create_date' => $this->date])->find();
            if (!$res) { //如果没有记录 就插入新数据
                $ins_data = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $this->date
                ];
                $this->db->table('gamelogdb.tb_newchannelstatistics')->add($ins_data);
            }
            // 留存人数
            /*  $keep_user = $this->db->table('tb_userinfo ')
                  ->field('count(UserID) user_num,DATE(RegTime)  reg_time,DATE(LastLoginTime) as login_time' )
                  ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => ['in', "$yesterday_time,$three_time,$seven_time,$fifteen_time,$thirty_time"], 'DATE(LastLoginTime)' => $this->date])
                  ->group('reg_time,login_time')
                  ->select();*/

            $yesterday_keep_user = $this->db->table('tb_userinfo ')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => $yesterday_time, 'DATE(LastLoginTime)' => $this->date])
                ->count();
            if ($yesterday_keep_user) {
                $yesterday_update_data = [
                    'next_day_keep' => $yesterday_keep_user,
                ];
                $yesterday_update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $yesterday_time
                ];
                $yesterday_keep_update_info = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($yesterday_update_where)->save($yesterday_update_data);
            }
            $three_keep_user = $this->db->table('tb_userinfo ')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => $three_time, 'DATE(LastLoginTime)' => $this->date])
                ->count();
            if ($three_keep_user) {
                $three_keep_update_data = [
                    'three_day_keep' => $three_keep_user,
                ];
                $three_update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $three_time
                ];
                $three_keep_update_info = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($three_update_where)->save($three_keep_update_data);
            }
            $seven_keep_user = $this->db->table('tb_userinfo ')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => $seven_time, 'DATE(LastLoginTime)' => $this->date])
                ->count();
            if ($seven_keep_user) {
                $seven_keep_update_data = [
                    'seven_day_keep' => $seven_keep_user,
                ];
                $seven_update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $seven_time
                ];
                $seven_keep_update_info = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($seven_update_where)->save($seven_keep_update_data);
            }
            $fifteen_keep_user = $this->db->table('tb_userinfo ')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => $fifteen_time, 'DATE(LastLoginTime)' => $this->date])
                ->count();
            if ($fifteen_keep_user) {
                $fifteen_keep_update_data = [
                    'fifteen_day_keep' => $fifteen_keep_user,
                ];
                $fifteen_update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $fifteen_time
                ];
                $fifteen_keep_update_info = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($fifteen_update_where)->save($fifteen_keep_update_data);
            }
            $thirty_keep_user = $this->db->table('tb_userinfo ')
                ->where(['ChannelID' => $val['channelid'], 'DATE(RegTime)' => $thirty_time, 'DATE(LastLoginTime)' => $this->date])
                ->count();
            if ($thirty_keep_user) {
                $thirty_keep_update_data = [
                    'thirty_day_keep' => $thirty_keep_user,
                ];
                $thirty_update_where = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $thirty_time
                ];
                $thirty_keep_update_info = $this->db->table('gamelogdb.tb_newchannelstatistics')->where($thirty_update_where)->save($thirty_keep_update_data);
            }

        }
        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }

    public function game_statistics_script()
    {
        Log::write('game_statistics_script runtime:' . date('Y-m-d H:i:s', time()));
        $game = $this->db->table('tb_childgamelist')->select();
        foreach ($game as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_gamestatistics')
                ->where([
                    'GameID' => $val['gameid'],
                    'ChildGameID' => $val['childgameid'],
                    'CreateDate' => $this->date
                ])
                ->find();
            if (!$res) {
                $ins_data = [
                    'GameID' => $val['gameid'],
                    'ChildGameID' => $val['childgameid'],
                    'CreateDate' => $this->date
                ];
                $this->db->table('gamelogdb.tb_gamestatistics')->add($ins_data);
            }
            $waste = $this->db->table('gamelogdb.tb_loggame')
                ->field('SUM(Waste) waste, SUM(Tax) tax')
                ->where([
                    'GameID' => $val['gameid'],
                    'ChildGameID' => $val['childgameid'],
                    //'DATE(CreateTime)' => $this->date
                    'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])
                ->find();
            $update_where = [
                'GameID' => $val['gameid'],
                'ChildGameID' => $val['childgameid'],
                'CreateDate' => $this->date
            ];
            $update_data = [
                'Waste' => $waste['waste'],
                'Revenue' => $waste['tax'],
            ];
            $this->db->table('gamelogdb.tb_gamestatistics')->where($update_where)->save($update_data);
        }
        echo 'success';
        die;
    }

    public function game_total_statistics_script()
    {
        Log::write('game_total_statistics_script runtime:' . date('Y-m-d H:i:s', time()));
        $date = date('Y-m-d', time() - 24 * 60 * 60);
        $game = $this->db->table('tb_childgamelist')->select();
        foreach ($game as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_gametotalstatistics')->where(['GameID' => $val['gameid'], 'ChildGameID' => $val['childgameid']])->find();
            if (!$res) {
                $ins_data = [
                    'GameID' => $val['gameid'],
                    'ChildGameID' => $val['childgameid'],
                ];
                $this->db->table('gamelogdb.tb_gametotalstatistics')->add($ins_data);
            }
            $waste = $this->db->table('gamelogdb.tb_loggame')
                ->field('SUM(Waste) waste, SUM(Tax) tax')
                ->where([
                    'GameID' => $val['gameid'],
                    'ChildGameID' => $val['childgameid'],
                    //'DATE(CreateTime)' => $date
                    'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])->find();
            $update_where = [
                'GameID' => $val['gameid'],
                'ChildGameID' => $val['childgameid'],
            ];
            $update_data = [
                'Waste' => ['exp', 'Waste +' . $waste['waste']],
                'Revenue' => ['exp', 'Waste +' . $waste['tax']],
            ];
            $this->db->table('gamelogdb.tb_gametotalstatistics')->where($update_where)->save($update_data);
        }
        echo 'success';
        die;
    }

    public function gameroom_statistics_script()
    {
        Log::write('gameroom_statistics_script runtime:' . date('Y-m-d H:i:s', time()));
        $game = $this->db->table('tb_childgamelist')->select();
        foreach ($game as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_gameroomstatistics')->where(['GameID' => $val['gameid'], 'ChildGameID' => $val['childgameid'], 'CreateDate' => $this->date])->find();
            if (!$res) {
                $ins_data = [
                    'GameID' => $val['gameid'],
                    'ChildGameID' => $val['childgameid'],
                    'CreateDate' => $this->date,
                    'HideTaxRatio' => $val['hidetaxratio'],
                    'TaxRatio' => $val['taxratio'],
                ];
                $this->db->table('gamelogdb.tb_gameroomstatistics')->add($ins_data);
            }
            $winnum = $this->db->table('gamelogdb.tb_loguser a')
                ->join('gamelogdb.tb_loggame b on a.GameLogIDX = b.IDX')
                ->field('SUM(a.Gold) gold,COUNT(DISTINCT a.UserID) num')
                ->where([
                    'b.GameID' => $val['gameid'],
                    'b.ChildGameID' => $val['childgameid'],
                    'a.Gold' => ['GT', 0],
                    'a.CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])->find();

            $totalnum = $this->db->table('gamelogdb.tb_loguser a')
                ->join('gamelogdb.tb_loggame b on a.GameLogIDX = b.IDX')
                ->field('SUM(a.Gold) gold,COUNT(DISTINCT a.UserID) num')
                ->where([
                    'b.GameID' => $val['gameid'],
                    'b.ChildGameID' => $val['childgameid'],
                    'a.Gold' => ['elt', 0],
                    //'DATE(a.CreateTime)' => $this->date
                    'a.CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']]
                ])->find();

            $update_where = [
                'GameID' => $val['gameid'],
                'ChildGameID' => $val['childgameid'],
                'CreateDate' => $this->date,
            ];
            $update_data = [
                'WinNum' => $winnum['num'],
                'TotalNum' => $totalnum['num'] + $winnum['num'],
                'WinGold' => $winnum['gold'],
                'LossGold' => abs($totalnum['gold']),
                'TotalGold' => $winnum['gold'] + abs($totalnum['gold'])
            ];
            $this->db->table('gamelogdb.tb_gameroomstatistics')->where($update_where)->save($update_data);
            sleep(2);
        }
        echo 'success';
        die;
    }

    public function paychannel_statistics_script()
    {
        Log::write('paychannel_statistics_script runtime:' . date('Y-m-d H:i:s', time()));
        $pay_channel = $this->db->table('tb_recharge_channel')->where(['Status' => 1])->select();
        foreach ($pay_channel as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_paychannelstatistics')->where(['PayChannelID' => $val['idx'], 'CreateDate' => $this->date])->find();
            if (!$res) {
                $ins_data = [
                    'PayChannelID' => $val['idx'],
                    'CreateDate' => $this->date
                ];
                $this->db->table('gamelogdb.tb_paychannelstatistics')->add($ins_data);
            }
            $recharge = $this->db->table('tb_recharge')
                ->field('SUM(Gold) gold,COUNT(*) num')
                ->where([
                    //'DATE(CreateTime)' => $this->date,
                    'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'Status' => 1, 'PayMethod' => ['GT', 1], 'PayChannelID' => $val['idx']])->find();
            $rechargeorder = $this->db->table('tb_recharge')->where([
                //'DATE(CreateTime)' => $this->date,
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                'PayMethod' => ['GT', 1], 'PayChannelID' => $val['idx']])->count();
            $alipay = $this->db->table('tb_recharge')
                ->field('SUM(Gold) gold,COUNT(*) num')
                ->where([
                    //'DATE(CreateTime)' => $this->date,
                    'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'Status' => 1, 'PayMethod' => 6, 'PayChannelID' => $val['idx']])->find();
            $alipayorder = $this->db->table('tb_recharge')->where([
                //'DATE(CreateTime)' => $this->date,
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                'PayMethod' => 6, 'PayChannelID' => $val['idx']])->count();
            $wxpay = $this->db->table('tb_recharge')
                ->field('SUM(Gold) gold,COUNT(*) num')
                ->where([
                    //'DATE(CreateTime)' => $this->date,
                    'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'Status' => 1, 'PayMethod' => 2, 'PayChannelID' => $val['idx']])->find();
            $wxpayorder = $this->db->table('tb_recharge')->where([
                //'DATE(CreateTime)' => $this->date,
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                'PayMethod' => 2, 'PayChannelID' => $val['idx']])->count();
            $qqpay = $this->db->table('tb_recharge')
                ->field('SUM(Gold) gold,COUNT(*) num')
                ->where([
                    //'DATE(CreateTime)' => $this->date,
                    'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'Status' => 1, 'PayMethod' => 4, 'PayChannelID' => $val['idx']])->find();
            $qqpayorder = $this->db->table('tb_recharge')->where([
                //'DATE(CreateTime)' => $this->date,
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                'PayMethod' => 4, 'PayChannelID' => $val['idx']])->count();
            $jdpay = $this->db->table('tb_recharge')
                ->field('SUM(Gold) gold,COUNT(*) num')
                ->where([
                    //'DATE(CreateTime)' => $this->date,
                    'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'Status' => 1, 'PayMethod' => 5, 'PayChannelID' => $val['idx']])->find();
            $jdpayorder = $this->db->table('tb_recharge')->where([
                //'DATE(CreateTime)' => $this->date,
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                'PayMethod' => 5, 'PayChannelID' => $val['idx']])->count();
            $unionpay = $this->db->table('tb_recharge')
                ->field('SUM(Gold) gold,COUNT(*) num')
                ->where([
                    //'DATE(CreateTime)' => $this->date,
                    'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                    'Status' => 1, 'PayMethod' => 3, 'PayChannelID' => $val['idx']])->find();
            $unionpayorder = $this->db->table('tb_recharge')->where([
                //'DATE(CreateTime)' => $this->date,
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                'PayMethod' => 3, 'PayChannelID' => $val['idx']])->count();
            $update_where = [
                'PayChannelID' => $val['idx'],
                'CreateDate' => $this->date
            ];
            $update_data = [
                'TotalRecharge' => $recharge['gold'],
                'TotalRechargeNum' => $recharge['num'],
                'TotalRechargeOrder' => $rechargeorder,
                'AlipayRecharge' => $alipay['gold'],
                'AlipayRechargeNum' => $alipay['num'],
                'AlipayRechargeOrder' => $alipayorder,
                'WxpayRecharge' => $wxpay['gold'],
                'WxpayRechargeNum' => $wxpay['num'],
                'WxpayRechargeOrder' => $wxpayorder,
                'QQpayRecharge' => $qqpay['gold'],
                'QQpayRechargeNum' => $qqpay['num'],
                'QQpayRechargeOrder' => $qqpayorder,
                'JdpayRecharge' => $jdpay['gold'],
                'JdpayRechargeNum' => $jdpay['num'],
                'JdpayRechargeOrder' => $jdpayorder,
                'UnionpayRecharge' => $unionpay['gold'],
                'UnionpayRechargeNum' => $unionpay['num'],
                'UnionpayRechargeOrder' => $unionpayorder,
            ];
            $this->db->table('gamelogdb.tb_paychannelstatistics')->where($update_where)->save($update_data);
        }
        echo 'success';
        die;
    }

    public function summary_statistics_script()
    {
        Log::write('summary_statistics_script runtime:' . date('Y-m-d H:i:s', time()));
        $Recharge = $this->db->table('tb_recharge')
            ->field('SUM(Gold) gold,COUNT(*) num')
            ->where([
                //'DATE(CreateTime)' => $this->date,
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                'Status' => 1, 'PayMethod' => ['GT', 1]])->select();
        $AlipayGold = $this->db->table('tb_recharge')->where([
            //'DATE(CreateTime)' => $this->date,
            'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            'Status' => 1, 'PayMethod' => 6])->sum('Gold');
        $WxpayGold = $this->db->table('tb_recharge')->where([
            //'DATE(CreateTime)' => $this->date,
            'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            'Status' => 1, 'PayMethod' => 2])->sum('Gold');
        $UnionpayGold = $this->db->table('tb_recharge')->where([
            //'DATE(CreateTime)' => $this->date,
            'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            'Status' => 1, 'PayMethod' => 3])->sum('Gold');
        $QQpayGold = $this->db->table('tb_recharge')->where([
            //'DATE(CreateTime)' => $this->date,
            'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            'Status' => 1, 'PayMethod' => 4])->sum('Gold');
        $JdpayGold = $this->db->table('tb_recharge')->where([
            //'DATE(CreateTime)' => $this->date,
            'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            'Status' => 1, 'PayMethod' => 5])->sum('Gold');
        $CardpayGold = $this->db->table('tb_recharge')->where([
            //'DATE(CreateTime)' => $this->date,
            'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            'Status' => 1, 'PayMethod' => 7])->sum('Gold');
        $Agentpay = $this->db->table('tb_recharge')
            ->field('SUM(Gold) gold,COUNT(*) num')
            ->where([
                //'DATE(CreateTime)' => $this->date,
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
                'Status' => 1, 'PayMethod' => 1])->find();
        $Exchange = $this->db->table('tb_exchange')
            ->field('SUM(Gold) money, SUM(Money) gold, COUNT(*) num')
            ->where([
                //'DATE(CreateTime)' => $this->date
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            ])->find();
        $Tax = $this->db->table('gamelogdb.tb_loggame')
            ->where([
                //'DATE(CreateTime)' => $this->date
                'CreateTime' => ['between', [$this->date.' 00:00:00', $this->date.' 23:59:59']],
            ])->sum('Tax');
        $data = [
            'RechargeOrderNum' => $Recharge['num'],
            'RechargeGold' => $Recharge['gold'],
            'AlipayGold' => $AlipayGold,
            'WxpayGold' => $WxpayGold,
            'QQpayGold' => $QQpayGold,
            'JdpayGold' => $JdpayGold,
            'UnionpayGold' => $UnionpayGold,
            'CardpayGold' => $CardpayGold,
            'AgentRechargeNum' => $Agentpay['num'],
            'AgentpayGold' => $Agentpay['gold'],
            'ExchangeOrderNum' => $Exchange['num'],
            'ExchangeOrderGold' => $Exchange['money'],
            'ExchangeGold' => $Exchange['gold'],
            'GameTax' => $Tax,
        ];
        $info = $this->db->table('gamelogdb.tb_summarystatistics')->where(['CreateDate' => $this->date])->find();
        if ($info) {
            $this->db->table('gamelogdb.tb_summarystatistics')->where(['CreateDate' => $this->date])->save($data);
        } else {
            $data['CreateDate'] = $this->date;
            $this->db->table('gamelogdb.tb_summarystatistics')->add($data);
        }
        echo 'success';
        die;
    }

    public function room_online_script()
    {
        $data = $this->db->table('gamelogdb.tb_roomstatistics')
            ->field('gameid,roomid,sum(playeronline) omlinenum')
            ->group('roomid')
            ->select();
        $date = date('Y-m-d H:i:s');
        foreach ($data as $key => $val) {
            $list[$key]['gameid'] = $val['gameid'];
            $list[$key]['roomid'] = $val['roomid'];
            $list[$key]['onlinenum'] = $val['omlinenum'];
            $list[$key]['createtime'] = $date;
        }
        if ($list) {
            $this->db->table('gamelogdb.tb_roomonlinerecord')->addAll($list);
        }
        echo 'success';
        die;
    }

    public function total_online_script()
    {
        $omlinenum = $this->db->table('gamelogdb.tb_roomstatistics')->sum('playeronline');
        $hallnum = $this->db->table('gamelogdb.tb_hallstatistics')->sum('playercount');
        $date = date('Y-m-d H:i:s');
        $data = [
            'hallnum' => $hallnum ? $hallnum : 0,
            'gamenum' => $omlinenum ? $omlinenum : 0,
            'createtime' => $date,
        ];
        if ($data) {
            $this->db->table('gamelogdb.tb_totalonlinerecord')->add($data);
        }
        echo 'success';
        die;
    }

    public function game_room_today_script()
    {
        Log::write('game_room_today_script runtime:' . date('Y-m-d H:i:s', time()));
        $gameroom = $this->db->table('tb_childgamelist')->field('ChildGameID,GameID')->select();
        foreach ($gameroom as $key => $val) {
            Db::startTrans();
            try {
                $where = ['ChildGameID' => $val['childgameid'], 'GameID' => $val['gameid']];
                $info = $this->db->table('gamelogdb.tb_gameroomstatistics_today')->where($where)->find();
                if (!$info) {
                    $insertdata = [
                        'GameID' => $val['gameid'],
                        'ChildGameID' => $val['childgameid'],
                    ];
                    $this->db->table('gamelogdb.tb_gameroomstatistics_today')->add($insertdata);
                }
                Log::write('tb_gameroomstatistics_today crontab :---' . date('Y-m-d H:i:s') . '---:' . json_encode($info));
                $update_where = [
                    'CreateDate' => $this->date,
                    'ChildGameID' => $val['childgameid'],
                    'GameID' => $val['gameid']
                ];
                $this->db->table('gamelogdb.tb_gameroomstatistics')->where($update_where)->save(['GameHideStock' => $info['gamehidestock'], 'GameTax' => $info['gametax']]);
                $this->db->table('gamelogdb.tb_gameroomstatistics_today')->where($where)->save(['GameHideStock' => 0, 'GameTax' => 0]);
                Db::commit();
            } catch (\Exception $e) {
                Log::write('game_room_today_script 事务错误：' . $e->getMessage(), 'error');
                // 回滚事务
                Db::rollback();
            }
        }
        echo 'success';
        die;
    }

    public function recharge_channel_info_script()
    {
        $channels = $this->db->table('tb_recharge_channel')->select();
        foreach ($channels as $key => $val) {
            if (!$val['infourl']) continue;
            switch ($val['englishname']) {
                case 'shanfutwo':
                    $time = time();
                    $sign = md5($val['account'] . $time . $val['token']);
                    $url = 'https://mbpay.9127pay.com/payment/seller/api/query_channel_status?merchant_id=' . $val['account'] . '&time=' . $time . '&sign=' . $sign;
                    $response = httpRequest($url, '', 'get');
                    Log::write('shanfutwo $response:' . $response);
                    $response = strstr($response, '{');
                    $data = json_decode($response, true);
                    $info = [];
                    $demo = ['wxpay', 'alipay', 'qqpay', 'jdpay', 'unionpay'];
                    foreach ($demo as $pay) {
                        if (array_key_exists($pay, $data)) {
                            $info[$pay]['min'] = $data[$pay]['min_price'];
                            $info[$pay]['max'] = $data[$pay]['max_price'];
                        } else {
                            $info[$pay] = 1;
                        }
                    }
                    Log::write('shanfutwo $info:' . json_encode($info));
                    break;
                case 'shanfuthree':
                    $time = time();
                    $requsetdata = [
                        'merchant_id' => $val['account'],
                        'op' => 'query_channel',
                    ];
                    $requsetjson = json_encode($requsetdata);
                    $sign = md5($requsetjson . $time . $val['token']);
                    $url = 'https://transpay.laitoy.com/xapi/merchant_query?time=' . $time . '&sign=' . $sign;
                    $response = httpRequest($url, $requsetjson, 'json');
                    Log::write('shanfuthree $response:' . $response);
                    $data = json_decode($response, true);
                    $info = [];
                    $demo = ['wxpay', 'alipay', 'qqpay', 'jdpay', 'unionpay'];
                    foreach ($demo as $pay) {
                        if (array_key_exists($pay, $data)) {
                            $info[$pay]['min'] = $data[$pay]['limit_min'];
                            $info[$pay]['max'] = $data[$pay]['limit_max'];
                        } else {
                            $info[$pay] = 1;
                        }
                    }
                    Log::write('shanfuthree $info:' . json_encode($info));
                    break;
                case 'shanfufour':
                    $time = time();
                    $sign = md5($val['account'] . ':' . $time . ':' . $val['token']);
                    $requsetdata = [
                        'merchant_id' => $val['account'],
                        'time' => $time,
                        'sign' => $sign,
                    ];
                    $url = 'https://web.363pay.com/api/v1/merchant/pay/status';
                    $response = httpRequest($url, $requsetdata, 'post');
                    Log::write('shanfufour $response:' . $response);
                    $response = strstr($response, '{');
                    $data = json_decode($response, true);
                    $info = [];
                    $demo = ['wxpay', 'alipay', 'qqpay', 'jdpay', 'unionpay'];
                    foreach ($demo as $pay) {
                        if (array_key_exists($pay, $data['data'])) {
                            $info[$pay]['min'] = $data['data'][$pay]['min_price'];
                            $info[$pay]['max'] = $data['data'][$pay]['max_price'];
                        } else {
                            $info[$pay] = 1;
                        }
                    }
                    Log::write('shanfufour $info:' . json_encode($info));
                    break;
            }
            $res = $this->db->table('tb_paychannelinfo')->where(['paychannelid' => $val['idx']])->find();
            if ($res) {
                $update_data = [
                    'wxpay' => json_encode($info['wxpay']),
                    'alipay' => json_encode($info['alipay']),
                    'qqpay' => json_encode($info['qqpay']),
                    'jdpay' => json_encode($info['jdpay']),
                    'unionpay' => json_encode($info['unionpay']),
                ];
                $this->db->table('tb_paychannelinfo')->where(['paychannelid' => $val['idx']])->save($update_data);
            } else {
                $insert_data = [
                    'paychannelid' => $val['idx'],
                    'payname' => $val['name'],
                    'wxpay' => json_encode($info['wxpay']),
                    'alipay' => json_encode($info['alipay']),
                    'qqpay' => json_encode($info['qqpay']),
                    'jdpay' => json_encode($info['jdpay']),
                    'unionpay' => json_encode($info['unionpay']),
                ];
                $this->db->table('tb_paychannelinfo')->add($insert_data);
            }
        }
        echo 'success';
        die;
    }

    /**
     * 今日统计  10分钟 跑一次
     */
    public function today_statistics()
    {
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        $early_timestamp = strtotime('00:00');
        if ($_GET['time'] && $_GET['key'] = 'allow_exec') {
            $today_time = date('Y-m-d', strtotime($_GET['time']));
        } else {
            // 脚本第一次执行时，而且与当前差 不超过 5分钟 刷新一下 昨天的数据
            if (S('today_statistics_first_exec') != 1 && (time()-$early_timestamp) <= 5*60 ) {
                $expire = strtotime("00:00") + 24 * 3600 - time();
                S('today_statistics_first_exec', 1, $expire);
                $today_time = date('Y-m-d', strtotime("00:00") - 24 * 3600); //昨天
            } else {
                $today_time = date('Y-m-d', time());
            }
        }
        $res = $this->db->table('gamelogdb.tb_today_statistics')->where(['DATE(create_date)' => $today_time])->find();
        if (!$res) { //如果没有记录 就插入新数据
            $ins_data = [
                'create_date' => $today_time
            ];
            $this->db->table('gamelogdb.tb_today_statistics')->add($ins_data);
        }
        //
        $register_where = [
            'DATE(RegTime)' => $today_time
        ];
        $data['register_num'] = $this->db->table('tb_userinfo')->where($register_where)->count();
        $data['login_user'] = $this->db->table('tb_userinfo')->where(['DATE(LastLoginTime)' => $today_time])->count('DISTINCT UserID');
        //安卓ios登录数
        //安卓ios登录数
        $os_login_user = $this->db->table('tb_userinfo a')
            ->join('tb_channel as b ON a.ChannelID = b.ChannelID')
            ->field('COUNT(DISTINCT a.UserID) num,b.OS os ')
            ->where([
                //'DATE(a.LastLoginTime)' => $today_time
                'a.LastLoginTime' => ['between', [$today_time.' 00:00:00', $today_time.' 23:59:59']]
            ])
            ->group('b.OS')
            ->select();
        $os_login_user = array_column($os_login_user, 'num', 'os');
        $data['ios_login'] = $os_login_user['1'];
        $data['android_login'] = $os_login_user['2'];

        $data['active_user'] = $this->db->table('gamelogdb.tb_loguser')->where(['CreateTime'=>['between',[$today_time.' 00:00:00', $today_time.' 23:59:59']]])->count('DISTINCT UserID');
        //绑定手机数
        $register_where['AccountType'] = 1;
        $data['bind_phone'] = $this->db->table('tb_userinfo')->where($register_where)->count();
        //绑定支付宝数
        $data['bind_alipay'] = $this->db->table('tb_setalipaybanklog')->where(['DATE(CreateTime)' => $today_time])->count();
        $data['today_bind_alipay'] = $this->db->table('tb_userinfo a')
            ->join('tb_setalipaybanklog as b ON a.UserID = b.UserID')
            ->where(['DATE(a.RegTime)' => $today_time, 'DATE(b.CreateTime)' => $today_time])
            ->count();
        // 最高在线数
        $high_online = $this->db->table('gamelogdb.tb_totalonlinerecord')->field('MAX(hallnum) as high_hallnum,MAX(gamenum) as high_gamenum')->where(['DATE(createtime)' => $today_time])->find();
        $data['high_online_hall'] = $high_online['high_hallnum'] ?? 0;
        $data['high_online_game'] = $high_online['high_gamenum'] ?? 0;
#############  充值 start #####################
        $recharge_where = [
            'Status' => 1,
            'DATE(PayTime)' => $today_time
        ];
        //总充值,总充值人数，笔数
        $total_recharge_data = $this->db->table('tb_recharge')
            ->field('COUNT(DISTINCT UserID) as num,sum(Gold) as total_gold,COUNT(1) as times ')
            ->where($recharge_where)
            ->find();
        $data['total_recharge_gold'] = $total_recharge_data['total_gold']??0;
        $data['total_recharge_user'] = $total_recharge_data['num']??0;
        $data['total_recharge_num'] = $total_recharge_data['times']??0;

        //当日注册并充值的用户数和充值金额
        $recharge_data = $this->db->table('tb_userinfo a')
            ->join('tb_recharge as b ON a.UserID = b.UserID')
            ->field('COUNT(DISTINCT a.UserID) recharge_num,sum(b.Gold) as gold_num')
            ->where(['b.Status' => 1, 'DATE(a.RegTime)' => $today_time, 'DATE(b.PayTime)' => $today_time])
            ->find();
        $data['new_recharge_user'] = $recharge_data['recharge_num'];
        $data['new_recharge_gold'] = $recharge_data['gold_num'];

        // 代理充值金额，充值人，笔数
        $recharge_agent_where = $recharge_where;
        $recharge_agent_where['PayMethod'] = 1;
        $agent_recharge_data = $this->db->table('tb_recharge')
            ->field('COUNT(DISTINCT UserID) as num,sum(Gold) as total_gold,COUNT(1) as times ')
            ->where($recharge_agent_where)
            ->find();
        $data['agent_recharge_gold'] = $agent_recharge_data['total_gold']??0;
        $data['agent_recharge_user'] = $agent_recharge_data['num']??0;
        $data['agent_recharge_num'] = $agent_recharge_data['times']??0;

        //新用户代理充值跟充值人数
        $new_agent_recharge_data = $this->db->table('tb_userinfo a')
            ->join('tb_recharge as b ON a.UserID = b.UserID')
            ->field('COUNT(DISTINCT a.UserID) recharge_user_num,sum(b.Gold) total_gold')
            ->where(['b.Status' => 1, 'b.PayMethod' => 1, 'DATE(a.RegTime)' => $today_time, 'DATE(b.PayTime)' => $today_time])
            ->find();
        //新用户代理充值金额和人数
        $data['new_agent_recharge_gold'] = $new_agent_recharge_data['total_gold']??0;
        $data['new_agent_recharge_user'] = $new_agent_recharge_data['recharge_user_num']??0;

        // 渠道充值总数，人数，笔数
        $recharge_channel_where = $recharge_where;
        $recharge_channel_where['PayMethod'] = ['Between', [2, 6]];
        $channel_recharge_data = $this->db->table('tb_recharge')
            ->field('COUNT(DISTINCT UserID) as num,sum(Gold) as total_gold,COUNT(1) as times ')
            ->where($recharge_channel_where)
            ->find();
        $data['channel_recharge_gold'] = $channel_recharge_data['total_gold']??0;
        $data['channel_recharge_user'] = $channel_recharge_data['num']??0;
        $data['channel_recharge_num'] = $channel_recharge_data['times']??0;

        //新用户渠道跟充值人数
        $new_channel_recharge_data = $this->db->table('tb_userinfo a')
            ->join('tb_recharge as b ON a.UserID = b.UserID')
            ->field('COUNT(DISTINCT a.UserID) recharge_user_num,sum(b.Gold) total_gold')
            ->where(['b.Status' => 1, 'b.PayMethod' => ['Between', [2, 6]], 'DATE(a.RegTime)' => $today_time, 'DATE(b.PayTime)' => $today_time])
            ->find();
        //新用户渠道充值金额和人数
        $data['new_channel_recharge_gold'] = $new_channel_recharge_data['total_gold']??0;
        $data['new_channel_recharge_user'] = $new_channel_recharge_data['recharge_user_num']??0;

        ######## 充值 end #############

        ######## 兑换 start ###########
        //总兑换
        $exchange_where = [
            'Status' => 1,
            'DATE(CreateTime)' => $today_time
        ];
        $exchange_data = $this->db->table('tb_exchange')
            ->field('SUM(Gold) as total_gold,COUNT(DISTINCT UserID) as num, COUNT(1) as times,SUM(Fee) as total_fee')
            ->where($exchange_where)
            ->find();
        $data['tixian_gold'] = $exchange_data['total_gold']??0;
        $data['tixian_user'] = $exchange_data['num']??0;
        $data['tixian_num'] = $exchange_data['times']??0;
        $data['tixian_fee'] = $exchange_data['total_fee']??0;


        ######## 系统 start ###########
          $tax_waste_data =  $this->db->table('gamelogdb.tb_loggame')
              ->field('sum(Tax) as total_tax,sum(Waste) as total_waste')
              ->where(['DATE(CreateTime)'=>$today_time])
              ->find();
          $data['tax'] =$tax_waste_data['total_tax']??0;
          $data['waste'] =$tax_waste_data['total_waste']??0;
          //代理进货金额， 跟进货次数
        $agent_stock_data = $this->db->table('tb_agentorder')
            ->field('SUM(Gold) as total_gold,COUNT(1) as num')
            ->where(['Status'=>1,'DATE(CreateTime)'=>$today_time])
            ->find();
       $data['agent_stock_gold'] = $agent_stock_data['total_gold'];
       $data['agent_stock_times']=$agent_stock_data['num'];


        $data['allagent_rnum'] = $this->db->table('tb_groom_contact as ar')
            ->join('tb_userinfo as u ON ar.uid = u.UserID')
            ->where(['ar.create_time' => ['between', [$today_time.' 00:00:00', $today_time.' 23:59:59']]])
            ->where(['u.RegTime' => ['between', [$today_time.' 00:00:00', $today_time.' 23:59:59']]])->count();
        $ymd = data('Ymd',strtotime($today_time));
        $allagent = $this->db->table('gamelogdb.tb_qmagent_profit')
            ->field('SUM(lower_commision) as tax,SUM(lower_profit) as lpro,SUM(llower_profit) as llpro')
            ->where(['ymd'=>$ymd])
            ->find();
        $data['allagent_profit'] = $allagent['lpro'] + $allagent['llpro'];
        $data['allagent_tax'] = $allagent['tax'];

        $this->db->table('gamelogdb.tb_today_statistics')->where(['DATE(create_date)' => $today_time])->save($data);

        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }

    /**
     *  各游戏统计
     */
    public function today_game_statistics(){
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        if ($_GET['time'] && $_GET['key'] = 'allow_exec') {
            $today_time = date('Y-m-d', strtotime($_GET['time']));
        } else {
            // 脚本第一次执行时，刷新一下 昨天的数据
            if (S('today_game_statistics_first_exec') != 1) {
                $expire = strtotime("00:00") + 24 * 3600 - time();
                S('today_game_statistics_first_exec', 1, $expire);
                $today_time = date('Y-m-d', strtotime("00:00") - 24 * 3600); //昨天
            } else {
                $today_time = date('Y-m-d', time());
            }
        }
        // 查询 所有游戏列表
        $game_list = $this->db->table('tb_gamelist')->field('GameID')->select();
        foreach ($game_list as $val){
            $res = $this->db->table('gamelogdb.tb_today_game_statistics')->where(['DATE(create_date)' => $today_time,'game_id'=>$val['gameid']])->find();
            if (!$res) { //如果没有记录 就插入新数据
                $ins_data = [
                    'create_date' => $today_time,
                    'game_id' => $val['gameid']
                ];
                $this->db->table('gamelogdb.tb_today_game_statistics')->add($ins_data);
            }
            // 查询每款游戏今日的活跃用户
           $active_user = $this->db->table('gamelogdb.tb_loguser a')
                ->join('gamelogdb.tb_loggame b ON a.GameLogIDX = b.IDX')
                ->field('COUNT(DISTINCT a.UserID) as active_user')
                ->where([
                    'b.GameID'=>$val['gameid'],
                    //'DATE(a.CreateTime)'=>$today_time
                    'a.CreateTime'=>['between', [$today_time.' 00:00:00', $today_time.' 23:59:59']]
                ])
                ->find();
           $data['active_user'] = $active_user['active_user']??0;
           //查询今日 该游戏的税收与盈亏
            $active_user = $this->db->table('gamelogdb.tb_loggame')
                ->field('SUM(Waste) as total_waste,SUM(Tax) as total_tax')
                ->where(['GameID'=>$val['gameid'],'DATE(CreateTime)'=>$today_time])
                ->find();
            $data['tax'] =$active_user['total_tax']??0;
            $data['waste'] =$active_user['total_waste']??0;
            $this->db->table('gamelogdb.tb_today_game_statistics')->where(['create_date' => $today_time,'game_id' => $val['gameid']])->save($data);
        }


        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }

    /**
     *  用户留存， 反着来
     */
    public function user_keep_statistics()
    {

        set_time_limit(0);
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        $early_timestamp = strtotime('00:00');
        if ($_GET['time'] && $_GET['key'] = 'allow_exec') {
            $today_time = date('Y-m-d', strtotime($_GET['time']));
        } else {
            // 脚本第一次执行时，并且与当前时间 不差 20分钟，刷新一下 昨天的数据
            if ((time() - $early_timestamp) <= 20*60 ) {
                $today_time = date('Y-m-d', strtotime("00:00") - 24 * 3600); //昨天
            } else {
                $today_time = date('Y-m-d', time());
            }
        }
        //初始化 日期，
        $thirty_day=  date('Y-m-d',strtotime('-30 day',strtotime($today_time)));
        $fourteen_day=  date('Y-m-d',strtotime('-14 day',strtotime($today_time)));
        $ten_day=  date('Y-m-d',strtotime('-10 day',strtotime($today_time)));
        // LTV　专用时间
        $seven_time =date('Y-m-d',strtotime('-6 day',strtotime($today_time)));
        $fourteen_day_time =date('Y-m-d',strtotime('-13 day',strtotime($today_time)));
        $thirty_day_time =date('Y-m-d',strtotime('-29 day',strtotime($today_time)));
        $date = $this->getDateFromRange($ten_day,$today_time);
        array_unshift($date,$thirty_day,$fourteen_day);
        $ltv_date =$date;
        //array_unshift($ltv_date,$fourteen_day_time,$thirty_day_time); //暂时去掉
        $ltv_date = array_unique($ltv_date);
        sort($ltv_date);
        $fields=['thirty_day_keep','fourteen_day_keep','ten_day_keep','nine_day_keep','eight_day_keep','seven_day_keep','six_day_keep','five_day_keep','four_day_keep','three_day_keep','two_day_keep','yesterday_keep'];
        $combine= array_combine($date,$fields);

        $channel = $this->db->table('tb_channel')->field('ChannelID')->select();
        // 初始化 所用到的时间区间
        foreach ($channel as $key => $val) {
            $res = $this->db->table('gamelogdb.tb_user_keep_statistics')->where(['channel_id' => $val['channelid'], 'create_date' => $today_time])->find();
            if (!$res) { //如果没有记录 就插入新数据
                $ins_data = [
                    'channel_id' => $val['channelid'],
                    'create_date' => $today_time
                ];
                $this->db->table('gamelogdb.tb_user_keep_statistics')->add($ins_data);
            }
            // 几天前 注册 今天登录的数据
           $keep_data = $this->db->table('tb_userinfo')
                ->field('COUNT(UserID) as login_user,DATE(RegTime) as reg_time,DATE(LastLoginTime) as login_time')
                ->where(['DATE(LastLoginTime)'=>$today_time,'DATE(RegTime)'=>['in',$date],'ChannelID'=>$val['channelid']])
                ->group('reg_time')
                ->select();
            $register_data =$this->db->table('tb_userinfo')
                ->field('COUNT(UserID) as register_user,DATE(RegTime) as reg_time')
                ->where(['DATE(RegTime)'=>['in',$ltv_date],'ChannelID'=>$val['channelid']])
                ->group('reg_time')
                ->select();
            $kepp_data_format = array_column($keep_data,'login_user','reg_time');
            $register_data_format = array_column($register_data,'register_user','reg_time');
            $update_where=['channel_id'=>$val['channelid'],'create_date'=>$today_time];
            $update_data=[];
            foreach ($combine as $k=>$v){
                if($kepp_data_format[$k] && $register_data_format[$k]){
                    $update_data[$v] =round(($kepp_data_format[$k]/$register_data_format[$k])*10000);
                }else{
                    continue;
                }
            }


            //ltv
            //7日充值总数
         /*   $seven_recharge_total = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) total_gold')
                ->where(['b.Status' => 1, 'a.ChannelID' => $val['channelid'], 'DATE(a.RegTime)' => $seven_time, 'b.PayTime' => ['BETWEEN', [$seven_time .' 00:00:00', $today_time.' 23:59:59']]])
                ->find();
            if ($seven_recharge_total && $seven_recharge_total['total_gold'] && $register_data_format[$seven_time]) {
                $update_data = [
                    'seven_day_ltv' => round((($seven_recharge_total['total_gold']/C('goldunit'))/$register_data_format[$seven_time])*100)
                ];
            }*/
            //14日充值总数
           /* $fourteen_recharge_total = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) total_gold')
                ->where(['b.Status' => 1, 'a.ChannelID' => $val['channelid'], 'DATE(a.RegTime)' => $fourteen_day_time, 'b.PayTime' => ['BETWEEN', [$fourteen_day_time .' 00:00:00', $today_time.' 23:59:59']]])
                ->find();
            if ($fourteen_recharge_total && $fourteen_recharge_total['total_gold'] && $register_data_format[$fourteen_day_time]) {
                $update_data = [
                    'fourteen_day_ltv' => round((($fourteen_recharge_total['total_gold']/C('goldunit'))/$register_data_format[$fourteen_day_time])*100)
                ];
            }*/
            //30日充值总数
           /* $thirty_recharge_total = $this->db->table('tb_userinfo a')
                ->join('tb_recharge as b ON a.UserID = b.UserID')
                ->field('SUM(b.Gold) total_gold')
                ->where(['b.Status' => 1, 'a.ChannelID' => $val['channelid'], 'DATE(a.RegTime)' => $thirty_day_time, 'b.PayTime' => ['BETWEEN', [$thirty_day_time .' 00:00:00', $today_time.' 23:59:59']]])
                ->find();
            if ($thirty_recharge_total && $thirty_recharge_total['total_gold'] && $register_data_format[$thirty_day_time]) {
                $update_data = [
                    'thirty_day_ltv' => round((($thirty_recharge_total['total_gold']/C('goldunit'))/$register_data_format[$thirty_day_time])*100)
                ];
            }
           */
            if($update_data){
                $this->db->table('gamelogdb.tb_user_keep_statistics')->where($update_where)->save($update_data);
            }
            // 调试
           /* echo '渠道id:'.$val['channelid'].PHP_EOL;
            echo 'keep_data:'.var_export($keep_data,true).PHP_EOL;
            echo 'register_data:'.var_export($register_data,true).PHP_EOL;
            echo 'update_where:'.var_export($update_where,true).PHP_EOL;
            echo 'update_data:'.var_export($update_data,true).PHP_EOL;*/
        }

        // //不分渠道总计插入
        $update_where=['channel_id'=>0,'create_date'=>$today_time];
        $update_data=[];
        $res = $this->db->table('gamelogdb.tb_user_keep_statistics')->where(['channel_id' => 0, 'create_date' => $today_time])->find();
        if(!$res){
            $this->db->table('gamelogdb.tb_user_keep_statistics')->add([ 'channel_id' => 0, 'create_date' => $today_time]);
        }

        $keep_data = $this->db->table('tb_userinfo')
            ->field('COUNT(UserID) as login_user,DATE(RegTime) as reg_time,DATE(LastLoginTime) as login_time')
            ->where(['DATE(LastLoginTime)'=>$today_time,'DATE(RegTime)'=>['in',$date]])
            ->group('reg_time')
            ->select();
        $register_data =$this->db->table('tb_userinfo')
            ->field('COUNT(UserID) as register_user,DATE(RegTime) as reg_time')
            ->where(['DATE(RegTime)'=>['in',$ltv_date]])
            ->group('reg_time')
            ->select();
        $kepp_data_format = array_column($keep_data,'login_user','reg_time');
        $register_data_format = array_column($register_data,'register_user','reg_time');

        foreach ($combine as $k=>$v){
            if($kepp_data_format[$k] && $register_data_format[$k]){
                $update_data[$v] =round(($kepp_data_format[$k]/$register_data_format[$k])*10000);
            }else{
                continue;
            }
        }
        //ltv
        //7日充值总数
      /*  $seven_recharge_total = $this->db->table('tb_userinfo a')
            ->join('tb_recharge as b ON a.UserID = b.UserID')
            ->field('SUM(b.Gold) total_gold')
            ->where(['b.Status' => 1, 'DATE(a.RegTime)' => $seven_time, 'b.PayTime' => ['BETWEEN', [$seven_time .' 00:00:00', $today_time.' 23:59:59']]])
            ->find();
        if ($seven_recharge_total && $seven_recharge_total['total_gold'] && $register_data_format[$seven_time]) {
            $update_data = [
                'seven_day_ltv' => round((($seven_recharge_total['total_gold']/C('goldunit'))/$register_data_format[$seven_time])*100)
            ];
        }
        //14日充值总数
        $fourteen_recharge_total = $this->db->table('tb_userinfo a')
            ->join('tb_recharge as b ON a.UserID = b.UserID')
            ->field('SUM(b.Gold) total_gold')
            ->where(['b.Status' => 1,'DATE(a.RegTime)' => $fourteen_day_time, 'b.PayTime' => ['BETWEEN', [$fourteen_day_time .' 00:00:00', $today_time.' 23:59:59']]])
            ->find();
        if ($fourteen_recharge_total && $fourteen_recharge_total['total_gold'] && $register_data_format[$fourteen_day_time]) {
            $update_data = [
                'fourteen_day_ltv' => round((($fourteen_recharge_total['total_gold']/C('goldunit'))/$register_data_format[$fourteen_day_time])*100)
            ];
        }
        //30日充值总数
        $thirty_recharge_total = $this->db->table('tb_userinfo a')
            ->join('tb_recharge as b ON a.UserID = b.UserID')
            ->field('SUM(b.Gold) total_gold')
            ->where(['b.Status' => 1,  'DATE(a.RegTime)' => $thirty_day_time, 'b.PayTime' => ['BETWEEN', [$thirty_day_time .' 00:00:00', $today_time.' 23:59:59']]])
            ->find();
        if ($thirty_recharge_total && $thirty_recharge_total['total_gold'] && $register_data_format[$thirty_day_time]) {
            $update_data = [
                'thirty_day_ltv' => round((($thirty_recharge_total['total_gold']/C('goldunit'))/$register_data_format[$thirty_day_time])*100)
            ];
        }*/

        if($update_data){
            $this->db->table('gamelogdb.tb_user_keep_statistics')->where($update_where)->save($update_data);
        }

      /*  echo '总keep_data:'.var_export($keep_data,true).PHP_EOL;
        echo '总register_data:'.var_export($register_data,true).PHP_EOL;
        echo '总update_where:'.var_export($update_where,true).PHP_EOL;
        echo '总update_data:'.var_export($update_data,true).PHP_EOL;*/
        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }

    /**
     * @param $start_date
     * @param $end_date
     * @return array
     */
    private function getDateFromRange($start_date, $end_date){

        $start_timestamp = strtotime($start_date);
        $end_timestamp = strtotime($end_date);
        // 计算日期段内有多少天
        $days = ($end_timestamp-$start_timestamp)/86400;
        // 保存每天日期
        $date = array();
        for($i=0; $i<$days; $i++){
            $date[] = date('Y-m-d', $start_timestamp+(86400*$i));
        }

        return $date;
    }

    /**
     * 全民代理业绩统计 一天 跑一次
     */
    public function allgent_performance_day(){
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        
        //$yestoday = date('Ymd',strtotime(date('Y-m-d H:i:s')) - 86400);
        //$yestime = date('Y-m-d',strtotime($yestoday));
        $yestoday = date('Ymd',strtotime(date('2019-05-16'))-86400);
        $yestime = date('Y-m-d',strtotime($yestoday));
        $yweek = date('oW',strtotime($yestoday));
        $sql = "SELECT b.UserID as userid,SUM(ABS(b.Gold)) as liushui,c.GameID as gameid,a.* FROM gamelogdb.tb_loguser as b LEFT JOIN gamelogdb.tb_loggame AS c ON c.IDX = b.GameLogIDX LEFT JOIN gamedb.tb_groom_contact AS a ON a.uid = b.UserID WHERE b.CreateTime >= '".$yestime." 00:00:00' AND b.CreateTime <= '".$yestime." 23:59:59' AND b.UserID IN (SELECT uid FROM gamedb.tb_groom_contact WHERE parent_id != 0) GROUP BY b.UserID,c.GameID";
        $result = Db::query($sql);
        $reward = [];
        $gameresult = [];
        if($result){
            $gamelist = Db::query('SELECT GameID as gameid,Percent as percent FROM gamedb.tb_gamelist');
            $liushuip = [];
            foreach($gamelist as $key=>$val){
                $liushuip[$val['gameid']] = $val['percent'];
            }
            foreach($result as $k=>$v){
                if(isset($liushuip[$v['gameid']]) && $liushuip[$v['gameid']] > 0){
                    $tmp_liushui = $v['liushui'] * $liushuip[$v['gameid']] / 100;

                    if(isset($gameresult[$v['userid']]['zero'])){
                        $gameresult[$v['userid']]['zero'] += $tmp_liushui;
                    }else{
                        $gameresult[$v['userid']]['zero'] = $tmp_liushui;
                    }

                    if($v['parent_id'] > 0){
                        if(isset($gameresult[$v['parent_id']]['one'])){
                            $gameresult[$v['parent_id']]['one'] += $tmp_liushui;
                        }else{
                            $active[$v['parent_id']] += $tmp_liushui;
                            $gameresult[$v['parent_id']]['one'] = $tmp_liushui;
                        }
                    }
                    if($v['farth_id'] > 0){
                        if(isset($gameresult[$v['farth_id']]['two'])){
                            $gameresult[$v['farth_id']]['two'] += $tmp_liushui;
                        }else{
                            $gameresult[$v['farth_id']]['two'] = $tmp_liushui;
                        }
                    }
                    if($v['farth_id_one'] > 0){
                        if(isset($gameresult[$v['farth_id_one']]['three'])){
                            $gameresult[$v['farth_id_one']]['three'] += $tmp_liushui;
                        }else{
                            $gameresult[$v['farth_id_one']]['three'] = $tmp_liushui;
                        }
                    }
                    if($v['farth_id_two'] > 0){
                        if(isset($gameresult[$v['farth_id_two']]['four'])){
                            $gameresult[$v['farth_id_two']]['four'] += $tmp_liushui;
                        }else{
                            $gameresult[$v['farth_id_two']]['four'] = $tmp_liushui;
                        }
                    }
                    if($v['farth_id_three'] > 0){
                        if(isset($gameresult[$v['farth_id_two']]['five'])){
                            $gameresult[$v['farth_id_three']]['five'] += $tmp_liushui;
                        }else{
                            $gameresult[$v['farth_id_three']]['five'] = $tmp_liushui;
                        }
                    }
                }
            }
        }
        if($gameresult){
            Db::startTrans();
            try{
                Db::execute('DELETE FROM gamelogdb.tb_qmagent_performance_day WHERE ymd = '.$yestoday);
                $check = Db::query('SELECT id FROM gamelogdb.tb_qmagent_performance_day ORDER BY id DESC LIMIT 1');
                $maxid = $check?$check[0]['id']:0;
                $insertsql = "INSERT INTO gamelogdb.tb_qmagent_performance_day (`id`,`uid`,`ymd`,`zero`,`one`,`two`,`three`,`four`,`five`,`team`,`active_num`,`yweek`) VALUES ";
                foreach($gameresult as $k=>$v){
                    $maxid++;
                    $zero = isset($v['zero'])?$v['zero']:0;
                    $one = isset($v['one'])?$v['one']:0;
                    $two = isset($v['two'])?$v['two']:0;
                    $three = isset($v['three'])?$v['three']:0;
                    $four = isset($v['four'])?$v['four']:0;
                    $five = isset($v['five'])?$v['five']:0;
                    $team = $one+$two+$three+$four+$five;
                    $active_num = isset($active[$k])?$active[$k]:0;
                    $insertsql .= "(".$maxid.",".$k.",".$yestoday.",".$zero.",".$one.",".$two.",".$three.",".$four.",".$five.",".$team.",".$active_num.",".$yweek."),";
                }
                Db::execute(substr($insertsql,0,-1).";");
                
                Db::commit();
            }catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
        }
        
        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }

    /**
     * 全民代理奖励统计 一周 跑一次
     */
    public function allagent_reward(){
        G('begin');

        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        $start = date("Y-m-d 00:00:00",mktime(0, 0 , 0,date("m"),date("d")-date("w")+1-7,date("Y")));
        $end = date("Y-m-d 23:59:59",mktime(23,59,59,date("m"),date("d")-date("w")+7-7,date("Y")));
        $yweek = date('oW',strtotime($start));
        $check = Db::query('SELECT * FROM gamelogdb.tb_qmagent_performance_week WHERE yweek = '.$yweek.' LIMIT 1');
        if(!$check){
            $result = Db::query('SELECT a.uid,sum(a.zero) as zero,sum(a.one) as one,sum(a.two) as two,sum(a.three) as three,sum(a.four) as four,sum(a.five) as five,sum(a.team) as team,b.ChannelID as channelid FROM gamelogdb.tb_qmagent_performance_day AS a LEFT JOIN gamedb.tb_userinfo AS b ON b.UserID = a.uid WHERE DATE(ymd) >= "'.$start.'" AND DATE(ymd) <= "'.$end.'" GROUP BY uid');
            $time = date('Y-m-d H:i:s');
            $reward_ratio = Db::query('SELECT * FROM gamelogdb.tb_qmagent_reward_ratio');
            $ischannel = [];
            $tmpratio = [];
            foreach($reward_ratio as $k=>$v){
                $tmpratio[$v['channelid']][] = $v;
                if(!in_array($v['channelid'],$ischannel)){
                    $ischannel[] = $v['channelid'];
                }
            }
            $this->reward_ratio = $tmpratio;
            $this->ischannel =  $ischannel;
            $isql = $isqlbase = 'INSERT INTO gamelogdb.tb_qmagent_performance_week (`uid`,`zero`,`team`,`reward`,`yweek`,`create_time`,`is_exchange`) VALUES ';
            $usql = $usqlbase = 'UPDATE gamedb.tb_userinfoext SET InsureGold = CASE UserID ';
            $i = 0;
            if($result){
                Db::startTrans();
                try{
                    foreach($result as $k=>$v){
                       $reward = $this->comparison($v);
                       if($reward > 0){
                            $i++;
                            $isql .= '('.$v["uid"].','.$v['zero'].','.$v["team"].','.$reward.','.$yweek.',"'.$time.'",1),';
                            $usql .= ' WHEN '.$v['uid'].' THEN InsureGold+'.$reward;
                            $ids .= $v['uid'].',';
                       }
                    }
                    if($i>0){
                        $usql .= ' END WHERE UserID IN ('.substr($ids,0,-1).');';
                        Db::execute(substr($isql,0,-1).";");
                        Db::execute($usql);
                    }
                    
                    Db::commit();
                }catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                }
            }

            $activesql = 'SELECT b.uid,b.parent_id FROM gamelogdb.tb_loguser as b LEFT JOIN gamedb.tb_groom_contact AS a ON a.uid = b.UserID WHERE b.CreateTime >= "'.$start.'" AND b.CreateTime <= "'.$end.'" AND b.UserID IN (SELECT uid FROM gamedb.tb_groom_contact WHERE parent_id != 0) GROUP BY b.UserID';
            $activeres = Db::query($activesql);
            if($activeres){
                foreach($activeres as $k=>$v){

                }
            }
        }
        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success:'.$i;
        die;
    }

    /*
        判断奖励金额
    */
    public function comparison($data){
        if(in_array($data['channelid'],$this->ischannel)){
            $result = $this->reward_ratio[$data['channelid']];
        }else{
            $result = $this->reward_ratio[0];
        }
        $reward = 0;
        $amount = 0;
        foreach($result as $k=>$v){
            $tmpa = explode(',',$v['interval']);
            if(($data['team']>=$tmpa[0] && $tmpa[1]>$data['team']) || ($data['team']>=$tmpa[0] && $tmpa[1]==0)){
                $amount = $v['reward'];
                break;
            }
        }
        if($amount > 0){
            $teamarr = ['one'=>$data['one'],'two'=>$data['two'],'three'=>$data['three'],'four'=>$data['four'],'five'=>$data['five']];
            foreach($teamarr as $key=>$val){
                foreach($result as $k=>$v){
                    $tmpa = explode(',',$v);
                    if(($val>=$tmpa[0] && $tmpa[1]>$val) || ($val>=$tmpa[0] && $tmpa[1]==0)){
                        if($key=="one"){
                            $reward += $val/10000/C('goldunit')*$amount;
                        }else{
                            $reward += $val/10000/C('goldunit')*($amount - $v['reward']);
                        }
                        break;
                    }
                }
            }
        }
        return $reward;
    }

    //每30分钟跑一次 统计用户收益 和他下面所有代理的收益
    public function profit_tox_data()
    {
         G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        $today = date('Y-m-d');
        $ymd = date('Ymd');
        
        $timeday = strtotime(date('Y-m-d H:i:s')) - 1860;
        $today0 = strtotime(date('Y-m-d 00:00:00'));
        //$apiconfig = include('/data/www/pay/application/config.php');
        $res = $this->db->table('gamelogdb.tb_qmagent_v3_performance_day')->where(['ymd'=>$ymd])->order('id DESC')->find();

        Db::startTrans();
        try{
            if (!$res && $timeday < $today0){
                $today = date('Y-m-d',strtotime(date('Y-m-d H:i:s')) - 86400);
                $ymd = date('Ymd',strtotime($today));
                $this->update_ratio();
            }
            
            $todaytaxs = $this->db->table('gamelogdb.tb_loguser a')
                              ->join('tb_qmagent_v3_groom_contact b ON a.UserID = b.uid','INNER')
                              ->where(['a.CreateTime' => ['between', [$today.' 00:00:00', $today.' 23:59:59']]])
                              ->where('a.CreateTime >= b.create_time AND a.Tax > 0')
                              ->field('SUM(a.Tax) tax,a.CreateTime,a.UserID uid,b.parent_id,b.ratio,b.ratio_modify,b.ratio_modify_time,b.is_agent')
                              ->group('a.UserID')
                              ->select();
            if($todaytaxs){
                $contact = $this->get_parent($todaytaxs);
                $adduser = [];
                $data = [];

                foreach($todaytaxs as $k=>$v){
                    $parent = explode(',',$contact[$v['uid']]);
                    $team = 0;
                    $player_amount = 0;
                    if($v['is_agent']==1){
                        $player_amount = $v['tax'];
                    }else{
                        $team = $v['tax'];
                    }
                    $amount = $player_amount + $team;
                    $cut = 0;
                    foreach($parent as $x=>$y){
                        if($y){
                            $tmp = explode('-',$y);
                            if(!isset($tmp[2]) || !$tmp[2]){
                                $tmp[2] = 0;
                            }
                            if(!isset($tmp[1]) || !$tmp[1]){
                                $tmp[1] = 0;
                            }

                            if($tmp[1]==1){
                                $profit = (int)$v['tax']*($tmp[2]-$cut)/100;
                                $cut += $tmp[2]-$cut;
                            }else{
                                $profit = 0;
                            }
                            if(!in_array($tmp[0],$adduser)){
                                $adduser[] = $tmp[0];
                                $data[$tmp[0]] = ['uid'=>$tmp[0],'ymd'=>$ymd,'ratio'=>$tmp[2],'profit'=>$profit,'player_amount'=>$player_amount,'team'=>$team,'amount'=>$amount];
                            }else{
                                $data[$tmp[0]]['profit'] += $profit;
                                $data[$tmp[0]]['player_amount'] += $player_amount;
                                $data[$tmp[0]]['team'] += $team;
                                $data[$tmp[0]]['amount'] += $amount;
                            }
                        }
                        
                    }
                    
                }

                if($data){
                    $this->db->table('gamelogdb.tb_qmagent_v3_performance_day')->where('ymd = '.$ymd)->delete();
                    $max = $this->db->table('gamelogdb.tb_qmagent_v3_performance_day')->max('id');
                    $sql = 'INSERT INTO gamelogdb.tb_qmagent_v3_performance_day (`id`,`uid`,`ymd`,`ratio`,`profit`,`player_amount`,`team`,`amount`,`is_count`) VALUES ';
                    foreach($data as $k=>$v){
                        $max++;
                        $sql .= '('.$max.','.$v['uid'].','.$ymd.','.$v['ratio'].','.$v['profit'].','.$v['player_amount'].','.$v['team'].','.$v['amount'].',0),';
                    }
                    Db::execute(substr($sql,0,-1).';');
                }
            }
            Db::commit();
        }catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }

    private function get_parent($todaytaxs){
        $contacts = $this->db->table('tb_qmagent_v3_groom_contact')->field('uid,parent_id,is_agent,ratio')->select();
        $strid = '';
        foreach($contacts as $k=>$v){
            $contact[$v['uid']] = $v;
        }
        
        foreach($todaytaxs as $k=>$v){
            $res[$v['uid']] = rtrim($this->parentstr($contact,$v['uid']),',');
        }
        return $res;
    }

    private function update_ratio(){
        $contacts = $this->db->table('tb_qmagent_v3_groom_contact')->field('id,ratio,ratio_modify,ratio_modify_time')->select();
        $updatesql = 'UPDATE tb_qmagent_v3_groom_contact SET ratio = CASE id';
        foreach($contacts as $k=>$v){
            if($v['ratio_modify_time'] && $v['ratio'] != $v['ratio_modify'] && date('oW',strtotime($v['ratio_modify_time'])) < date('oW')){
                $strid .= $v['id'].',';
                $updatesql .= ' WHEN '.$v['id'].' THEN '.$v['ratio_modify'];
            }
        }
        
        if($strid != ''){
            $updatesql .= ' END WHERE id IN ('.substr($strid,0,-1).');';
            Db::execute($updatesql);
        }
    }

    private function parentstr($contact,$uid){
        if($contact[$uid]['parent_id']){
            $parent_id = $contact[$uid]['parent_id'].'-'.$contact[$contact[$uid]['parent_id']]['is_agent'].'-'.$contact[$contact[$uid]['parent_id']]['ratio'].',';
            if(isset($contact[$uid]['parent_id']) && $contact[$uid]['parent_id']){
                $parent_id .= $this->parentstr($contact,$contact[$uid]['parent_id']);
            }
        }else{
            $parent_id = '';
        }
        return $parent_id;
    }

    public function allgent_profit(){
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;

        $today = date('Y-m-d');
        $ymd = date('Ymd');

        $timeday = strtotime(date('Y-m-d H:i:s')) - 1860;
        $today0 = strtotime(date('Y-m-d 00:00:00'));
        $apiconfig = include('/data/www/pay/application/config.php');
        $lrate = $apiconfig['qmagent']['lrate'];
        $llrate = $apiconfig['qmagent']['llrate'];
        $lastlogid = $this->db->table('gamelogdb.tb_qmagent_logendid')->where(['id'=>1])->find();
        $lasttotalid = 0;
        //if($lastlogid) $lasttotalid = $lastlogid['endid'];
        //$map['IDX']  = array('gt',$lasttotalid);
        $res = $this->db->table('gamelogdb.tb_qmagent_profit')->where(['ymd'=>$ymd])->order('id DESC')->find();
        Db::startTrans();
        try{
            if (!$res && $timeday < $today0){
                $yestoday = date('Ymd',strtotime(date('Y-m-d H:i:s')) - 86400);
                $yestime = date('Y-m-d',strtotime($yestoday));

                $yres = $this->db->table('gamelogdb.tb_qmagent_profit')->where(['ymd'=>$yestoday])->order('id DESC')->find();
                if($yres){
                    $yesdaytaxs = $this->db->table('gamelogdb.tb_loguser a')->join('gamedb.tb_groom_contact b ON a.UserID = b.uid','INNER')->where(['a.CreateTime' => ['between', [$yestime.' 00:00:00', $yestime.' 23:59:59']]])->where('a.CreateTime > b.create_time and a.Tax > 0')->field('SUM(a.Tax) tax,a.UserID uid')->group('a.UserID')->select();
                    //$newlastids = $this->db->table('gamelogdb.tb_loguser')->where(['CreateTime' => ['between', [$yestime.' 00:00:00', $yestime.' 23:59:59']]])->field('IDX id')->order('id DESC')->find();
                    //$newlsid = $newlastids['id'];
                    if($yesdaytaxs){
                        $player_tax = [];
                        $uids = [];
                        foreach($yesdaytaxs as $yst){
                            if($yst['tax'] > 0){
                                $player_tax[$yst['uid']] = $yst['tax'];
                                $uids[] = $yst['uid'];
                            }
                        }
                        if($uids){
                            $where['uid']  = array('in',$uids);
                            $allagent = $this->db->table('tb_groom_contact')->where($where)->field('uid,parent_id,farth_id')->select();
                            if($allagent){
                                $this->db->table('gamelogdb.tb_qmagent_profit')->where(['ymd'=>$yestoday])->save(['lower_commision'=>0,'lower_profit'=>0,'llower_commision'=>0,'llower_profit'=>0]);
                                foreach($allagent as $ag){
                                    $profit = $this->db->table('gamelogdb.tb_qmagent_profit')->where(['uid'=>$ag['parent_id'],'ymd'=>$yestoday])->field('id,uid,lower_commision,lower_profit')->find();
                                    $lpro = ceil($player_tax[$ag['uid']] * $lrate);
                                    $llpro = ceil($player_tax[$ag['uid']] * $llrate);
                                    if($profit){
                                        $lpdate = ['lower_commision'=>$profit['lower_commision']+$player_tax[$ag['uid']],'lower_profit'=>$profit['lower_profit']+$lpro];
                                        $this->db->table('gamelogdb.tb_qmagent_profit')->where(['uid'=>$ag['parent_id'],'ymd'=>$yestoday])->save($lpdate);
                                    }else{
                                        $ins_data = ['uid'=>$ag['parent_id'],'ymd'=>$yestoday,'lower_commision'=>$player_tax[$ag['uid']],'lower_profit'=>$lpro,'llower_commision'=>0,'llower_profit'=>0,'is_count'=>0];
                                        $this->db->table('gamelogdb.tb_qmagent_profit')->add($ins_data);
                                    }
                                    
                                    $this->profit_from_log(['level'=>1,'uid'=>$ag['parent_id'],'fuid'=>$ag['uid'],'profit'=>$lpro,'tax'=>$player_tax[$ag['uid']],'ymd'=>$yestoday,"percent"=>$lrate*100]);

                                    $profit1 = $this->db->table('gamelogdb.tb_qmagent_profit')->where(['uid'=>$ag['farth_id'],'ymd'=>$yestoday])->field('id,uid,llower_commision,llower_profit')->find();
                                    if($profit1){
                                        $lpdate = ['llower_commision'=>$profit1['llower_commision']+$player_tax[$ag['uid']],'llower_profit'=>$profit1['llower_profit']+$llpro];
                                        $this->db->table('gamelogdb.tb_qmagent_profit')->where(['uid'=>$ag['farth_id'],'ymd'=>$yestoday])->save($lpdate);
                                    }else{
                                        $ins_data = ['uid'=>$ag['farth_id'],'ymd'=>$yestoday,'lower_commision'=>0,'lower_profit'=>0,'llower_commision'=>$player_tax[$ag['uid']],'llower_profit'=>$llpro,'is_count'=>0];
                                        $this->db->table('gamelogdb.tb_qmagent_profit')->add($ins_data);
                                    }
                                    if($lastlogid['enddate'] == $yestoday){
                                        if($ag['parent_id']){
                                            $this->db->table('gamelogdb.tb_qmagent_total')->where(['uid'=>$ag['parent_id']])->setInc('lower_profit',$lpro);
                                        }
                                        $this->db->table('gamelogdb.tb_qmagent_total')->where(['uid'=>$ag['uid']])->setInc('level_profit',$lpro);

                                        if($ag['parent_id']){
                                            $this->db->table('gamelogdb.tb_qmagent_total')->where(['uid'=>$ag['parent_id']])->setInc('level_profit',$llpro);
                                        }
                                        if($ag['farth_id']){
                                            $this->db->table('gamelogdb.tb_qmagent_total')->where(['uid'=>$ag['farth_id']])->setInc('llower_profit',$llpro);
                                        }
                                    }
                                    
                                    $this->profit_from_log(['level'=>2,'uid'=>$ag['farth_id'],'fuid'=>$ag['uid'],'profit'=>$llpro,'tax'=>$player_tax[$ag['uid']],'ymd'=>$yestoday,"percent"=>$llrate*100]);
                                }
                                
                            }
                        }
                        
                    }
                }
                $this->db->table('gamelogdb.tb_qmagent_logendid')->where(['id'=>1])->save(['enddate'=>$ymd]);
            }else{
                $todaytaxs = $this->db->table('gamelogdb.tb_loguser a')->join('gamedb.tb_groom_contact b ON a.UserID = b.uid','INNER')->where(['a.CreateTime' => ['between', [$today.' 00:00:00', $today.' 23:59:59']]])->where('a.CreateTime > b.create_time and a.Tax > 0')->field('SUM(a.Tax) tax,a.UserID uid')->group('a.UserID')->select();
                //$newlsid = $newlastids['id'];
                if($todaytaxs){
                    $player_tax = [];
                    $uids = [];
                    foreach($todaytaxs as $tt){
                        if($tt['tax'] > 0){
                            $player_tax[$tt['uid']] = $tt['tax'];
                            $uids[] = $tt['uid'];
                        }
                    }
                    if($uids){
                        $where['uid']  = array('in',$uids);
                        $allagent = $this->db->table('tb_groom_contact')->where($where)->field('uid,parent_id,farth_id')->select();
                        if($allagent){
                            $this->db->table('gamelogdb.tb_qmagent_profit')->where(['ymd'=>$ymd])->save(['lower_commision'=>0,'lower_profit'=>0,'llower_commision'=>0,'llower_profit'=>0]);
                            foreach($allagent as $ag){
                                $profit = $this->db->table('gamelogdb.tb_qmagent_profit')->where(['uid'=>$ag['parent_id'],'ymd'=>$ymd])->field('id,uid,lower_commision,lower_profit')->find();

                                $lpro = ceil($player_tax[$ag['uid']] * $lrate);
                                $llpro = ceil($player_tax[$ag['uid']] * $llrate);
                                if($profit){
                                    $lpdate = ['lower_commision'=>$profit['lower_commision']+$player_tax[$ag['uid']],'lower_profit'=>$profit['lower_profit']+$lpro];
                                    $this->db->table('gamelogdb.tb_qmagent_profit')->where(['uid'=>$ag['parent_id'],'ymd'=>$ymd])->save($lpdate);
                                }else{
                                    $ins_data = ['uid'=>$ag['parent_id'],'ymd'=>$ymd,'lower_commision'=>$player_tax[$ag['uid']],'lower_profit'=>$lpro,'llower_commision'=>0,'llower_profit'=>0,'is_count'=>0];
                                    $this->db->table('gamelogdb.tb_qmagent_profit')->add($ins_data);
                                }
                                //$this->profit_from_log(['level'=>1,'uid'=>$ag['parent_id'],'fuid'=>$ag['uid'],'profit'=>$lpro,'tax'=>$player_tax[$ag['uid']],'ymd'=>$ymd,"percent"=>$lrate*100]);
                                
                                $profit1 = $this->db->table('gamelogdb.tb_qmagent_profit')->where(['uid'=>$ag['farth_id'],'ymd'=>$ymd])->field('id,uid,llower_commision,llower_profit')->find();
                                if($profit1){
                                    $lpdate = ['llower_commision'=>$profit1['llower_commision']+$player_tax[$ag['uid']],'llower_profit'=>$profit1['llower_profit']+$llpro];
                                    $this->db->table('gamelogdb.tb_qmagent_profit')->where(['uid'=>$ag['farth_id'],'ymd'=>$ymd])->save($lpdate);
                                }else{
                                    $ins_data = ['uid'=>$ag['farth_id'],'ymd'=>$ymd,'lower_commision'=>0,'lower_profit'=>0,'llower_commision'=>$player_tax[$ag['uid']],'llower_profit'=>$llpro,'is_count'=>0];
                                    $this->db->table('gamelogdb.tb_qmagent_profit')->add($ins_data);
                                }
                                            
                                //$this->profit_from_log(['level'=>2,'uid'=>$ag['farth_id'],'fuid'=>$ag['uid'],'profit'=>$llpro,'tax'=>$player_tax[$ag['uid']],'ymd'=>$ymd,"percent"=>$llrate*100]);
                            }
                            
                        }
                    }
                }
                
            }
            Db::commit();
        }catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }


    //活动滚动信息
    public function scrolling_message(){
        G('begin');
        echo '执行时间：' . date('Y-m-d H:i:s', time()) . PHP_EOL;
        $activity = $this->db->table('tb_activity a')
                        ->where(['a.ScrollingMessage'=>1])
                        ->join('tb_activity_result_param b on b.ActivityID = a.IDX')
                        ->join('tb_userinfo as c on c.UserID = b.UserID')
                        ->limit(100)
                        ->order('b.IDX DESC')
                        ->field('a.IDX,b.ResultID,b.UserID,b.IntValue,b.CreateTime,c.NickName')->select();
        if($activity){
            $insertStr = 'INSERT INTO tb_scrolling_message (`ActivityID`,`UserID`,`ResultID`,`Content`,`IsRobotMessage`,`CreateTime`) VALUES ';
            $templateid = [];
            $template = [];
            foreach($activity as $k=>$v){
                $content = '';
                if(!in_array($v['idx'],$templateid)){
                    $templateid[] = $v['idx'];
                    $template[$v['idx']]['data'] = $this->db->table('tb_scrolling_template')->where(['ActivityID'=>$v['idx']])->order('IDX DESC')->find();
                }
                if(isset($template[$v['idx']]['data']['content']) && $template[$v['idx']]['data']['content']){
                    $content = preg_replace(['#{userid}#','#{nickname}#','#{gold}#'], [$v['userid'],$v['nickname'],$v['intvalue']/C('goldunit')], $template[$v['idx']]['data']['content']);
                }
                $insertStr .= '('.$v['idx'].','.$v['userid'].','.$v['resultid'].',"'.$content.'",0,"'.$v['createtime'].'"),';
            }
            $insertStr = substr($insertStr,0,-1).';';
            Db::startTrans();
            try{
                Db::execute('DELETE FROM tb_scrolling_message;');
                Db::execute($insertStr);
                Db::commit();
            }catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
        }else{
            Db::execute('DELETE FROM tb_scrolling_message;');
        }

        G('end');
        echo '运行时间：' . G('begin', 'end') . 's' . PHP_EOL;
        echo '消耗内存:' . G('begin', 'end', 'm') . 'kb' . PHP_EOL;
        echo 'success';
        die;
    }
}
