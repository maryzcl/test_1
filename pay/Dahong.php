<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/2/11
 * Time: 12:23
 */
namespace app\callback\controller;

use app\callback\controller\Callbackbase;
use think\Log;
use think\Db;
class Dahong extends Callbackbase
{
   public $publickey='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDhM2ID5SrUY44RCE22LhfPPmY9hPtXqdazofVTpsOpoD9TPENju8qqjsQZKMhWHEJeTSt+3+gbrba3jztd0SAbMR0AmEjjmVLRHoOy0hGEk12Nk+9tOsQkya5iU6yb0PCuBwG767mEIT8qnpoXWLfAupbUpkyyJvp9IYQz8q48qwIDAQAB';
    public function notify(){
        $postarr = input('param.');
        Log::write('dahong Callback success: '.json_encode($postarr));
        $paychannel=Db::table('tb_recharge a')
                  ->join('tb_recharge_channel b','a.PayChannelID=b.IDX')
                  ->field("a.PayChannelID,b.IDX,b.Token")
                  ->where(['a.orderID'=>$postarr['out_trade_no']])
                  ->find();
        //    ksort($postarr);
        // $postbuff = "";
        // foreach ($postarr as $key => $value) {
        //     if($key=='sign' || $value=='')
        //     {
        //         continue;
        //     }
        //         $postbuff .=$key . '=' . $value . '&';
        // }
            $encodeStr=$this->rsaVerify($postarr);
             Log::write("返回的签名是:".$postarr['sign']);
            Log::write("验签的签名是:".$encodeStr);
        if($postarr['sign']==$encodeStr)
        {
            Log::write('签名成功');
            if($postarr['out_trade_no']) //支付成功
            {
                $rechargeinfo = Db::table('tb_recharge')->where(['OrderID' => $postarr['out_trade_no'], 'Status' => 0])->find();
                if (!$rechargeinfo){
                    echo 'SUCCESS';die;
                }
                $info = [
                    "orderno" => $postarr['trade_no'],
                    "money" =>$postarr['amount'],
                    "merorderno" => $postarr['out_trade_no']
                ];
                $r = $this->updaterecharge($info, $rechargeinfo);
                if ($r){
                    Log::write('回调了');
                    echo 'SUCCESS';die;
                }else{
                    echo 'failure';die;
                }
            }
            else
            {
                echo "支付失败";
            }
        }
        else
        {
            Log::write('签名失败');
            echo "签名验证失败";
        }
        
    }
    //openssl_verify函数验证签名  如果签名正确返回 1, 签名错误返回 0, 内部发生错误则返回-1.
 // public function rsaVerify_string($arr)  {
 //      $sign = $arr['sign'];
 //      unset($arr['sign']);
 //      $toSign = serialize($arr);
 //      $publicKeyId = openssl_pkey_get_public($this->setPublicKey($this->publickey));
 //      Log::write("openssl_pkey_get_public函数的返回值:".$publicKeyId);
 //      //$publicKeyId = openssl_get_publickey($this->bublickey);
 //      Log::write("公钥验签的结果是:".openssl_verify($toSign, base64_decode($sign), $publicKeyId,OPENSSL_ALGO_SHA1));
 //      $result = (bool)openssl_verify($toSign, base64_decode($sign), $publicKeyId,OPENSSL_ALGO_SHA1);
 //      openssl_free_key($publicKeyId);
 //      return $result;

 //    }
    public function setPublicKey($value){

        if(is_file($value)) $value = file_get_contents($value);
        $value = chunk_split($value, 64, "\n");
        $value = "-----BEGIN PUBLIC KEY-----\n$value-----END PUBLIC KEY-----\n";
        return openssl_pkey_get_public($value);
    }
    /**
     * 格式化待签名数据
     *
     * @param array $data
     *
     * @return string
     */
    private function formatSignatureString($data)
    {
        ksort($data);
        $string = [];
        foreach ($data as $key => $value) {
            if ($key != 'sign' && $key != '' && $value != '') {
                $string[] = $key . '=' . $value;
            }
        }
        $string = join('&', $string);
        return $string;
    }

/**
 2  * 校验签名
 $data为签名的数组数据
 3  * @param    string     $pubKey 公钥
 4  * @param    string     $sign   签名
 5  * @param    string     $string 待签名字符串
 6  * @param    string     $signature_alg 签名方式 比如 sha1WithRSAEncryption 或者sha512
 7  * @return   bool
 8  */
    public function rsaVerify($data)
    {
        //$sign为对方返回的签名串
        $sign = base64_decode($data['sign']);
        $string = $this->formatSignatureString($data);
        $pub_key_id = openssl_pkey_get_public($this->setPublicKey($this->publickey));
        $result = (bool) openssl_verify($string, $sign, $pub_key_id, OPENSSL_ALGO_SHA1);
        openssl_free_key($pub_key_id);
        return $result;
    }


   
}

