# HeartPhrame Workspace Module

[Hrvatska verzija](README_hr.md)

The Workspace module organizes related content into **Workspaces** (`Područja`
in Croatian). Each Workspace has its own URL, owner, visibility, members,
permissions, and hierarchical page tree.

## Dependencies

Required, in enable order:

1. `aaieduhr/heartphrame-framework` (`dev-main`)
2. `aaieduhr/heartphrame-module-orm` (`dev-main`)
3. `aaieduhr/heartphrame-module-auth` (`dev-main`)
4. `aaieduhr/heartphrame-module-workspace` (`dev-main`)

Optional integrations:

- HTML Editor provides owned pages and editing; Menu adds navigation.
- Notification informs reviewers/authors; E-mail can copy those notifications.
- API adds ACL-aware Workspace resources and tree-management endpoints.

```bash
composer require aaieduhr/heartphrame-module-workspace:dev-main
vendor/bin/hph workspace:install-migration
vendor/bin/hph orm-migrate:up
```

Croatian documentation: [README_hr.md](README_hr.md)

## Features

- built-in **Public** and **All signed-in users** audiences plus restricted Workspaces
- user and group permissions: view, add, edit, publish, delete, and manage
- asynchronous Auth-directory search without listing every user and group
- page-level restrictions inherited by every descendant
- ACL-filtered Workspace Shorts with rendered article excerpts, depth, count, and ordering controls
- hierarchical document, internal-link, and external-link nodes
- collapsible and responsive page tree
- page creation directly from an open Workspace
- soft deletion and administrator restoration of Workspaces
- optional HTML editor integration for content, versions, and attachments
- per-page and per-language publishing workflow: draft, review, published, archived
- readers keep seeing the last published immutable version while editors prepare a draft
- optional in-app and e-mail notifications for review requests and publications
- optional Menu integration for application and Settings navigation
- public, signed-in, and personal application-homepage selection with ACL-safe fallback
- optional versioned REST API for Workspace metadata, ACL, and link-tree operations
- portable initial schema for SQLite, PostgreSQL, and MySQL/MariaDB

Page restrictions only narrow the permissions granted at Workspace level. They
never grant access to a user or group that is not already a Workspace member.
The Workspace owner and application administrators retain management access.
In an archived Workspace, add, edit, and delete are disabled for them as well
until they reactivate it.

Open **Edit tree**, then the pencil beside a page, to inspect page restrictions.
Green checkboxes show permissions inherited from the Workspace and ancestor pages; red checkboxes
show permissions retained by a direct restriction on that page. Saving no red
checkboxes removes the direct restriction and returns to full inheritance.

`Public` is a built-in view-only audience. `All signed-in users` is not a real
Auth group either, but it may receive broader permissions. The form renders
assigned ACL rows only; users and groups are added through bounded server-side
search without loading the complete directory.

## Requirements

