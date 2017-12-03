<?php
/**
 * 光大银行支付类
 * Created by PhpStorm.
 * User: Gilbert.Ho
 * Date: 12/4/17
 * Time: 12:10 AM
 * FILENAME:Ceb.php
 */

namespace hegzh\Payment;


class Ceb
{

    /**
     * 光大本行支付
     *
     * @param string $orderID 订单ID
     * @param float $orderAmt 订单金额
     * @param string $orderTime 订单时间
     * @param string $payType 支付类型 E企业 I个人
     * @param array $params 其它参数
     * @param int $currencyType 交易币种 默认软妹币
     *
     * @return array
     */
    public function payment($orderID, $orderAmt, $orderTime, $payType = 'E', $params = [], $currencyType = '01')
    {
        //支付类型
        $transId = empty($payType) ? 'EPER' : ($payType == 'E' ? 'EPER' : 'IPER');
        $params = [
            'transId'       => $transId,
            'merchantId'    => $this->mer_id,
            'orderId'       => (string)$orderID, //订单ID
            'transAmt'      => (float)$orderAmt,//交易金额
            'transDateTime' => $orderTime, //交易时间
            'currencyType'  => (string)$currencyType, //支付币种 默认人民币
            'customerName'  => empty($params['customerName']) ? '' : $params['customerName'],
            'merSecName'    => empty($params['merSecName']) ? '' : $params['merSecName'],//二级商户 非比输项
            'productInfo'   => empty($params['productInfo']) ? '' : $params['productInfo'],//商户信息描述（非必输）
            'customerEMail' => empty($params['customerEMail']) ? '' : $params['customerEMail'],//订货人Email
            'merURL'        => empty($params['merURL']) ? '' : $params['merURL'], //商户Url
            'merURL1'       => empty($params['merURL1']) ? '' : $params['merURL1'],//商户Url1
            'payIp'         => get_client_ip(),//支付ip
            'msgExt'        => empty($params['msgExt']) ? '' : $params['msgExt'], //附加信息 非比输项
        ];

        $text = $this->formatText($params);
        $return = [
            'name'      => $transId,
            'text'      => $text,
            'signature' => $this->signUseMerchantCert($text),
            'url'       => $transId == 'EPER' ? $this->url_enterprise . '/cebent/preEpayLogin.do?_locale=zh_CN' :
                $this->url_individual . '/per/preEpayLogin.do?_locale=zh_CN',
        ];

        error_log(date('Ymd H:i:s') . "\tpayment\t" . json_encode($return) . "\n", 3, LOG_PATH . "/ceb_post_" . date('Ymd') . ".log");
        return $return;
    }

    /**
     * 客户手续费试算
     *
     * @param float $transAmt 分期金额
     * @param int $stageTimes 分期数 0-0期 3-3期 6-6期 12-12期
     * @param string $currencyType 货币类型 默认软妹币
     *
     * @return array
     */
    public function chargeFee($transAmt, $stageTimes = 12, $currencyType = '01')
    {
        $plainName = 'SPFC';
        $params = [
            "transId"      => $plainName,
            "merchantId"   => $this->mer_id,
            "stageTimes"   => (int)$stageTimes,
            "transAmt"     => $transAmt,
            "transDate"    => date('Ymd'),
            "currencyType" => $currencyType,
        ];

        $text = $this->formatText($params);
        $return = [
            'name'      => $plainName,
            'text'      => $text,
            'signature' => $this->signUseMerchantCert($text),
            'url'       => $this->url_individual . '/per/stagePayCost.do',
        ];

        error_log(date('Ymd H:i:s') . "\tchargeFee\t" . json_encode($return) . "\n", 3, LOG_PATH . "/ceb_post_" . date('Ymd') . ".log");
        return $return;
    }

    /**
     * 订单查询
     *
     * @param string $orderId 原订单ID
     * @param string $orderTime 原订单时间 date('YmdHis')
     * @param float $originalTransAmt 原交易金额
     *
     * @return array
     */
    public function queryOrder($orderId, $orderTime, $originalTransAmt = '')
    {
        $plainName = 'IQSR';
        $params = [
            "transId"               => $plainName,
            "merchantId"            => $this->mer_id,
            "originalorderId"       => $orderId, //原订单号
            "originalTransDateTime" => $orderTime, //原交易时间
        ];
        if ($originalTransAmt)
        {
            $params['originalTransAmt'] = $originalTransAmt;
        }

        $text = $this->formatText($params);
        $return = [
            'name'      => $plainName,
            'text'      => $text,
            'signature' => $this->signUseMerchantCert($text),
            'url'       => $this->url_individual . '/per/QueryMerchantEpay.do',
        ];

        error_log(date('Ymd H:i:s') . "\tqueryOrder\t" . json_encode($return) . "\n", 3, LOG_PATH . "/ceb_post_" . date('Ymd') . ".log");
        return $return;
    }

