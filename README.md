# yii2-translate
Translation component for Yii2, multiple language support, html translate support.

用于Yii2的翻译组件，支持同时翻译多种语言， 支持翻译Html。

## 安装
    composer require spiritbox/yii2-translate
    
## 备注
    Google翻译接口可能会被封IP
    有道翻译网址：https://ai.youdao.com   

## 配置
    'components' => [
        ...
        'translate' => [
            'class' => 'yii\translate\Translate',
            'provider' => [
                'class' => 'yii\translate\GoogleTranslate',
                'url' => 'http://translate.google.cn/translate_a/single',
                'options' => [
                    'timeout' => 2,//单次请求超时时限
                    'concurrency' => 10,//并发请求数
                ],
            ],
        ],
    ]
    或
    'components' => [
        ...
        'translate' => [
            'class' => 'yii\translate\Translate',
            'provider' => [
                'class' => 'yii\translate\YoudaoTranslate',
                'appKey' => '***',
                'appSecret' => '***',
                'options' => [
                    'timeout' => 2,//单次请求超时时限
                    'concurrency' => 10,//并发请求数
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
        
### 多语言键值翻译示例
    Yii::$app->get('translate')
        ->setSource('en')
        ->setTarget(['zh-CN', 'zh-TW', 'de', 'es', 'it', 'fr'])
        ->transText(['k1' => Hello World!', 'k2' => Hello World!']); 
        
### 多语言Html翻译示例
    Yii::$app->get('translate')
        ->setSource('en')
        ->setTarget(['zh-CN', 'zh-TW', 'de', 'es', 'it', 'fr'])
        ->transHtml('<h1>Hello World!</h1>');                
            