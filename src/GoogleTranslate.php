<?php

namespace yii\translate;

use yii\base\BaseObject;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;

class GoogleTranslate extends BaseObject implements TranslateInterface
{
    public $url = 'https://translate.google.com/translate_a/single';
    public $tokenProvider;

    protected $source;
    protected $target = 'en';
    protected $client;
    protected $options = [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.94 Safari/537.36',
        ],
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
            $this->client = new Client();
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

    public function setOptions($options = null)
    {
        $this->options = $options ?? [];
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
            $promises = [];
            foreach ($this->target as $target) {
                $this->options['query'] = $this->buildQueryString($this->source, $target, $content);
                $promises[$target] = $this->client->getAsync($this->url, $this->options);
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
                $this->options['query'] = $this->buildQueryString($this->source, $this->target, $content);
                $response = $this->client->get($this->url, $this->options);
                $result = [
                    $this->target => $this->handleResponse($response),
                ];
                return $result;
            } catch (RequestException $e) {
                throw new TranslateException('Google Translate Error！');
            }
        }
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
            throw new TranslateException('Data cannot be decoded or it is deeper than the recursion limit');
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
}
