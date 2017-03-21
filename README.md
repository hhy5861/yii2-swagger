## 安装

1. 修改 `composer.json` 文件


```
"repositories": [
    {
        "type": "vcs",
        "url": "git@gitlab.oapol.com:yii/yii2-cy-swagger.git"
    }
]
```

2. 执行 `php composer.phar --prefer-dist require cy/swagger`

3. 修改项目配置

```
'modules' => [
    'swagger' => [
        'class' => 'cy\swagger\Module',
    ],
]
```

