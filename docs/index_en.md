# Workspace Module Guide

Croatian version: [index_hr.md](index_hr.md)

## 1. Mental model

A Workspace is an organizational and security boundary above individual
pages. A Workspace owns the page tree and permissions, while specialized
modules continue to own their content.

For example, a document node stores an HTML editor `document_key`; the HTML
editor still stores HTML versions and attachment metadata. Link nodes contain
an internal route/path or an external HTTPS URL and do not require the editor.

The design prevents the Workspace module from reaching into another module's
private database tables.

## 2. Data model

The single initial migration creates five tables through the ORM:

| Table | Responsibility |
| --- | --- |
| `workspaces` | Identity, slug, visibility, owner, archive and soft-delete state |
| `workspace_acl` | User/group grants at Workspace level |
| `workspace_nodes` | Ordered hierarchy of documents and links |
| `workspace_node_acl` | Additional restrictions inherited through the tree |
| `workspace_node_workflows` | Per-page and per-language publication state and immutable Editor version pointers |

There are no database-specific SQL statements. Boolean defaults use real
booleans, and schema creation is compatible with SQLite, PostgreSQL, and
MySQL/MariaDB.

The migration intentionally contains no test data.

## 3. Workspace visibility

Visibility is configured in the same ACL table as every other permission. Two
built-in audiences look like groups in the interface, but they are not Auth
groups and are never created in the user-group directory:

- **Public** (`public`) includes guests and signed-in users and always grants
  view only. Add, edit, delete, and manage are deliberately unavailable for
  this audience.
- **All signed-in users** (`authenticated`) includes every authenticated user
  and may receive view plus the broader permissions selected by a Workspace
  manager.
- When neither built-in audience is assigned, the Workspace is `restricted`
  and is visible only to its owner, administrators, and explicitly authorized
  users or Auth groups.

The `workspaces.visibility` column retains a summarized value for efficient
Workspace filtering, but it is synchronized automatically from the built-in
ACL rows. The form therefore has no second visibility selector that could
drift away from the actual permissions.

An archived Workspace remains readable to authorized users, but content
changes are disabled for the owner and administrator as well. Their
`can_manage` permission remains active so they can reactivate the Workspace.
Deleting a Workspace is a soft delete. Administrators can list and restore
deleted Workspaces, including resolving a slug conflict.

## 4. Permissions and inheritance

Workspace ACL accepts individual users, real Auth groups, and the built-in
`public` and `authenticated` audiences. Grants from the current user, every
built-in audience they belong to, and all their groups are combined:

- `can_view`
- `can_add`
- `can_edit`
- `can_delete`
- `can_manage`

`can_manage` implies every other permission. The owner and application
administrators receive the complete permission set.

The management screen does not load every user and group. It renders assigned
ACL rows only, while a searchable picker adds new subjects. Search runs on the
server, accepts part of a display name, login identifier, or group name, and
returns at most 20 results per request. The previous request is cancelled when
the operator keeps typing. This remains usable with thousands of users and
hundreds of groups without embedding the complete Auth directory in HTML.

Changing the owner uses the same bounded user picker. Removing a table row
removes only the permission assignment after save; it never deletes the user
or group from the Auth module.

Node ACL is deliberately restrictive:

1. calculate effective Workspace permission;
2. walk from the root node to the requested node;
3. whenever an ancestor has restriction rows, retain only permissions allowed
   to the current user's already-authorized user/group subjects;
4. never broaden the Workspace permission.

An empty node ACL means “inherit without an additional restriction.” A
restriction on a parent automatically applies to every descendant.

## 5. Page tree

The left-side tree can be shown or hidden. It visually follows the HTML
editor's outline card by using the same heading, `list-group`, theme, and
internal scrolling. On desktop it remains available while reading; on mobile
it becomes a bounded, collapsible block.

The tree card and HTML card start in the same row. The tree toggle is the first
SVG action in Editor's shared view, while SVG actions for creating a page and
managing the Workspace live in the tree header itself. Actions therefore do
not reserve a separate empty row and their visibility still follows effective
ACL permissions.
Compact header actions are right-aligned in their own row, while the complete
Workspace name is rendered below them without truncation.

Node types:

- `document`: links to one HTML editor document;
- `internal_link`: follows a named project route or an internal path;
- `external_link`: follows a validated external URL.

