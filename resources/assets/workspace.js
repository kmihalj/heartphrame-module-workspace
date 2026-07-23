(function () {
    'use strict';

    /**
     * HR: Prikazuje samo kontrole koje pripadaju odabranoj vrsti stavke stabla
     * i onemogućuje skrivena polja kako se ne bi slala u POST zahtjevu.
     *
     * EN: Shows only controls belonging to the selected tree item type and
     * disables hidden fields so they are not submitted in the POST request.
     *
     * @param {HTMLElement} container
     * @returns {void}
     */
    function synchronizeNodeFields(container) {
        const typeControl = container.querySelector('[data-workspace-node-type]');
        if (!(typeControl instanceof HTMLSelectElement)) {
            return;
        }

        const selectedType = typeControl.value;
        container.querySelectorAll('[data-workspace-node-types]').forEach((group) => {
            const allowedTypes = String(group.dataset.workspaceNodeTypes || '')
                .split(' ')
                .filter(Boolean);
            const isVisible = allowedTypes.includes(selectedType);

            group.hidden = !isVisible;
            group.querySelectorAll('input, select, textarea, button').forEach((control) => {
                control.disabled = !isVisible;
            });
        });
    }

    /**
     * HR: Povezuje sve napredne obrasce stabla nakon učitavanja dokumenta.
     * EN: Connects every advanced tree form after the document has loaded.
     *
     * @param {ParentNode} [root=document]
     * @returns {void}
     */
    function initializeNodeForms(root = document) {
        root.querySelectorAll('[data-workspace-node-fields]').forEach((container) => {
            if (container.dataset.workspaceNodeFieldsReady === '1') {
                return;
            }

            const typeControl = container.querySelector('[data-workspace-node-type]');
            if (!(typeControl instanceof HTMLSelectElement)) {
                return;
            }

            container.dataset.workspaceNodeFieldsReady = '1';
            typeControl.addEventListener('change', () => {
                synchronizeNodeFields(container);
            });
            synchronizeNodeFields(container);
        });
    }

    /**
     * HR: Vraća retke jednog vizualnog organizatora redom kojim su prikazani.
     * EN: Returns rows of one visual organizer in their displayed order.
     *
     * @param {HTMLElement} list
     * @returns {HTMLElement[]}
     */
    function treeRows(list) {
        return Array.from(list.querySelectorAll('[data-workspace-tree-order-row]'));
    }

    /**
     * HR: Čita tehnički ID stranice iz jednog retka organizatora.
     * EN: Reads the technical page ID from one organizer row.
     *
     * @param {HTMLElement} row
     * @returns {string}
     */
    function treeNodeId(row) {
        const input = row.querySelector('.workspace-tree-node-id');
        return input instanceof HTMLInputElement ? input.value.trim() : '';
    }

    /**
     * HR: Vraća skriveno polje roditelja jednog retka.
     * EN: Returns the hidden parent field of one row.
     *
     * @param {HTMLElement} row
     * @returns {HTMLInputElement|null}
     */
    function treeParentInput(row) {
        const input = row.querySelector('.workspace-tree-parent-id');
        return input instanceof HTMLInputElement ? input : null;
    }

    /**
     * HR: Čita ID roditelja, pri čemu prazan tekst označava korijen.
     * EN: Reads the parent ID, where an empty string denotes the tree root.
     *
     * @param {HTMLElement} row
     * @returns {string}
     */
    function treeParentId(row) {
        const input = treeParentInput(row);
        return input instanceof HTMLInputElement ? input.value.trim() : '';
    }

    /**
     * HR: Pronalazi red prema ID-u unutar istog organizatora.
     * EN: Finds a row by ID inside the same organizer.
     *
     * @param {HTMLElement} list
     * @param {string} nodeId
     * @returns {HTMLElement|null}
     */
    function treeFindRow(list, nodeId) {
        if (nodeId === '') {
            return null;
        }

        return treeRows(list).find((row) => treeNodeId(row) === nodeId) || null;
    }

    /**
     * HR: Izračunava dubinu retka hodanjem prema korijenu i prekida mogući ciklus.
     * EN: Calculates row depth by walking toward the root and stops a possible cycle.
     *
     * @param {HTMLElement} list
     * @param {HTMLElement} row
     * @returns {number}
     */
    function treeLevelFor(list, row) {
        let level = 0;
        let cursor = treeFindRow(list, treeParentId(row));
        const seen = new Set([treeNodeId(row)]);

        while (cursor instanceof HTMLElement) {
            const cursorId = treeNodeId(cursor);
            if (cursorId === '' || seen.has(cursorId)) {
                break;
            }

            seen.add(cursorId);
            level += 1;
            cursor = treeFindRow(list, treeParentId(cursor));
        }

        return level;
    }

    /**
     * HR: Provjerava pripada li red podgrani zadanog pretka.
     * EN: Checks whether a row belongs to the specified ancestor subtree.
     *
     * @param {HTMLElement} list
     * @param {HTMLElement} row
     * @param {string} ancestorId
     * @returns {boolean}
     */
    function treeIsDescendantOf(list, row, ancestorId) {
        let cursorId = treeParentId(row);
        const seen = new Set();

        while (cursorId !== '') {
            if (cursorId === ancestorId) {
                return true;
            }
            if (seen.has(cursorId)) {
                return false;
            }

            seen.add(cursorId);
            const parentRow = treeFindRow(list, cursorId);
            if (!(parentRow instanceof HTMLElement)) {
                return false;
            }
            cursorId = treeParentId(parentRow);
        }

        return false;
    }

    /**
     * HR: Vraća odabrani red i sve njegove uzastopno prikazane potomke.
     * EN: Returns the selected row and all of its consecutively displayed descendants.
     *
     * @param {HTMLElement} list
     * @param {HTMLElement} rootRow
     * @returns {HTMLElement[]}
     */
    function treeSubtreeRows(list, rootRow) {
        const rootId = treeNodeId(rootRow);
        if (rootId === '') {
            return [rootRow];
        }

        const rows = treeRows(list);
        const start = rows.indexOf(rootRow);
        const block = [rootRow];
        for (let index = start + 1; index < rows.length; index += 1) {
            if (!treeIsDescendantOf(list, rows[index], rootId)) {
                break;
            }
            block.push(rows[index]);
        }

        return block;
    }

    /**
     * HR: Pronalazi najbližu prethodnu stavku istog roditelja.
     * EN: Finds the nearest preceding item with the same parent.
     *
     * @param {HTMLElement} list
     * @param {HTMLElement} row
     * @returns {HTMLElement|null}
     */
    function treePreviousSibling(list, row) {
        const rows = treeRows(list);
        const parentId = treeParentId(row);
        return rows
            .slice(0, rows.indexOf(row))
            .reverse()
            .find((candidate) => treeParentId(candidate) === parentId) || null;
    }

    /**
     * HR: Pronalazi najbližu sljedeću stavku istog roditelja iza cijele podgrane.
     * EN: Finds the nearest following item with the same parent after the complete subtree.
     *
     * @param {HTMLElement} list
     * @param {HTMLElement} row
     * @returns {HTMLElement|null}
     */
    function treeNextSibling(list, row) {
        const rows = treeRows(list);
        const block = treeSubtreeRows(list, row);
        const start = rows.indexOf(block[block.length - 1]) + 1;
        const parentId = treeParentId(row);
        return rows.slice(start).find((candidate) => treeParentId(candidate) === parentId) || null;
    }

    /**
     * HR: Provjerava smije li stavka postati roditelj druge stavke.
     * EN: Checks whether an item may become another item's parent.
     *
     * @param {HTMLElement|null} row
     * @returns {boolean}
     */
    function treeCanBeParent(row) {
        return row instanceof HTMLElement && row.dataset.canParent === '1';
    }

    /**
     * HR: Premješta cijeli blok prije zadane točke ili na kraj popisa.
     * EN: Moves a complete block before the specified anchor or to the list end.
     *
     * @param {HTMLElement} list
     * @param {HTMLElement[]} block
     * @param {Element|null} anchor
     * @returns {void}
     */
    function treeMoveBlock(list, block, anchor) {
        block.forEach((blockRow) => {
            list.insertBefore(blockRow, anchor);
        });
    }

    /**
     * HR: Sinkronizira uvlake, redoslijed među braćom i dostupnost strelica.
     * EN: Synchronizes indentation, sibling order, and arrow availability.
     *
     * @param {HTMLElement} list
     * @returns {void}
     */
    function refreshTreeOrganizer(list) {
        const siblingPositions = new Map();
        treeRows(list).forEach((row) => {
            const parentId = treeParentId(row);
            const position = (siblingPositions.get(parentId) || 0) + 1;
            siblingPositions.set(parentId, position);

            const sortInput = row.querySelector('.workspace-tree-sort-order');
            if (sortInput instanceof HTMLInputElement) {
                sortInput.value = String(position * 10);
            }

            const label = row.querySelector('.workspace-tree-order-label');
            if (label instanceof HTMLElement) {
                label.style.setProperty('--workspace-tree-level', String(treeLevelFor(list, row)));
            }

            const previous = treePreviousSibling(list, row);
            const next = treeNextSibling(list, row);
            const parent = treeFindRow(list, treeParentId(row));
            const grandparent = parent instanceof HTMLElement
                ? treeFindRow(list, treeParentId(parent))
                : null;
            const canUseRoot = list.dataset.canUseRoot === '1';

            const up = row.querySelector('[data-workspace-tree-action="up"]');
            const down = row.querySelector('[data-workspace-tree-action="down"]');
            const indent = row.querySelector('[data-workspace-tree-action="indent"]');
            const outdent = row.querySelector('[data-workspace-tree-action="outdent"]');
            if (up instanceof HTMLButtonElement) {
                up.disabled = !(previous instanceof HTMLElement);
            }
            if (down instanceof HTMLButtonElement) {
                down.disabled = !(next instanceof HTMLElement);
            }
            if (indent instanceof HTMLButtonElement) {
                indent.disabled = !treeCanBeParent(previous);
            }
            if (outdent instanceof HTMLButtonElement) {
                outdent.disabled = !(parent instanceof HTMLElement)
                    || (grandparent === null && !canUseRoot)
                    || (grandparent !== null && !treeCanBeParent(grandparent));
            }
        });
    }

    /**
     * HR: Povezuje strelice i završnu sinkronizaciju jednoga organizatora.
     * EN: Connects arrows and final synchronization for one organizer.
     *
     * @param {HTMLFormElement} form
     * @returns {void}
     */
    function initializeTreeOrganizer(form) {
        const list = form.querySelector('[data-workspace-tree-order-list]');
        if (!(list instanceof HTMLElement)) {
            return;
        }

        list.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const button = target.closest('[data-workspace-tree-action]');
            const row = target.closest('[data-workspace-tree-order-row]');
            if (
                !(button instanceof HTMLButtonElement)
                || button.disabled
                || !(row instanceof HTMLElement)
            ) {
                return;
            }

            const action = button.dataset.workspaceTreeAction || '';
            if (action === 'up') {
                const previous = treePreviousSibling(list, row);
                if (previous instanceof HTMLElement) {
                    treeMoveBlock(list, treeSubtreeRows(list, row), previous);
                }
            } else if (action === 'down') {
                const next = treeNextSibling(list, row);
                if (next instanceof HTMLElement) {
                    const nextBlock = treeSubtreeRows(list, next);
                    treeMoveBlock(
                        list,
                        treeSubtreeRows(list, row),
                        nextBlock[nextBlock.length - 1].nextElementSibling,
                    );
                }
            } else if (action === 'indent') {
                const newParent = treePreviousSibling(list, row);
                const input = treeParentInput(row);
                if (treeCanBeParent(newParent) && input instanceof HTMLInputElement) {
                    input.value = treeNodeId(newParent);
                }
            } else if (action === 'outdent') {
                const oldParent = treeFindRow(list, treeParentId(row));
                const input = treeParentInput(row);
                if (oldParent instanceof HTMLElement && input instanceof HTMLInputElement) {
                    const parentBlock = treeSubtreeRows(list, oldParent);
                    const anchor = parentBlock[parentBlock.length - 1].nextElementSibling;
                    treeMoveBlock(list, treeSubtreeRows(list, row), anchor);
                    input.value = treeParentId(oldParent);
                }
            }

            refreshTreeOrganizer(list);
        });

        form.addEventListener('submit', () => {
            refreshTreeOrganizer(list);
        });
        refreshTreeOrganizer(list);
    }

    /**
     * HR: Povezuje sve vizualne organizatore stabla na stranici.
     * EN: Connects every visual tree organizer on the page.
     *
     * @returns {void}
     */
    function initializeTreeOrganizers() {
        document.querySelectorAll('[data-workspace-tree-order-form]').forEach((form) => {
            if (form instanceof HTMLFormElement) {
                initializeTreeOrganizer(form);
            }
        });
    }

    /**
     * HR: Prebacuje lijevu karticu između običnog stabla i organizatora bez
     * napuštanja prikazane stranice.
     *
     * EN: Switches the left card between the regular tree and organizer without
     * leaving the displayed page.
     *
     * @returns {void}
     */
    function initializeTreeEditModes() {
        document.querySelectorAll('[data-workspace-tree-edit-toggle]').forEach((toggle) => {
            if (!(toggle instanceof HTMLButtonElement)) {
                return;
            }

            const card = toggle.closest('.workspace-tree-card');
            const treeView = card?.querySelector('[data-workspace-tree-view]');
            const treeEditor = card?.querySelector('[data-workspace-tree-editor]');
            if (!(treeView instanceof HTMLElement) || !(treeEditor instanceof HTMLElement)) {
                return;
            }

            toggle.addEventListener('click', () => {
                const editing = treeEditor.hidden;
                treeEditor.hidden = !editing;
                treeView.hidden = editing;
                toggle.setAttribute('aria-pressed', editing ? 'true' : 'false');
                toggle.classList.toggle('active', editing);

                if (editing) {
                    const list = treeEditor.querySelector('[data-workspace-tree-order-list]');
                    if (list instanceof HTMLElement) {
                        refreshTreeOrganizer(list);
                    }
                }
            });
        });
    }

    /**
     * HR: Vraća lagani početni sadržaj zajedničkog modala dok se postavke
     * odabranog čvora učitavaju sa servera.
     *
     * EN: Returns lightweight initial content for the shared modal while the
     * selected node settings are loaded from the server.
     *
     * @param {string} message
     * @param {string} closeLabel
     * @returns {string}
     */
    function nodeDialogPlaceholder(message, closeLabel) {
        const safeMessage = document.createElement('div');
        const safeCloseLabel = document.createElement('div');
        safeMessage.textContent = message;
        safeCloseLabel.textContent = closeLabel;

        return '<div class="modal-header">'
            + '<h2 class="modal-title fs-5">'
            + safeMessage.innerHTML
            + '</h2>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'
            + safeCloseLabel.innerHTML
            + '"></button>'
            + '</div><div class="modal-body"><p class="text-body-secondary mb-0">'
            + safeMessage.innerHTML
            + '</p></div>';
    }

    /**
     * HR: U jedan zajednički Bootstrap modal učitava obrazac, ograničenja i
     * brisanje tek za čvor čiju je edit ikonu korisnik odabrao.
     *
     * EN: Loads the form, restrictions, and delete action into one shared
     * Bootstrap modal only for the node whose edit icon the user selected.
     *
     * @returns {void}
     */
    function initializeNodeDialog() {
        const modal = document.querySelector('[data-workspace-node-editor-modal]');
        if (!(modal instanceof HTMLElement)) {
            return;
        }

        const content = modal.querySelector('.modal-content');
        if (!(content instanceof HTMLElement)) {
            return;
        }

        const loadingMessage = modal.dataset.workspaceNodeDialogLoading || 'Loading...';
        const errorMessage = modal.dataset.workspaceNodeDialogError || 'Unable to load settings.';
        const closeLabel = modal.dataset.workspaceNodeDialogClose || 'Close';
        let requestController = null;

        document.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const trigger = target.closest('[data-workspace-node-dialog-url]');
            if (!(trigger instanceof HTMLElement)) {
                return;
            }

            const url = trigger.dataset.workspaceNodeDialogUrl || '';
            if (url === '') {
                return;
            }

            if (requestController instanceof AbortController) {
                requestController.abort();
            }
            requestController = new AbortController();
            content.innerHTML = nodeDialogPlaceholder(loadingMessage, closeLabel);

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    signal: requestController.signal,
                });
                const html = await response.text();
                if (!response.ok && html.trim() === '') {
                    throw new Error(errorMessage);
                }

                content.innerHTML = html;
                initializeNodeForms(modal);
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                content.innerHTML = nodeDialogPlaceholder(errorMessage, closeLabel);
            }
        });

        modal.addEventListener('hidden.bs.modal', () => {
            if (requestController instanceof AbortController) {
                requestController.abort();
            }
            content.innerHTML = nodeDialogPlaceholder(loadingMessage, closeLabel);
        });
    }

    /**
     * HR: Osvježava poruku prazne ACL tablice nakon dodavanja ili uklanjanja subjekta.
     * EN: Refreshes the empty ACL-table message after adding or removing a subject.
     *
     * @param {HTMLElement} section
     * @returns {void}
     */
    function refreshAclEmptyState(section) {
        const emptyRow = section.querySelector('[data-workspace-acl-empty]');
        const assignedRows = section.querySelectorAll('[data-workspace-acl-row]');
        if (emptyRow instanceof HTMLTableRowElement) {
            emptyRow.hidden = assignedRows.length > 0;
        }
    }

    /**
     * HR: Stvara malu SVG ikonu uklanjanja bez umetanja korisničkog HTML-a.
     * EN: Creates a small removal SVG icon without inserting user-provided HTML.
     *
     * @returns {SVGElement}
     */
    function aclRemoveIcon() {
        const namespace = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(namespace, 'svg');
        const path = document.createElementNS(namespace, 'path');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('viewBox', '0 0 24 24');
        path.setAttribute('d', 'M6 6l12 12M18 6L6 18');
        svg.append(path);

        return svg;
    }

    /**
     * HR: Dodaje odabrani imenik-subjekt u odgovarajuću ACL tablicu i zadano
     *     mu uključuje pregled. Javno dobiva isključivo pravo pregleda.
     * EN: Adds a selected directory subject to its ACL table and enables view
     *     by default. Public receives view permission only.
     *
     * @param {HTMLFormElement} form
     * @param {Object} subject
     * @returns {void}
     */
    function addAclSubjectRow(form, subject) {
        const category = String(subject.category || '');
        const subjectType = String(subject.type || '');
        const subjectId = String(subject.id || '');
        const label = String(subject.label || '');
        const key = subjectType + ':' + subjectId;
        if (
            !['user', 'group'].includes(category)
            || subjectType === ''
            || subjectId === ''
            || label === ''
            || form.querySelector('[data-workspace-acl-row="' + CSS.escape(key) + '"]')
        ) {
            return;
        }

        const section = form.querySelector('[data-workspace-acl-section="' + category + '"]');
        const body = section?.querySelector('[data-workspace-acl-rows="' + category + '"]');
        if (!(section instanceof HTMLElement) || !(body instanceof HTMLTableSectionElement)) {
            return;
        }

        const row = document.createElement('tr');
        row.dataset.workspaceAclRow = key;
        const heading = document.createElement('th');
        heading.scope = 'row';
        heading.append(document.createTextNode(label));
        if (Boolean(subject.is_builtin)) {
            const badge = document.createElement('span');
            badge.className = 'badge text-bg-secondary ms-1';
            badge.textContent = String(form.dataset.workspaceBuiltInLabel || 'Built-in');
            heading.append(badge);
        }
        row.append(heading);

        ['can_view', 'can_add', 'can_edit', 'can_publish', 'can_delete', 'can_manage'].forEach((permission) => {
            const cell = document.createElement('td');
            const checkbox = document.createElement('input');
            cell.className = 'text-center';
            checkbox.className = 'form-check-input';
            checkbox.type = 'checkbox';
            checkbox.name = 'acl[' + subjectType + '][' + subjectId + '][' + permission + ']';
            checkbox.value = '1';
            checkbox.checked = permission === 'can_view';
            checkbox.disabled = Boolean(subject.is_read_only) && permission !== 'can_view';
            const permissionLabel = form.getAttribute(
                'data-workspace-permission-' + permission.replaceAll('_', '-') + '-label',
            ) || permission;
            checkbox.setAttribute('aria-label', permissionLabel + ': ' + label);
            cell.append(checkbox);
            row.append(cell);
        });

        const actionCell = document.createElement('td');
        const removeButton = document.createElement('button');
        const removeLabel = String(form.dataset.workspaceRemoveLabel || 'Remove');
        actionCell.className = 'text-end';
        removeButton.className = 'btn btn-sm btn-link text-danger workspace-acl-remove';
        removeButton.type = 'button';
        removeButton.title = removeLabel;
        removeButton.setAttribute('aria-label', removeLabel + ': ' + label);
        removeButton.dataset.workspaceAclRemove = '';
        removeButton.append(aclRemoveIcon());
        actionCell.append(removeButton);
        row.append(actionCell);

        const emptyRow = body.querySelector('[data-workspace-acl-empty]');
        body.insertBefore(row, emptyRow);
        refreshAclEmptyState(section);
    }

    /**
     * HR: Zatvara jedan popis rezultata i vraća ispravno ARIA stanje comboboxa.
     * EN: Closes one result list and restores the correct combobox ARIA state.
     *
     * @param {HTMLInputElement} input
     * @param {HTMLElement} results
     * @returns {void}
     */
    function closeSubjectResults(input, results) {
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    }

    /**
     * HR: Ispisuje dohvaćene rezultate kao tipkovnicom dostupne gumbe odabira.
     * EN: Renders fetched results as keyboard-accessible selection buttons.
     *
     * @param {HTMLElement} picker
     * @param {HTMLInputElement} input
     * @param {HTMLElement} results
     * @param {Object[]} subjects
     * @returns {void}
     */
    function renderSubjectResults(picker, input, results, subjects) {
        const mode = String(picker.dataset.workspacePickerMode || 'acl');
        const form = picker.closest('form');
        results.replaceChildren();

        const visibleSubjects = subjects.filter((subject) => {
            if (mode !== 'acl' || !(form instanceof HTMLFormElement)) {
                return true;
            }

            const key = String(subject.type || '') + ':' + String(subject.id || '');
            return !form.querySelector('[data-workspace-acl-row="' + CSS.escape(key) + '"]');
        });

        if (visibleSubjects.length === 0) {
            const message = document.createElement('div');
            message.className = 'list-group-item text-body-secondary';
            message.textContent = String(picker.dataset.workspaceNoResults || 'No results.');
            results.append(message);
        } else {
            visibleSubjects.forEach((subject) => {
                const option = document.createElement('button');
                option.className = 'list-group-item list-group-item-action';
                option.type = 'button';
                option.role = 'option';
                option.textContent = String(subject.label || '');
                option.addEventListener('click', () => {
                    if (mode === 'owner') {
                        const value = picker.querySelector('[data-workspace-owner-value]');
                        if (value instanceof HTMLInputElement) {
                            value.value = String(subject.id || '');
                        }
                        input.value = String(subject.label || '');
                        input.setCustomValidity('');
                    } else if (form instanceof HTMLFormElement) {
                        addAclSubjectRow(form, subject);
                        input.value = '';
                    }
                    closeSubjectResults(input, results);
                });
                results.append(option);
            });
        }

        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    /**
     * HR: Dohvaća ograničene rezultate imenika uz prekid prethodnog upita i
     *     zaštitu od odgovora koji stigne nakon novijeg unosa.
     * EN: Fetches bounded directory results while cancelling the previous
     *     request and preventing stale responses from replacing newer input.
     *
     * @param {HTMLElement} picker
     * @param {HTMLInputElement} input
     * @param {HTMLElement} results
     * @param {{controller: AbortController|null}} state
     * @returns {Promise<void>}
     */
    async function searchSubjects(picker, input, results, state) {
        if (state.controller instanceof AbortController) {
            state.controller.abort();
        }
        state.controller = new AbortController();

        const url = new URL(String(picker.dataset.workspaceSearchUrl || ''), window.location.href);
        url.searchParams.set('type', String(picker.dataset.workspaceSubjectType || ''));
        url.searchParams.set('q', input.value.trim());
        const workspaceId = String(picker.dataset.workspaceId || '');
        if (workspaceId !== '' && workspaceId !== '0') {
            url.searchParams.set('workspace_id', workspaceId);
        }

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                signal: state.controller.signal,
            });
            const payload = await response.json();
            if (!response.ok || payload.ok !== true || !Array.isArray(payload.results)) {
                throw new Error(String(payload.error || 'Search failed.'));
            }
            renderSubjectResults(picker, input, results, payload.results);
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            results.replaceChildren();
            const message = document.createElement('div');
            message.className = 'list-group-item text-danger';
            message.textContent = String(picker.dataset.workspaceSearchError || 'Search failed.');
            results.append(message);
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
    }

    /**
     * HR: Povezuje jedan owner ili ACL picker s odgođenom serverskom pretragom.
     * EN: Connects one owner or ACL picker to debounced server-side search.
     *
     * @param {HTMLElement} picker
     * @returns {void}
     */
    function initializeSubjectPicker(picker) {
        const input = picker.querySelector('[data-workspace-subject-search]');
        const results = picker.querySelector('[data-workspace-subject-results]');
        if (!(input instanceof HTMLInputElement) || !(results instanceof HTMLElement)) {
            return;
        }

        const state = {controller: null};
        let timer = 0;
        const schedule = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                void searchSubjects(picker, input, results, state);
            }, 180);
        };

        input.addEventListener('focus', schedule);
        input.addEventListener('input', () => {
            if (picker.dataset.workspacePickerMode === 'owner') {
                const value = picker.querySelector('[data-workspace-owner-value]');
                if (value instanceof HTMLInputElement) {
                    value.value = '';
                }
            }
            schedule();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSubjectResults(input, results);
            }
        });

        document.addEventListener('click', (event) => {
            if (event.target instanceof Node && !picker.contains(event.target)) {
                closeSubjectResults(input, results);
            }
        });

        const form = picker.closest('form');
        if (picker.dataset.workspacePickerMode === 'owner' && form instanceof HTMLFormElement) {
            form.addEventListener('submit', (event) => {
                const value = picker.querySelector('[data-workspace-owner-value]');
                const valid = value instanceof HTMLInputElement && value.value !== '';
                input.setCustomValidity(valid ? '' : String(picker.dataset.workspaceNoResults || 'Select a user.'));
                if (!valid) {
                    event.preventDefault();
                    input.reportValidity();
                }
            });
        }
    }

    /**
     * HR: Povezuje uklanjanje ACL redaka i sve asinkrone pickere na stranici.
     * EN: Connects ACL-row removal and every asynchronous picker on the page.
     *
     * @returns {void}
     */
    function initializeAclControls() {
        document.querySelectorAll('[data-workspace-subject-picker]').forEach((picker) => {
            if (picker instanceof HTMLElement) {
                initializeSubjectPicker(picker);
            }
        });

        document.querySelectorAll('[data-workspace-acl-form]').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof Element)) {
                    return;
                }

                const button = target.closest('[data-workspace-acl-remove]');
                const row = button?.closest('[data-workspace-acl-row]');
                const section = row?.closest('[data-workspace-acl-section]');
                if (!(row instanceof HTMLTableRowElement) || !(section instanceof HTMLElement)) {
                    return;
                }

                row.remove();
                refreshAclEmptyState(section);
            });
        });
    }

    /**
     * HR: Inicijalizira sve Workspace kontrole nakon što je DOM spreman.
     * EN: Initializes every Workspace control after the DOM is ready.
     *
     * @returns {void}
     */
    function initializeWorkspaceControls() {
        initializeNodeForms();
        initializeTreeOrganizers();
        initializeTreeEditModes();
        initializeNodeDialog();
        initializeAclControls();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeWorkspaceControls, { once: true });
    } else {
        initializeWorkspaceControls();
    }
}());
