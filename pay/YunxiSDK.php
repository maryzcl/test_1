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

class YunxiSDK extends Pay
{
    protected $ApiBase = 'http://pay.1000pays.com/Gateway/api';
    public function call($api, $param = '', $method = 'GET', $multi = false)
    {
        $parter = $this->AppAccount;
        $submiturl = isset($param['ApiUrl'])?$param['ApiUrl']:$this->ApiBase;

        switch (strtolower($param['payment'])){
            //ali_native  支付宝二维码支付
           // ali_jsapi   支付宝服务窗支付
            case 'alipay':
                $type='ali_h5';
                $s_type='ALISCAN';
                break;
            case 'wxpay':
                $type = 'wx_jsapi';
                 $s_type='WXSCAN';
                break;
            // case 'unionpay':
            //     $type = 'heepay_union';
            //     $s_type='WXSCAN';
            //     break;
        }
        $data['appid']=$this->AppAccount;//商户号
        $data['method']=$type;
        $data['data']=array('store_id'=>'',
            'total'=>$param['bill_price'],
            'nonce_str'=>rand(1000,9999),
            'body'=>'',
            'out_trade_no'=>$param['order_id'],
            );
        $sign= $this->setSign($data['data']);//
        $data['sign']=$sign;
        Log::write('提交的数据是:'.json_encode($data));
        $posturl = $this->http($submiturl,json_encode($data), 'JSON');
        Log::write('返回的信息是:'.$posturl);
        $result= json_decode($posturl,true);
        if($result['code']==100 && $result['data']['result_code']=='0000')
        {
            $poststr = '';
            
        foreach($data['data'] as $k=>$v){
            if($v!=null)
            {
                 $poststr .= $k . "=" . $v . "&";
            }
           
        }
        $xcpt_mysign = $this->turnSign($data['data']);
        $apiurl =  $this->turnUrl();
        $res['status'] = true;
        $time = time();
        $s_type='UPSCAN';
        $pay_url = $apiurl.'/api/payturn/subparam?'.$poststr.'&mysign='.$xcpt_mysign;
        $sign = md5('pay_url='.$result['data']['H5'].'&total_fee='.$param['bill_price'].'&time='.$time.md5('MD5_DIVISOR').'&type='.$s_type);
        $res['payurl'] = $apiurl.'/api/payturn/shilian?pay_url='.urlencode($result['data']['H5']).'&sign='.$sign.'&total_fee='.$param['bill_price'].'&time='.$time.'&type='.$s_type.'&externalId='.$param['order_id'];
        }
        else {
            $res['status'] = false;
            $res['payurl'] = '';
            $res['text'] = '';
        }
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
                    if($value=='')
                    {
                        continue;
                    }
			
			$str .= $key . '=' . $value . '&';
		}
		$sign = md5($str .'key=' . $this->AppSecret);
		return strtoupper($sign);
        }
    
        
        
}




