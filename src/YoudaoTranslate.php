<?php

namespace yii\translate;

use yii\base\InvalidConfigException;
use GuzzleHttp\Psr7\Request;

class YoudaoTranslate extends Translate
{
    public $url = 'https://openapi.youdao.com/api';

    protected function beforeTranslate()
    {
        if (!isset($this->options['app_key']) || empty($this->options['app_key'])) {
            throw new InvalidConfigException("Options must contain 'app_key' and 'app_secret' field!");
        }
        if (!isset($this->options['app_secret']) || empty($this->options['app_secret'])) {
            throw new InvalidConfigException("Options must contain 'app_key' and 'app_secret' field!");
        }
        parent::beforeTranslate();
    }

    protected function buildRequest($target, $text)
    {
        $curtime = time();
        $salt = $this->_createGuid();
        $signStr = $this->options['app_key'] . $this->_truncate($text) . $salt . $curtime . $this->options['app_secret'];
        $queryParams = [
            'q' => $text,
            'appKey' => $this->options['app_key'],
            'salt' => $salt,
            'from' => $this->source,
            'to' => $target,
            'signType' => 'v3',
            'curtime' => $curtime,
            'sign' => hash('sha256', $signStr),
        ];
        $queryData = '';
        foreach ($queryParams as $key => $val) {
            $queryData .= "$key=" . rawurlencode($val) . "&";
        }
        $queryData = trim($queryData, "&");
        return new Request('POST', $this->url, [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], $queryData);
    }

    protected function handleResponse($response)
    {
        $body = $response->getBody();
        $data = json_decode($body, true);
        if ($data && $data['errorCode'] == 0) {
            return $data['translation'][0];
        } else {
            return false;
        }
    }

    private function _createGuid()
    {
        $microTime = microtime();
        list($a_dec, $a_sec) = explode(" ", $microTime);
        $dec_hex = dechex($a_dec * 1000000);
        $sec_hex = dechex($a_sec);
        $this->_ensureLength($dec_hex, 5);
        $this->_ensureLength($sec_hex, 6);
        $guid = "";
        $guid .= $dec_hex;
        $guid .= $this->_createGuidSection(3);
        $guid .= '-';
        $guid .= $this->_createGuidSection(4);
        $guid .= '-';
        $guid .= $this->_createGuidSection(4);
        $guid .= '-';
        $guid .= $this->_createGuidSection(4);
        $guid .= '-';
        $guid .= $sec_hex;
        $guid .= $this->_createGuidSection(6);
        return $guid;
    }

    private function _createGuidSection($characters)
    {
        $return = "";
        for ($i = 0; $i < $characters; $i++) {
            $return .= dechex(mt_rand(0, 15));
        }
        return $return;
    }

    private function _truncate($q)
    {
        $len = $this->_abslength($q);
        return $len <= 20 ? $q : (mb_substr($q, 0, 10) . $len . mb_substr($q, $len - 10, $len));
    }

    private function _abslength($str)
    {
        if (empty($str)) {
            return 0;
        }
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, 'utf-8');
        } else {
            preg_match_all("/./u", $str, $ar);
            return count($ar[0]);
        }
    }

    private function _ensureLength(&$string, $length)
    {
        $strlen = strlen($string);
        if ($strlen < $length) {
            $string = str_pad($string, $length, "0");
        } else if ($strlen > $length) {
            $string = substr($string, 0, $length);
        }
    }
}
