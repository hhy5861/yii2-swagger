<?php

namespace cy\swagger;

use Yii;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Model;
use yii\helpers\Inflector;
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * Class Action
 */
class Action extends Component
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $summary;

    /**
     * @var array
     */
    public $description;

    /**
     * @var bool
     */
    public $isJson = false;

    /**
     * @var bool
     */
    public $isJsonArray = false;

    /**
     * @var Parameter[]
     */
    public $parameters;

    /**
     * @param \ReflectionMethod $method
     * @return Action
     * @throws ErrorException
     */
    public static function parse(\ReflectionMethod $method)
    {
        $id = Inflector::camel2id(str_replace('action', '', $method->getName()));

        $docComment = $method->getDocComment();

        if (!$docComment) {
            throw new ErrorException(
                sprintf(
                    '%s::%s comment lose.',
                    $method->getDeclaringClass()->getName(),
                    $method->getName()
                )
            );
        }

        $factory = DocBlockFactory::createInstance();
        $docBlock = $factory->create($docComment);

        $summary = $docBlock->getSummary();
        $description = array_map('trim', array_filter(explode(PHP_EOL, $docBlock->getDescription()->render())));

        $body = self::getMethodBody($method);

        $isJson = preg_match('/getRawBody/', $body) ? true : false;
        $isJsonArray = $isJson ? (preg_match('/(for|foreach)\s*\(/', $body) ? true : false) : false;

        $parameters = self::parseActionParameters($method);

        $config = compact('id', 'summary', 'description', 'isJson', 'isJsonArray', 'parameters');

        return new self($config);
    }

    /**
     * @param \ReflectionMethod $method
     * @return Parameter[]|array
     */
    protected static function parseActionParameters(\ReflectionMethod $method)
    {
        $form = self::parseForm($method);

        if (!$form) {
            return [];
        }

        list($formClassName, $scenario) = $form;

        return Parameter::parse(self::createModelObject($formClassName), $scenario);
    }

    /**
     * @param $name
     * @return Model
     */
    protected static function createModelObject($name)
    {
        return Yii::createObject($name);
    }

    /**
     * @param \ReflectionMethod $method
     * @return array|null
     */
    protected static function parseForm(\ReflectionMethod $method)
    {
        $methodBody = self::getMethodBody($method);

        $formName = null;
        $formCallMethod = null;

        if (preg_match('/\(new\s+(.*Form)(?:\(\))?\)->(.*)\(/m', $methodBody, $match)) {
            list($formName, $formCallMethod) = [$match[1], $match[2]];
        } else {
            if (preg_match('/(\$.+)\s+=\s+new\s+(.+Form)/m', $methodBody, $m1)) {
                if (preg_match('/' . preg_quote($m1[1]) . '->([^\(\s]+)\(/m', $methodBody, $m2)) {
                    list($formName, $formCallMethod) = [$m1[2], $m2[1]];
                }
            }
        }

        if (!$formName) {
            return null;
        }

        $formClassName = self::parseFormClassName($method->getDeclaringClass(), $formName);

        if ($formCallMethod == 'validateScenario') {
            $scenario = self::parseScenarioFromValidateScenarioMethod($methodBody);
        } else {
            $method = (new \ReflectionClass($formClassName))->getMethod($formCallMethod);
            $formCallMethodBody = self::getMethodBody($method);
            $scenario = self::parseScenarioFromValidateScenarioMethod($formCallMethodBody);
        }

        return [$formClassName, $scenario ?: Model::SCENARIO_DEFAULT];
    }

    /**
     * @param $body
     * @return bool|string
     */
    protected static function parseScenarioFromValidateScenarioMethod($body)
    {
        if (preg_match('/\->validateScenario\(.*,\s(.*)\)/', $body, $match)) {
            $scenario = trim($match[1], '"\'');
            if (strpos($scenario, '::SCENARIO_')) {
                $arr = explode('::SCENARIO_', $scenario);
                $scenario = strtolower($arr[1]);
                $scenario = Inflector::variablize($scenario);
            }
            return $scenario;
        }

        return false;
    }

    /**
     * @param \ReflectionClass $class
     * @param $name
     * @return mixed
     * @throws \ErrorException
     */
    protected static function parseFormClassName(\ReflectionClass $class, $name)
    {
        $forms = self::getControllerUsedForms($class);
        foreach ($forms as $form) {
            if (preg_match('/' . preg_quote('\\' . $name) . '$/', $form)) {
                return $form;
            }
        }
        $message = sprintf('无法从 \'%s\' 中解析表单 \'%s\'', $class->getName(), $name);
        throw new \ErrorException($message);
    }

    /**
     * @param \ReflectionClass $class
     * @return mixed
     */
    protected static function getControllerUsedForms(\ReflectionClass $class)
    {
        static $caches = [];
        if (!isset($caches[$class->getName()])) {
            $body = self::getClassBody($class);
            preg_match_all('/^use\s+(.*Form);/mU', $body, $matchs);
            $caches[$class->getName()] = $matchs ? $matchs[1] : [];
        }
        return $caches[$class->getName()];
    }

    /**
     * @param \ReflectionClass $class
     * @return string
     */
    protected static function getClassBody(\ReflectionClass $class)
    {
        $content = file($class->getFileName());

        return implode('', $content);
    }

    /**
     * @param \ReflectionMethod $method
     * @return string
     */
    protected static function getMethodBody(\ReflectionMethod $method)
    {
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $content = file($method->getFileName());
        $length = $endLine - $startLine;

        return implode('', array_slice($content, $startLine, $length));
    }
}