- PHP 8.2 or newer
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`

The HTML editor, API, Menu, Notification, and E-mail modules are optional integrations.

## API Integration

Workspace publishes the neutral `workspace:read` and `workspace:manage`
descriptors from `config/api.php` without depending on the API module. When
the API module is also installed, it conditionally exposes versioned Workspace
routes under `/api/v1/workspaces`.

`workspace:manage` covers Workspace metadata, soft deletion/restoration, ACL,
tree order, and internal/external link nodes. It does not create or delete
HTML documents and attachments; those remain the HTML editor's responsibility.
Every operation checks both the key scope and the effective Workspace ACL of
the key owner. A broad scope never turns an unauthorized user into a manager.

See [docs/index_en.md](docs/index_en.md#10-api-integration) for the route list
and response behavior.

## Installation

```bash
composer require aaieduhr/heartphrame-module-workspace
vendor/bin/hph workspace:install-migration
vendor/bin/hph orm-migrate:up
```

Add the package after Auth and ORM in `app.modules.enabled`:

```php
'aaieduhr/heartphrame-module-workspace',
```

Copy `config/workspace.php` into the host application when its defaults need
to be changed.

No sample Workspace, user, group, or page is created by the migration.

For an application that already ran the older Workspace migration, install the
additive homepage migration once:

```bash
vendor/bin/hph workspace:install-homepage-migration
vendor/bin/hph orm-migrate:up
```

## Workspace Shorts

Every visible Workspace tree exposes a **Shorts** icon. The page at
`/{workspace-root}/{workspace}/shorts` renders the exact published Editor
version of every eligible page as a six-line fading excerpt with a **Read
more** link. Drafts, archived publications, inaccessible pages, and every
descendant of an inaccessible page are excluded before content is loaded.

Visitors may select tree levels 1, 1–2, or 1–3; 5, 10, 25, 50, or all
articles; and hierarchy, newest-first, or oldest-first ordering. **All** is
available only when fewer than 100 articles pass publication and ACL checks.
The server enforces the same limit even for a hand-crafted query string.

Defaults are configured under **Settings → Workspaces** and stored in the host
application's `config/workspace.php`:

```php
'shorts' => [
    'depth' => 1,
    'limit' => 10,
    'order' => 'hierarchy',
],
```

These are site configuration, not Theme design data. Include
`config/workspace.php` in a complete site backup; Theme package export does
not and should not own these values.

## Application homepage

Administrators configure the homepage under **Settings → Workspaces →
Application homepage**. They may select a published page for anonymous guests,
a different page available to every signed-in user, and whether users may
choose a personal page in their Auth profile.

Resolution order for a signed-in user is personal page, signed-in default,
public default, and finally the host application's built-in homepage. Guests
use the public default and then the built-in homepage. Every request rechecks
the current Workspace ACL and publication state; a deleted, unpublished, or
newly restricted page is skipped instead of producing a homepage `403`.

The host application may consume the neutral
`heartphrame.application_homepage_resolver` service at `/` and issue a
temporary, non-cacheable redirect to the canonical Workspace page. Auth has no
dependency on Workspace: the profile section is registered only by Workspace
while that module is enabled.

The settings and personal preferences are stored in
`workspace_homepage_settings` and `workspace_user_homepages`. A complete
database/site backup must include both tables. These values are site content
configuration and intentionally do not belong to Theme package export.

## HTML Editor Integration

The Workspace module does not store HTML. It links a tree node to the editor's
stable document key through an optional service bridge.

When both modules are enabled:

- Workspace routes and inherited ACL own linked document access;
- the editor's standalone public slug route is disabled;
- authorized Workspace members can add, edit, and delete linked pages;
- a regular editor automatically creates a new document and cannot attach
  somebody else's existing document by guessing its key; attaching existing
  documents is reserved for administrators;
- internal absolute paths are resolved inside the application's configured base
  path, so `/calendars` also works when the application runs under `/hfc`;
- a page uses the complete HTML editor view, including theme, languages,
  history, attachments, ZIP export, document outline, audit data, and responsive behavior;
- Workspace adds only the left tree, while effective node ACL controls editing,
  history, and other protected actions;
- document versions and attachments remain owned by the HTML editor;
- a new or changed page becomes a draft, while only an explicit publish action
  changes the immutable version visible to readers;
- there is one shared draft per page and locale; the regular view always shows
  the latest publication, while draft editing and preview are explicit actions;
- editors may submit or withdraw a review, users with publish permission may
  publish, and managers archive or restore pages;
- submitting for review notifies effective publishers, while publication
  notifies the submitting author; the Notification inbox is primary and the
  E-mail module may queue an optional SMTP copy;
- the tree marks new unpublished pages, while its header exposes permission-aware
  lists of new pages, pages submitted for review, and the Shorts page;
- Shorts requests exact published versions through one optional batch Editor
  service call after applying depth, publication, ACL, ordering, and count filters;
- an editor document can belong to only one active Workspace page.

The HTML editor continues to work independently when Workspaces are absent.
Its standalone view always uses the current editor version and does not expose
Workspace workflow controls.

## Documentation

The detailed architecture and beginner-oriented operational guide are in
[docs/index_en.md](docs/index_en.md).

## Licence

This work is published under the
[European Union Public License (EUPL) v1.2](LICENSE).

## Dependency policy

The Framework and internal HeartPhrame modules are required from the moving
`dev-main` branch. This module does not commit `composer.lock`; CI resolves
the latest development heads and runs the complete `composer on-commit` suite.
