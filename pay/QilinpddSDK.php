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

class QilinpddSDK extends Pay
{
	private $root = 'root';
	private $encoding = 'UTF-8';
	private $version = 'V1.0.0';
    //扫码非快捷
    //提交和返回数据都为 XML 格式
    //商户号:900052031101
    //秘钥加密密文:DE0029CE164E96CF8876D12
    //先判断协议字段返回，再判断业务返回，最后判断交易状态
    protected $ApiBase='http://47.102.47.181:9103/pay/v1/jiey';//扫码支付
    //h5支付protected $ApiBase1='http://jft.mingjianmy.com/sweep/Sweep/scan';
    public function call($api, $param = '', $method = 'GET', $multi = false)
    {
        $parter = $this->AppAccount;
        $submiturl = isset($param['ApiUrl'])?$param['ApiUrl']:$this->ApiBase;

        switch (strtolower($param['payment'])){
            case 'alipay':
                $type='ali_wap';
                break;
            // case 'wxpay':
            //     $type = 'trade.weixin.native';
            //     break;
        }
        $data['version']='1.0';//版本号
        $data['signType']='MD5';
        $data['payMethod']=$type;
        $data['partner']=$this->AppAccount;//商户号
        $data['orderAmount']=$param['bill_price'];//总金额 单位元 支持小数点后2位
        $data['orderId']=$param['order_id'];
        $data['attach']='recharge';
        $data['mchCreateIp']=$param['device_ip'];
        $data['productName']='recharge';
        $data['productDesc']='recharge';
        $data['notifyUrl']=$this->Callback;
        $data['callbackUrl']=$this->Callback;
        $sign= $this->setSign($data);//
        $data['sign']=$sign;
        Log::write("吊起支付的相关参数是:".json_encode($data));
        //   $this->xml = new XmlWriter();
        // $xmldata = $this->toXml($data).'</xml>';
        $xmldata=$this->arrayToXml($data);
        Log::write("转换xml后的数据:".$xmldata);
        $result = $this->http($submiturl,$xmldata,'XML');
        $returnarr = $this->xmlToArray($result);
        $resultdata = json_decode(json_encode($returnarr),TRUE);
        Log::write('qilinpdd return code :'.json_encode($resultdata,JSON_UNESCAPED_UNICODE));
        if($resultdata['status']=='0')
        {
             $res['status'] = true;
             $res['payurl'] = $resultdata['pay_info'];
        }
        else {
            $res['status'] = false;
            $res['payurl'] = '';
            $res['text'] = $resultdata['message'];
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
			if ($value === '' || $value == null) {
				continue;
			}
			$str .= $key . '=' . $value . '&';
		}
		$sign = md5($str .'key=' . $this->AppSecret);
		return strtoupper($sign);
        }
         function xmlToArray($xml){ 

        //禁止引用外部xml实体 

        libxml_disable_entity_loader(true); 

        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA); 

        $val = json_decode(json_encode($xmlstring),true); 

        return $val; 

    }
    
     function toXml($data, $eIsArray=FALSE) {
        if(!$eIsArray) {
            $this->xml->openMemory();
            $this->xml->startDocument($this->version, $this->encoding);
        }
        foreach($data as $key => $value){

            if(is_array($value)){
                $this->xml->startElement($key);
                $this->toXml($value, TRUE);
                $this->xml->endElement();
                continue;
            }
            $this->xml->writeElement($key, $value);
        }
        if(!$eIsArray) {
            $this->xml->endElement();
            return $this->xml->outputMemory(true);
        }
    }
    function arrayToXml($arr){ 
$xml = "<root>"; 
foreach ($arr as $key=>$val){ 
if(is_array($val)){ 
$xml.="<".$key.">".arrayToXml($val)."</".$key.">"; 
}else{ 
$xml.="<".$key.">".$val."</".$key.">"; 
} 
} 
$xml.="</root>"; 
return $xml; 
}
        
        
}




