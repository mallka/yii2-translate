<?php

namespace yii\translate;

use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\base\InvalidConfigException;
use PHPHtmlParser\Dom;
use GuzzleHttp\Pool;
use GuzzleHttp\Client;

abstract class Translate extends Component implements TranslateInterface
{
    public $url;
    public $source;
    public $target;
    public $clientOptions = [
        'timeout' => 2,
        'concurrency' => 10,
    ];
    public $options;

    protected $client;

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
        $this->beforeTranslate();
        if (is_null($this->source)) {
            $this->source = 'auto';
        }
        if ($this->source == $this->target) {
            return $content;
        }
        if (is_array($content) && is_string($this->target)) {
            $this->target = [$this->target];
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
                            yield $this->buildRequest($target, $text);
                        }
                    } else {
                        $labels[] = [
                            'lang' => $target,
                            'key' => false,
                        ];
                        yield $this->buildRequest($target, $content);
                    }
                }
            };
            $responses = [];
            $pool = new Pool($this->getClient(), $requests(), [
                'concurrency' => isset($this->clientOptions['concurrency']) ? $this->clientOptions['concurrency'] : 10,
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
            $request = $this->buildRequest($this->target, $content);
            $response = $this->getClient()->send($request);
            return $this->handleResponse($response);
        }
    }

    public function transHtml($content)
    {
        $this->beforeTranslate();
        $dom = new Dom();
        $dom->load($content);
        $index = 0;
        $textList = $this->getTextList($dom, $index);
        $html = $dom->root->innerHtml();
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

    abstract protected function buildRequest($target, $text);

    abstract protected function handleResponse($response);

    protected function beforeTranslate()
    {
        if (is_null($this->target)) {
            throw new InvalidConfigException("Target language can not be empty!");
        }
        if (is_null($this->url)) {
            throw new InvalidConfigException('Url can not be empty!');
        }
    }

    protected function getClient()
    {
        if (is_null($this->client)) {
            $this->client = new Client([
                'timeout' => isset($this->clientOptions['timeout']) ? $this->clientOptions['timeout'] : 2,
            ]);
        }
        return $this->client;
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
