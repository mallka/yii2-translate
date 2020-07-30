<?php

namespace yii\translate\drivers;

use yii\base\BaseObject;
use yii\translate\TranslateInterface;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use PHPHtmlParser\Dom;
use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class YoudaoTranslate extends BaseObject implements TranslateInterface
{
    public $url = 'https://openapi.youdao.com/api';
    public $appKey;
    public $appSecret;
    public $options = [
        'timeout' =>  2,
        'concurrency' => 10,
    ];

    protected $source;
    protected $target = 'en';
    protected $client;

    public function init()
    {
        if(is_null($this->appKey) || is_null($this->appSecret)) {
            throw new InvalidConfigException('AppKey & AppSecret can not empty!');
        }
        if (is_null($this->client)) {
            $this->client = new Client([
                'timeout' => $this->options['timeout'],
            ]);
        }
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function setOptions($options)
    {
        $this->options = ArrayHelper::merge($this->options, $options);
        return $this;
    }

    public function setSource($source = null)
    {
        $this->source = $source ?? 'auto';
        return $this;
    }

    public function setTarget($target)
    {
        $this->target = $target;
        return $this;
    }

    public function transText($content)
    {
        if (is_null($this->source)) {
            $this->source = 'auto';
        }
        if ($this->source == $this->target) {
            return $content;
        }
        if (is_array($this->target)) {
            $labels = [];
            $isMulti = is_array($content) ? true : false;
            $requests = function () use (&$labels, $isMulti, $content) {
                foreach ($this->target as $target) {
                    if ($isMulti) {
                        foreach ($content as $k => $text) {
                            $labels[] = [
                                'lang' => $target,
                                'key' => $k,
                            ];
                            yield new Request('POST', $this->url, [
                                'Content-Type' => 'application/x-www-form-urlencoded',
                            ], $this->buildQueryData($text, $target));
                        }
                    } else {
                        $labels[] = [
                            'lang' => $target,
                            'key' => false,
                        ];
                        yield new Request('POST', $this->url, [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ], $this->buildQueryData($content, $target));
                    }
                }
            };
            $responses = [];
            $pool = new Pool($this->client, $requests(), [
                'concurrency' => $this->options['concurrency'],
                'fulfilled' => function ($response, $index) use (&$responses) {
                    $responses[$index] = [
                        'status' => 1,
                        'response' => $response,
                    ];
                },
                'rejected' => function ($reason, $index) use (&$responses) {
                    $responses[$index] = [
                        'status' => 0,
                        'reason' => $reason,
                    ];
                },
            ]);
            $promise = $pool->promise();
            $promise->wait();
            $result = [];
            foreach ($labels as $k => $label) {
                if (isset($responses[$k])) {
                    if ($responses[$k]['status'] == 1) {
                        $data = $this->handleResponse($responses[$k]['response']);
                    } else {
                        $data = false;
                    }
                } else {
                    $data = false;
                }
                if ($label['key']) {
                    $result[$label['lang']][$label['key']] = $data;
                } else {
                    $result[$label['lang']] = $data;
                }
            }
            return $result;
        } else {
            $response = $this->client->request('POST', $this->url, [
                'query' => $this->buildQueryData($content, $this->target),
            ]);
            return $this->handleResponse($response);
        }
    }

    public function transHtml($content)
    {
        $dom = new Dom();
        $dom->load($content);
        $index = 0;
        $textList = $this->getTextList($dom, $index);
        $html = $dom->innerHtml;
        $result = [];
        if ($textList) {
            $data = $this->transText($textList);
            foreach ($data as $k => $item) {
                if ($item) {
                    $result[$k] = $this->concatText($html, $textList, $item);
                } else {
                    $result[$k] = false;
                }
            }
        } else {
            $targets = is_array($this->target) ? $this->target : [$this->target];
            foreach ($targets as $target) {
                $result[$target] = $html;
            }
        }
        return $result;
    }

    protected function getTextList(&$node, &$index = 0)
    {
        $textList = [];
        if ($node->hasChildren()) {
            foreach ($node->getChildren() as $subNode) {
                if ($subNode->isTextNode()) {
                    $text = trim($subNode->text);
                    if (!empty($text)) {
                        $key = 'node_' . $index;
                        $textList[$key] = $text;
                        $subNode->setText('{{' . $key . '}}');
                        $index++;
                    }
                } else {
                    $subTextList = $this->getTextList($subNode, $index);
                    if ($subTextList) {
                        $textList = array_merge($textList, $subTextList);
                    }
                }
            }
        }
        return $textList;
    }

    protected function concatText($rawText, $rawData, $newData)
    {
        foreach ($newData as $k => $item) {
            $key = '{{' . $k . '}}';
            if ($item) {
                $rawText = str_replace($key, $item, $rawText);
            } else {
                $rawText = str_replace($key, $rawData[$k], $rawText);
            }
        }
        return $rawText;
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

    protected function buildQueryData($text, $targetLang)
    {
        $curtime = time();
        $salt = $this->_createGuid();
        $signStr = $this->appKey . $this->_truncate($text) . $salt . $curtime . $this->appSecret;
        $queryParams = [
            'q' => $text,
            'appKey' => $this->appKey,
            'salt' => $salt,
            'from' => $this->source == 'zh-CN' ? 'zh-CHS' : $this->source,
            'to' => $targetLang == 'zh-CN' ? 'zh-CHS' : $targetLang,
            'signType' => 'v3',
            'curtime' => $curtime,
            'sign' => hash('sha256', $signStr),
        ];
        $result = '';
        foreach ($queryParams as $key => $val) {
            $result .= "$key=" . rawurlencode($val) . "&";
        }
        return trim($result, "&");
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
