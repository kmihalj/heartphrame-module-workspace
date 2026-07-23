# HeartPhrame Workspace Module

The Workspace module organizes related content into **Workspaces** (`Područja`
in Croatian). Each Workspace has its own URL, owner, visibility, members,
permissions, and hierarchical page tree.

Croatian documentation: [README_hr.md](README_hr.md)

## Features

- built-in **Public** and **All signed-in users** audiences plus restricted Workspaces
- user and group permissions: view, add, edit, delete, and manage
- asynchronous Auth-directory search without listing every user and group
- page-level restrictions inherited by every descendant
- hierarchical document, internal-link, and external-link nodes
- collapsible and responsive page tree
- page creation directly from an open Workspace
- soft deletion and administrator restoration of Workspaces
- optional HTML editor integration for content, versions, and attachments
- per-page and per-language publishing workflow: draft, review, published, archived
- readers keep seeing the last published immutable version while editors prepare a draft
- optional in-app and e-mail notifications for review requests and publications
- optional Menu integration for application and Settings navigation
- portable initial schema for SQLite, PostgreSQL, and MySQL/MariaDB

Page restrictions only narrow the permissions granted at Workspace level. They
never grant access to a user or group that is not already a Workspace member.
The Workspace owner and application administrators retain management access.
In an archived Workspace, add, edit, and delete are disabled for them as well
until they reactivate it.

`Public` is a built-in view-only audience. `All signed-in users` is not a real
Auth group either, but it may receive broader permissions. The form renders
assigned ACL rows only; users and groups are added through bounded server-side
search without loading the complete directory.

## Requirements

- PHP 8.2 or newer
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`

The HTML editor, Menu, Notification, and E-mail modules are optional integrations.

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
  lists of new pages and pages submitted for review;
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
