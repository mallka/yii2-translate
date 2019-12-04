<?php

namespace yii\translate;

use yii\base\BaseObject;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;

class BingTranslate extends BaseObject implements TranslateInterface
{
    public $url = 'https://www.bing.com/ttranslatev3';

    protected $source;
    protected $target = 'en';
    protected $client;
    protected $options = [
        'headers' => [
            'User-Agent' => 'AndroidTranslate/5.3.0.RC02.130475354-53000263 5.1 phone TRANSLATE_OPM5_TEST_1',
        ],
        'query' => [
            'IG' => '2E6B6EAFE202440C904436E362C05775',
            'IID' => 'translator.5025.1',
        ],
    ];

    protected $resultRegexes = [
        '/,+/' => ',',
        '/\[,/' => '[',
    ];

    public function init()
    {
        if (is_null($this->client)) {
            $this->client = new Client();
        }
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function setOptions($options = null)
    {
        $this->options = $options ?? [];
        return $this;
    }

    public function setSource($source = null)
    {
        $this->source = $source ?? 'auto-detect';
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
            $promises = [];
            foreach ($this->target as $target) {
                $this->options['form_params'] = [
                    'fromLang' => $this->normalize($this->source),
                    'text' => $content,
                    'to' => $this->normalize($target),
                ];
                $promises[$target] = $this->client->postAsync($this->url, $this->options);
            }
            $results = Promise\unwrap($promises);
            $results = Promise\settle($promises)->wait();
            $result = [];
            foreach ($results as $k => $item) {
                if ($item['state'] == Promise\Promise::FULFILLED) {
                    $result[$k] = $this->handleResponse($item['value']);
                }
            }
            return $result;
        } else {
            try {
                $this->options['form_params'] = [
                    'fromLang' => $this->source,
                    'text' => $content,
                    'to' => $this->normalize($this->target),
                ];
                $response = $this->client->post($this->url, $this->options);
                $result = [
                    $this->target => $this->handleResponse($response),
                ];
                return $result;
            } catch (RequestException $e) {
                throw new TranslateException('Bing Translate Error！');
            }
        }
    }

    protected function handleResponse($response)
    {
        $body = $response->getBody();
        $body = preg_replace(array_keys($this->resultRegexes), array_values($this->resultRegexes), $body);
        $data = json_decode($body, true);
        if ($data === null) {
            throw new TranslateException('Data cannot be decoded or it is deeper than the recursion limit');
        }
        if (isset($data['statusCode'])) {
            throw new TranslateException('Bing Translate no return dataset');
        }
        return $data[0]['translations'][0]['text'];
    }

    protected function normalize($language)
    {
        if ($language == 'zh-CN') {
            $language = 'zh-Hans';
        }
        if ($language == 'zh-TW') {
            $language = 'zh-Hant';
        }
        return $language;
    }
}
