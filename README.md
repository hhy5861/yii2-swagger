## 安装

1. 修改 `composer.json` 文件


```
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:hhy5861/yii2-swagger.git"
    }
]
```

2. 执行 `composer require "mike/swagger:dev-master"`

3. 修改项目配置

```
'modules' => [
    'swagger' => [
        'class' => 'mike\swagger\Module',
    ],
]
```

