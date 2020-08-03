# yii2-translate
用于Yii2的翻译组件，支持同时翻译多种语言， 支持Html翻译。
Translation component for Yii2, multiple language support, html translate support.

## 安装
    composer require spiritbox/yii2-translate
    
## 备注
    Google翻译接口可能会被封IP， 可用于测试， 真是环境建议使用有道翻译。
    有道翻译网址：https://ai.youdao.com   

## 配置
    'translate' => [
        'class' => 'yii\translate\GoogleTranslate',
        'url' => 'https://translate.google.cn/translate_a/single',
        'clientOptions' => [
            'timeout' => 3, //单次请求超时时限
            'concurrency' => 10, //并发请求数
        ],
    ],
    或
    'translate' => [
        'class' => 'yii\translate\YoudaoTranslate',
        'clientOptions' => [
            'timeout' => 3, //单次请求超时时限
            'concurrency' => 10, //并发请求数
        ],
        'options' => [
            'app_key' => '******',//有道应用ID
            'app_secret' => '******',//有道应用密钥
        ],
    ],
    
## 用法
#### Google翻译（支持语种见：src/GoogleLanguages.php）
    ### 单语言示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget('zh-CN')
            ->transText('Hello World!');
    
    ### 多语言示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget(['zh-CN', 'de', 'es', 'it', 'fr'])
            ->transText('Hello World!');
            
    ### 多语言键值翻译示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget(['zh-CN', 'de', 'es', 'it', 'fr'])
            ->transText(['k1' => 'Hello World!', 'k2' =>'I come from china!']);
             
    ### 单语种Html翻译示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget('zh-CN')
            ->transHtml('<h1>Hello World!</h1>');
                    
    ### 多语言Html翻译示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget(['zh-CN', 'de', 'es', 'it', 'fr'])
            ->transHtml('<h1>Hello World!</h1>');
            
#### 有道翻译（支持语种见：src/YoudaoLanguages.php）
    ### 单语言示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget('zh-CHS')
            ->transText('Hello World!');
    
    ### 多语言示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget(['zh-CHS', 'de', 'es', 'it', 'fr'])
            ->transText('Hello World!');
            
    ### 多语言键值翻译示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget(['zh-CHS', 'de', 'es', 'it', 'fr'])
            ->transText(['k1' => 'Hello World!', 'k2' =>'I come from china!']); 
    
    ### 单语种Html翻译示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget('zh-CHS')
            ->transHtml('<h1>Hello World!</h1>');
            
    ### 多语言Html翻译示例
        Yii::$app->get('translate')
            ->setSource('en')
            ->setTarget(['zh-CHS', 'de', 'es', 'it', 'fr'])
            ->transHtml('<h1>Hello World!</h1>');                
            