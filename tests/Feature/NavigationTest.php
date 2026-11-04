<?php

declare(strict_types=1);

it('adds dashboards marked for navigation to the user navigation', function (): void {
    $me = user();
    dashboard($me, ['name' => 'Sales', 'show_in_navigation' => true, 'navigation_label' => 'Sales board', 'navigation_group' => 'Reports']);
    dashboard($me, ['name' => 'Hidden', 'slug' => 'hidden']);
    dashboard(user(), ['name' => 'Someone else', 'slug' => 'other', 'show_in_navigation' => true]);

    $this->actingAs($me)
        ->get('/demo/custom-dashboards')
        ->assertOk()
        ->assertSee('Sales board')
        ->assertSee('Reports')
        ->assertSee('/demo/custom-dashboards/sales', escape: false)
        ->assertDontSee('Someone else');
});
