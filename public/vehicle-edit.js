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

    const gallery = document.getElementById('vehicleGallery');
    const uploadInput = document.getElementById('vehicleImageUpload');

    function renderPhoto(image) {
        const col = document.createElement('div');
        col.className = 'col-md-4 col-6 vehicle-photo';
        col.dataset.id = image.id;

        const card = document.createElement('div');
        card.className = 'card';

        const img = document.createElement('img');
        img.src = image.path;
        img.alt = '';
        img.className = 'card-img-top';
        img.style.cssText = 'height: 120px; object-fit: cover; cursor: grab;';

        const body = document.createElement('div');
        body.className = 'card-body p-2';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-outline-danger w-100';
        remove.innerHTML = '<i class="bx bx-trash"></i> Remove';
        remove.addEventListener('click', async function () {
            if (!(await adminConfirm('Remove this photo?'))) return;
            try {
                await post({ action: 'delete_image', id: vehicleId, image_id: image.id });
                col.remove();
            } catch (error) {
                adminAlert(error.message);
            }
        });

        body.appendChild(remove);
        card.append(img, body);
        col.appendChild(card);
        gallery.appendChild(col);
    }

    if (gallery) {
        (config.images || []).forEach(renderPhoto);

        Sortable.create(gallery, {
            animation: 150,
            onEnd: async function () {
                const order = collect('#vehicleGallery .vehicle-photo').map(function (el) { return Number(el.dataset.id); });
                try {
                    await post({ action: 'reorder_images', id: vehicleId, order: order });
                } catch (error) {
                    adminAlert(error.message);
                }
            }
        });

        uploadInput.addEventListener('change', async function () {
            const files = Array.from(uploadInput.files);
            uploadInput.disabled = true;

            for (const file of files) {
                const formData = new FormData();
                formData.append('csrf_token', window.csrfToken);
                formData.append('action', 'upload_image');
                formData.append('id', vehicleId);
                formData.append('image', file);

                try {
                    const response = await fetch(actionUrl, { method: 'POST', body: formData });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.error || data.message || 'Upload failed');
                    renderPhoto(data.image);
                } catch (error) {
                    adminAlert(file.name + ': ' + error.message);
                }
            }

            uploadInput.value = '';
            uploadInput.disabled = false;
        });
    }

    // ---- Save ----

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
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
            showResult(error.message, 'danger');
            saveButton.disabled = false;
        }
    });
})();
