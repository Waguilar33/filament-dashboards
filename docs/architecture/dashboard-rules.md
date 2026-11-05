# Dashboard Rules

**Status:** Stages 1–3 built and tagged v1.0.0-beta.1, beta.2 and beta.3 (2026-11-04, on branch `feat/stage-1-core`), waiting for the owner's review.
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
| FD17 | Set as home shows only on dashboards that are not the user's home; the home dashboard offers Remove as home instead, which brings back the panel's normal dashboard. | Owner, 2026-11-04: "Shouldn't 'Set Home' disappear once you have set it as home?"; on Remove as home: "go ahead". |

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
| FDP9 | An app policy for the dashboard model decides only who sees the dashboards list (`viewAny`) and who creates (`create`). View, edit, share and delete always follow FD6 and FD7. | A generated policy (Guardian's `Update:Dashboard`) would otherwise let a role edit or delete dashboards it does not own. |
| FDP10 | Add widget shows cards (preview, name, description) in a carousel, like the reference demo. A widget's `dashboardPreview()` image wins; otherwise a drawing by kind (stats, chart, table, other). | Matches the screenshots the owner shared. |
| FDP11 | Spanish screens call a dashboard "Escritorio", Filament's own word for its dashboard page. | One term on screen for the same thing. |
| FDP12 | In Edit layout, widgets are dragged by a handle and resized by their corner (Gridstack.js). Height follows the widget's content; width is what the user sets. The layout is saved when a drag or resize ends. | A widget's own content decides how tall it must be; a fixed height either clips charts or leaves gaps. |
| FDP13 | Below 1024 px wide every widget stacks in one column, and changes made there are not saved. | Saving a one-column arrangement would overwrite the desktop layout. |
| FDP14 | A saved builder widget shows on a dashboard only to people who can see the widget itself (owner, its shares, or everyone for a system widget). Someone who can see the dashboard but not the widget sees the dashboard without it. | FD12 and FD6: a private widget is seen only by its owner, wherever it is placed. |
| FDP15 | Builder widgets calculate with the viewer's own user and tenant, through the data source's own query. | The source decides what each person may see (FD15); a widget never carries its owner's access to someone else. |
| FDP16 | The builder offers: stats cards (count, sum, average, minimum, maximum per card), line and bar over time (last 7, 30, 90 days, 12 months or this year, grouped by day, week, month or year), pie, doughnut and polar area split by a field (top 1–20), scatter of two numbers, and a table (chosen columns, sort, up to 100 rows). Filters are groups joined by "or", each a list of rules joined by "and". | Covers every widget kind in the reference demo with the fewest settings. |
| FDP17 | Saved widgets have their own Widgets list (mine, shared with me, system) with edit, share, clone and delete. The list shows only when the user may view at least one data source (FDP19). | Sharing and cloning widgets (FD12) needs a place where widgets exist outside a dashboard. |
| FDP18 | After a widget is added, edited or removed the dashboard page reloads (keeping Edit layout on). Every other action (Set as home, Share) leaves the grid as it is. | Gridstack does not rebuild reliably when Livewire swaps the grid in place, so Livewire never re-renders the grid (`wire:ignore`). Set as home broke the layout before this. |
| FDP19 | A data source can refuse a user (`canView`). A refused source is not offered in the builder, its saved widgets are not offered in Add widget, and a widget built on it is left off the dashboard for that viewer, as in FDP14. With no source left, New widget and the Widgets list are hidden. | Sortivo, 2026-11-04: a user without permission to see orders must not see the Orders source at all; an empty query still offered it and built empty widgets. Sortivo asked for the card to be hidden, not shown empty (same reasoning as FDP14). |
| FDP20 | A number field can be stored scaled (`->divideBy(100)` for cents). Metrics, trends, breakdowns, scatter points and table cells show the value divided; number filters are multiplied before comparing, so users always work in the shown units. | Sortivo, 2026-11-04: order totals are stored in cents; a sum showed 12345 for Q123.45. |
| FDP21 | System dashboards and system widgets show the name their class gives in the viewer's language; the stored name is only a fallback for a class no longer registered. | Sortivo, 2026-11-04: `dashboards:sync` runs in the CLI's language, so Spanish companies saw the English name. |
| FDP22 | Owner, share, home and tenant keys follow the app's key type: `key_types.users` and `key_types.tenants` in the config (`int`, `uuid`, `ulid`), else Laravel's default morph type. Roles share the users' key type. The subject of a share is always numeric, as the plugin's own ids are. | Sortivo, 2026-11-04: users, roles and tenants have uuid keys and every insert failed. |
| FDP23 | A number field can format its value (`formatUsing`) for stats cards and table cells; a stats card without its own label is titled metric · field · source. Charts show plain numbers. | Sortivo, 2026-11-04: a money card read "544,909" with no currency, titled "Suma · Órdenes" without the field. |
| FDP24 | Create dashboard and Settings open in a medium slide-over with the form in one column. The app changes the plugin's screens through `modify…Using` hooks on the plugin: the dashboard form, and the create, settings, share and add widget actions. Each hook receives the configured component and returns it; it runs last, so the app's settings win. | Owner, 2026-11-04: the plugin must not hardcode Sortivo's preference for a centered create modal; defaults are a medium slide-over in one column. |
| FDP25 | A dashboard's navigation group is picked from the panel's registered groups, or from the app's `navigationGroupsUsing` list of key => label. The key is stored and turned into its label when the navigation is built; a stored value that is not a key shows as written. The navigation icon field is the plugin's Heroicon select unless the app replaces it with `navigationIconFieldUsing`; any blade-icons name renders. | Owner, 2026-11-04: Sortivo translates its group labels per user, so a stored label broke for users in another language; Sortivo has its own Font Awesome icon picker. |

## Open decisions

None.
