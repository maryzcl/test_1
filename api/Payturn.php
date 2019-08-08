<?php
namespace app\api\controller;
use think\Db;
use think\Log;
use think\Loader;
use think\Controller;
Loader::import('phpqrcode.phpqrcode', EXTEND_PATH, '.php');
class Payturn extends Controller
{	
	protected $mprefix = 'xcpt_';

	function printparam(){
		header("Content-type:text/html;charset=utf-8");
		$data = input('param.');
		Log::write('payturn data : '.json_encode($data));
		extract($data);
		foreach($data as $k=>$v){
			if(strpos('s'.$k,$this->mprefix)==1){
				unset($data[$k]);
			}
		}
		$mysigns = $this->mySign($data);
		if(isset($data['submiturl'])){
			unset($data['submiturl']);
		}

		if(strtoupper($mysigns) != strtoupper($xcpt_mysign)){
			echo json_encode(['result'=>-1,'msg'=>'签名失败']);die;
		}
		if(isset($xcpt_posttype))
		{
			switch ($xcpt_posttype) {
				case 'JSON':
					$geturl = $this->http($submiturl,json_encode($data),'JSON');
					break;
				case 'GET':
					$geturl = $this->http($submiturl,$data,'GET');
				default:
					$geturl = $this->http($submiturl,$data,'POST');
					break;
			}
		}else{
			$geturl = $this->http($submiturl,$data,'POST');
		}
		
        Log::write('return code: '.$geturl);
        
        if(isset($xcpt_condition_code) && isset($xcpt_condition_value)){
        	$result = json_decode($geturl,true);
        	if($result[$xcpt_condition_code]==$xcpt_condition_value){
        		if(isset($xcpt_key)){
        			echo $result[$xcpt_key][$xcpt_printr];
        		}else{
        			echo $result[$xcpt_printr];
        		}
        		
        	}
        }else{
        	echo $geturl;
        }

	}

	function subparam(){
		$data = input('param.');
		extract($data);
		if(isset($data['mysign'])){
			unset($data['mysign']);
		}
		
		$mysigns = $this->mySign($data);

		if(isset($data['submiturl'])){
			unset($data['submiturl']);
		}
		if(strtoupper($mysigns) != strtoupper($mysign)){
			echo json_encode(['result'=>-1,'msg'=>'签名失败']);die;
		}
		$str = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html>
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
			<meta content="application/json">
			<title>跳转支付...</title>
		</head>';
		$str .= "<body onLoad='document.yeepay.submit();'><form name='yeepay' action='".$submiturl."' method='post' >";
		foreach ($data as $key => $value) {
			if($key=="mysign"){
				continue;
			}
			$str .= "<input type='hidden' name='".$key."' value='".$value."'>";
		}
		
		$str.= "</form></body></html>";
		echo $str;
	}

	function mySign($data)
    {
        // TODO: Implement setSign() method.
        ksort($data);
        reset($data);
        $str = '';
        foreach($data as $key=>$val){
            if(!$val){
                continue;
            }
            $str .= $key .'='.$val.'&';
        }
        $str .= 'key='.md5('MD5_DIVISOR');
        $sign  = strtoupper(md5($str));
        return $sign;
    }


	function xinchen(){
		header("Content-type:text/html;charset=utf-8");
		$data = input('param.');
		Log::write('payturn data : '.json_encode($data));
		extract($data);
		$mysign = md5('externalId='.$externalId.'&amount='.$amount.'&time='.$time.md5('MD5_DIVISOR'));
		if(strtolower($sign) != strtolower($mysign)){
			return json_encode(['msg'=>'参数错误','result'=>-1]);
		}
		if(isset($pay_url)){
			$http_type = $this->is_https();
		    if($http_type){
		    	$hp = "https";
		    }else{
		    	$hp = "http";
		    }
		    if(isset($type)){
		    	switch ($type) {
		    		case '0011':
		    			$appnotice = '支付宝';
		    			break;
		    		case '0040':
		    			$appnotice = '手机银行app';
		    			break;
		    		case '0002':
		    			$appnotice = '微信';
		    			break;
		    		default:
		    			$appnotice = '支付宝';
		    			break;
		    	}
		    }else{
		    	$appnotice = '支付宝';
		    }
			$apiurl = $hp.'://'.$_SERVER['HTTP_HOST'];
			$this->assign('pay_url',rtrim($apiurl,'/').'/api/payturn/shilianqr?pay_url='.urlencode($pay_url));
			$this->assign('money',sprintf("%.2f",$amount/100));
			$time = isset($time)?$time:time();
			$startTime = date('Y年m月d H:i:s',$time);
			$endTime = date('Y年m月d H:i:s',$time+180);
			$this->assign('startTime',$startTime);
			$this->assign('endTime',$endTime);
			$this->assign('appnotice',$appnotice);
            return $this->fetch('shilian');
		}else{
			return json_encode(['msg'=>'参数错误','result'=>-1]);exit();
		}
	}

