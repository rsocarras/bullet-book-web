<?php

/** @var yii\web\View $this */

use yii\bootstrap5\Html;

$this->title = 'Bullet Book | Tracking simple y diario';
$this->params['meta_description'] = 'Tu vida en bullets, en 60 segundos al día. Haz check-in diario, sigue tareas y obtén insights claros.';
$this->registerCss('.landing-hero{background:linear-gradient(135deg,#eff6ff 0%,#f8fafc 45%,#eef2ff 100%);border:1px solid #e5e7eb;}');
?>

<div class="site-landing">
    <section class="landing-hero rounded-4 p-5 mb-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-primary-subtle text-primary-emphasis mb-3">Bullet Book</span>
                <h1 class="display-5 fw-bold mb-3">Tu vida en bullets, en 60 segundos al día</h1>
                <p class="lead text-secondary mb-4">
                    Registra hábitos, estados de ánimo, finanzas y objetivos en un solo flujo diario.
                    Mantén el foco con tareas y recordatorios.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <?= Html::a('Crear cuenta', ['/user/registration/register'], ['class' => 'btn btn-primary btn-lg']) ?>
                    <?= Html::a('Iniciar sesión', ['/user/security/login'], ['class' => 'btn btn-outline-dark btn-lg']) ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="card-title">Check-in de hoy</h5>
                        <p class="text-muted mb-3">3 bullets en menos de un minuto.</p>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>Mood</span><span class="badge bg-success">4/5</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>Hábito: Entrenar</span><span class="badge bg-success">Sí</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>Gasto diario</span><span class="badge bg-warning text-dark">18.50</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2 class="h3 mb-4">Cómo funciona</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="h5">1. Configura bullets</h3>
                        <p class="text-muted mb-0">Define qué quieres medir: hábitos, feelings, finanzas o metas.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="h5">2. Check-in diario</h3>
                        <p class="text-muted mb-0">Registra tus valores del día con inputs simples y rápidos.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="h5">3. Insights</h3>
                        <p class="text-muted mb-0">Visualiza patrones mensuales para tomar mejores decisiones.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2 class="h3 mb-4">Features clave</h2>
        <div class="row g-3">
            <?php
            $features = [
                ['Bullets', 'Habits, feelings, finanzas y objetivos en un mismo panel.'],
                ['Heatmap', 'Vista rápida del mes para ver consistencia y progreso.'],
                ['Tareas', 'Inbox y flujo todo/doing/done para ejecutar sin fricción.'],
                ['Recordatorios', 'Configura avisos diarios para no romper la racha.'],
                ['Sync móvil', 'Tus datos se sincronizan con app móvil offline-first.'],
            ];
            foreach ($features as $feature):
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h5"><?= Html::encode($feature[0]) ?></h3>
                            <p class="text-muted mb-0"><?= Html::encode($feature[1]) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="mb-5">
        <h2 class="h3 mb-4">Lo que dicen usuarios</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <blockquote class="blockquote p-3 border rounded-3 bg-light h-100">
                    <p class="mb-2">“Por fin tengo hábitos y tareas en el mismo lugar, sin ruido.”</p>
                    <footer class="blockquote-footer mb-0">Usuario beta #1</footer>
                </blockquote>
            </div>
            <div class="col-md-6">
                <blockquote class="blockquote p-3 border rounded-3 bg-light h-100">
                    <p class="mb-2">“El check-in diario me toma menos de un minuto y sí lo mantengo.”</p>
                    <footer class="blockquote-footer mb-0">Usuario beta #2</footer>
                </blockquote>
            </div>
        </div>
    </section>

    <footer class="py-4 border-top text-muted d-flex flex-wrap justify-content-between gap-2">
        <span><?= date('Y') ?> Bullet Book</span>
        <div class="d-flex gap-3">
            <?= Html::a('Terms', '#', ['class' => 'text-decoration-none']) ?>
            <?= Html::a('Privacy', '#', ['class' => 'text-decoration-none']) ?>
        </div>
    </footer>
</div>
