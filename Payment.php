<?php
/**
 * Power by similing
 * QQ:845206213
 * Date: 2020-06-15
 * Time: 10:47
 */

namespace app\index\controller;

use think\Controller;
use think\Db;
use think\Config;
use think\Exception;
use think\Log;
use Payment\WeixinAppPayment;
use Payment\PaymentCallbackInterface;

/**
 * Class Payment 用户充值控制器类，集成了用户充值相关内容
 * Power by similing
 * @time,2020-06-15
 */
class Payment extends Controller {
    /*
    * 创建微信APP支付订单接口
    * 地址：/index/payment/create_weixin_order.html
    * 参数：
    * uid：充值目标的用户ID
    * money：充值金额（单位分）
    * 返回值：APP登录所需JSON
    */
    public function create_weixin_order($uid, $money) {
        if (!is_numeric($money) || $money == "" || $money < 0)
            $this->error("充值金额不正确");
        $money = intval($money);
        if ($money <= 0)
            $this->error("充值金额不正确");
        $extra = "余额充值" . ($money / 100) . "元";
        $data = [
            "uid"       => $uid,
            "money"     => $money,
            "paymethod" => "weixin",
            "status"    => 0,
            "extra"     => $extra
        ];
        $orderid = Db::name("payment_order")->insertGetId($data);
        $wxapp = new WeixinAppPayment('你服务器的公网IP');
        $orderData = $wxapp->makeOrder($orderid, $money, $extra, 'https://你的域名/index/payment/weixin_notify.html');
        echo json_encode($orderData);
    }

    /*
    * 微信APP支付回调接口
    * 地址：/index/payment/weixin_notify.html
    */
    public function weixin_notify() {
        $wxapp = new WeixinAppPayment('你服务器的公网IP');
        $wxapp->notify(new class() implements PaymentCallbackInterface {
            public function callback($order, $fee) {
                $u = Db::name("user")->where('id', $order['uid'])->find();
                if (!$u)
                    return;
                Db::name("user")->where('id', $order['uid'])->setInc("money", $fee);
                $userManager = new UserManager();
                $userManager->reLoadUserDataOnly($order['uid']);
                Db::name("payment_user_order")->insert([
                    "uid"          => $order['uid'],
                    "type"         => 0,
                    "detail"       => "微信余额充值",
                    "money"        => $fee,
                    "detail_id"    => $order['id'],
                    "before_money" => $u['money'],
                    "after_money"  => $u['money'] + $fee
                ]);
            }
        });
    }
}
