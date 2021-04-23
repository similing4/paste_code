<?php
/**
 * Power by similing
 * QQ:845206213
 * Date: 2020-04-28
 * Time: 18:00
 */

namespace Payment;
use RedisDeal\RedisDeal;
use think\Request;
use think\Config;
use think\exception\HttpResponseException;
use think\Response;
use think\response\Redirect;
use think\Url;
use think\View as ViewTemplate;
/**
 * Class PaymentBase 支付类，集成了用户支付相关内容
 * Power by similing
 * @time,2020-04-28
 */

abstract class PaymentBase{
	protected $config;
	protected $payTable = "payment_order";
	public function __construct(){
	    $this->config = json_decode(file_get_contents(EXTEND_PATH."/config.json"),true);
	}
    protected function getRedis(){
	    $redis = new RedisDeal([]);
	    return $redis;
    }
    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param mixed  $msg    提示信息
     * @param string $url    跳转的 URL 地址
     * @param mixed  $data   返回的数据
     * @param int    $wait   跳转等待时间
     * @param array  $header 发送的 Header 信息
     * @return void
     * @throws HttpResponseException
     */
    protected function error($msg = '', $url = null, $data = '', $wait = 3, array $header = []){
        if (is_null($url)) {
            $url = Request::instance()->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif ('' !== $url && !strpos($url, '://') && 0 !== strpos($url, '/')) {
            $url = Url::build($url);
        }
        $type = $this->getResponseType();
        $result = [
            'code' => 0,
            'msg'  => $msg,
            'data' => $data,
            'url'  => $url,
            'wait' => $wait,
        ];
        if ('html' == strtolower($type)) {
            $template = Config::get('template');
            $view = Config::get('view_replace_str');

            $result = ViewTemplate::instance($template, $view)
                ->fetch(Config::get('dispatch_error_tmpl'), $result);
        }
        $response = Response::create($result, $type)->header($header);
        throw new HttpResponseException($response);
    }
    /**
     * 获取当前的 response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType(){
        return Request::instance()->isAjax()
            ? Config::get('default_ajax_return')
            : Config::get('default_return_type');
    }
	protected function getIp(){
		return Request::instance()->ip();
	}
	protected function getParam($k=false){
		if($k)
			return Request::instance()->param($k);
		else
			return Request::instance()->param();
	}
}