	function tongtong(){
		$data = input('param.');
		extract($data);
		$mysigns = md5('memberid='.$memberid.'&orderid='.$orderid.'&notify_url='.$notify_url.'&amount='.$amount.'&sign='.$sign.md5('MD5_DIVISOR'));
		if(strtoupper($mysigns) != strtoupper($mysign)){
			echo json_encode(['result'=>-1,'msg'=>'签名失败']);die;
		}
		$data['datetime'] = date('Y-m-d H:i:s',$data['datetime']);
		$str = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html>
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
			<meta content="application/json">
			<title>跳转支付...</title>
		</head>';
		$str .= "<body onLoad='document.yeepay.submit();'><form name='yeepay' action='https://www.53k.cc/user/transfer.php' method='post' >";
		foreach ($data as $key => $value) {
			if($key=="mysign"){
				continue;
			}
			$str .= "<input type='hidden' name='".$key."' value='".$value."'>";
		}
		
		$str.= "</form></body></html>";
		echo $str;
	}

	function zihaizhifu(){
		$data = input('param.');
		$data['pay_applydate'] = date('Y-m-d H:i:s',$data['pay_applydate']);
		extract($data);

		$mysigns = md5('pay_amount='.$pay_amount.'&pay_orderid='.$pay_orderid.'&pay_memberid='.$pay_memberid.md5('MD5_DIVISOR'));
		if(strtoupper($mysigns) != strtoupper($mysign)){
			echo json_encode(['result'=>-1,'msg'=>'签名失败']);die;
		}
		$str = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html>
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
			<meta content="application/json">
			<title>跳转支付...</title>
		</head>';
		$str .= "<body onLoad='document.yeepay.submit();'><form name='yeepay' action='https://zzdmou.com/Pay_Index.html' method='post' >";
		foreach ($data as $key => $value) {
			if($key=="mysign"){
				continue;
			}
			if($key=="pay_productname"){
				$value = '充值';
			}
			$str .= "<input type='hidden' name='".$key."' value='".$value."'>";
		}
		
		$str.= "</form></body></html>";		
				
		echo $str;
	}
	
	function quanqiufu(){
		$data = input('param.');
		$data['pay_applydate'] = date('Y-m-d H:i:s',$data['pay_applydate']);
		extract($data);

		$mysigns = md5('pay_amount='.$pay_amount.'&pay_orderid='.$pay_orderid.'&pay_memberid='.$pay_memberid.md5('MD5_DIVISOR'));
		if(strtoupper($mysigns) != strtoupper($mysign)){
			echo json_encode(['result'=>-1,'msg'=>'签名失败']);die;
		}
		$str = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html>
		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
			<meta content="application/json">
			<title>跳转支付...</title>
		</head>';
		$str .= "<body onLoad='document.yeepay.submit();'><form name='yeepay' action='http://www.yzw788.com/Pay_Index.html' method='post' >";
		foreach ($data as $key => $value) {
			if($key=="mysign"){
				continue;
			}
			$str .= "<input type='hidden' name='".$key."' value='".$value."'>";
		}
		
		$str.= "</form></body></html>";		
				
		echo $str;exit();
	}

