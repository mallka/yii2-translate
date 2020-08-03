<?php

namespace yii\translate;

use GuzzleHttp\Psr7\Request;

class GoogleTranslate extends Translate
{
    public $url = 'https://translate.google.com/translate_a/single';

    protected $tokenProvider;
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

    protected function buildRequest($target, $text)
    {
        $token = $this->getTokenProvider()->generateToken($this->source, $target, $text);
        $queryParams = array_merge($this->urlParams, [
            'sl' => $this->source,
            'tl' => $target,
            'tk' => $token,
            'q' => $text
        ]);
        $url = $this->url . '?' . preg_replace('/%5B(?:[0-9]|[1-9][0-9]+)%5D=/', '=', http_build_query($queryParams));
        return new Request('GET', $url, $this->headers);
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

    protected function getTokenProvider()
    {
        if (is_null($this->tokenProvider)) {
            $this->tokenProvider = new GoogleTokenGenerator();
        }
        return $this->tokenProvider;
    }
}
