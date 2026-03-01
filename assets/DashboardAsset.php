<?php

namespace app\assets;

use yii\web\AssetBundle;

class DashboardAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/dashboard.css',
    ];
    public $js = [
        'js/dashboard.js',
    ];
    public $depends = [
        AppAsset::class,
        'yii\\bootstrap5\\BootstrapPluginAsset',
        'yii\\widgets\\PjaxAsset',
    ];
}
