<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/8/17
 * Time: 13:45
 */
namespace app\api\controller;
use http\Exception;
use think\exception\ValidateException;
use think\Request;
use think\Db;
use think\Log;
use Predis\Client;
use think\Loader;
Loader::import('phpqrcode.phpqrcode', EXTEND_PATH, '.php');

class Allagent extends RestBaseController
{
    protected $request;
    protected $redis;
    private $lrate;
    private $llrate;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->lrate = (config('qmagent')['lrate']*100).'%';
        $this->llrate = (config('qmagent')['llrate']*100).'%';
        $this->minexchange = config('qmagent')['minexchange'];
    }

    public function getCreateQrcode()
    {
        if(strpos($this->request->get('url'),'qmclear')){
            $url = substr($this->request->get('url'),0,strpos($this->request->get('url'),'qmclear'));
        }else{
            $url = $this->request->get('url');
        }
        
        vendor("phpqrcode.phpqrcode");
        $level = 'L';
        $size = 6;
        $QRcode = new \QRcode();
        ob_start();
       $QRcode->png($url,false,$level,$size);
       $imageString =ob_get_contents();
       ob_end_clean();
       echo $imageString;
       exit();
    }

    public function postUserCode(){
        $json = $this->request->post('js');
        $sign = $this->request->post('sign');
        $mysign = strtoupper(md5(config('MD5_DIVISOR').$json));
        if (strtoupper($sign) != $mysign){
            $result = ['code' => 10001, 'msg' => '签名失败！',];
            return json_encode($result);
        }
        $info = json_decode($json, true);
        if (!array_key_exists('userid', $info)) {
            $result = ['code' => 10002, 'msg' => '缺少参数！'];
            return json_encode($result);
        }

        if (!array_key_exists('ChannelID', $info)) {
            $result = ['code' => 10003, 'msg' => '缺少参数邀请码！'];
            return json_encode($result);
        }
        $channel = $url_info = Db::table('tb_channel')->where(['ChannelID'=>$info['ChannelID']])->field('Agenttype')->find();
        if($channel['Agenttype'] == 1){
            $table = 'tb_qmagent_v3_groom_contact';
        }else{
            $table = 'tb_groom_contact';
        }
        $user_info = Db::table('tb_userinfo a')
            ->field('a.UserID,a.ChannelID,a.UserName,a.invite_code,a.LoginCount')
            ->where('UserID',$info['userid'])->find();
        if (!$user_info){
            $result = ['code' => 10101, 'msg' => '用户不存在'];
            return json_encode($result);
        }

        $invite_code = $user_info['invite_code'];
        if($user_info['invite_code'] == null){
            $invite_code = rand(100,999).$info['userid'];
            Db::table('tb_userinfo')->where('UserID', $info['userid'])->update(['invite_code' => $invite_code]);
        }

        $retutn_data = ['code'=>10000,'myID'=>$invite_code,'is_extension'=>0,'qcode_url'=>'','extension_url'=>'','extension_id'=>''];
        $contact = Db::table($table)->where(['uid' => $info['userid']])->find();
        if($contact){
            $retutn_data['is_extension'] = 1;
            $retutn_data['extension_id'] = $contact['parent_id'];
        }
        $contactc = Db::table($table)->where(['parent_id' => $info['userid']])->find();
        if(!$contactc){
            if(!$contact && $user_info['LoginCount'] < 4){
                $retutn_data['is_openqm'] = 1;
            }
        }else{
            $retutn_data['is_qmagent'] = 1; 
        }
        
        $url_info = Db::table('tb_channel')->where(['ChannelID'=>$info['ChannelID']])->field('Url,PlatID')->order('IDX desc')->find();

        if($url_info){
            if(!$url_info['Url']){
                $url_info = Db::table('tb_plat')->where(['PlatID'=>$url_info['PlatID']])->field('Url')->order('IDX desc')->find();
            }
            $retutn_data['extension_url'] = $url_info['Url'].'?channelid='.$info['ChannelID'].'&weitjqm='.$invite_code;
            $retutn_data['qcode_url'] = config('qcorurl').'/api/allagent/getCreateQrcode?url='.urlencode($retutn_data['extension_url'].'qmclear');
        }
        return json_encode($retutn_data);
    }

    public function postBindAgent(){
        $json = $this->request->post('js');
        $sign = $this->request->post('sign');
        $mysign = strtoupper(md5(config('MD5_DIVISOR').$json));
        if (strtoupper($sign) != $mysign){
            $result = ['code' => 10001, 'msg' => '签名失败！',];
            return json_encode($result);
        }
        $info = json_decode($json, true);
        if (!array_key_exists('userid', $info)) {
            $result = ['code' => 10002, 'msg' => '缺少参数用户ID！'];
            return json_encode($result);
        }

        if (!array_key_exists('invitecode', $info)) {
            $result = ['code' => 10002, 'msg' => '缺少参数邀请码！'];
            return json_encode($result);
        }
        if (!array_key_exists('ChannelID', $info)) {
            $result = ['code' => 10002, 'msg' => '缺少参数邀请码！'];
            return json_encode($result);
        }
        

        $user_info = Db::table('tb_userinfo a')
            ->field('a.UserID,a.invite_code')
            ->where('UserID',$info['userid'])->find();
        if (!$user_info){
            $result = ['code' => 10101, 'msg' => '用户不存在'];
            return json_encode($result);
        }
        $code_info = Db::table('tb_userinfo a')
            ->field('a.UserID,a.ChannelID as channelid,a.invite_code')
            ->where('invite_code',$info['invitecode'])->find();
        if (!$code_info){
            $result = ['code' => 10101, 'msg' => '无效的推荐码'];
            return json_encode($result);
        }

        //$info['userid'] 是当前用户 $code_info['UserID']是推广人的uid
        if ($code_info['UserID'] == $info['userid']){
            $result = ['code' => 10103, 'msg' => '推荐人不能是自己'];
            return json_encode($result);
        }
        $channel =  Db::table('tb_channel')->where(['ChannelID'=>$info['ChannelID']])->field('Agenttype')->find();
        $channelParent =  Db::table('tb_channel')->where(['ChannelID'=>$code_info['channelid']])->field('Agenttype')->find();
        if($channel['Agenttype'] == 1 || $channelParent['Agenttype'] == 1){
            if($code_info['channelid'] != $info['ChannelID']){
                $result = ['code' => 10102, 'msg' => '不同渠道用户不能相互绑定'];
                return json_encode($result);
            }
            return $this->postBindAgent1();
        }

        $verity = Db::table('tb_groom_contact')->where(['parent_id' => $info['userid']])->find();
        if ($verity){
            $result = ['code' => 20003, 'msg' => '您已经有下级用户，无法绑定其他代理！'];
            return json_encode($result);
        }
        $retutn_data = ['code'=>'10000','msg'=>'绑定失败'];
        $contact = Db::table('tb_groom_contact')->where(['uid' => $user_info['UserID']])->find();
        if($contact){
            $result = ['code' => 20001, 'msg' => '用户已绑定代理，请勿重复绑定'];
            return json_encode($result);
        }
        $father = Db::table('tb_groom_contact as gc')->where(['uid' => $code_info['UserID']])->find();
        $farth_id = 0;
        if($father) $farth_id = $father['parent_id'];
        $insert = ['uid'=>$info['userid'],'parent_id'=>$code_info['UserID'],'farth_id'=>$farth_id,'create_time'=>date('Y-m-d H:i:s')];
        $res = Db::table('tb_groom_contact')->insert($insert);
        if(!$res){
            $retutn_data['code'] = '20002';
            return json_encode($retutn_data);
        }
        $agent_total = Db::table('gamelogdb.tb_qmagent_total')->where('uid',$code_info['UserID'])->find();
        if($agent_total){
            Db::table('gamelogdb.tb_qmagent_total')->where('uid', $code_info['UserID'])->update(['level_num' => $agent_total['level_num']+1]);
        }else{
            $total = ['uid'=>$code_info['UserID'],'level_num'=>1,'lower_profit'=>0,'llower_profit'=>0,'low_settlement'=>0,'llow_settlement'=>0];
            Db::table('gamelogdb.tb_qmagent_total')->insert($total);
        }
        $new_total = ['uid'=>$info['userid'],'level_num'=>0,'lower_profit'=>0,'llower_profit'=>0,'low_settlement'=>0,'llow_settlement'=>0];
        Db::table('gamelogdb.tb_qmagent_total')->insert($new_total);
        $retutn_data['msg'] = '绑定成功';
        return json_encode($retutn_data);
    }

    public function postBindAgent1(){
        $json = $this->request->post('js');
        $sign = $this->request->post('sign');
        $info = json_decode($json, true);
        $user_info = Db::table('tb_userinfo a')
            ->field('a.UserID,a.invite_code')
            ->where('UserID',$info['userid'])->find();
        if (!$user_info){
            $result = ['code' => 10101, 'msg' => '用户不存在'];
            return json_encode($result);
        }

        $code_info = Db::table('tb_userinfo a')
            ->field('a.UserID,a.ChannelID as channelid,a.invite_code')
            ->where('invite_code',$info['invitecode'])->find();
        if (!$code_info){
            $result = ['code' => 10101, 'msg' => '无效的推荐码'];
            return json_encode($result);
        }
        $verity = Db::table('tb_qmagent_v3_groom_contact')->where(['parent_id' => $info['userid']])->find();
        if ($verity){
            $result = ['code' => 20003, 'msg' => '您已经有下级用户，无法绑定其他代理！'];
            return json_encode($result);
        }
        $retutn_data = ['code'=>10000,'msg'=>'绑定失败'];
        $contact = Db::table('tb_qmagent_v3_groom_contact')->where(['uid' => $user_info['UserID']])->find();
        if($contact){
            $result = ['code' => 20001, 'msg' => '用户已绑定代理，请勿重复绑定'];
            return json_encode($result);
        }
        
        $insert = ['uid'=>$info['userid'],'parent_id'=>$code_info['UserID'],'create_time'=>date('Y-m-d H:i:s')];
        
        $res = Db::table('tb_qmagent_v3_groom_contact')->insert($insert);
        if(!$res){
            $retutn_data['code'] = 20002;
            return json_encode($retutn_data);
        }
        
        $retutn_data['msg'] = '绑定成功';
        return json_encode($retutn_data);
    }

    public function postMyProfit()
    {
        $json = $this->request->post('js');
        $sign = $this->request->post('sign');
        $mysign = strtoupper(md5(config('MD5_DIVISOR') . $json));
        if (strtoupper($sign) != $mysign) {
            $result = ['code' => 10001, 'msg' => '签名失败！',];
            return json_encode($result);
        }
        $info = json_decode($json, true);
        if (!array_key_exists('userid', $info)) {
            $result = ['code' => 10002, 'msg' => '缺少参数用户ID！'];
            return json_encode($result);
        }
        $user_info = Db::table('tb_userinfo a')
            ->field('a.UserID')
            ->where('UserID', $info['userid'])->find();
        if (!$user_info) {
            $result = ['code' => 10101, 'msg' => '用户不存在'];
            return json_encode($result);
        }
        $page = $info['page']??1;
        $type = $info['type']??1;
        $count = Db::table('gamelogdb.tb_qmagent_profit')->where('uid',$info['userid'])->count();
        $total_page = ceil($count/15);
        if($page > $total_page){
            $result = ['code' => 20004, 'msg' => '暂无分页数据'];
            return json_encode($result);
        }
        $result_data = ['code' => 10000,'count_page'=>$total_page, 'list' => []];
        $statrpage = ($page - 1) * 15;
        $entpahe = $statrpage + 15;
        $lists = Db::table('gamelogdb.tb_qmagent_profit')->where('uid',$info['userid'])->limit($statrpage,$entpahe)->order('ymd desc')->select();
        foreach ($lists as $val){
            $tem['date'] = date('Y.m.d',strtotime($val['ymd']));
            if($type == 1){
                $tem['tax'] = sprintf('%.2f',$val['lower_commision']/config('goldunit'));
                $tem['rate'] = $this->lrate;
                $tem['profit'] = sprintf('%.2f',$val['lower_profit']/config('goldunit'));
            }
            if($type == 2){
                $tem['tax'] = sprintf('%.2f',$val['llower_commision']/config('goldunit'));
                $tem['rate'] = $this->llrate;
                $tem['profit'] = sprintf('%.2f',$val['llower_profit']/config('goldunit'));
            }
            $result_data['list'][] = $tem;
        }
        return json_encode($result_data);
    }

    public function postMyLevel()
    {
        $json = $this->request->post('js');
        $sign = $this->request->post('sign');
        $mysign = strtoupper(md5(config('MD5_DIVISOR') . $json));
        if (strtoupper($sign) != $mysign) {
            $result = ['code' => 10001, 'msg' => '签名失败！',];
            return json_encode($result);
        }
        $info = json_decode($json, true);
        if (!array_key_exists('userid', $info)) {
            $result = ['code' => 10002, 'msg' => '缺少参数用户ID！'];
            return json_encode($result);
        }
        $user_info = Db::table('tb_userinfo a')
            ->field('a.UserID')
            ->where('UserID', $info['userid'])->find();
        if (!$user_info) {
            $result = ['code' => 10101, 'msg' => '用户不存在'];
            return json_encode($result);
        }
        $page = $info['page'] ?? 1;
        $count = Db::table('tb_groom_contact')->where('parent_id',$info['userid'])->count();
        $total_page = ceil($count/15);
        if($page > $total_page){
            $result = ['code' => 20004, 'msg' => '暂无分页数据'];
            return json_encode($result);
        }
        $result_data = ['code' => 10000,'count_page'=>$total_page, 'list' => []];
        $statrpage = ($page - 1) * 15;
        $entpahe = $statrpage + 15;

        $lists = Db::table('tb_groom_contact')->where('parent_id',$info['userid'])->limit($statrpage,$entpahe)->order('id desc')->select();
        $my_list = [];
        $my_list_ids = [];
        foreach($lists as $val){
            $tem['date'] = date('Y.m.d',strtotime($val['create_time']));
            $tem['uid'] = $val['uid'];
            $tem['level_num'] = 0;
            $tem['profit'] = 0;
            $my_list[$val['uid']] = $tem;
            $my_list_ids[] = $val['uid'];
        }
        $agent_total = Db::table('gamelogdb.tb_qmagent_total')->field('id,uid,level_num,level_profit')->whereIn('uid',$my_list_ids)->select();
        foreach($agent_total as $at){
            $my_list[$at['uid']]['level_num'] = $at['level_num'];
            $my_list[$at['uid']]['profit'] = sprintf('%.2f',$at['level_profit'] / config('goldunit'));
        }
        $result_data['list'] = $my_list;
        return json_encode($result_data);
    }

    public function postMyExtension(){
        $json = $this->request->post('js');
        $sign = $this->request->post('sign');
        $mysign = strtoupper(md5(config('MD5_DIVISOR').$json));
        if (strtoupper($sign) != $mysign){
            $result = ['code' => 10001, 'msg' => '签名失败！',];
            return json_encode($result);
        }
        $info = json_decode($json, true);
        if (!array_key_exists('userid', $info)) {
            $result = ['code' => 10002, 'msg' => '缺少参数用户ID！'];
            return json_encode($result);
        }
        $user_info = Db::table('tb_userinfo a')
            ->field('a.UserID')
            ->where('UserID',$info['userid'])->find();
        if (!$user_info){
            $result = ['code' => 10101, 'msg' => '用户不存在'];
            return json_encode($result);
        }
        $result_data = ['code' => 10000,'yesday_ltax'=>0.00,'yesday_lprofit'=>0.00,'yesday_lrate'=>$this->lrate,'yesday_lltax'=>0.00,'yesday_llprofit'=>0.00,'yesday_llrate'=>$this->llrate,'level_num'=>0,'total_profit'=>0.00,'use_peofit'=>0.00];
        $yestoday = date('Ymd',strtotime(date('Y-m-d H:i:s')) - 86400);
        $yesday_info = Db::table('gamelogdb.tb_qmagent_profit')->where('uid',$info['userid'])->where('ymd',$yestoday)->find();

        if($yesday_info){
            $result_data['yesday_ltax'] = sprintf('%.2f',$yesday_info['lower_commision']/config('goldunit'));
            $result_data['yesday_lprofit'] = sprintf('%.2f',$yesday_info['lower_profit']/config('goldunit'));
            $result_data['yesday_lltax'] = sprintf('%.2f',$yesday_info['llower_commision']/config('goldunit'));
            $result_data['yesday_llprofit'] = sprintf('%.2f',$yesday_info['llower_profit']/config('goldunit'));
        }
        $agent_total = Db::table('gamelogdb.tb_qmagent_total')->where('uid',$info['userid'])->find();
        if($agent_total){
            $result_data['level_num'] = $agent_total['level_num'];
            $result_data['total_profit'] = sprintf('%.2f',($agent_total['low_settlement'] + $agent_total['llow_settlement'])/config('goldunit'));;
        }
        $today = date('Ymd',strtotime(date('Y-m-d H:i:s')));
        $alluse = Db::table('gamelogdb.tb_qmagent_profit')->where('ymd','<',$today)->where('uid',$info['userid'])->where('is_count',0)->field('SUM(lower_profit) lp,SUM(llower_profit) llp')->find();
        if($alluse) $result_data['use_peofit'] = sprintf('%.2f',($alluse['lp'] + $alluse['llp'])/config('goldunit'));
        return json_encode($result_data);
    }

    public function postSettlement()
    {
        $json = $this->request->post('js');
        $sign = $this->request->post('sign');
        $mysign = strtoupper(md5(config('MD5_DIVISOR') . $json));
        if (strtoupper($sign) != $mysign) {
            $result = ['code' => 10001, 'msg' => '签名失败！',];
            return json_encode($result);
        }
        $info = json_decode($json, true);
        if (!array_key_exists('userid', $info)) {
            $result = ['code' => 10002, 'msg' => '缺少参数用户ID！'];
            return json_encode($result);
        }
        $user_info = Db::table('tb_userinfo a')
            ->field('a.UserID')
            ->where('UserID', $info['userid'])->find();
        if (!$user_info) {
            $result = ['code' => 10101, 'msg' => '用户不存在'];
            return json_encode($result);
        }
        $today = date('Ymd',strtotime(date('Y-m-d H:i:s'))-5400);
        $alluse = Db::table('gamelogdb.tb_qmagent_profit')->where('ymd','<',$today)->where('uid',$info['userid'])->where('is_count',0)->select();
        if(!$alluse){
            $result = ['code' => 20006, 'msg' => '无可兑换佣金额度'];
            return json_encode($result);
        }
        $level_profit = 0;
        $llevel_profit = 0;
        $sellt_ids = [];
        foreach($alluse as $val){
            $level_profit += $val['lower_profit'];
            $llevel_profit += $val['llower_profit'];
            $sellt_ids[] = $val['id'];
        }
        $total = sprintf('%.2f',($level_profit + $llevel_profit)/config('goldunit'));
        if($total <= $this->minexchange){
            $result = ['code' => 20006, 'msg' => '无可兑换佣金额度'];
            return json_encode($result);
        }
        Db::startTrans();
        try{
            $account = Db::table('tb_userinfoext')->where('UserID', $info['userid'])->find();
            if($account){
                Db::table('tb_userinfoext')->where('UserID', $info['userid'])->update(['Gold' => $account['Gold'] + $level_profit + $llevel_profit]);
                $exchange_log = ['uid'=>$info['userid'],'lexchange'=>$level_profit,'llexchange'=>$llevel_profit,'sellte_ids'=>json_encode($sellt_ids),'create_time'=>date('Y-m-d H:i:s')];
                $log_id = Db::table('gamelogdb.tb_qmagent_exchange')->insertGetId($exchange_log);

                $gold_log = ['UserID'=>$info['userid'],'LogType'=>21,'BeforeGold'=>$account['Gold'],'Gold'=>$level_profit + $llevel_profit,'LogIDX'=>$log_id,'CreateTime'=>date('Y-m-d H:i:s')];
                Db::table('gamelogdb.tb_loggoldchange')->insert($gold_log);

                Db::table('gamelogdb.tb_qmagent_profit')->where('ymd','<',$today)->where('uid',$info['userid'])->where('is_count',0)->update(['is_count' => 1]);
            }

            $sellt_total = Db::table('gamelogdb.tb_qmagent_total')->where('uid', $info['userid'])->find();
            if($sellt_total){
                Db::table('gamelogdb.tb_qmagent_total')->where('uid', $info['userid'])->update(['low_settlement' => $sellt_total['low_settlement'] + $level_profit,'llow_settlement'=>$sellt_total['llow_settlement'] + $llevel_profit]);
            }
            Db::commit();
            
            send_http(
                ['msgid' => 108,'param'=>(int)$info['userid']]
            );
            $result_data = ['code' => 10000,'msg'=>'结算成功','score'=>$level_profit+$llevel_profit];

            return json_encode($result_data);
        } catch (\Exception $e) {
            Db::rollback();
            $result_data = ['code' => 20008,'msg'=>'结算失败'];
            return json_encode($result_data);
        }
    }

    //获取代理指引联系方式
    public function connectmethod(){
        $data = input('param.');
        extract($data);
        if(!isset($sign)){
            return json_encode(['code'=>10000,'msg'=>'参数错误']);
        }
        $signstr = '';
        foreach ($data as $key => $value) {
            if($key == "sign"){
                continue;
            }else{
                $signstr .= $value;
            }
        }
        $mysign = strtoupper(md5(config('MD5_DIVISOR') . $signstr));
        if (strtoupper($sign) != $mysign) {
            return json_encode(['code'=>10001,'msg'=>'签名错误']);
        }
        $data = Db::table('tb_qmagent_guidelines')->field('connectwx,connectqq')->select();
        if($data){
            $connectwx = [];
            $connectqq = [];
            foreach($data as $k=>$v){
                if($v['connectwx']){
                    $connectwx[] = $v['connectwx'];
                }
                if($v['connectqq']){
                    $connectqq[] = $v['connectqq'];
                }
            }
            return json_encode(['connectWC'=>$connectwx,'connectQQ'=>$connectqq]);
        }else{
            return json_encode(['connectWC'=>['8888888'],'connectQQ'=>['9999999']]);
        }
        
    }
	public function demo()
	{
		echo 'demo demo '
	}
	public function demo1()
	{
		echo 'demo2'
	}
	
}
