(function () {
    'use strict';

    const config = window.VehicleConfig || {};
    const actionUrl = config.basePath + 'vehicles/action/';
    const form = document.getElementById('vehicleForm');
    const result = document.getElementById('vehicleResult');
    const saveButton = document.getElementById('vehicleSaveButton');
    const slugInput = document.getElementById('vehicleSlug');
    const vehicleId = Number(document.getElementById('vehicleId').value) || 0;

    // Mirrors VehicleHtml on the server, which is the check that counts
    const purifyConfig = {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'u', 'h3', 'h4', 'ul', 'ol', 'li', 'a'],
        ALLOWED_ATTR: ['href', 'target', 'rel']
    };

    $('#vehicleDescription').summernote({
        height: 260,
        toolbar: [
            ['style', ['style', 'bold', 'italic', 'underline']],
            ['para', ['ul', 'ol']],
            ['insert', ['link']],
            ['view', ['codeview']]
        ],
        styleTags: ['p', 'h3', 'h4']
    });

    function sanitize(html) {
        return DOMPurify.sanitize(html, purifyConfig)
            .replace(/<b>/g, '<strong>').replace(/<\/b>/g, '</strong>')
            .replace(/<i>/g, '<em>').replace(/<\/i>/g, '</em>');
    }

    function showResult(message, type) {
        result.className = 'alert alert-' + type;
        result.textContent = message;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function slugify(text) {
        return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }

    async function post(payload) {
        const response = await fetch(actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ csrf_token: window.csrfToken }, payload))
        });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || data.message || 'Something went wrong');
        return data;
    }

    // ---- A new vehicle's address follows its make, model and year until someone edits it by hand ----

    const nameInputs = ['vehicleMake', 'vehicleModel', 'vehicleYear'].map(function (id) { return document.getElementById(id); });
    let slugTouched = slugInput.value !== '';
    slugInput.addEventListener('input', function () { slugTouched = true; });
    nameInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            if (!slugTouched) slugInput.value = slugify(nameInputs.map(function (i) { return i.value; }).join(' '));
        });
    });

    // ---- Features and extra specs: rows of inputs ----

    function addRow(containerId, cells, removeLabel) {
        const row = document.createElement('div');
        row.className = 'input-group mb-2';
        cells.forEach(function (cell) { row.appendChild(cell); });

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-outline-danger';
        remove.title = removeLabel;
        remove.innerHTML = '<i class="bx bx-x"></i>';
        remove.addEventListener('click', function () { row.remove(); });
        row.appendChild(remove);

        document.getElementById(containerId).appendChild(row);
    }

    function textInput(className, placeholder, value, maxLength) {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control ' + className;
        input.placeholder = placeholder;
        input.maxLength = maxLength;
        input.value = value || '';
        return input;
    }

    function addFeature(value) {
        addRow('vehicleFeatures', [textInput('feature-input', 'e.g. Solar panel', value, 200)], 'Remove feature');
    }

    function addSpec(spec) {
        spec = spec || {};
        addRow('vehicleSpecs', [textInput('spec-label', 'Label, e.g. Berths', spec.label, 60), textInput('spec-value', 'Value, e.g. 4', spec.value, 120)], 'Remove spec');
    }

    (config.features || []).forEach(addFeature);
    (config.specs || []).forEach(addSpec);
    document.getElementById('addFeatureButton').addEventListener('click', function () { addFeature(); });
    document.getElementById('addSpecButton').addEventListener('click', function () { addSpec(); });

    function collect(selector) {
        return Array.from(document.querySelectorAll(selector));
    }

    // ---- Photos ----

    // A reload (see below) keeps the tab you were on: it lives in the address
    document.querySelectorAll('.vehicle-tabs [data-bs-toggle="tab"]').forEach(function (link) {
        link.addEventListener('shown.bs.tab', function () {
            history.replaceState(null, '', link.getAttribute('href'));
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust(); // The Photos list was sized while its tab was hidden
        });
    });
    const openTab = document.querySelector('.vehicle-tabs [href="' + window.location.hash + '"]');
    if (openTab) window.addEventListener('load', function () { bootstrap.Tab.getOrCreateInstance(openTab).show(); });

    // The Photos list is a ListView: its drag, delete and the uploads below reload the page, which would lose edits to the
    // form that are not saved yet, so they wait until it is
    let unsaved = false;
    const markUnsaved = function (event) {
        if (event && event.target.closest && event.target.closest('.dataTables_wrapper')) return; // The Photos list's own search and page size
        unsaved = true;
    };
    form.addEventListener('input', markUnsaved);
    form.addEventListener('change', markUnsaved);
    $('#vehicleDescription').on('summernote.change', markUnsaved);
    ['addFeatureButton', 'addSpecButton', 'vehicleFeatures', 'vehicleSpecs'].forEach(function (id) {
        document.getElementById(id).addEventListener('click', function (event) { if (event.target.closest('button')) markUnsaved(); });
    });

    function blockWhileUnsaved(event) {
        if (!unsaved) return;
        event.preventDefault();
        event.stopPropagation();
        adminAlert('Save your changes first. Changing photos reloads the page.');
    }

    const photoList = document.getElementById('vehicle-photos');
    const dropzone = document.getElementById('vehicleDropzone');
    const uploadInput = document.getElementById('vehicleImageUpload');

    if (photoList) {
        // Capture phase: runs before the list's own handlers
        document.addEventListener('click', function (event) {
            if (event.target.closest('#vehicle-photos [data-list-action]')) blockWhileUnsaved(event);
        }, true);
        photoList.addEventListener('mousedown', function (event) {
            if (event.target.closest('.drag-handle')) blockWhileUnsaved(event);
        }, true);
        dropzone.addEventListener('click', blockWhileUnsaved, true);
        dropzone.addEventListener('drop', blockWhileUnsaved, true);

        // ----- View a photo, turn it, save the turn (the same as Gallery's) -----

        const byId = function (id) { return document.getElementById(id); };
        const viewModal = { // Bootstrap loads after this script, so it is looked up when needed
            show: function () { bootstrap.Modal.getOrCreateInstance(byId('viewModal')).show(); },
            hide: function () { bootstrap.Modal.getOrCreateInstance(byId('viewModal')).hide(); }
        };
        const viewImage = byId('viewImage');
        let viewingId = 0;
        let turn = 0; // Degrees clockwise on screen, not saved until Save

        function showTurn() {
            const quarter = ((turn % 360) + 360) % 360;
            viewImage.style.transform = 'rotate(' + turn + 'deg)';
            byId('viewStage').classList.toggle('turned-sideways', quarter === 90 || quarter === 270);
            byId('turnSave').disabled = quarter === 0;
        }

        function openViewer(id) {
            const photo = (config.photos || {})[id];
            if (!photo) return;
            viewingId = id;
            turn = 0;
            viewImage.src = photo.viewUrl;
            viewImage.alt = photo.alt || '';
            byId('viewTitle').textContent = photo.alt || 'Photo';
            showTurn();
            viewModal.show();
        }

        document.addEventListener('list:action', function (e) {
            if (e.detail.action === 'view_photo') openViewer(e.detail.id);
        });

        // Clicking a photo in the grid opens it
        photoList.addEventListener('click', function (e) {
            const image = e.target.closest('img');
            const row = image && image.closest('tr');
            if (row && row.dataset.id) openViewer(Number(row.dataset.id));
        });

        byId('turnLeft').addEventListener('click', function () { turn -= 90; showTurn(); });
        byId('turnRight').addEventListener('click', function () { turn += 90; showTurn(); });

        byId('turnSave').addEventListener('click', async function (event) {
            const degrees = ((turn % 360) + 360) % 360;
            if (degrees === 0) return;
            if (unsaved) { viewModal.hide(); blockWhileUnsaved(event); return; } // The page reloads after a turn
            this.disabled = true;
            try {
                await post({ action: 'rotate_photo', id: viewingId, degrees: degrees });
                window.location.reload();
            } catch (error) {
                viewModal.hide();
                adminAlert(error.message);
            }
        });

        let added = 0;
        VehicleUploader.attach({
            input: uploadInput,
            dropzone: dropzone,
            list: document.getElementById('vehicleUploadList'),
            actionUrl: actionUrl,
            getVehicleId: function () { return vehicleId; },
            onUploaded: function () { added++; },
            // Once the queue is done the server-rendered list is out of date; failed rows stay on screen so their reasons can be read
            onActiveChange: function (active) {
                if (active === 0 && added > 0 && !document.querySelector('#vehicleUploadList .is-failed')) window.location.reload();
            }
        });
    }

    // ---- Save ----

    // The form is not natively validated (a required field in a hidden tab cannot be focused), so show the tab instead
    function showTabFor(element) {
        const pane = element.closest('.tab-pane');
        const link = pane ? document.querySelector('.vehicle-tabs [href="#' + pane.id + '"]') : null;
        if (link) bootstrap.Tab.getOrCreateInstance(link).show();
    }

    function tabIdForError(message) {
        if (/address|url/i.test(message)) return 'vehicleSlug';
        if (/make|model|year|mileage|price/i.test(message)) return 'vehicleMake';
        return null;
    }

    function missingRequired() {
        return Array.from(form.querySelectorAll('[required]')).find(function (input) { return input.value.trim() === ''; });
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const missing = missingRequired();
        if (missing) {
            showTabFor(missing);
            missing.focus();
            showResult('Make and model are required.', 'danger');
            return;
        }

        saveButton.disabled = true;

        const value = function (id) { return document.getElementById(id).value; };
        const payload = {
            action: 'save',
            id: vehicleId,
            make: value('vehicleMake'),
            model: value('vehicleModel'),
            year: value('vehicleYear'),
            registration: value('vehicleRegistration'),
            price: value('vehiclePrice'),
            mileage: value('vehicleMileage'),
            fuel: value('vehicleFuel'),
            engine: value('vehicleEngine'),
            transmission: value('vehicleTransmission'),
            status: value('vehicleStatus'),
            description: sanitize($('#vehicleDescription').summernote('code')),
            features: collect('.feature-input').map(function (input) { return input.value; }),
            specs: collect('#vehicleSpecs .input-group').map(function (row) {
                return { label: row.querySelector('.spec-label').value, value: row.querySelector('.spec-value').value };
            }),
            is_published: document.getElementById('vehiclePublished').checked,
            slug: slugInput.value,
            meta_description: value('vehicleMetaDescription')
        };

        try {
            const data = await post(payload);
            // Go to the vehicle by id: the page shows the same saved bar as every other edit screen
            window.location.href = config.editUrl + '?id=' + data.id;
        } catch (error) {
            const fieldId = tabIdForError(error.message);
            if (fieldId) showTabFor(document.getElementById(fieldId));
            showResult(error.message, 'danger');
            saveButton.disabled = false;
        }
    });
})();
