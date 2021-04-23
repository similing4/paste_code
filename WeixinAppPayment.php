<?php
/**
 * Power by similing
 * QQ:845206213
 * Date: 2020-04-25
 * Time: 18:00
 */

namespace Payment;

use think\Db;

/**
 * Class WeixinAppPayment 微信APP支付类，集成了微信APP支付相关内容
 * Power by similing
 * @time,2020-04-28
 */
class WeixinAppPayment extends PaymentBase {
    private $public_ip;

    /*
    * 构造方法
    * 参数：
    * public_ip：服务器公网IP
    */
    public function __construct($public_ip) {// 必须设置，公网IP与域名
        parent::__construct();
        $this->public_ip = $public_ip;
    }

    /*
    * 创建第三方订单发起支付，需要设置payTable属性（默认值为pay_order，也就是默认为tp_pay_order表）
    * 该表必须包含自增主键和order_id（var_char(100)）、status(tinyint(1))、money（int(11)）三列
    * 参数：
    * id：订单ID，即payTable的主键ID
    * money：订单金额，单位为分
    * extra：订单注释，用于显示给用户支付相关的提示
    * notify_url：回调URL
    * 返回：要输出的数据数组
    */
    public function makeOrder($id, $money, $extra, $notify_url) {
        $order = Db::name($this->payTable)->where('id', $id)->find();
        if (!$order)
            $this->error("创建订单失效");
        $detail = $extra;
        $wxapp = $this->config["weixin_app"];
        $total_fee = $money;  //价格（分）
        $body = $detail;  //商品名称
        $appid = $wxapp['APPID'];  //appid
        $mch_id = $wxapp['MCHID'];    //商户号
        $nonce_str = $this->nonce_str();//随机字符串
        $wx_key = $wxapp['KEY'];//申请支付后有给予一个商户账号和密码，登陆后自己设置的key
        $out_trade_no = str_pad($id, 32, "0", STR_PAD_LEFT);//商户订单号
        $spbill_create_ip = $this->public_ip;//服务器的ip【自己填写】;
        $trade_type = 'APP';//交易类型 默认
        //这里是按照顺序的 因为下面的签名是按照顺序 排序错误 肯定出错
        $post = [];
        $post['appid'] = $appid;
        $post['body'] = $body;
        $post['detail'] = $detail;
        $post['mch_id'] = $mch_id;
        $post['nonce_str'] = $nonce_str;//随机字符串
        $post['notify_url'] = $notify_url;
        $post['out_trade_no'] = $out_trade_no;
        $post['spbill_create_ip'] = $spbill_create_ip;//终端的ip
        $post['total_fee'] = $total_fee;//总金额
        $post['trade_type'] = $trade_type;
        $sign = $this->sign($post, $wx_key);//签名
        $post_xml = '<xml>
			<appid>' . $appid . '</appid>
			<body>' . $body . '</body>
			<detail>' . $detail . '</detail>
			<mch_id>' . $mch_id . '</mch_id>
			<nonce_str>' . $nonce_str . '</nonce_str>
			<notify_url>' . $notify_url . '</notify_url>
			<out_trade_no>' . $out_trade_no . '</out_trade_no>
			<spbill_create_ip>' . $spbill_create_ip . '</spbill_create_ip>
			<total_fee>' . $total_fee . '</total_fee>
			<trade_type>' . $trade_type . '</trade_type>
			<sign>' . $sign . '</sign>
		</xml> ';
        //统一接口prepay_id
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $xml = $this->http_request($url, $post_xml);
        $array = $this->xml($xml);//全要大写
        if ($array['RETURN_CODE'] == "SUCCESS" && $array["RESULT_CODE"] == "SUCCESS") {
            $arr = [];
            $arr["appid"] = $array['APPID'];
            $arr["noncestr"] = $array['NONCE_STR'];
            $arr["package"] = "Sign=WXPay";
            $arr["partnerid"] = $array['MCH_ID'];
            $arr["prepayid"] = $array['PREPAY_ID'];
            $arr["timestamp"] = time();
            $sign = $this->sign($arr, $wx_key);
            $data = [
                "appid"     => $array['APPID'],
                "partnerid" => $array['MCH_ID'],
                "prepayid"  => $array['PREPAY_ID'],
                "package"   => "Sign=WXPay",
                "noncestr"  => $array['NONCE_STR'],
                "timestamp" => $arr["timestamp"],
                "paySign"   => $sign
            ];
            return $data;
        } else {
            $this->error($array['ERR_CODE_DES']);
        }
    }

