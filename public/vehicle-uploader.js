// Drop zone and upload queue shared by the add wizard and the edit page (markup and CSS: vehicle-photos.css).
// One file per request, in sequence, through the existing upload_image action.
window.VehicleUploader = (function () {
    'use strict';

    function addRow(list, file) {
        const row = document.createElement('div');
        row.className = 'wizard-upload-row';
        const label = document.createElement('div');
        label.className = 'd-flex justify-content-between';
        const name = document.createElement('span');
        name.className = 'text-truncate me-2';
        name.textContent = file.name;
        const state = document.createElement('span');
        state.className = 'text-muted flex-shrink-0';
        state.textContent = 'Queued';
        label.append(name, state);
        const progress = document.createElement('div');
        progress.className = 'progress';
        const bar = document.createElement('div');
        bar.className = 'progress-bar';
        bar.style.width = '0%';
        progress.appendChild(bar);
        row.append(label, progress);
        list.appendChild(row);

        return { el: row, state: state, bar: bar };
    }

    // XMLHttpRequest rather than fetch, because only it reports upload progress
    function sendFile(options, file, row) {
        return new Promise(function (resolve, reject) {
            const formData = new FormData();
            formData.append('csrf_token', window.csrfToken);
            formData.append('action', 'upload_image');
            formData.append('id', options.getVehicleId());
            formData.append('image', file);

            const request = new XMLHttpRequest();
            request.open('POST', options.actionUrl);
            request.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable) row.bar.style.width = Math.round(event.loaded / event.total * 100) + '%';
            });
            request.addEventListener('load', function () {
                try {
                    const data = JSON.parse(request.responseText);
                    if (!data.success) throw new Error(data.error || data.message || 'Upload failed');
                    resolve(data.image);
                } catch (error) {
                    reject(error instanceof SyntaxError ? new Error('Upload failed (the server sent an unexpected reply)') : error);
                }
            });
            request.addEventListener('error', function () { reject(new Error('Network error')); });
            request.send(formData);
        });
    }

    /**
     * options: input, dropzone, list (elements), actionUrl, getVehicleId(), onUploaded(image),
     * onActiveChange(count) - called with the number of files uploading or waiting
     */
    function attach(options) {
        const queue = [];
        let running = false;

        async function uploadOne(item) {
            item.row.state.textContent = 'Uploading';
            try {
                const image = await sendFile(options, item.file, item.row);
                item.row.el.remove(); // The thumbnail in the gallery is the record of a finished upload
                options.onUploaded(image);
            } catch (error) {
                item.row.el.classList.add('is-failed');
                item.row.bar.classList.add('bg-danger');
                item.row.state.textContent = 'Failed: ' + error.message;
                item.row.state.className = 'text-danger';
            }
        }

        async function run() {
            if (running) return;
            running = true;
            while (queue.length > 0) {
                const item = queue.shift();
                options.onActiveChange(queue.length + 1); // This one plus those still waiting
                await uploadOne(item);
            }
            running = false;
            options.onActiveChange(0);
        }

        function addFiles(files) {
            options.list.querySelectorAll('.is-failed').forEach(function (failed) { failed.remove(); });
            const images = Array.from(files).filter(function (file) { return file.type.indexOf('image/') === 0; });
            images.forEach(function (file) { queue.push({ file: file, row: addRow(options.list, file) }); });
            options.onActiveChange(queue.length + (running ? 1 : 0));
            run();
        }

        options.input.addEventListener('change', function () {
            addFiles(options.input.files);
            options.input.value = '';
        });

        ['dragenter', 'dragover'].forEach(function (type) {
            options.dropzone.addEventListener(type, function (event) {
                event.preventDefault();
                options.dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (type) {
            options.dropzone.addEventListener(type, function (event) {
                event.preventDefault();
                options.dropzone.classList.remove('is-dragover');
            });
        });
        options.dropzone.addEventListener('drop', function (event) { addFiles(event.dataTransfer.files); });
    }

    return { attach: attach };
})();
