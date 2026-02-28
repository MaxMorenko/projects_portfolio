<?php

namespace app\controllers;

use app\models\Project;
use app\models\User;
use Yii;
use yii\filters\ContentNegotiator;
use yii\filters\VerbFilter;
use yii\rest\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\filters\auth\HttpBearerAuth;
use yii\web\UnauthorizedHttpException;

class ApiController extends Controller
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => ['register', 'login'],
        ];

        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'register' => ['POST'],
                'login' => ['POST'],
                'me' => ['GET'],
                'projects' => ['GET'],
                'create-project' => ['POST'],
                'save-jira' => ['PUT'],
            ],
        ];

        return $behaviors;
    }

    public function actionRegister(): array
    {
        $body = Yii::$app->request->bodyParams;
        $name = trim((string) ($body['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if (!$name || !$email || !$password) {
            throw new BadRequestHttpException('name, email и password обязательны');
        }

        if (User::find()->where(['email' => $email])->exists()) {
            throw new BadRequestHttpException('Пользователь с таким email уже существует');
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password_hash = Yii::$app->security->generatePasswordHash($password);
        $this->refreshToken($user);

        if (!$user->save()) {
            throw new BadRequestHttpException('Не удалось зарегистрироваться');
        }

        return ['token' => $user->auth_token];
    }

    public function actionLogin(): array
    {
        $body = Yii::$app->request->bodyParams;
        $email = mb_strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        $user = User::findOne(['email' => $email]);
        if (!$user || !Yii::$app->security->validatePassword($password, $user->password_hash)) {
            throw new UnauthorizedHttpException('Неверный email или password');
        }

        $this->refreshToken($user);
        $user->save(false, ['auth_token', 'auth_token_expires_at']);

        return ['token' => $user->auth_token];
    }

    public function actionMe(): array
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        if (!$user) {
            throw new UnauthorizedHttpException('Требуется авторизация');
        }

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'jira_base_url' => $user->jira_base_url ?: '',
            'jira_email' => $user->jira_email ?: '',
            'jira_api_token' => $user->jira_api_token ? '********' : '',
        ];
    }

    public function actionProjects(): array
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        $projects = Project::find()
            ->where(['user_id' => $user->id])
            ->orderBy(['id' => SORT_DESC])
            ->asArray()
            ->all();

        return array_map(static function (array $project): array {
            return [
                'id' => (int) $project['id'],
                'title' => $project['title'],
                'status' => $project['status'],
                'description' => $project['description'],
                'created_at' => $project['created_at'],
            ];
        }, $projects);
    }

    public function actionCreateProject(): array
    {
        $body = Yii::$app->request->bodyParams;
        $title = trim((string) ($body['title'] ?? ''));
        $status = trim((string) ($body['status'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));

        if (!$title || !$status) {
            throw new BadRequestHttpException('title и status обязательны');
        }

        /** @var User $user */
        $user = Yii::$app->user->identity;

        $project = new Project();
        $project->user_id = $user->id;
        $project->title = $title;
        $project->status = $status;
        $project->description = $description;

        if (!$project->save()) {
            throw new BadRequestHttpException('Не удалось сохранить проект');
        }

        return ['ok' => true, 'id' => (int) $project->id];
    }

    public function actionSaveJira(): array
    {
        $body = Yii::$app->request->bodyParams;

        /** @var User $user */
        $user = Yii::$app->user->identity;
        $user->jira_base_url = trim((string) ($body['jiraBaseUrl'] ?? ''));
        $user->jira_email = trim((string) ($body['jiraEmail'] ?? ''));

        $jiraApiToken = trim((string) ($body['jiraApiToken'] ?? ''));
        if ($jiraApiToken !== '') {
            $user->jira_api_token = $jiraApiToken;
        }

        $user->save(false, ['jira_base_url', 'jira_email', 'jira_api_token']);

        return ['ok' => true];
    }

    private function refreshToken(User $user): void
    {
        $user->auth_token = Yii::$app->security->generateRandomString(64);
        $user->auth_token_expires_at = date('Y-m-d H:i:s', time() + (60 * 60 * 8));
    }
}