    public function notify($callback) {
        $xml = file_get_contents('php://input');
        $data = $this->xml($xml);
        $tid = $data['TRANSACTION_ID'];
        $fee = $data['CASH_FEE'];
        $data = $this->getOrderByTid($tid);
        if ($data) {
            $order_id = $data['OUT_TRADE_NO'];
            $torder_id = $data['TRANSACTION_ID'];
            $fee = $data['TOTAL_FEE'];
            $order = Db::name($this->payTable)->where('id', intval($order_id))->find();
            if ($order['status'] != 1) {
                Db::name($this->payTable)->where('id', intval($order_id))->setField([
                    "order_id" => $torder_id,
                    "status"   => 1,
                    'money'    => $fee
                ]);
                $callback->callback($order, $fee);
            }
        }
        echo("<?xml version='1.0' encoding='UTF-8'?><xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>");
        exit();
    }

    private function getOrderByTid($transaction_id) {
        $wxapp = $this->config["weixin_app"];
        $appid = $wxapp['APPID'];
        $mch_id = $wxapp['MCHID'];
        $wx_key = $wxapp['KEY'];
        $nonce_str = $this->nonce_str();
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";

        $post = [];
        $post['appid'] = $appid;
        $post['mch_id'] = $mch_id;
        $post['nonce_str'] = $nonce_str;
        $post['transaction_id'] = $transaction_id;
        $sign = $this->sign($post, $wx_key);
        $post['sign'] = $sign;
        $xml = $this->ToXml($post);
        $response = $this->http_request($url, $xml);
        $result = $this->xml($response);
        if (array_key_exists("RETURN_CODE", $result) && array_key_exists("RESULT_CODE", $result)
            && $result["RETURN_CODE"] == "SUCCESS" && $result["RESULT_CODE"] == "SUCCESS")
            return $result;
        return false;
    }

    private function ToXml($array) {
        $xml = "<xml>";
        foreach ($array as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    //获取xml
    private function xml($xml) {
        $p = xml_parser_create();
        xml_parse_into_struct($p, $xml, $vals, $index);
        xml_parser_free($p);
        $data = "";
        foreach ($index as $key => $value) {
            if ($key == 'xml' || $key == 'XML') continue;
            $tag = $vals[$value[0]]['tag'];
            $value = $vals[$value[0]]['value'];
            $data[$tag] = $value;
        }
        return $data;
    }

    //随机32位字符串
    private function nonce_str() {
        $result = '';
        $str = 'QWERTYUIOPASDFGHJKLZXVBNMqwertyuioplkjhgfdsamnbvcxz';
        for ($i = 0; $i < 32; $i++) {
            $result .= $str[rand(0, 48)];
        }
        return $result;
    }

    //签名 $data要先排好顺序
    private function sign($data, $wx_key) {
        $stringA = '';
        foreach ($data as $key => $value) {
            if (!$value) continue;
            if ($stringA) $stringA .= '&' . $key . "=" . $value;
            else $stringA = $key . "=" . $value;
        }
        $stringSignTemp = $stringA . '&key=' . $wx_key;
        return strtoupper(md5($stringSignTemp));
    }

    //curl请求
    private function http_request($url, $data = null, $headers = array()) {
        $curl = curl_init();
        if (count($headers) >= 1) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}