    /**
     * 分期支付
     *
     * @param string $orderId 订单ID
     * @param float $orderAmt 交易金额
     * @param int $stageTimes 分期期数
     * @param float $fee 手续费
     * @param array $params 其它参数
     * @param string $currencyType 交易币种 默认是软妹币
     *
     * @return array
     */
    public function payDevide($orderId, $orderAmt, $stageTimes, $fee, $params = [], $currencyType = '01')
    {
        $plainName = 'SPER';
        $params = [
            'transId'       => $plainName,
            'merchantId'    => $this->mer_id,
            'orderId'       => (string)$orderId, //订单ID
            'transAmt'      => (float)$orderAmt, //交易金额
            'stageTimes'    => (int)$stageTimes, //分期期数
            'cifFee'        => (float)$fee, //客户手续费
            'transDateTime' => date('YmdHis'), //交易时间
            'currencyType'  => (string)$currencyType,
            'customerName'  => empty($params['customerName']) ? '' : $params['customerName'],
            'merSecName'    => empty($params['merSecName']) ? '' : $params['merSecName'],//二级商户 非比输项
            'productInfo'   => empty($params['productInfo']) ? '' : $params['productInfo'],//商户信息描述（非必输）
            'customerEMail' => empty($params['customerEMail']) ? '' : $params['customerEMail'],//订货人Email
            'merURL'        => empty($params['merURL']) ? '' : $params['merURL'], //商户Url
            'merURL1'       => empty($params['merURL1']) ? '' : $params['merURL1'],//商户Url1
            'payIp'         => get_client_ip(),//支付ip
            'msgExt'        => empty($params['msgExt']) ? '' : $params['msgExt'], //附加信息 非比输项
        ];

        $text = $this->formatText($params);
        $return = [
            'name'      => $plainName,
            'text'      => $text,
            'signature' => $this->signUseMerchantCert($text),
            'url'       => $this->url_individual . '/per/stagePay.do?_locale=zh_CN',
        ];

        error_log(date('Ymd H:i:s') . "\tpayDevide\t" . json_encode($return) . "\n", 3, LOG_PATH . "/ceb_post_" . date('Ymd') . ".log");
        return $return;
    }

    /**
     * 跨行支付
     *
     * @param string $orderId 订单ID
     * @param float $orderAmt 订单金额
     * @param string $payBankNo 他行行号
     * @param string $payType 支付类别 E=企业 I=个人
     * @param array $params 其它参数
     * @param string $currencyType 币种 默认软妹币
     *
     * @return array
     */
    public function crossPayment($orderId, $orderAmt, $payBankNo = '13', $payType = 'E', $params = [], $currencyType = '01')
    {
        $transId = empty($payType) ? 'EPER' : ($payType == 'E' ? 'EPER' : 'IPER');

        $params = [
            'transId'       => $transId,
            'merchantId'    => $this->mer_id_other,
            'orderId'       => (string)$orderId,
            'transAmt'      => (float)$orderAmt,
            'transDateTime' => date('YmdHis'),
            'currencyType'  => (string)$currencyType, //交易币种
            'payBankNo'     => (string)$payBankNo, //他行行号
            'customerName'  => empty($params['customerName']) ? '' : $params['customerName'],
            'merSecName'    => empty($params['merSecName']) ? '' : $params['merSecName'],//二级商户 非比输项
            'productInfo'   => empty($params['productInfo']) ? '' : $params['productInfo'],//商户信息描述（非必输）
            'customerEMail' => empty($params['customerEMail']) ? '' : $params['customerEMail'],//订货人Email
            'merURL'        => empty($params['merURL']) ? '' : $params['merURL'], //商户Url
            'merURL1'       => empty($params['merURL1']) ? '' : $params['merURL1'],//商户Url1
            'payIp'         => get_client_ip(),//支付ip
            'msgExt'        => empty($params['msgExt']) ? '' : $params['msgExt'], //附加信息 非比输项
        ];

        $text = $this->formatText($params);
        $return = [
            'text'      => $text,
            'name'      => $transId,
            'signature' => $this->signUseMerchantCert($text),
            'url'       => $transId == 'EPER' ? $this->url_individual . '/cebent/preEpayLogin2.do?_locale=zh_CN' : $this->url_individual . '/per/preEpayLogin2.do?_locale=zh_CN',
        ];

        error_log(date('Ymd H:i:s') . "\tcrossPayment\t" . json_encode($return) . "\n", 3, LOG_PATH . "/ceb_post_" . date('Ymd') . ".log");
        return $return;
    }

    /**
     * 格式化参数
     *
     * @param array $param
     *
     * @return bool|string
     */
    public function formatText($param)
    {
        $rs = '';
        foreach ($param as $k => $v)
        {
            $rs .= $k . '=' . $v . $this->devide_tag;
        }
        if (!empty($rs))
        {
            $rs = substr($rs, 0, -3);
        }

        return $rs;
    }

    /**
     * 暂未使用
     * @param $xml
     * @param $url
     */
    public function curl_xml($xml, $url)
    {
        //初始一个curl会话
        $curl = curl_init();

        //不推荐，应该对证书和域名进行校验
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 不验证主机域名

        curl_setopt($curl, CURLOPT_URL, $url);//设置请求url
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);//数据传输超时
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);//连接超时
        curl_setopt($curl, CURLOPT_HEADER, false); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_POST, true);//设置发送方式：POST
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // 获取的信息以文件流的形式返回
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: text/xml;charset=UTF-8']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);//设置发送数据

        //抓取URL并把它传递给浏览器
        $response = curl_exec($curl);

        echo "退货地址URI：" . $url . "<br />";
        echo "请求内容：<textarea>" . $xml . "</textarea><br/>";
        echo "请求返回的状态码：" . curl_error($curl);

        //关闭cURL资源，并且释放系统资源
        curl_close($curl);
        echo "银行返回的响应：<br/>";
        echo $response;
    }
}