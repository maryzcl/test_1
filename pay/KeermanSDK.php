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

class KeermanSDK extends Pay
{
    /**
     * API根路径
     * @var string
     */
    protected $ApiBase = 'http://222.186.34.211:8090/api/unify/scan/pay';

    private  $token = '';

    /**
     * 抽象方法，在SDK中实现
     * 组装接口调用参数 并调用接口
     */
    public function call($api, $param = '', $method = 'GET', $multi = false)
    {
        $submiturl = isset($param['ApiUrl'])?$param['ApiUrl']:$this->ApiBase;

        switch (strtolower($param['payment'])){
            
            case 'alipay':
            case 'alipayfixed':
                $type = '904';
                break;
            case 'wxpay':
            case 'wxpayfixed':
                $type = '902';
            break;
            case 'unionpay':
            case 'ysfpay':
                $type = '907';
            break;
           
        }
        //$dt = date("Y-m-d H:i:s");
        $data['payMerchantId']=$this->AppAccount;//商户号
        $data['payOrderId']=$param['order_id'];
        $data['payBankCode']=$type;
        $data['payNotifyUrl']=$this->Callback;
        $data['payCallBackUrl']=$this->InfoUrl;
        $data['payAmount']=$param['bill_price']/100;
        $data['payProductName']='recharge';
        $data['sign'] = $this->setSign($data);
      // $data['submiturl'] =$submiturl;
      //         $poststr = '';
      //   foreach($data as $k=>$v){
      //       $poststr .= $k . "=" . urlencode($v) . "&";
      //   }
      //   $xcpt_mysign = $this->turnSign($data);
 
      //   $apiurl =  $this->turnUrl();
      //   if(!strpos('1'.$submiturl,'https:')){
      //       $apiurl = preg_replace('#https:#','http:',$apiurl);
      //   }
      //   $res['status'] = true;
      //   $res['payurl'] = $apiurl.'/api/payturn/printparam?'.$poststr.'&xcpt_condition_code=code&xcpt_condition_value=200&xcpt_key=data&xcpt_printr=payData'.'&xcpt_mysign='.$xcpt_mysign.'&submiturl='.$submiturl;
             Log::write("提交的参数是:".json_encode($data));
            $posturl = $this->http($submiturl,$data,'POST');
            Log::write('返回的信息是:'.$posturl);
            $result= json_decode($posturl,true);
            if($result['code']=='200')
            {
               $res['status'] = true;
               $res['payurl']=$result['data']['payData'];
           }
           else {
            $res['status'] = false;
            $res['payurl'] = $result['message'];
            $res['text'] = '';
        }
        return $res;
      
    }

    /**
     * 抽象方法，在SDK中实现
     * 设置签名
     */
    protected function setSign($param)
    {
       ksort($param); //按参数首字母升序
        $data = '';
        foreach ($param as $x => $x_value) {
          
            if( $x_value =='') 
            {
               continue;//键名或键值为空不参与签名 
            }
            
            $data .= $x . '=' . $x_value . '&';
        }
        $str=substr($data,0,-1);
        $data =$this->AppSecret.$str.$this->AppSecret;
        Log::write('秘钥签名串为:'.$data);
        $sign=md5($data);
        $sign = strtoupper($sign);
        Log::write("加密后的sign值为:".$sign);
       return  $sign;
    }

}