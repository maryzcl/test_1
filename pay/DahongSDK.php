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

class DahongSDK extends Pay
{
    protected $ApiBase = 'http://154.221.23.135:8080/gateway/gateway.htm';//提交地址
    public $prikey='MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBALRmJ2pA7P60Sw8besszO/jBnvm6396ZxGTPWVnBKvDuaDHMBZtb1YHXee7rgC3i2ZnryKSNo5mgL3rmrpZ3OQe4LY5O1toJQ+NyisfVB++3EbK9ZiwXAmPuXy0Vhjm5eXz7OX3KWzqY4eu+9gbcRldByjavVRWxdfQ3bVQ8H6BXAgMBAAECgYBV4zTsz5CGC4yY2rpxuCjbXEO2HhSrxIXOgTHHG8K4Bqmw27PnradPNCmnaJQURKbZM3rJbW3wnGU+nEmO9aA+kf3Kio1uuWIKHmdB63o9FlrnI1j0y97oC2MTBl4oJeVlWIvaaUEdmjfe3WdMgPrLNIYOvMLjI1JQyAQd4yIJAQJBANfLO5U8hd8tiI1ehMpMJVYI5BCE9i15IrGcyhx9MpKKiiCdaZ9HSj+EJtm9ZEZfQoPr0OEhpGHRA/Uep/PAmRcCQQDWAq+ZKX0VD5UHhcFJzypJNVRkUUSTRoC9EfOTRafdB7Mvy+MpSwzj4XXkGqPMLHUmvvrowQ4M1Js9ffcQyzrBAkEA0LHfFMwsmBM8LaRMfiy4KwV8MzGzt3Sghe8hU/4Mq8ZKIZK69GyItPbEb+4HDTvRYy3rm97iUCtJTYTxXv5TzwJBAJmB6bUCRn9x4uM3dRLdb6Z9g2BFztuZKcT0+HN99k+cM1Koe/PlqoRW97o7xZwxk4LMYKvNtqdLZWMxVKQOMsECQBi5S/tcZTM3cXduspm+kJks3D75eE9ZyEsR3sX3Mm3Bsl9pUhnaX18KP8NAEKNz8tibIbHNVrvUKSDyZ+CBusc=';
    public function call($api, $param = '', $method = 'GET', $multi = false)
    {
     $submiturl = isset($param['ApiUrl'])?$param['ApiUrl']:$this->ApiBase;
     switch (strtolower($param['payment'])){
        case 'alipay':
        $type='JSAPI';
        $pay_channel='ALIPAY';
        break;
         case 'wxpay':
         $type = 'NATIVE';//WEIXIN\ALIPAY\JHPAY
         $pay_channel='WEIXIN';
         break;
        case 'unionpay':
        $pay_channel='JHPAY';
        $type = 'JSAPI';
        break;
    }
         $arr = array(
                    'method'=>'jypay.eacq.order.create',
                    'version'=> '1.0',
                    'merchant_no'=>$this->AppAccount,
                    'timestamp'=> date('Y-m-d H:i:s',time()),
                     'biz_content'=>array(
                        'pay_channel'=>$pay_channel,
                        'pay_type'=>$type,
                        'out_trade_no'=> $param['order_id'],
                        'amount'=> $param['bill_price'],
                        'notify_url'=>$this->Callback,
                        'body'=>'recharge',
                        'attach'=>$this->getIP()
                    ),
                 );
                 Log::write("参与签名的参数是:".json_encode($arr));
                  ksort($arr);
        $rul2="";
        //$arr['biz_content']=json_encode($arr['biz_content'],JSON_UNESCAPED_UNICODE);
        $arr['biz_content']=json_encode($arr['biz_content']);
        foreach ($arr as $k=>$value){
            $rul2.=$k."=".$value."&";
        }
        $rul2=substr($rul2,0,strlen($rul2)-1);
        $arr['sign'] = $this->rsaSign($rul2,$this->setPrivateKey($this->prikey));
         Log::write("提交参数数据是:".json_encode($arr));
         $posturl = $this->http($submiturl,$arr,'POST');
        Log::write('返回的信息是:'.$posturl);
        $result= json_decode($posturl,true);
        if($result['msg']=='success')
        {
           $res['status'] = true;
           $res['payurl']=$result['code_url'];
        }
        else {
            $res['status'] = false;
            $res['payurl'] = $result['msg'];
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
       $data['biz_content']=json_encode($arr['biz_content'],JSON_UNESCAPED_UNICODE);
        $str = "";
        foreach ($data as $key => $value) {
                   if ($value === '' || $value == null ) {
                continue;
            }
        
            $str .= $key . '=' . $value."&";
        }
        $sign = substr($str,0,-1);
        return $sign;
        
        
    }
        private function rsaSign($data,$private_key) {
        $res = openssl_get_privatekey($private_key);
        openssl_sign($data, $sign, $res,OPENSSL_ALGO_SHA1);
        openssl_free_key($res);
        $sign = base64_encode($sign);
        return $sign;
    }
      public function setPrivateKey($value, $passphrase = ''){

        if(is_file($value)) $value = file_get_contents($value);
       $value = chunk_split($value, 64, "\n");
        $value = "-----BEGIN RSA PRIVATE KEY-----\n$value-----END RSA PRIVATE KEY-----\n";
        return openssl_pkey_get_private($value, $passphrase);
    }
       public function getIP()
        {
            $ip=getenv('REMOTE_ADDR');
            $ip_ = getenv('HTTP_X_FORWARDED_FOR');
            if (($ip != "") && ($ip != "unknown"))
            {
            $ip=$ip_;
            }
            return $ip;
        }

    }




