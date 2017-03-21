<?php

namespace cy\swagger;

use yii\base\Component;
use yii\base\Module as BaseModule;
use yii\helpers\Inflector;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * Class Controller
 */
class Controller extends Component
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $moduleId;

    /**
     * @var string
     */
    public $summary;

    /**
     * @var array
     */
    public $description;

    /**
     * @var array
     */
    public $verbs;

    /**
     * @var Action[]
     */
    public $actions;

    /**
     * @var Module[]
     */
    protected static $modules;

    /**
     * @param \ReflectionClass $class
     * @return Controller
     * @throws \Exception
     */
    public static function parse(\ReflectionClass $class)
    {
        $docComment = $class->getDocComment();

        if (!$docComment) {
            throw new \Exception(sprintf('%s comment lose.', $class->getName()));
        }

        $id = Inflector::camel2id(str_replace('Controller', '', $class->getShortName()));
        $factory = DocBlockFactory::createInstance();
        $docBlock = $factory->create($docComment);
        $moduleId = current(explode('\\', $class->getNamespaceName()));
        $summary = $docBlock->getSummary();
        $description = array_map('trim', array_filter(explode(PHP_EOL, $docBlock->getDescription()->render())));
        $instance = $class->newInstance($id, self::getModule($moduleId));
        $verbs = $instance->verbs();

        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);

        $actionMethods = array_filter($methods, function (\ReflectionMethod $method) {
            return strpos($method->name, 'action') === 0 && $method->name != 'actions';
        });

        $actions = [];

        foreach ($actionMethods as $method) {
            $actions[] = Action::parse($method);
        }

        $config = compact('id', 'moduleId', 'summary', 'description', 'verbs', 'actions');

        return new self($config);
    }

    /**
     * @param $id
     * @return Module
     */
    protected static function getModule($id)
    {
        if (!isset(self::$modules[$id])) {
            self::$modules[$id] = new BaseModule($id);
        }

        return self::$modules[$id];
    }
}
