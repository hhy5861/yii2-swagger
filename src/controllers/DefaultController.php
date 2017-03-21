<?php

namespace cy\swagger\controllers;

use Yii;
use yii\base\Exception;
use yii\helpers\Json;
use yii\web\Controller as WebController;
use yii\helpers\Inflector;
use cy\swagger\Controller;
use cy\swagger\Parameter;

/**
 * Default controller for the `swagger` module
 */
class DefaultController extends WebController
{
    /**
     * @inheritdoc
     */
    public $layout = false;

    /**
     * @var array
     */
    public $ignoreModules = ['debug', 'gii', 'swagger', 'api'];

    /**
     * Renders the index view for the module
     *
     * @return string
     */
    public function actionIndex()
    {
        $jsonUrl = current($this->getUrls());
        return $this->render('index', ['jsonUrl' => $jsonUrl]);
    }

    /**
     * Get urls from modules
     */
    protected function getUrls()
    {
        $urls = [];

        foreach (array_keys(Yii::$app->getModules()) as $moduleId) {
            if (in_array($moduleId, $this->ignoreModules)) {
                continue;
            }

            $url = Yii::$app->request->hostInfo .
                '/' . $this->module->id .
                '/' . $this->id .
                '/json?module=' .
                $moduleId;

            if (!json_decode(file_get_contents($url))) {
                continue;
            }

            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * Show all urls
     *
     * @return string
     */
    public function actionUrls()
    {
        return Json::encode($this->getUrls());
    }

    /**
     * render swagger json
     *
     * @param $module
     * @throws Exception
     */
    public function actionJson($module)
    {
        if (!Yii::$app->hasModule($module)) {
            echo sprintf('模块 %s 不存在。' . PHP_EOL, $module);
            Yii::$app->end();
        }

        list($scheme, $host) = explode('//', Yii::$app->request->hostInfo);

        $jsonArray = [
            'swagger' => '2.0',
            'info' => [
                'title' => $module,
            ],
            'host' => $host,
            'tags' => [],
            'schemes' => [trim($scheme, ':')],
            'paths' => [],
            'definitions' => [],
        ];

        $controllerPath = Yii::$app->getModule($module)->controllerPath;
        $controllerNamespace = Yii::$app->getModule($module)->controllerNamespace;

        foreach (glob($controllerPath . '/*Controller.php') as $filename) {
            $name = $controllerNamespace . '\\' . str_replace([$controllerPath . '/', '.php'], '', $filename);

            if (!strpos($name, 'SiteController')) {
                $class = new \ReflectionClass($name);

                $controller = Controller::parse($class);

                $jsonArray['tags'][] = [
                    'name' => $controller->id,
                    'description' => $controller->summary,
                ];

                foreach ($controller->actions as $action) {
                    $id = '/' . $controller->moduleId . '/' . $controller->id . '/' . $action->id;
                    $verb = $this->getActionVerb($action->id, $controller->verbs);

                    $path = [
                        'tags' => [$controller->id],
                        'summary' => $action->summary,
                        'description' => implode(PHP_EOL, array_map(function ($value) {
                            return '- ' . $value;
                        }, $action->description)),
                        'operationId' => $id,
                        'consumes' => $verb == 'post' ?
                            ($action->isJson ? ['application/json'] : ['application/x-www-form-urlencoded']) : [],
                        'produces' => ['application/json'],
                        'parameters' => [],
                    ];

                    if (!$action->isJsonArray) {
                        $path['parameters'] = array_map(function (Parameter $parameter) use ($verb) {
                            return [
                                'name' => Inflector::underscore($parameter->name),
                                'in' => $verb == 'get' ? 'query' : 'formData',
                                'description' => $parameter->description,
                                'required' => $parameter->required,
                                'type' => $parameter->type,
                            ];
                        }, $action->parameters);
                    } else {
                        $refId = str_replace('/', '__', $id);

                        $scheme = [
                            '$ref' => '#/definitions/' . $refId,
                        ];

                        if ($action->isJsonArray) {
                            $scheme = [
                                'type' => 'array',
                                'items' => [
                                    '$ref' => '#/definitions/' . $refId
                                ]
                            ];
                        }

                        $path['parameters'][] = [
                            'in' => 'body',
                            'name' => 'body',
                            'description' => '',
                            'required' => true,
                            'schema' => $scheme,
                        ];

                        $jsonArray['definitions'][$refId] = [
                            'type' => 'object',
                            'properties' => [],
                        ];

                        foreach ($action->parameters as $parameter) {
                            $jsonArray['definitions'][$refId]['properties'][$parameter->name] = [
                                'type' => $parameter->type,
                                'default' => '',
                            ];
                        }

                        $markdownParameters = [
                            '| 名称 | 类型 | 是否必选 | 描述 |',
                            '| -- | -- | -- | -- |',
                        ];

                        foreach ($action->parameters as $parameter) {
                            $markdownParameters[] = sprintf(
                                '| %s | %s | %s | %s |',
                                $parameter->name,
                                $parameter->type,
                                $parameter->required ? '是' : '否',
                                $parameter->description
                            );
                        }

                        $path['description'] .= str_repeat(PHP_EOL, 2) . implode(PHP_EOL, $markdownParameters);
                    }

                    $path['parameters'][] = [
                        'name' => 'token',
                        'in' => 'header',
                        'description' => '',
                        'required' => true,
                        'type' => 'string',
                        'default' => 'xx',
                    ];

                    $jsonArray['paths'][$id][$verb] = $path;
                }
            }
        }

        echo json_encode($jsonArray);
    }

    /**
     * 获得 action 的 verb
     *
     * @param $id
     * @param $verbs
     * @return mixed|string
     */
    public function getActionVerb($id, $verbs)
    {
        if (isset($verbs[$id])) {
            return current($verbs[$id]);
        }

        if (preg_match('/(get|search|list|query)/', $id)) {
            return 'get';
        } elseif (preg_match('/(update|create|delete|edit|save)/', $id)) {
            return 'post';
        }

        return 'post';
    }
}
