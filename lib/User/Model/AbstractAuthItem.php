<?php

/*
 * This file is part of the 2amigos/yii2-usuario project.
 *
 * (c) 2amigOS! <http://2amigos.us/>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Da\User\Model;

use Da\User\Traits\AuthManagerTrait;
use Da\User\Validator\RbacItemsValidator;
use Da\User\Validator\RbacRuleValidator;
use yii\base\Model;
use yii\rbac\Item;
use Yii;

abstract class AbstractAuthItem extends Model
{
    use AuthManagerTrait;

    /**
     * @var string
     */
    public $itemName;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $description;
    /**
     * @var string
     */
    public $rule;
    /**
     * @var string[]
     */
    public $children;
    /**
     * @var \yii\rbac\Role|\yii\rbac\Permission
     */
    public $item;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if ($this->item instanceof Item) {
            $this->itemName = $this->item->name;
            $this->name = $this->item->name;
            $this->description = $this->item->description;
            $this->children = array_keys($this->getAuthManager()->getChildren($this->item->name));
            if ($this->item->ruleName !== null) {
                $this->rule = get_class($this->getAuthManager()->getRule($this->item->ruleName));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'name' => Yii::t('user', 'Name'),
            'description' => Yii::t('user', 'Description'),
            'children' => Yii::t('user', 'Children'),
            'rule' => Yii::t('user', 'Rule'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        return [
            'create' => ['name', 'description', 'children', 'rule'],
            'update' => ['name', 'description', 'children', 'rule'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['itemName', 'safe'],
            ['name', 'required'],
            ['name', 'match', 'pattern' => '/^[\w][\w-.:]+[\w]$/'],
            [['name', 'description', 'rule'], 'trim'],
            [
                'name',
                function () {
                    if ($this->getAuthManager()->getItem($this->name) !== null) {
                        $this->addError('name', Yii::t('user', 'Auth item with such name already exists'));
                    }
                },
                'when' => function () {
                    return $this->scenario == 'create' || $this->item->name != $this->name;
                },
            ],
            ['children', RbacItemsValidator::class],
            ['rule', RbacRuleValidator::class],
        ];
    }

    /**
     * @return bool
     */
    public function getIsNewRecord()
    {
        return $this->item === null;
    }

    /**
     * @return Item
     */
    abstract public function getType();
}
