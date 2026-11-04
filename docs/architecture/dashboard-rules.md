# Dashboard Rules

**Status:** In design; stage 1 being built.
**Last updated:** 2026-11-04

## Agreed rules

| # | Rule | Reason |
|---|---|---|
| FD1 | Widgets are already-built functionality written by developers; stage 1 offers no widget configuration. | Owner, 2026-11-03: "the widgets, for now, are going to be like already developed functionality." |
| FD2 | A widget is a dashboard widget when it is registered on the panel (discovered or listed) and implements the `DashboardWidget` contract. The plugin keeps no widget list of its own. | Owner, 2026-11-04: Guardian already picks up the panel's widgets, so registering in one place gives Guardian its `View:<Widget>` permissions with no change to Guardian. |
| FD3 | The plugin never keeps its own widget-to-user or widget-to-role table. A widget is offered when its `canView()` passes and, if set, the plugin's `authorizeWidgetsUsing()` closure passes. | Owner chose Guardian permissions over a plugin table (2026-11-03): one source of truth for who sees what. |
| FD4 | Guardian is optional. Without it, every widget and dashboard creation is open to every signed-in user, narrowed only by `canView()` and the plugin's closures. With it, access is managed from the role screen, and dashboards can be shared with roles. | Owner, 2026-11-04: "we can build both ways." The plugin must work in projects without Guardian. |
| FD5 | The plugin tells developers what Guardian adds: `composer.json` `suggest`, a README section, and a tip printed by the install command. Never in screens end users see. | Owner, 2026-11-03: explain why adding Guardian benefits them. End users cannot act on a developer tip. |
| FD6 | A dashboard is either private (only its owner sees it) or shared with users or roles at Read or Edit. Edit includes Read. | Owner, 2026-11-04. |
| FD7 | Only the owner deletes a dashboard and decides who it is shared with. A share never grants delete. | Owner, 2026-11-04: "Never delete, only edit or read." |
| FD8 | The app can ship system dashboards. Every user sees them read-only; nobody edits or deletes them; anyone can clone one into a dashboard of their own. | Owner, 2026-11-04: a head start users can learn from and make their own. |
| FD9 | Any dashboard a user can see can be cloned into a new private dashboard they own. | Owner, 2026-11-03 (demo's clone) and 2026-11-04. |
| FD10 | Each user can pick one dashboard they can see as their home page; without one, the panel's normal dashboard shows. Built in stage 1. | Owner, 2026-11-04. |
| FD11 | Creating a dashboard goes through Laravel's Gate when the app defines a policy for the dashboard model (Guardian's `Create:Dashboard`); otherwise the plugin's `canCreateDashboardsUsing()` closure decides; otherwise any signed-in user can. | Owner chose policy-driven, 2026-11-03. |
| FD12 | Sharing and access rules apply to builder widgets the same way as to dashboards: private, or shared at Read or Edit, never delete; system widgets are read-only and cloneable. | Owner, 2026-11-04: "So that applies to both widgets and dashboards together." Lands with the builder (stage 3). |
| FD13 | Stages: 1 core, 2 drag-and-resize, 3 the "New widget" builder. Each ends with a beta tag. | Owner, 2026-11-03: builder and drag-and-resize are needed. |
| FD14 | The repository is private and released as `v1.0.0-beta.N`. | Owner, 2026-11-03 and 2026-11-04. |
| FD15 | The builder offers only data sources the app registers, each with its own query limited to what the user may see. It never discovers models by itself. | Raised 2026-11-03 with the stage list; not objected. A discovered model would count every tenant's records and skip the app's permissions. |
| FD16 | System dashboards are classes registered with the plugin and copied into the database by `dashboards:sync`, run on deploy (no owner, panel-wide, no tenant). | Owner agreed 2026-11-04 (was FDP1). Defined in code so they cannot drift or be deleted; copied so the list, home page, sharing and clone treat every dashboard alike. |

## Superseded

None yet.

## Facts

| Fact | Source |
|---|---|
| Guardian's sync builds widget permissions from `$panel->getWidgets()`, which holds discovered and listed widgets; a widget with `$isDiscovered = false` is not discovered. | `filament-guardian/src/Commands/Concerns/DiscoversEntities.php`, `filament/src/Panel/Concerns/HasComponents.php` |
| Filament's `Dashboard::getWidgets()` returns every panel widget, so dashboard widgets would also appear on the default dashboard. | `filament/src/Pages/Dashboard.php` |
| Guardian does not gate a widget by itself; the widget's `canView()` has to check the permission. | `filament-guardian/src` has no `canView` |
| Sortivo runs spatie/laravel-permission with teams on: roles belong to a company. | Sortivo `config/permission.php` |

## Proposals (Claude, not yet confirmed by the owner)

| # | Proposal | Reason |
|---|---|---|
| FDP2 | The plugin's home page extends Filament's `Dashboard`: it shows the user's home dashboard, else the panel's widgets minus dashboard widgets. | Keeps dashboard widgets off the default dashboard (Facts) and gives FD10 a place. |
| FDP3 | Widgets on a dashboard store x, y, width and height on a 12-column grid from stage 1. Stage 1 orders them and uses the width as span. | Stage 2's drag-and-resize then needs no data change. |
| FDP4 | Dashboards, items, shares and homes carry the panel id; dashboards carry an optional tenant that follows Filament's tenancy. | `/admin` and `/portal` dashboards never mix; in Sortivo a dashboard belongs to one company. |
| FDP5 | Role sharing switches on when spatie/laravel-permission is installed and the user model has `roles()`; a user's roles are `$user->roles`, which follows spatie's teams. | FD4 without a hard dependency. |
| FDP6 | Coded widgets (PHP widget classes) are not owned, shared or cloned on their own; only builder widgets are (FD12). | A coded widget has no settings to copy. |
| FDP7 | Supports Filament 5 only; CI runs a small matrix. | Sortivo is on Filament 5; the repository is private and Actions minutes are billed. |
| FDP8 | Table names are configurable, defaulting to `custom_dashboards`, `custom_dashboard_items`, `custom_dashboard_shares`, `custom_dashboard_homes`. | Avoid clashing with an app's own `dashboards` table. |

## Open decisions

None.
