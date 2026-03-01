<?php

/** @var yii\web\View $this */
/** @var string $today */
/** @var app\models\BbBullet[] $activeBullets */
/** @var app\models\BbBulletEntry[] $todayEntries */
/** @var bool $hasTodayEntries */
/** @var array $taskStatusCounts */
/** @var app\models\BbTask[] $upcomingTasks */
/** @var app\models\BbReminder[] $upcomingReminders */
/** @var array $heatmap */

use app\assets\DashboardAsset;
use app\models\BbBullet;
use app\models\BbTask;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

DashboardAsset::register($this);

$this->title = 'Dashboard | Bullet Book';
$this->params['meta_description'] = 'Dashboard diario de Bullet Book: check-in, tareas, heatmap y recordatorios.';

$todayFormatted = Yii::$app->formatter->asDate($today, 'php:l, d M Y');
$taskStatuses = [
    BbTask::STATUS_INBOX => 'Inbox',
    BbTask::STATUS_TODO => 'To Do',
    BbTask::STATUS_DOING => 'Doing',
    BbTask::STATUS_DONE => 'Done',
    BbTask::STATUS_ARCHIVED => 'Archived',
];

$heatmapLevel = static function (array $cell): int {
    if (!$cell['hasEntry']) {
        return 0;
    }

    $level = (int) ceil($cell['intensity'] * 5);
    return max(1, min(5, $level));
};
?>