Parent and order values define the hierarchy. A user with complete management
permission enables the organizer directly from the icon in the left tree
header: up/down arrows move a complete subtree among items with the same
parent, while left/right arrows outdent or indent the subtree by one level.
Unavailable actions are disabled. Order numbers are synchronized automatically
and the complete arrangement is saved by one atomic ORM transaction. Before
the first write, the repository verifies that every active node is present,
all parents are document pages, and the resulting tree contains no cycle.

A small edit icon beside an item opens a Bootstrap modal with title, slug,
type, target, inherited restrictions, and deletion. The form is loaded on
demand so large trees do not create hundreds of hidden forms. The organizer
footer opens a modal for adding a link or existing document and selecting its
initial parent. Deleting a node soft-deletes the complete subtree. A linked
editor document is soft-deleted through the optional bridge. The separate
**Manage Workspace** screen retains only Workspace data, members, Workspace
ACL, and Workspace deletion.

Moving a node requires `can_edit` on the node and `can_add` on the new parent or
tree root. The management view never sends nodes for which the user lacks
effective `can_view`. A user with content-change rights can open tree
management, but Workspace metadata and ACL remain editable only with
`can_manage`.

An internal link accepts an existing named route or a local absolute path that
starts with one slash, for example `/calendars`. The local path is automatically
resolved under the application base path, so it becomes `/hfc/calendars` when
the application is mounted below `/hfc`. Absolute HTTP(S) URLs are accepted only
by `external_link`.

One active HTML editor document may be linked to only one active Workspace
node. This keeps URL and ACL ownership unambiguous.

## 6. HTML editor integration

Integration is optional and service-based. Workspace dynamically detects the
editor package and uses its public services. The editor likewise detects the
Workspace ACL service without declaring a hard dependency.

When Workspace is enabled:

- the editor Settings screen shows that Workspace owns public routes;
- the standalone editor slug switch is disabled;
- a linked document is loaded only when inherited `can_view` succeeds;
- Workspace embeds Editor's shared complete view in the right column, so theme,
  languages, history, attachments, ZIP export, document outline, and audit are
  identical to the standalone view;
- edit, upload, metadata, version, and attachment actions require `can_edit`;
- document deletion requires `can_delete`;
- menu document URLs point to Workspace pages.

Workspace neither reads Editor's private tables nor copies its HTML template.
It requests `EditorDocumentViewBuilder` through the optional service bridge and
renders Editor's official `editor/view` partial next to the left tree. Language
and outline links therefore remain in the current Workspace, while export and
asset routes re-check the same inherited ACL on the server.

A user with `can_add` sees **New page** in the open Workspace. The compact
form asks only for a title, optional slug, and parent page. On submit, the
module creates an editor document, links its stable key to the tree page, and
immediately opens the HTML editor. The first created page automatically
becomes the Workspace homepage.

Page creation first validates `can_add` on the Workspace and selected parent
page. A link item cannot be the parent of a new page.
A regular editor may keep the document owned by their node or automatically
create a new one. A crafted POST request cannot attach another existing
document. An administrator may select an existing document, while the server
checks that it exists and is not already owned by another active node.

The management screen is neither the content-authoring nor tree-arrangement
entry point. The tree, links, and inherited restrictions are managed in the
context of the open Workspace. The advanced modal shows only fields relevant
to the selected item type.

Without the editor module, Workspaces and link nodes remain usable. Without
Workspace, the HTML editor retains its standalone behavior.

## 7. Publishing workflow

Publication state belongs to a document node and a language. Workspace stores
only status, audit timestamps, user identifiers, and Editor version numbers.
The HTML payload, attachments, and immutable versions continue to belong to the
HTML editor.

The clean initial state is **Draft**. A document node without a workflow row is
also treated as an unpublished draft; there is no legacy auto-publish fallback.
The supported states are:

1. **Draft**: editors work on one shared mutable draft. The regular view shows
   the earlier published version to everyone, if one exists. Editing and
   previewing the draft are separate explicit actions. The regular published
   view does not offer actions that mutate the draft; discard, submit, and
   publish are available only on the explicit draft preview.
2. **In review**: the working version is ready for review. It is still not
   public.
