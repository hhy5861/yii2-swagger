<?php

namespace mike\swagger;

use yii\web\AssetBundle;

/**
 * Swagger Asset
 */
class SwaggerAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/assets';
        parent::init();
    }
}