<div class="dashboard-page">
    <div id="dashboard-toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;"></div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Hola, <?= Html::encode(Yii::$app->user->identity->username) ?></h1>
            <p class="text-muted mb-0">Resumen rápido de tu día en Bullet Book.</p>
        </div>
        <span class="badge rounded-pill <?= $hasTodayEntries ? 'text-bg-success' : 'text-bg-warning text-dark' ?>">
            <?= $hasTodayEntries ? 'Check-in completado' : 'Check-in pendiente' ?>
        </span>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4" id="today-checkin">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-0">Hoy</h2>
                        <small class="text-muted"><?= Html::encode($todayFormatted) ?></small>
                    </div>
                    <div>
                        <span id="today-checkin-status" class="fw-semibold <?= $hasTodayEntries ? 'text-success' : 'text-warning' ?>">
                            <?= $hasTodayEntries ? 'Check-in completado' : 'Pendiente' ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0">Registra tus bullets activos sin salir del dashboard.</p>
                        <a href="#today-checkin" class="btn btn-sm btn-primary">Registrar check-in</a>
                    </div>

                    <?php if (empty($activeBullets)): ?>
                        <div class="alert alert-info mb-0">
                            No tienes bullets activos. Crea uno para comenzar tu check-in diario.
                        </div>
                    <?php else: ?>
                        <?php foreach ($activeBullets as $bullet): ?>
                            <?php
                            $entry = $todayEntries[(int) $bullet->id] ?? null;
                            $hasValue = $entry !== null && (
                                $entry->value_int !== null ||
                                $entry->value_decimal !== null ||
                                ($entry->value_text !== null && $entry->value_text !== '')
                            );
                            ?>
                            <div class="bullet-row">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                    <div>
                                        <strong><?= Html::encode($bullet->name) ?></strong>
                                        <div class="small text-muted"><?= Html::encode($bullet->bullet_type) ?> · <?= Html::encode($bullet->input_type) ?></div>
                                    </div>
                                </div>

                                <?php // Each row submits independently so users can save one bullet at a time. ?>
                                <form class="js-entry-form" method="post" action="<?= Url::to(['/entry/quick-save']) ?>" data-input-type="<?= Html::encode($bullet->input_type) ?>">
                                    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                                    <?= Html::hiddenInput('bullet_id', (string) $bullet->id) ?>
                                    <?= Html::hiddenInput('entry_date', $today) ?>

                                    <div class="row g-2 align-items-center">
                                        <div class="col-md-4">
                                            <?php if ($bullet->input_type === BbBullet::INPUT_BINARY): ?>
                                                <div class="form-check form-switch mt-1">
                                                    <input class="form-check-input" type="checkbox" name="value_int_switch" value="1" <?= $entry && (int) $entry->value_int === 1 ? 'checked' : '' ?>>
                                                    <label class="form-check-label">Sí / No</label>
                                                </div>
                                            <?php elseif (in_array($bullet->input_type, [BbBullet::INPUT_SCALE, BbBullet::INPUT_STARS], true)): ?>
                                                <?php
                                                $min = (int) ($bullet->scale_min ?? 1);
                                                $max = (int) ($bullet->scale_max ?? 5);
                                                $options = ['' => 'Selecciona...'];
                                                for ($i = $min; $i <= $max; $i++) {
                                                    $options[$i] = (string) $i;
                                                }
                                                echo Html::dropDownList('value_int', $entry?->value_int, $options, ['class' => 'form-select']);
                                                ?>
                                            <?php elseif ($bullet->input_type === BbBullet::INPUT_NUMERIC): ?>
                                                <?= Html::input('number', 'value_decimal', $entry?->value_decimal ?? $entry?->value_int, ['class' => 'form-control', 'step' => '0.01', 'placeholder' => 'Ej: 10.5']) ?>
                                            <?php else: ?>
                                                <?= Html::textInput('value_text', $entry?->value_text, ['class' => 'form-control', 'maxlength' => 1000, 'placeholder' => 'Escribe aquí']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-5">
                                            <?= Html::textInput('note', $entry?->note, ['class' => 'form-control', 'maxlength' => 1000, 'placeholder' => 'Nota (opcional)']) ?>
                                        </div>
                                        <div class="col-md-3 d-grid">
                                            <button type="submit" class="btn btn-outline-primary js-entry-save-btn" <?= !$hasValue && $bullet->input_type !== BbBullet::INPUT_BINARY ? 'disabled' : '' ?>>Guardar</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Tareas</h2>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#quickTaskModal">Nueva tarea</button>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-3"><span class="badge text-bg-secondary w-100">Inbox: <?= (int) ($taskStatusCounts[BbTask::STATUS_INBOX] ?? 0) ?></span></div>
                        <div class="col-sm-3"><span class="badge text-bg-info w-100">Todo: <?= (int) ($taskStatusCounts[BbTask::STATUS_TODO] ?? 0) ?></span></div>
                        <div class="col-sm-3"><span class="badge text-bg-warning w-100">Doing: <?= (int) ($taskStatusCounts[BbTask::STATUS_DOING] ?? 0) ?></span></div>
                        <div class="col-sm-3"><span class="badge text-bg-success w-100">Done: <?= (int) ($taskStatusCounts[BbTask::STATUS_DONE] ?? 0) ?></span></div>
                    </div>

                    <?php Pjax::begin(['id' => 'tasks-pjax', 'enablePushState' => false, 'timeout' => 5000]); ?>
                    <h3 class="h6">Próximas 7 días</h3>
                    <?php if (empty($upcomingTasks)): ?>
                        <p class="text-muted mb-0">No hay tareas próximas con fecha límite.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Vence</th>
                                    <th>Estado</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($upcomingTasks as $task): ?>
                                    <tr>
                                        <td>
                                            <strong><?= Html::encode($task->title) ?></strong>
                                            <?php if (!empty($task->description)): ?>
                                                <div class="small text-muted"><?= Html::encode(mb_strimwidth($task->description, 0, 80, '...')) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $task->due_at ? Html::encode(Yii::$app->formatter->asDatetime($task->due_at, 'php:d M H:i')) : '-' ?></td>
                                        <td>
                                            <?= Html::dropDownList(
                                                'status',
                                                $task->status,
                                                $taskStatuses,
                                                [
                                                    'class' => 'form-select form-select-sm js-task-status',
                                                    'data-task-id' => (int) $task->id,
                                                    'data-update-url' => Url::to(['/task/quick-update', 'id' => (int) $task->id]),
                                                ]
                                            ) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php Pjax::end(); ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Heatmap del mes</h2>
                    <a href="#" class="small">Ver insights</a>
                </div>
                <div class="card-body">
                    <?php if (empty($heatmap['primaryBullet'])): ?>
                        <p class="text-muted mb-0">Activa al menos un bullet para ver el heatmap.</p>
                    <?php else: ?>
                        <p class="mb-2">
                            <strong><?= Html::encode($heatmap['primaryBullet']->name) ?></strong><br>
                            <small class="text-muted"><?= Html::encode($heatmap['monthLabel']) ?></small>
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm heatmap-table mb-0">
                                <thead>
                                <tr>
                                    <th>L</th><th>M</th><th>M</th><th>J</th><th>V</th><th>S</th><th>D</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($heatmap['weeks'] as $week): ?>
                                    <tr>
                                        <?php foreach ($week as $cell): ?>
                                            <?php if ($cell === null): ?>
                                                <td class="heatmap-empty"></td>
                                            <?php else: ?>
                                                <?php $level = $heatmapLevel($cell); ?>
                                                <td class="heatmap-day heatmap-<?= $level ?>" title="<?= Html::encode($cell['date']) ?>">
                                                    <?= (int) $cell['day'] ?>
                                                </td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Recordatorios</h2>
                </div>
                <div class="card-body">
                    <?php Pjax::begin(['id' => 'reminders-pjax', 'enablePushState' => false, 'timeout' => 5000]); ?>
                    <?php if (empty($upcomingReminders)): ?>
                        <p class="text-muted">No tienes recordatorios programados.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach ($upcomingReminders as $reminder): ?>
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= Html::encode($reminder->kind) ?></strong>
                                        <div class="small text-muted"><?= $reminder->fire_at ? Html::encode(Yii::$app->formatter->asDatetime($reminder->fire_at, 'php:d M H:i')) : 'Sin fecha' ?></div>
                                    </div>
                                    <span class="badge text-bg-light"><?= Html::encode($reminder->status) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php Pjax::end(); ?>

                    <hr>
                    <h3 class="h6">Configurar recordatorio diario</h3>
                    <form id="daily-reminder-form" action="<?= Url::to(['/reminder/daily-checkin']) ?>" method="post" data-pjax="0">
                        <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                        <div class="mb-2">
                            <label class="form-label">Hora</label>
                            <?= Html::input('time', 'time', '20:00', ['class' => 'form-control', 'required' => true]) ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Timezone</label>
                            <?= Html::dropDownList('timezone', Yii::$app->timeZone ?: 'UTC', [
                                'UTC' => 'UTC',
                                'America/Bogota' => 'America/Bogota',
                                'America/New_York' => 'America/New_York',
                                'America/Mexico_City' => 'America/Mexico_City',
                                'Europe/Madrid' => 'Europe/Madrid',
                            ], ['class' => 'form-select']) ?>
                        </div>
                        <button class="btn btn-outline-primary w-100" type="submit">Guardar recordatorio</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title mb-0">Nueva tarea</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <?php // ActiveForm keeps a familiar Yii2 form structure even when submitting by AJAX. ?>
                <?php $form = ActiveForm::begin([
                    'id' => 'quick-task-form',
                    'action' => Url::to(['/task/quick-create']),
                    'enableClientValidation' => false,
                    'options' => ['data-pjax' => 0],
                ]); ?>
                <div class="mb-3">
                    <label class="form-label">Título</label>
                    <?= Html::textInput('title', '', ['class' => 'form-control', 'maxlength' => 200, 'required' => true, 'placeholder' => 'Ej: Revisar finanzas semanales']) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha límite (opcional)</label>
                    <?= Html::input('datetime-local', 'due_at', '', ['class' => 'form-control']) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Prioridad</label>
                    <?= Html::dropDownList('priority', BbTask::PRIORITY_MEDIUM, [
                        BbTask::PRIORITY_LOW => 'Low',
                        BbTask::PRIORITY_MEDIUM => 'Medium',
                        BbTask::PRIORITY_HIGH => 'High',
                        BbTask::PRIORITY_URGENT => 'Urgent',
                    ], ['class' => 'form-select']) ?>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Guardar tarea</button>
                </div>
                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>
</div>
