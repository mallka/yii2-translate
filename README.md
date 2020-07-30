# yii2-translate
Translation component for Yii2, multiple language support, html translate support.

用于Yii2的翻译组件，支持同时翻译多种语言， 支持翻译Html。

## 安装
    composer require spiritbox/yii2-translate

## 配置
    'components' => [
        ...
        'translate' => [
            'class' => 'yii\translate\Translate',
            //'provider' => 'google',//直接配置为google或bing
            'provider' => [
                'class' => 'yii\translate\GoogleTranslate', //'class' => 'yii\translate\BingTranslate',
                'url' => 'http://translate.google.cn/translate_a/single', //'url' => 'https://cn.bing.com/ttranslatev3',
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
            