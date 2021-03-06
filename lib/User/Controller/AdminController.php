<?php

/*
 * This file is part of the 2amigos/yii2-usuario project.
 *
 * (c) 2amigOS! <http://2amigos.us/>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Da\User\Controller;

use Da\User\Event\UserEvent;
use Da\User\Factory\MailFactory;
use Da\User\Filter\AccessRuleFilter;
use Da\User\Model\Profile;
use Da\User\Model\User;
use Da\User\Query\UserQuery;
use Da\User\Search\UserSearch;
use Da\User\Service\UserBlockService;
use Da\User\Service\UserConfirmationService;
use Da\User\Service\UserCreateService;
use Da\User\Traits\ContainerTrait;
use Da\User\Validator\AjaxRequestModelValidator;
use Yii;
use yii\base\Module;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;

class AdminController extends Controller
{
    use ContainerTrait;

    /**
     * @var UserQuery
     */
    protected $userQuery;

    /**
     * AdminController constructor.
     *
     * @param string    $id
     * @param Module    $module
     * @param UserQuery $userQuery
     * @param array     $config
     */
    public function __construct($id, Module $module, UserQuery $userQuery, array $config = [])
    {
        $this->userQuery = $userQuery;
        parent::__construct($id, $module, $config);
    }

    /**
     * @param \yii\base\Action $action
     *
     * @return bool
     */
    public function beforeAction($action)
    {
        if (in_array($action->id, ['index', 'update', 'update-profile', 'info', 'assignments'])) {
            Url::remember('', 'actions-redirect');
        }

        return parent::beforeAction($action);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                    'confirm' => ['post'],
                    'block' => ['post'],
                ],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'ruleConfig' => [
                    'class' => AccessRuleFilter::className(),
                ],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['admin'],
                    ],
                ],
            ],
        ];
    }

    public function actionIndex()
    {
        $searchModel = $this->make(UserSearch::class);
        $dataProvider = $searchModel->search(Yii::$app->request->get());

        return $this->render(
            'index',
            [
                'dataProvider' => $dataProvider,
                'searchModel' => $searchModel,
            ]
        );
    }

    public function actionCreate()
    {
        /** @var User $user */
        $user = $this->make(User::class, [], ['scenario' => 'create']);

        /** @var UserEvent $event */
        $event = $this->make(UserEvent::class, [$user]);

        $this->make(AjaxRequestModelValidator::class, [$user])->validate();

        if ($user->load(Yii::$app->request->post())) {
            $this->trigger(UserEvent::EVENT_BEFORE_CREATE, $event);

            $mailService = MailFactory::makeWelcomeMailerService($user);

            if ($this->make(UserCreateService::class, [$user, $mailService])->run()) {
                Yii::$app->getSession()->setFlash('success', Yii::t('user', 'User has been created'));
                $this->trigger(UserEvent::EVENT_AFTER_CREATE, $event);

                return $this->redirect(['update', 'id' => $user->id]);
            }
        }

        return $this->render('create', ['user' => $user]);
    }

    public function actionUpdate($id)
    {
        $user = $this->userQuery->where(['id' => $id])->one();
        $user->setScenario('update');
        /** @var UserEvent $event */
        $event = $this->make(UserEvent::class, [$user]);

        $this->make(AjaxRequestModelValidator::class, [$user])->validate();

        if ($user->load(Yii::$app->request->post())) {
            $this->trigger(ActiveRecord::EVENT_BEFORE_UPDATE, $event);

            if ($user->save()) {
                Yii::$app->getSession()->setFlash('success', Yii::t('user', 'Account details have been updated'));
                $this->trigger(ActiveRecord::EVENT_AFTER_UPDATE, $event);

                return $this->refresh();
            }
        }

        return $this->render('_account', ['user' => $user]);
    }

    public function actionUpdateProfile($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();
        $profile = $user->profile;
        if ($profile === null) {
            $profile = $this->make(Profile::class);
            $profile->link($user);
        }
        /** @var UserEvent $event */
        $event = $this->make(UserEvent::class, [$user]);

        $this->make(AjaxRequestModelValidator::class, [$user])->validate();

        if ($profile->load(Yii::$app->request->post())) {
            if ($profile->save()) {
                $this->trigger(UserEvent::EVENT_BEFORE_PROFILE_UPDATE, $event);
                Yii::$app->getSession()->setFlash('success', Yii::t('user', 'Profile details have been updated'));
                $this->trigger(UserEvent::EVENT_AFTER_PROFILE_UPDATE, $event);

                return $this->refresh();
            }
        }

        return $this->render(
            '_profile',
            [
                'user' => $user,
                'profile' => $profile,
            ]
        );
    }

    public function actionInfo($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();

        return $this->render(
            '_info',
            [
                'user' => $user,
            ]
        );
    }

    public function actionAssignments($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();

        return $this->render(
            '_assignments',
            [
                'user' => $user,
                'params' => Yii::$app->request->post(),
            ]
        );
    }

    public function actionConfirm($id)
    {
        /** @var User $user */
        $user = $this->userQuery->where(['id' => $id])->one();
        /** @var UserEvent $event */
        $event = $this->make(UserEvent::class, [$user]);

        $this->trigger(UserEvent::EVENT_BEFORE_CONFIRMATION, $event);

        if ($this->make(UserConfirmationService::class, [$user])->run()) {
            Yii::$app->getSession()->setFlash('success', Yii::t('user', 'User has been confirmed'));
            $this->trigger(UserEvent::EVENT_AFTER_CONFIRMATION, $event);
        } else {
            Yii::$app->getSession()->setFlash('warning', Yii::t('user', 'Unable to confirm user. Please, try again.'));
        }

        return $this->redirect(Url::previous('actions-redirect'));
    }

    public function actionDelete($id)
    {
        if ($id === Yii::$app->user->getId()) {
            Yii::$app->getSession()->setFlash('danger', Yii::t('user', 'You cannot remove your own account'));
        } else {
            /** @var User $user */
            $user = $this->userQuery->where(['id' => $id])->one();
            /** @var UserEvent $event */
            $event = $this->make(UserEvent::class, [$user]);
            $this->trigger(ActiveRecord::EVENT_BEFORE_DELETE, $event);

            if ($user->delete()) {
                Yii::$app->getSession()->setFlash('success', \Yii::t('user', 'User has been deleted'));
                $this->trigger(ActiveRecord::EVENT_AFTER_DELETE, $event);
            } else {
                Yii::$app->getSession()->setFlash(
                    'warning',
                    Yii::t('user', 'Unable to delete user. Please, try again later.')
                );
            }
        }

        return $this->redirect(['index']);
    }

    public function actionBlock($id)
    {
        if ($id === Yii::$app->user->getId()) {
            Yii::$app->getSession()->setFlash('danger', Yii::t('user', 'You cannot remove your own account'));
        } else {
            /** @var User $user */
            $user = $this->userQuery->where(['id' => $id])->one();
            /** @var UserEvent $event */
            $event = $this->make(UserEvent::class, [$user]);

            if ($this->make(UserBlockService::class, [$user, $event, $this])->run()) {
                Yii::$app->getSession()->setFlash('success', Yii::t('user', 'User block status has been updated.'));
            } else {
                Yii::$app->getSession()->setFlash('danger', Yii::t('user', 'Unable to update block status.'));
            }
        }

        return $this->redirect(Url::previous('actions-redirect'));
    }
}
