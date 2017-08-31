<?php

namespace mike\swagger;

use yii\base\Component;
use yii\base\Model;
use yii\helpers\Inflector;

/**
 * Class Parameter
 */
class Parameter extends Component
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var boolean
     */
    public $required;

    /**
     * @var string
     */
    public $type;

    /**
     * @param Model $model
     * @param $scenario
     * @return Parameter[]
     */
    public static function parse(Model $model, $scenario)
    {
        $scenarios = $model->scenarios();
        $parameters = isset($scenarios[$scenario]) ? $scenarios[$scenario] : [];
        $rules = $model->rules();

        $parameters = array_map(function ($fieldName) use ($model, $rules, $scenario) {
            $property = new \ReflectionProperty($model, $fieldName);
            $comment = $property->getDocComment();
            $varTag = self::parseVarTag($comment);
            $type = 'string';
            $description = '';

            if ($varTag) {
                $type = $varTag[0];
                $description = $varTag[1];
            }

            $required = self::isRequired($fieldName, $rules, $scenario);
            $name = Inflector::underscore($fieldName);

            return new self(compact('name', 'type', 'description', 'required'));
        }, $parameters);

        return $parameters;
    }

    /**
     * @param $comment
     * @return array|bool
     */
    protected static function parseVarTag($comment)
    {
        if (preg_match('/@var\s+(.+)\s+(.*)$/isUm', $comment, $match)) {
            return array_splice($match, 1);
        };

        return false;
    }

    /**
     * @param $name
     * @param $rules
     * @param $scenario
     * @return bool
     */
    protected static function isRequired($name, $rules, $scenario)
    {
        $required = false;

        foreach ($rules as $rule) {
            if ($rule[1] != 'required') {
                continue;
            }
            if (is_array($rule[0])) {
                if (!in_array($name, $rule[0])) {
                    continue;
                }
            } elseif ($name != $rule[0]) {
                continue;
            }
            if (isset($rule['on']) && is_array($rule['on'])) {
                if (in_array($scenario, $rule['on'])) {
                    $required = true;
                }
            } else {
                $required = true;
            }
            if ($required) {
                break;
            }
        }

        return $required;
    }
}
