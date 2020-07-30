<?php
/***
 * 组件
 */

namespace yii\translate;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\translate\drivers\GoogleTranslate;
use yii\translate\drivers\YoudaoTranslate;

class Translate extends Component
{
    public $provider = 'google';

    private $_provider = null;

    public function setUrl($url)
    {
        $this->getProvider()->setUrl($url);
        return $this;
    }

    public function setSource($source = null)
    {
        $this->getProvider()->setSource($source);
        return $this;
    }

    public function setTarget($target)
    {
        $this->getProvider()->setTarget($target);
        return $this;
    }

    public function transText($content)
    {
        return $this->getProvider()->transText($content);
    }

    public function transHtml($content)
    {
        return $this->getProvider()->transHtml($content);
    }

    public function getProvider()
    {
        $this->ensureProvider();
        return $this->_provider;
    }

    protected function ensureProvider()
    {
        if (!($this->_provider instanceof TranslateInterface)) {
            if (is_string($this->provider)) {
                if ($this->provider == 'google') {
                    $this->_provider = new GoogleTranslate();
                } elseif ($this->provider == 'youdao') {
                    $this->_provider = new YoudaoTranslate();
                } else {
                    throw new InvalidConfigException('Invalid translate provider config!');
                }
            } elseif (is_array($this->provider)) {
                $this->_provider = Yii::createObject($this->provider);
            } else {
                throw new InvalidConfigException('Invalid translate provider config!');
            }
        }
    }
}
