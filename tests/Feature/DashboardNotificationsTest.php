<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\AddDashboardWidget;
use Waguilar\FilamentDashboards\Enums\AlertOperator;
use Waguilar\FilamentDashboards\Enums\DigestFrequency;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Mail\DashboardDigestMail;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardAlert;
use Waguilar\FilamentDashboards\Models\DashboardSubscription;
use Waguilar\FilamentDashboards\Notifications\DashboardAlertNotification;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    FilamentDashboardsPlugin::get()->scheduledNotifications();
    Mail::fake();
    Notification::fake();
});

function salesDashboard(Model $owner): array
{
    $dashboard = dashboard($owner);
    $widget = builderWidget($owner, ['name' => 'Sales', 'config' => ['cards' => [
        ['key' => 'revenue', 'source' => 'orders', 'metric' => 'sum', 'field' => 'total', 'label' => 'Revenue', 'dates_field' => 'ordered_at'],
        ['source' => 'orders', 'metric' => 'count', 'label' => 'Old card'],
    ]]]);
    app(AddDashboardWidget::class)->handle($dashboard, $widget->dashboardKey(), ['w' => 12, 'h' => 4], $owner);

    return [$dashboard, $widget];
}

function subscribe(Model $user, Dashboard $dashboard, DigestFrequency $frequency): DashboardSubscription
{
    $subscription = new DashboardSubscription(['panel' => 'demo', 'dashboard_id' => $dashboard->id, 'frequency' => $frequency]);
    $subscription->user()->associate($user);
    $subscription->save();

    return $subscription;
}

it('saves and removes a person\'s email subscription from the dashboard', function (): void {
    $owner = user();
    [$dashboard] = salesDashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('emailMe', data: ['frequency' => 'weekly'])
        ->assertHasNoActionErrors();

    expect(DashboardSubscription::query()->sole()->frequency)->toBe(DigestFrequency::Weekly);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->callAction('emailMe', data: ['frequency' => null]);

    expect(DashboardSubscription::query()->count())->toBe(0);
});

it('offers email and alerts only when the app schedules notifications', function (): void {
    FilamentDashboardsPlugin::get()->scheduledNotifications(false);
    $owner = user();
    [$dashboard] = salesDashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertActionHidden('emailMe')
        ->assertActionHidden('alerts');
});

it('emails yesterday\'s cards once a day after seven in the dashboard time zone', function (): void {
    $owner = user();
    [$dashboard] = salesDashboard($owner);
    subscribe($owner, $dashboard, DigestFrequency::Daily);
    DemoOrderFactory::new()->create(['total' => 40, 'ordered_at' => '2026-11-04 10:00:00']);
    DemoOrderFactory::new()->create(['total' => 99, 'ordered_at' => '2026-11-05 08:00:00']);

    $this->travelTo(CarbonImmutable::parse('2026-11-05 06:30:00', 'UTC'));
    $this->artisan('dashboards:notify')->assertSuccessful();
    Mail::assertNothingSent();

    $this->travelTo(CarbonImmutable::parse('2026-11-05 07:10:00', 'UTC'));
    $this->artisan('dashboards:notify')->assertSuccessful();
    $this->artisan('dashboards:notify')->assertSuccessful();

    Mail::assertSent(DashboardDigestMail::class, 1);
    Mail::assertSent(DashboardDigestMail::class, fn (DashboardDigestMail $mail): bool => $mail->hasTo($owner->email)
        && $mail->sections[0]['name'] === 'Sales'
        && $mail->sections[0]['cards'][0]->formatted === '40'
        && $mail->rangeLabel === '4 Nov 2026');
});

it('sends nothing to someone who can no longer open the dashboard', function (): void {
    $owner = user();
    $reader = user();
    [$dashboard] = salesDashboard($owner);
    share($dashboard, $reader, ShareLevel::Read);
    subscribe($reader, $dashboard, DigestFrequency::Daily);
    $dashboard->shares()->delete();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'UTC'));

    $this->artisan('dashboards:notify')->assertSuccessful();

    Mail::assertNothingSent();
});

it('saves alerts only on cards that can be told apart', function (): void {
    $owner = user();
    [$dashboard, $widget] = salesDashboard($owner);
    $this->actingAs($owner);

    $page = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug]);

    expect((fn (): array => $this->alertableCards())->call($page->instance()))->toBe(["{$widget->id}:revenue" => 'Sales · Revenue']);

    $page->callAction('alerts', data: ['alerts' => [['card' => "{$widget->id}:revenue", 'operator' => 'above', 'threshold' => 100]]])
        ->assertHasNoActionErrors();

    expect(DashboardAlert::query()->get(['card_key', 'operator', 'threshold'])->map(fn (DashboardAlert $alert): array => [$alert->card_key, $alert->operator, $alert->threshold])->all())
        ->toBe([['revenue', AlertOperator::Above, 100.0]]);
});

