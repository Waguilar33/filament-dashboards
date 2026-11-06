<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Actions\TransferOwnership;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class TransferOwnershipAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'transfer';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $plugin = FilamentDashboardsPlugin::get();

        $this
            ->label(__('filament-dashboards::dashboards.actions.transfer'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::TRANSFER))
            ->visible(fn (Dashboard|BuilderWidget $record): bool => $record->canBeSharedBy(DashboardResource::user()))
            ->modalWidth(Width::Medium)
            ->modalDescription(__('filament-dashboards::dashboards.modals.transfer.description'))
            ->schema([
                Select::make('owner')
                    ->label(__('filament-dashboards::dashboards.modals.transfer.owner'))
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search, Dashboard|BuilderWidget $record): array => self::options($plugin->searchShareableUsers($plugin->getShareableUsers(Filament::getTenant())->whereKeyNot($record->owner_id), $search)->limit(50)->get()))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => is_int($value) || is_string($value) ? self::options($plugin->getShareableUsers(Filament::getTenant())->whereKey($value)->get())[$value] ?? null : null),
            ])
            ->action(function (array $data, Dashboard|BuilderWidget $record, Action $action) use ($plugin): void {
                $owner = $plugin->getShareableUsers(Filament::getTenant())->whereKey($data['owner'] ?? null)->first();

                abort_unless($owner instanceof Model && $record->canBeSharedBy(DashboardResource::user()), 403);

                app(TransferOwnership::class)->handle($record, $owner);

                $action->success();
            })
            ->successNotificationTitle(__('filament-dashboards::dashboards.notifications.transferred'));
    }

    /**
     * @param  iterable<Model>  $users
     * @return array<int|string, string>
     */
    protected static function options(iterable $users): array
    {
        $options = [];

        foreach ($users as $user) {
            $key = $user->getKey();

            if (is_int($key) || is_string($key)) {
                $options[$key] = Filament::getUserName($user);
            }
        }

        return $options;
    }
}
