# Dashboard Flows

What each action does: what starts it, what it checks, what it writes, and which screens show the result. The rules are in [dashboard-rules.md](dashboard-rules.md).

**How to use it.** When an action is built or changed, its section changes in the same commit. When something looks wrong, find the action and check each line.

**Status:** Draft, 2026-11-04, stage 1.

## DF1 Offer widgets (Insert widget)

| Step | What |
|---|---|
| Starts | Insert widget, on a dashboard the user can edit. |
| Checks | Panel widgets that implement `DashboardWidget` (FD2); each one's `canView()` and `authorizeWidgetsUsing()` (FD3). |
| Writes | A dashboard item: widget key, position and size (FDP3). |
| Shows | The quick-widgets carousel; the dashboard grid. |

## DF2 Create a dashboard

| Step | What |
|---|---|
| Starts | New dashboard on the dashboards list. |
| Checks | FD11. Slug unique in the panel and tenant. |
| Writes | A private dashboard owned by the user, in the current panel and tenant (FDP4), with its navigation settings. |
| Shows | Dashboards list; navigation when Add to navigation is on. |

## DF3 Change settings or delete

| Step | What |
|---|---|
| Starts | Settings on a dashboard. |
| Checks | Edit: owner or an Edit share; never a system dashboard. Delete: owner only (FD7). |
| Writes | Name, slug, navigation; delete removes its items, shares and any home pointing at it. |
| Shows | Dashboard page, list, navigation, home page of users who had it as home. |

## DF4 Share

| Step | What |
|---|---|
| Starts | Share on a dashboard. |
| Checks | Owner only (FD7). Roles offered only when roles are detected (FDP5). |
| Writes | One share per user or role, at Read or Edit (FD6). |
| Shows | The shared users' lists, navigation and home choices. |

## DF5 Clone

| Step | What |
|---|---|
| Starts | Clone on any dashboard the user can see (FD9), including system ones (FD8). |
| Checks | FD11 (cloning creates a dashboard). |
| Writes | A new private dashboard owned by the user, with copies of the items; no shares copied. |
| Shows | The new dashboard's page. |

## DF6 Set as home

| Step | What |
|---|---|
| Starts | Set as home on any dashboard the user can see. |
| Checks | The user can see it (FD10). |
| Writes | The user's one home for this panel and tenant. |
| Shows | The panel's home page (FDP2). |

## DF7 Sync system dashboards

| Step | What |
|---|---|
| Starts | `dashboards:sync`, run on deploy. |
| Checks | The system dashboard classes registered with the plugin (FDP1). |
| Writes | Creates or updates each one and its items; removes system dashboards no longer registered. |
| Shows | Every user's list, read-only (FD8). |
