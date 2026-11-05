# Dashboard Flows

What each action does: what starts it, what it checks, what it writes, and which screens show the result. The rules are in [dashboard-rules.md](dashboard-rules.md).

**How to use it.** When an action is built or changed, its section changes in the same commit. When something looks wrong, find the action and check each line.

**Status:** Draft, 2026-11-04, stage 1. Built 2026-11-04: data layer and screens. Layout editing (move, resize, remove) is part of DF1's screen.

## DF1 Offer widgets (Insert widget)

| Step | What |
|---|---|
| Starts | Insert widget, on a dashboard the user can edit. |
| Checks | Panel widgets that implement `DashboardWidget` (FD2); each one's `canView()` and `authorizeWidgetsUsing()` (FD3). |
| Writes | A dashboard item: widget key, position and size (FDP3). |
| Shows | Add widget's card carousel (FDP10); the dashboard grid. |

## DF8 Arrange widgets (Edit layout)

| Step | What |
|---|---|
| Starts | Edit layout, then dragging a widget's frame or its side edges; or Duplicate or Remove under a widget. Finish editing in the banner ends it. |
| Checks | Owner or Edit share, checked again on the server (FD6). Only items of this dashboard; Duplicate only of a widget the user can see; position and size kept inside the 12-column grid. Nothing saved on narrow screens (FDP13). |
| Writes | x, y, w, h of every item when a drag or resize ends (FDP12); Remove deletes the item; Duplicate adds an item below the others, with a copy of a builder widget owned by the user. |
| Shows | The dashboard grid, for everyone who can see it. |

## DF9 Build a widget

| Step | What |
|---|---|
| Starts | Add widget, then a card under New widget; or Edit on a saved widget (grid toolbar or Widgets list). |
| Checks | Edit on the dashboard (FD6) to add; edit on the widget to change it (FD12). Only registered data sources and their fields (FD15). |
| Writes | A builder widget owned by the user in the current panel and tenant, and an item for it on the dashboard; editing changes name, refresh and settings. |
| Shows | Preview tab while building; the dashboard grid (FDP18); the Widgets list; every dashboard that holds the widget. |

## DF10 Share, clone or delete a widget

| Step | What |
|---|---|
| Starts | The row menu in the Widgets list. |
| Checks | Share and delete: owner only, never a system widget (FD7). Clone: anyone who can see it. |
| Writes | Shares (FD6); a private copy for clone; delete removes the widget and its shares. |
| Shows | The Widgets list; Add widget's Quick widgets for the people it is shared with. |

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
| Starts | Set as home on any dashboard the user can see, except their current home (FD17). |
| Checks | The user can see it (FD10). |
| Writes | The user's one home for this panel and tenant. |
| Shows | The panel's home page (FDP2). |

## DF11 Remove as home

| Step | What |
|---|---|
| Starts | Remove as home on the user's current home dashboard (FD17). |
| Checks | The dashboard is the user's home for this panel and tenant. |
| Writes | Deletes the user's home for this panel and tenant. |
| Shows | The panel's normal dashboard on the home page (FD10, FDP2). |

## DF7 Sync system dashboards

| Step | What |
|---|---|
| Starts | `dashboards:sync`, run on deploy. |
| Checks | The system widget and system dashboard classes registered with the plugin (FD16). Widgets sync first so system dashboards can place them. |
| Writes | Creates or updates each one and its items; removes system dashboards no longer registered. |
| Shows | Every user's list, read-only (FD8). |
