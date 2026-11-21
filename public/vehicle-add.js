document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const config = window.VehicleAddConfig || {};
    const actionUrl = adminBasePath + 'vehicles/action/';
    const modalElement = document.getElementById('wizardModal');
    const modal = new bootstrap.Modal(modalElement);
    const errorBox = document.getElementById('wizard-error');
    const backButton = document.getElementById('wizard-back');
    const nextButton = document.getElementById('wizard-next');
    const keepButton = document.getElementById('wizard-keep-hidden');
    const publishButton = document.getElementById('wizard-publish');
    const totalSteps = 4;
    const maxFeatureLength = 200; // Same cap as the edit page's feature inputs

    let currentStep = 1;
    let vehicleId = 0;
    let busy = false;
    let uploading = 0;
    let leaving = false;
    let editorReady = false;

    // Mirrors VehicleHtml on the server, which is the check that counts
    const purifyConfig = {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'u', 'h3', 'h4', 'ul', 'ol', 'li', 'a'],
        ALLOWED_ATTR: ['href', 'target', 'rel']
    };

    const field = function (id) { return document.getElementById(id); };

    // ---- Server calls ----

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

    function sanitize(html) {
        return DOMPurify.sanitize(html, purifyConfig)
            .replace(/<b>/g, '<strong>').replace(/<\/b>/g, '</strong>')
            .replace(/<i>/g, '<em>').replace(/<\/i>/g, '</em>');
    }

    function description() {
        if (!editorReady) return '';
        const editor = $('#vehicleDescription');
        return editor.summernote('isEmpty') ? '' : sanitize(editor.summernote('code'));
    }

    function features() {
        return field('vehicleFeatures').value.split(/\r?\n/)
            .map(function (line) { return line.trim().slice(0, maxFeatureLength); })
            .filter(function (line) { return line !== ''; });
    }

    // Always the full current values from every step, because save writes every column
    function payload(published) {
        return {
            action: 'save',
            id: vehicleId,
            make: field('vehicleMake').value,
            model: field('vehicleModel').value,
            year: field('vehicleYear').value,
            registration: field('vehicleRegistration').value,
            price: field('vehiclePrice').value,
            mileage: field('vehicleMileage').value,
            fuel: field('vehicleFuel').value,
            engine: field('vehicleEngine').value,
            transmission: field('vehicleTransmission').value,
            status: 'For Sale',
            description: description(),
            features: features(),
            specs: [],
            is_published: published,
            slug: '',
            meta_description: ''
        };
    }

    async function saveVehicle(published) {
        const data = await post(payload(published));
        vehicleId = data.id;
    }

    // ---- Navigation ----

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    }

    function setBusy(isBusy) {
        busy = isBusy;
        const locked = busy || uploading > 0;
        [backButton, nextButton, keepButton, publishButton].forEach(function (button) { button.disabled = locked; });
    }

    function validateStep(step) {
        if (step !== 1) return true;

        let valid = true;
        ['vehicleMake', 'vehicleModel'].forEach(function (id) {
            const missing = field(id).value.trim() === '';
            field(id).classList.toggle('is-invalid', missing);
            if (missing) valid = false;
        });
        if (!valid) showError('Make and model are required.');

        return valid;
    }

    async function nextStep() {
        if (busy || uploading > 0) return;
        clearError();
        if (!validateStep(currentStep)) return;

        const savesFirst = currentStep === 1 || currentStep === 3;
        if (savesFirst) {
            setBusy(true);
            try {
                await saveVehicle(false);
            } catch (error) {
                showError(error.message);
                setBusy(false);
                return;
            }
            setBusy(false);
        }

        currentStep++;
        updateWizard();
    }

    function prevStep() {
        if (busy || uploading > 0 || currentStep === 1) return;
        clearError();
        currentStep--;
        updateWizard();
    }

    function initEditor() {
        if (editorReady) return;
        $('#vehicleDescription').summernote({
            height: 220,
            toolbar: [
                ['style', ['style', 'bold', 'italic', 'underline']],
                ['para', ['ul', 'ol']],
                ['insert', ['link']],
                ['view', ['codeview']]
            ],
            styleTags: ['p', 'h3', 'h4']
        });
        editorReady = true;
    }

    function updateWizard() {
        document.querySelectorAll('.wizard-pane').forEach(function (pane) {
            pane.classList.toggle('active', parseInt(pane.dataset.step, 10) === currentStep);
        });

        document.querySelectorAll('.wizard-step').forEach(function (step) {
            const stepNumber = parseInt(step.dataset.step, 10);
            step.classList.toggle('active', stepNumber === currentStep);
            step.classList.toggle('completed', stepNumber < currentStep);
        });

        backButton.style.display = currentStep > 1 ? '' : 'none';
        nextButton.style.display = currentStep < totalSteps ? '' : 'none';
        keepButton.style.display = currentStep === totalSteps ? '' : 'none';
        publishButton.style.display = currentStep === totalSteps ? '' : 'none';

        if (currentStep === 3) initEditor();
        if (currentStep === totalSteps) updateSummary();
    }

    // ---- Summary and publish ----

    function textLength(html) {
        const doc = new DOMParser().parseFromString(html, 'text/html');

        return doc.body.textContent.trim().length;
    }

    function addWarning(container, message) {
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning py-2 mb-2';
        warning.textContent = message;
        container.appendChild(warning);
    }

    function updateSummary() {
        const make = field('vehicleMake').value.trim();
        const model = field('vehicleModel').value.trim();
        const year = field('vehicleYear').value.trim();
        const price = field('vehiclePrice').value.trim();
        const mileage = field('vehicleMileage').value.trim();
        const photoCount = field('wizard-gallery').children.length;
        const featureCount = features().length;
        const descriptionLength = textLength(description());

        field('summary-name').textContent = make + ' ' + model;
        field('summary-year').textContent = year || '-';
        field('summary-price').textContent = price ? '£' + Number(price).toLocaleString('en-GB') : '-';
        field('summary-mileage').textContent = mileage ? Number(mileage).toLocaleString('en-GB') + ' miles' : '-';
        field('summary-photos').textContent = photoCount;
        field('summary-features').textContent = featureCount;
        field('summary-description').textContent = descriptionLength + ' characters';

        const warnings = field('wizard-warnings');
        warnings.innerHTML = '';
        if (!price) addWarning(warnings, 'No price has been entered.');
        if (photoCount === 0) addWarning(warnings, 'There are no photos yet.');
        if (descriptionLength < 100) addWarning(warnings, 'The description is under 100 characters.');
    }

    function goToEditPage() {
        leaving = true;
        window.location.href = config.editUrl + '?id=' + vehicleId;
    }

    async function publish() {
        if (busy || uploading > 0) return;
        clearError();
        setBusy(true);
        try {
            await saveVehicle(true);
        } catch (error) {
            showError(error.message);
            setBusy(false);
            return;
        }
        goToEditPage();
    }

    function keepHidden() {
        if (busy || uploading > 0) return;
        goToEditPage();
    }

    // ---- Photos ----

    const gallery = field('wizard-gallery');

    function renderPhoto(image) {
        const col = document.createElement('div');
        col.className = 'col-4 col-sm-3 wizard-photo';
        col.dataset.id = image.id;

        const img = document.createElement('img');
        img.src = image.path;
        img.alt = '';

        const badge = document.createElement('span');
        badge.className = 'cover-badge badge bg-primary';
        badge.textContent = 'Cover';

        col.append(img, badge);
        gallery.appendChild(col);
        field('wizard-gallery-help').classList.remove('d-none');
    }

    Sortable.create(gallery, {
        animation: 150,
        onEnd: async function () {
            const order = Array.from(gallery.children).map(function (el) { return Number(el.dataset.id); });
            try {
                await post({ action: 'reorder_images', id: vehicleId, order: order });
            } catch (error) {
                showError(error.message);
            }
        }
    });

    VehicleUploader.attach({
        input: field('vehicleImageUpload'),
        dropzone: field('wizard-dropzone'),
        list: field('wizard-upload-list'),
        actionUrl: actionUrl,
        getVehicleId: function () { return vehicleId; },
        onUploaded: renderPhoto,
        onActiveChange: function (count) {
            uploading = count;
            setBusy(busy);
        }
    });

    // ---- Wiring ----

    field('start-wizard').addEventListener('click', function () {
        modal.show();
        field('vehicleMake').focus();
    });

    nextButton.addEventListener('click', nextStep);
    backButton.addEventListener('click', prevStep);
    keepButton.addEventListener('click', keepHidden);
    publishButton.addEventListener('click', publish);

    // Enter moves on, except in text areas (new lines, the editor) and on the last step
    field('wizard-form').addEventListener('keydown', function (event) {
        const isEnter = event.key === 'Enter';
        const isTextInput = event.target.tagName === 'INPUT' && event.target.type !== 'file';
        if (!isEnter || !isTextInput) return;
        event.preventDefault();
        if (currentStep < totalSteps) nextStep();
    });
    field('wizard-form').addEventListener('submit', function (event) { event.preventDefault(); });

    ['vehicleMake', 'vehicleModel'].forEach(function (id) {
        field(id).addEventListener('input', function () { this.classList.remove('is-invalid'); });
    });

    // Closing part way keeps any vehicle already created (hidden) and goes back to the list
    modalElement.addEventListener('hidden.bs.modal', function () {
        if (!leaving) window.location.href = config.listUrl;
    });
});