3. **Published**: the selected immutable version becomes the reader version.
4. **Archived**: the page is removed from the reader tree and public view.
   Restoring it creates an unpublished draft that must be published again.

Saving updates the same shared draft and does not add it to history. History
contains published immutable versions only. Restoring a historical version,
copying a locale, deleting an attachment, or another content change also
prepares the shared draft. This does not replace `published_version_number`, so
every regular view receives stable published content while the next publication
is prepared.

Permissions deliberately separate editing from publication:

- `can_edit`: submit a draft for review and withdraw it;
- `can_publish`: publish a draft, including direct `Save and publish`, which
  then opens the public view of the published page;
- `can_manage`: implies every permission and additionally archives or restores;
- every user receives exactly the recorded published version and its historical
  attachment set on the regular view;
- users with edit or publish permission receive separate actions for editing
  and previewing the shared draft.

Workflow transitions are server-validated through
`POST /workspaces/workflow`; changing a button, URL, or request body cannot
bypass effective inherited ACL. Discreet workflow actions are rendered only for
users who may perform them. Never-published pages are marked next to their tree
title and listed behind the `New unpublished pages` counter. Users with
`can_publish` also receive a `Submitted for review` counter and review queue.
Discarding a new page with no publication in any locale permanently deletes
its Workspace node, workflow, restrictions, and Editor document with its
attachments. It therefore does not enter the soft-deleted document list. If
the node has children, they are reparented to its parent. This destructive
discard variant requires effective `can_delete` permission. If another locale
has a publication, only the current locale draft is discarded.

When the optional Notification module is installed, submitting a draft for
review creates a deduplicated inbox message for every effective publisher
except the actor. Publishing creates a message for the user who submitted the
draft when that user is different from the publisher. Notification delivery is
an auxiliary channel and cannot roll back a successful workflow transition.
If the optional E-mail module is enabled, the same notification may also be
queued in its persistent SMTP outbox.

When Workspace is not installed, all integration calls are no-ops. Standalone
Editor save, view, history, and export continue using the current document
version exactly as before.

## 8. Configuration

`config/workspace.php` supports:

```php
return [
    'enabled' => true,
    'routing' => [
        'root_path' => 'workspace',
    ],
    'defaults' => [
        'visibility' => 'restricted',
        'tree_visible' => true,
    ],
    'creation' => [
        'authenticated_users' => false,
    ],
    'menu' => [
        'auto_register_top' => true,
        'auto_register_settings' => true,
    ],
];
```

The root path must be a free first route segment. Settings reject collisions
with an existing application route.

If Menu is enabled, Workspace idempotently registers:

- a top-menu Workspace entry;
- General settings;
- All Workspaces;
- Deleted Workspaces.

Repeated requests do not duplicate or relocate those entries.
Workspace administration pages render the shared Settings sidebar when Menu is
available. Without Menu, the same pages remain usable through a local fallback
sidebar containing General settings, All Workspaces, and Deleted Workspaces.

## 9. Installation and operation

```bash
composer require aaieduhr/heartphrame-module-workspace
vendor/bin/hph workspace:install-migration
vendor/bin/hph orm-migrate:up
```

Register Auth and ORM before Workspace in the enabled module list. The module
defers loading until those required services are available.

Useful URLs with the default configuration:

- `/workspaces`: visible Workspace list
- `/workspaces/manage`: create or manage a Workspace
- `GET /workspaces/acl/subjects`: bounded server-side search for users, groups,
  and built-in audiences; requires Workspace management permission
- `GET /workspaces/node/dialog`: ACL-protected modal content for a selected item
- `POST /workspaces/page/create`: securely create a page from an open Workspace
- `POST /workspaces/tree/order`: atomically save the visual tree arrangement
- `POST /workspaces/workflow`: perform an ACL-protected publication transition
- `/workspace/{workspace}`: Workspace homepage
- `/workspace/{workspace}/{page}`: page or link node
- `/settings/workspaces`: administrator settings
- `/settings/workspaces/all`: administrator list
- `/settings/workspaces/deleted`: restore screen

## 10. Developer checks

```bash
composer on-commit
```

The command runs PHPCS, Rector dry-run, PHPStan for source and tests, and
PHPUnit. Every method is documented in Croatian and English. Views use
escaped output, forms use the framework CSRF field, and controllers validate
Workspace ownership before write operations.
