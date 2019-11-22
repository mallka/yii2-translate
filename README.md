# yii2-translate
Google, Bing translation component for Yii2, multiple language support, 用于Yii2的Google, Bing 翻译组件，支持同时翻译多种语言

## 差异
    Google 以中文简体（zh-CN）和中文繁体（zh-TW）标识
    Bing 以中文简体（zh-Hans）和中文繁体（zh-Hant）标识


## 配置
    'components' => [
        ...
        'translate' => [
            'class' => 'app\components\translate\Translate',
            //'provider' => 'google',//直接配置为google或bing
            'provider' => [
                'class' => 'app\components\translate\GoogleTranslate', //'class' => 'app\components\translate\BingTranslate',
                'url' => 'http://translate.google.cn/translate_a/single', //'url' => 'https://cn.bing.com/ttranslatev3',
                ],
            ],
        ],
    ]
## 用法
### 单语言示例
    Yii::$app->get('translate')
        ->setSource('en')
        ->setTarget('zh-CN')
        ->transText('Hello World!');

### 多语言示例
    Yii::$app->get('translate')
        ->setSource('en')
        ->setTarget(['zh-CN', 'zh-TW', 'de', 'es', 'it', 'fr'])
        ->transText('Hello World!');