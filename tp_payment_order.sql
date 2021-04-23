CREATE TABLE `tp_payment_order` (
 `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '订单ID',
 `uid` int(11) NOT NULL COMMENT '用户ID',
 `paymethod` varchar(20) NOT NULL COMMENT '支付手段（微信wexin,支付宝alipay）',
 `order_id` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方订单ID',
 `money` int(11) NOT NULL COMMENT '金额（单位分）',
 `status` tinyint(1) NOT NULL COMMENT '订单状态（0未付款1已付款）',
 `extra` varchar(200) NOT NULL DEFAULT '' COMMENT '附加说明',
 `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8 COMMENT='第三方支付订单表'
