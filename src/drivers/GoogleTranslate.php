<?php

namespace yii\translate\drivers;

use yii\base\BaseObject;
use yii\translate\TranslateInterface;
use yii\helpers\ArrayHelper;
use PHPHtmlParser\Dom;
use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

class GoogleTranslate extends BaseObject implements TranslateInterface
{
    public $url = 'https://translate.google.com/translate_a/single';
    public $tokenProvider;
    public $options = [
        'timeout' =>  2,
        'concurrency' => 10,
    ];

    protected $source;
    protected $target = 'en';
    protected $client;
    protected $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.94 Safari/537.36',
    ];
    protected $urlParams = [
        'client' => 'webapp',
        'hl' => 'en',
        'dt' => [
            't',
            'bd',
            'at',
            'ex',
            'ld',
            'md',
            'qca',
            'rw',
            'rm',
            'ss'
        ],
        'sl' => null,
        'tl' => null,
        'q' => null,
        'ie' => 'UTF-8',
        'oe' => 'UTF-8',
        'multires' => 1,
        'otf' => 0,
        'pc' => 1,
        'trs' => 1,
        'ssel' => 0,
        'tsel' => 0,
        'kc' => 1,
        'tk' => null,
    ];
    protected $resultRegexes = [
        '/,+/' => ',',
        '/\[,/' => '[',
    ];

    public function init()
    {
        if (is_null($this->client)) {
            $this->client = new Client([
                'timeout' => $this->options['timeout'],
            ]);
        }
        if (is_null($this->tokenProvider)) {
            $this->tokenProvider = new GoogleTokenGenerator();
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
            $this->setSource();
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
                            $url = $this->url . '?' . $this->buildQueryString($this->source, $target, $text);
                            yield new Request('GET', $url, $this->headers);
                        }
                    } else {
                        $labels[] = [
                            'lang' => $target,
                            'key' => false,
                        ];
                        $url = $this->url . '?' . $this->buildQueryString($this->source, $target, $content);
                        yield new Request('GET', $url, $this->headers);
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
            try {
                $options = [
                    'query' => $this->buildQueryString($this->source, $this->target, $content),
                    'headers' => $this->headers,
                ];
                $response = $this->client->get($this->url, $options);
                return [
                    $this->target => $this->handleResponse($response),
                ];
            } catch (RequestException $e) {
                return [
                    $this->target => false,
                ];
            }
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

    public function buildQueryString($source, $target, $content)
    {
        $queryParams = array_merge($this->urlParams, [
            'sl' => $source,
            'tl' => $target,
            'tk' => $this->tokenProvider->generateToken($source, $target, $content),
            'q' => $content
        ]);
        return preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', http_build_query($queryParams));
    }

    protected function handleResponse($response)
    {
        $body = $response->getBody();
        $body = preg_replace(array_keys($this->resultRegexes), array_values($this->resultRegexes), $body);
        $data = json_decode($body, true);
        if ($data === null) {
            return false;
        }
        if (!isset($data[0]) || empty($data[0])) {
            return null;
        }
        if (is_array($data[0])) {
            return (string)array_reduce($data[0], function ($carry, $item) {
                $carry .= $item[0];
                return $carry;
            });
        } else {
            return (string)$data[0];
        }
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
}
