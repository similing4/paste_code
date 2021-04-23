<?php
/**
 * Power by similing
 * QQ:845206213
 * Date: 2020-04-25
 * Time: 18:00
 */

namespace Payment;
/**
 * Class PaymentCallbackInterface 微信APP支付回调接口
 * Power by similing
 * @time,2020-04-28
 */
 interface PaymentCallbackInterface{
    public function callback($order,$fee);
}
