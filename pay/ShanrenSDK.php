<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/5/17
 * Time: 16:59
 */
use pay\Pay;
use think\Log;
use think\Db;

class ShanrenSDK extends Pay
{
    protected $ApiBase = 'http://www.srsrsrpay.com/pay%5E%5Egateway.html';
    public function call($api, $param = '', $method = 'GET', $multi = false)
    {
        $parter = $this->AppAccount;
        $submiturl = isset($param['ApiUrl'])?$param['ApiUrl']:$this->ApiBase;

        switch (strtolower($param['payment'])){
            case 'alipay':
                //$type='alipayh5';
                $bankcode='929';
                break;
            case 'wxpay':
                //$type = 'pay.weixin.wappay';
                $bankcode='930';
                break;
            case 'unionpay':
                //$type = 'pay.unionpay.codepay';
                
                break;
        }

        $data['version']='V6.79';
        $data['merchantid']=intval($this->AppAccount);//商户号
        $data['bankcode']=$bankcode;
        $data['paytime']=date("Y-m-d H:i:s");
        Log::write('商户号:'.$data['merchantid']);
       
        $data['merordernum']=$param['order_id'];//商户订单号 订单order_id
        $data['orderamt']=round($param['bill_price']/100);//总金额 单位元 支持小数点后2位
        
        $data['notifyurl']=$this->Callback;//接受通知的url许给绝对路径
        $data['returnurl']=$this->InfoUrl;//
         Log::write('提交的参数信息:'.json_encode($data));
         $postdata = array(     //组装参数
        "version" => $data['version'],
        "merchantid" => $data['merchantid'],
        "merordernum" =>  $data['merordernum'],
        "orderamt" => $data['orderamt'],
        "paytime" => $data['paytime'],
        "bankcode" =>  $data['bankcode'],
        "notifyurl" => $data['notifyurl'],
        "returnurl" => $data['returnurl'],
       );

        $sign= $this->setSign($postdata);//
        $data['hmac']=$sign;
        $data['submiturl'] = urldecode($submiturl);
        $poststr = '';
        foreach($data as $k=>$v){
            $poststr .= $k . "=" . urlencode($v) . "&";
        }
        $xcpt_mysign = $this->turnSign($data);
 
        $apiurl =  $this->turnUrl();
        if(!strpos('1'.$submiturl,'https:')){
            $apiurl = preg_replace('#https:#','http:',$apiurl);
        }
        $res['status'] = true;
        $res['payurl'] = $apiurl.'/api/payturn/subparam?'.$poststr.'&mysign='.$xcpt_mysign.'&submiturl='.$submiturl;

        return $res;
    }

    /**
     * 抽象方法，在SDK中实现
     * 设置签名
     * token商户秘钥
     */
        protected function setSign($data)
        {

               ksort($data);
		$str = "";
		foreach ($data as $key => $value) {
			if ($value === '' || $value == null) {
				continue;
			}
			$str .= $key . '=' . $value . '&';
		}
		$sign =strtoupper(bin2hex(hash('sha256', $str . "key=" . $this->AppSecret, true))); 
		return $sign;
        
        
}
}