it('alerts once when a card goes above and again only after it drops back', function (): void {
    $owner = user();
    [$dashboard, $widget] = salesDashboard($owner);
    $alert = new DashboardAlert(['panel' => 'demo', 'dashboard_id' => $dashboard->id, 'builder_widget_id' => $widget->id, 'card_key' => 'revenue', 'operator' => AlertOperator::Above, 'threshold' => 100]);
    $alert->user()->associate($owner);
    $alert->save();
    $this->travelTo(CarbonImmutable::parse('2026-11-05 12:00:00', 'UTC'));
    DemoOrderFactory::new()->create(['total' => 150, 'ordered_at' => '2026-11-05 09:00:00']);

    $this->artisan('dashboards:notify');
    $this->artisan('dashboards:notify');

    Notification::assertSentToTimes($owner, DashboardAlertNotification::class, 1);
    expect($alert->refresh()->triggered)->toBeTrue();
});

it('alerts when a card ended the day before below its value, once a day', function (): void {
    $owner = user();
    [$dashboard, $widget] = salesDashboard($owner);
    $alert = new DashboardAlert(['panel' => 'demo', 'dashboard_id' => $dashboard->id, 'builder_widget_id' => $widget->id, 'card_key' => 'revenue', 'operator' => AlertOperator::Below, 'threshold' => 100]);
    $alert->user()->associate($owner);
    $alert->save();
    DemoOrderFactory::new()->create(['total' => 40, 'ordered_at' => '2026-11-04 10:00:00']);
    DemoOrderFactory::new()->create(['total' => 500, 'ordered_at' => '2026-11-05 08:00:00']);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'UTC'));

    $this->artisan('dashboards:notify');
    $this->artisan('dashboards:notify');

    Notification::assertSentToTimes($owner, DashboardAlertNotification::class, 1);
    expect($alert->refresh()->last_period)->toBe('2026-11-04');
});

it('runs the app hook around every recipient', function (): void {
    $owner = user();
    [$dashboard] = salesDashboard($owner);
    subscribe($owner, $dashboard, DigestFrequency::Daily);
    $seen = [];
    FilamentDashboardsPlugin::get()->recipientContextUsing(function ($user, $tenant, Closure $next) use (&$seen): mixed {
        $seen[] = [$user->getKey(), $tenant, Filament::auth()->id()];

        return $next();
    });
    $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'UTC'));

    $this->artisan('dashboards:notify');

    expect($seen)->toBe([[$owner->getKey(), null, $owner->getKey()]])
        ->and(Filament::auth()->check())->toBeFalse();
});

it('drops subscriptions and alerts with their dashboard or widget', function (): void {
    $owner = user();
    [$dashboard, $widget] = salesDashboard($owner);
    subscribe($owner, $dashboard, DigestFrequency::Daily);
    $alert = new DashboardAlert(['panel' => 'demo', 'dashboard_id' => $dashboard->id, 'builder_widget_id' => $widget->id, 'card_key' => 'revenue', 'operator' => AlertOperator::Above, 'threshold' => 1]);
    $alert->user()->associate($owner);
    $alert->save();

    $widget->delete();
    expect(DashboardAlert::query()->count())->toBe(0);

    $dashboard->delete();
    expect(DashboardSubscription::query()->count())->toBe(0);
});

it('lets system widget cards take alerts by their fixed position', function (): void {
    $system = builderWidget(attributes: ['system_key' => 'kpis', 'name' => 'KPIs', 'config' => ['cards' => [['source' => 'orders', 'metric' => 'count', 'label' => 'Orders']]]]);
    $user = builderWidget(user(), ['config' => ['cards' => [['source' => 'orders', 'metric' => 'count']]]]);

    expect(array_keys($system->keyedCards()))->toBe([0])
        ->and($user->keyedCards())->toBe([]);
});

it('computes each recipient from a fresh user, so roles loaded for one company never carry over', function (): void {
    $owner = user();
    [$dashboard] = salesDashboard($owner);
    subscribe($owner, $dashboard, DigestFrequency::Daily);
    subscribe($owner, dashboard($owner, ['slug' => 'second']), DigestFrequency::Daily);
    $instances = [];
    FilamentDashboardsPlugin::get()->recipientContextUsing(function ($user, $tenant, Closure $next) use (&$instances): mixed {
        $instances[] = $user;

        return $next();
    });
    $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'UTC'));

    $this->artisan('dashboards:notify');

    expect($instances)->toHaveCount(2)
        ->and($instances[0])->not->toBe($instances[1]);
});