	function shilian(){
		header("Content-type:text/html;charset=utf-8");
		$data = input('param.');
		Log::write('payturn data : '.json_encode($data));
		extract($data);
		$mysign =  md5('pay_url='.$pay_url.'&total_fee='.$total_fee.'&time='.$time.md5('MD5_DIVISOR').'&type='.$type);
		if(strtolower($sign) != strtolower($mysign)){
			return json_encode(['msg'=>'参数错误','result'=>-1]);
		}
		if(isset($pay_url)){
			$http_type = $this->is_https();
		    if($http_type){
		    	$hp = "https";
		    }else{
		    	$hp = "http";
		    }
		    if(isset($type)){
		    	switch ($type) {
		    		case 'ALISCAN':
		    			$appnotice = '支付宝';
		    			break;
		    		case 'UPSCAN':
		    			$appnotice = '手机银行app';
		    			break;
		    		case 'WXSCAN':
		    			$appnotice = '微信';
		    			break;
		    		default:
		    			$appnotice = '支付宝';
		    			break;
		    	}
		    }else{
		    	$appnotice = '支付宝';
		    }
			$apiurl = $hp.'://'.$_SERVER['HTTP_HOST'];
			$this->assign('pay_url',rtrim($apiurl,'/').'/api/payturn/shilianqr?pay_url='.$pay_url);
			$this->assign('money',sprintf("%.2f",$total_fee/100));
			$time = isset($time)?$time:time();
			$startTime = date('Y年m月d H:i:s',$time);
			$endTime = date('Y年m月d H:i:s',$time+180);
			$this->assign('startTime',$startTime);
			$this->assign('endTime',$endTime);
			$this->assign('appnotice',$appnotice);
            return $this->fetch('shilian');
		}else{
			return json_encode(['msg'=>'参数错误','result'=>-1]);exit();
		}
	}
	public static function shilianqr($pay_url,$b=false,$level='L',$size=8){
		header('Content-Type: image/png');
		vendor("phpqrcode.phpqrcode");
		ob_start();
		$QRcode = new \QRcode();
		$imageString = $QRcode::png($pay_url,$b,$level,$size,2,true);
		$content = ob_get_clean();
		ob_end_clean();
		echo $content;exit();
	}

	function is_https() {
	    if ( !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
	        return true;
	    } elseif ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
	        return true;
	    } elseif ( !empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
	        return true;
	    }
	    return false;
	}

	protected function http($url, $params, $method = 'GET', $header = array(), $multi = false){
        $proxy = '47.52.170.243';
        $proxyport = '31128';

        $opts = array(
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $header,
            CURLOPT_PROXY          => $proxy,
            CURLOPT_PROXYPORT      => $proxyport
        );
        $CA = true;
        $cacert = 'data/ca/cacert.pem'; //CA根证书
        if ($cacert){
            $SSL = substr($url, 0, 8) == "https://" ? true : false;
            if ($SSL && $CA) {
                $opts[CURLOPT_SSL_VERIFYPEER] = true;   // 只信任CA颁布的证书
                $opts[CURLOPT_CAINFO] = $cacert;        // CA根证书（用来验证的网站证书是否是CA颁布）
                $opts[CURLOPT_SSL_VERIFYHOST] = 2;      // 检查证书中是否设置域名，并且是否与提供的主机名匹配
            }else if ($SSL && !$CA) {
                $opts[CURLOPT_SSL_VERIFYPEER] = false;
                $opts[CURLOPT_SSL_VERIFYHOST] = 1;
            }
        }else{
            $opts[CURLOPT_SSL_VERIFYPEER] = 0;
        }

        /* 根据请求类型设置特定参数 */
        switch(strtoupper($method)){
            case 'GET':
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                break;
            case 'POST':
                //判断是否传输文件
                $params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            case 'JSON':
                //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length:' . strlen($data)));
                //判断是否传输文件
                //$params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_HTTPHEADER] = array('Content-Type: application/json', 'Content-Length:' . strlen($params));
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            case 'XML':
                //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: '. strlen($data)) );
                //判断是否传输文件
                //$params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_HTTPHEADER] = array('Content-Type: application/xml', 'Content-Length:' . strlen($params));
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default:
                throw new Exception('不支持的请求方式！');
        }
        $opts[CURLOPT_RETURNTRANSFER] = 1;
        /* 初始化并执行curl请求 */
        $ch = curl_init();

        curl_setopt_array($ch, $opts);
        $data  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if($error) throw new Exception('请求发生错误：' . $error);
        return  $data;
    }
}
?>