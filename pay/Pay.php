<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/2/2
 * Time: 14:26
 */
namespace pay;
use think\Log;

abstract class Pay
{
    /**
     * 申请应用时分配的app_account
     * @var string
     */
    protected $AppAccount = '';

    /**
     * 申请应用时分配的 app_secret
     * @var string
     */
    protected $AppSecret = '';

    /**
     * 回调页面URL  可以通过配置文件配置
     * @var string
     */
    protected $Callback = '';

    /**
     * API根路径
     * @var string
     */
    protected $ApiBase = '';


    /**
     * 调用接口类型
     * @var string
     */
    private $Type = '';

    /**
     * 构造方法，配置应用信息
     * @param array $token
     */
    public function __construct($config){
        //设置SDK类型
        $class = get_class($this);
        $this->Type = strtoupper(substr($class, 0, strlen($class)-3));

        //获取应用配置
        if (empty($config)){
            throw new Exception('请配置您申请的APP_Account和APP_SECRET');
        }else{
            $this->AppAccount    = $config['APP_ACCOUNT'];
            $this->AppSecret = $config['APP_SECRET'];
            $this->Callback = $config['APP_CALLBACK'];
			$this->InfoUrl = $config['APP_INFOURL'];
        }
    }

    /**
     * 取得Oauth实例
     * @static
     * @return mixed 返回Oauth
     */
    public static function getInstance($type, $config) {
        $name = ucfirst(strtolower($type)) . 'SDK';
        require_once "sdk/{$name}.php";
        if (class_exists($name)) {
            return new $name($config);
        } else {
            E(L('_CLASS_NOT_EXIST_') . ':' . $name);
        }
    }

    /**
     * 初始化配置
     */
    public function config(){
        $config = C("THINK_SDK_{$this->Type}");
        if(!empty($config['CALLBACK']))
            $this->Callback = $config['CALLBACK'];
        else
            throw new Exception('请配置回调页面地址');
    }

    /**
     * 合并默认参数和额外参数
     * @param array $params  默认参数
     * @param array/string $param 额外参数
     * @return array:
     */
    protected function param($params, $param){
        if(is_string($param))
            parse_str($param, $param);
        return array_merge($params, $param);
    }

    /**
     * 获取指定API请求的URL
     * @param  string $api API名称
     * @param  string $fix api后缀
     * @return string      请求的完整URL
     */
    protected function url($api, $fix = ''){
        return $this->ApiBase . $api . $fix;
    }

    /**
     * 发送HTTP请求方法，目前只支持CURL发送请求
     * @param  string $url    请求URL
     * @param  array  $params 请求参数
     * @param  string $method 请求方法GET/POST/JSON/XML
     * @return array  $data   响应数据
     */
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

    protected function turnSign($data)
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

    protected function turnUrl() {
        if ( !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            $hp = 'https';
        } elseif ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
            $hp = 'https';
        } elseif ( !empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            $hp = 'https';
        }else{
            $hp = "http";
        }
        $apiurl = $hp.'://'.$_SERVER['HTTP_HOST'];
        return $apiurl;
    }

    /**
     * 抽象方法，在SDK中实现
     * 组装接口调用参数 并调用接口
     */
    abstract protected function call($api, $param = '', $method = 'GET', $multi = false);

    /**
     * 抽象方法，在SDK中实现
     * 设置签名
     */
    abstract protected function setSign($data);
}
