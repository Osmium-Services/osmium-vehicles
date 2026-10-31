/**
 * Vehicle list: the Delete menu item opens a modal with an optional redirect target
 * (list:action is fired by the shared list view; config comes from window.VehicleList)
 */
(function () {
    const modalEl = document.getElementById('deleteVehicleModal');
    if (!modalEl) return;

    const idInput = document.getElementById('deleteVehicleId');
    const redirectInput = document.getElementById('deleteRedirectTarget');
    const confirmBtn = document.getElementById('confirmDeleteVehicleBtn');
    const confirmHtml = confirmBtn.innerHTML;

    document.addEventListener('list:action', (event) => {
        const { action, id } = event.detail;
        if (action !== 'delete') return;

        const vehicle = window.VehicleList.vehicles[id];
        if (!vehicle) return;

        idInput.value = id;
        redirectInput.value = '';
        document.getElementById('deleteVehicleTitle').textContent = vehicle.name || 'Untitled';
        document.getElementById('deleteVehicleUrl').textContent = vehicle.url;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    confirmBtn.addEventListener('click', async () => {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

        try {
            const response = await fetch(window.VehicleList.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    id: Number(idInput.value),
                    redirect_target: redirectInput.value.trim() || null,
                    csrf_token: window.csrfToken
                })
            });
            const result = await response.json();

            if (result.success) {
                window.location.reload();
                return;
            }
            adminAlert(result.error || 'Delete failed');
        } catch (error) {
            console.error('Delete error:', error);
            adminAlert('An error occurred');
        }

        confirmBtn.disabled = false;
        confirmBtn.innerHTML = confirmHtml;
    });
})();
