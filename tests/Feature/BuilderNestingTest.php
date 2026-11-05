<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('keeps a stats card filter rule within Livewire nesting depth while building and saving', function (): void {
    expect(config('livewire.payload.max_nesting_depth'))->toBe(10);
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    $page = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->mountAction('buildWidget', ['type' => 'stats']);

    $data = $page->instance()->mountedActions[0]['data'];
    $card = array_key_first($data['cards']);
    $base = "mountedActions.0.data.cards.{$card}";

    $page->set("{$base}.source", 'orders')
        ->set("{$base}.filters", ['group' => ['rules' => ['rule' => ['field' => null, 'operator' => null, 'value' => null, 'choice' => null]]]])
        ->set("{$base}.filters.group.rules.rule.field", 'channel')
        ->set("{$base}.filters.group.rules.rule.operator", 'is')
        ->set("{$base}.filters.group.rules.rule.choice", 'web')
        ->set('mountedActions.0.data.name', 'Web orders')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(BuilderWidget::query()->sole()->config['cards'][0]['filters'])
        ->toBe([['rules' => [['field' => 'channel', 'operator' => 'is', 'choice' => 'web']]]]);
});
