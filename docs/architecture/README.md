# Rules Index

Filament Dashboards lets each user build their own dashboards from the panel's widgets, share them with users or roles, clone them, and pick one as their home page. The app can ship read-only system dashboards.

Read the entry that matches your work before designing, planning or changing it. List the rules your work touches, and raise any conflict before building.

| When your work touches | Read |
|---|---|
| Anything in this plugin: widgets, dashboards, sharing, system dashboards, home page, roles, Guardian | [dashboard-rules.md](dashboard-rules.md) |
| Any action a user or the app takes: what starts it, what it checks, what it writes, which screens show it | [dashboard-flows.md](dashboard-flows.md) |

Every rulebook has the same shape: agreed rules with a reason each, a superseded log, facts, proposals not yet agreed, and open decisions. A rule is never edited silently; the old wording moves to the superseded log. When an action is built or changed, its flow changes in the same commit.

This folder is export-ignored: it lives in the repository, never in the installed package.
